<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\jobs;

use Craft;
use coyshdigital\webhooknotifier\errors\RetryableSendException;
use coyshdigital\webhooknotifier\Plugin;
use coyshdigital\webhooknotifier\services\Deliveries;
use craft\queue\BaseJob;
use yii\queue\RetryableJobInterface;

/**
 * Queue job: delivers one rendered Adaptive Card to one connection.
 *
 * The card is rendered at dispatch time (while the triggering context is live)
 * and passed here as a plain array, so the job is cleanly serializable and the
 * queue can retry it.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class SendNotification extends BaseJob implements RetryableJobInterface
{
    // Public Properties
    // =========================================================================

    /**
     * @var int|null The connection to deliver to.
     */
    public ?int $connectionId = null;

    /**
     * @var array<string, mixed> The Adaptive Card content to send.
     */
    public array $cardContent = [];

    /**
     * @var int|null The rule that produced this notification, for logging.
     */
    public ?int $ruleId = null;

    /**
     * @var string|null The source type, for logging.
     */
    public ?string $sourceType = null;

    /**
     * @var string|null A short human-readable summary, for logging.
     */
    public ?string $contextSummary = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @throws RetryableSendException if the send failed transiently.
     */
    public function execute($queue): void
    {
        $plugin = Plugin::getInstance();

        $connection = $this->connectionId !== null
            ? $plugin->connections->getConnectionById($this->connectionId)
            : null;

        if ($connection === null || !$connection->enabled) {
            $plugin->deliveries->log([
                'ruleId' => $this->ruleId,
                'connectionId' => $this->connectionId,
                'sourceType' => $this->sourceType,
                'contextSummary' => $this->contextSummary,
                'status' => Deliveries::STATUS_FAILED,
                'errorMessage' => Craft::t('webhook-notifier', 'The connection is missing or disabled.'),
            ]);
            return;
        }

        $delivery = $plugin->sender->send($connection, $this->cardContent, [
            'ruleId' => $this->ruleId,
            'sourceType' => $this->sourceType,
            'contextSummary' => $this->contextSummary,
        ]);

        if ($delivery->status === Deliveries::STATUS_RETRYING) {
            throw new RetryableSendException($delivery->errorMessage ?: 'Transient send failure.');
        }
    }

    /**
     * @inheritdoc
     */
    public function getTtr(): int
    {
        // Generous headroom over the HTTP timeout.
        return Plugin::getInstance()->getSettings()->httpTimeout + 50;
    }

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        return $attempt < Plugin::getInstance()->getSettings()->maxRetries
            && $error instanceof RetryableSendException;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('webhook-notifier', 'Sending Microsoft Teams notification');
    }
}
