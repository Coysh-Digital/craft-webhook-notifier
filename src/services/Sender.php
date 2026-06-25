<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\services;

use Craft;
use coyshdigital\webhooknotifier\card\TeamsEnvelope;
use coyshdigital\webhooknotifier\Plugin;
use coyshdigital\webhooknotifier\records\ConnectionRecord;
use coyshdigital\webhooknotifier\records\DeliveryRecord;
use craft\helpers\Json;
use Throwable;
use yii\base\Component;

/**
 * Sender service — wraps an Adaptive Card in a Workflows envelope, POSTs it to a
 * connection's webhook, and records the outcome in the delivery log.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class Sender extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Sends a card to a connection and logs the result.
     *
     * Always returns a saved {@see DeliveryRecord}; a transient failure is
     * recorded with status {@see Deliveries::STATUS_RETRYING} so the caller (the
     * queue job) can decide whether to retry.
     *
     * @param ConnectionRecord $connection
     * @param array<string, mixed> $cardContent The Adaptive Card content array.
     * @param array<string, mixed> $context Optional log context: `ruleId`,
     * `sourceType`, `contextSummary`, `attempt`.
     * @return DeliveryRecord
     */
    public function send(ConnectionRecord $connection, array $cardContent, array $context = []): DeliveryRecord
    {
        $deliveries = Plugin::getInstance()->deliveries;
        $envelope = TeamsEnvelope::wrap($cardContent);

        $attributes = [
            'ruleId' => $context['ruleId'] ?? null,
            'connectionId' => $connection->id,
            'sourceType' => $context['sourceType'] ?? null,
            'contextSummary' => $context['contextSummary'] ?? null,
            'attempt' => (int)($context['attempt'] ?? 1),
            'requestPayload' => Json::encode($envelope),
        ];

        $url = Plugin::getInstance()->connections->resolveUrl($connection);

        if (trim($url) === '') {
            return $deliveries->log(array_merge($attributes, [
                'status' => Deliveries::STATUS_FAILED,
                'errorMessage' => Craft::t('webhook-notifier', 'The connection webhook URL could not be resolved.'),
            ]));
        }

        try {
            $client = Craft::createGuzzleClient(['timeout' => Plugin::getInstance()->getSettings()->httpTimeout]);
            $response = $client->post($url, [
                'json' => $envelope,
                'http_errors' => false,
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string)$response->getBody();
            $status = $this->_statusForHttpCode($statusCode);

            $attributes = array_merge($attributes, [
                'status' => $status,
                'httpStatus' => $statusCode,
                'responseBody' => $body,
            ]);

            if ($status !== Deliveries::STATUS_SENT) {
                $retryAfter = $response->getHeaderLine('Retry-After');
                $attributes['errorMessage'] = $retryAfter !== ''
                    ? Craft::t('webhook-notifier', 'HTTP {code} (Retry-After: {retry})', ['code' => $statusCode, 'retry' => $retryAfter])
                    : Craft::t('webhook-notifier', 'HTTP {code}', ['code' => $statusCode]);
            }

            return $deliveries->log($attributes);
        } catch (Throwable $e) {
            // Network/transport failure — treat as transient.
            return $deliveries->log(array_merge($attributes, [
                'status' => Deliveries::STATUS_RETRYING,
                'errorMessage' => $e->getMessage(),
            ]));
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Maps an HTTP status code to a delivery status.
     *
     * 2xx is a success; 429 and 5xx are transient (retry); any other 4xx is a
     * permanent failure.
     *
     * @param int $code
     * @return string
     */
    private function _statusForHttpCode(int $code): string
    {
        if ($code >= 200 && $code < 300) {
            return Deliveries::STATUS_SENT;
        }

        if ($code === 429 || $code >= 500) {
            return Deliveries::STATUS_RETRYING;
        }

        return Deliveries::STATUS_FAILED;
    }
}
