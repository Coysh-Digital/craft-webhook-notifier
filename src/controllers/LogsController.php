<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\controllers;

use Craft;
use coyshdigital\webhooknotifier\Plugin;
use coyshdigital\webhooknotifier\services\Cards;
use coyshdigital\webhooknotifier\services\Deliveries;
use craft\helpers\Json;
use craft\web\Controller;
use Throwable;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Shows the delivery log.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class LogsController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(Plugin::PERMISSION_VIEW_LOGS);

        return true;
    }

    /**
     * Lists recent deliveries.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $status = Craft::$app->getRequest()->getParam('status') ?: null;

        return $this->renderTemplate('webhook-notifier/logs/index', [
            'deliveries' => Plugin::getInstance()->deliveries->getRecentDeliveries($status),
            'status' => $status,
            'canResend' => Craft::$app->getUser()->checkPermission(Plugin::PERMISSION_MANAGE_RULES),
        ]);
    }

    /**
     * Re-sends a delivery, re-rendering the rule's *current* card/payload against
     * the delivery's stored context (so payload/card edits are picked up).
     *
     * @return Response
     * @throws NotFoundHttpException if the delivery doesn't exist.
     */
    public function actionResend(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(Plugin::PERMISSION_MANAGE_RULES);

        $plugin = Plugin::getInstance();
        $session = Craft::$app->getSession();

        $delivery = $plugin->deliveries->getDeliveryById((int)Craft::$app->getRequest()->getRequiredBodyParam('id'));
        if ($delivery === null) {
            throw new NotFoundHttpException('Delivery not found.');
        }

        $rule = $delivery->ruleId ? $plugin->rules->getRuleById((int)$delivery->ruleId) : null;
        $context = $delivery->context ? (Json::decodeIfJson($delivery->context) ?: []) : [];

        // Re-render the rule's CURRENT card only when we have both the rule and a
        // saved context (deliveries sent from 1.5.0 onward). Otherwise re-send the
        // original payload exactly, so an older delivery resends as it was rather
        // than coming through blank.
        $reRender = $rule !== null && $context !== [];

        if ($reRender) {
            $connectionId = $rule->connectionId ?: $plugin->getSettings()->defaultConnectionId;
        } else {
            $connectionId = $delivery->connectionId;
        }

        $connection = $connectionId ? $plugin->connections->getConnectionById((int)$connectionId) : null;
        if ($connection === null) {
            $session->setError(Craft::t('webhook-notifier', 'Can’t resend: no connection available.'));
            return $this->redirectToPostedUrl();
        }

        if ($reRender) {
            try {
                $payload = $plugin->cards->render(Json::decodeIfJson((string)$rule->cardConfig) ?: [], $context);
            } catch (Throwable $e) {
                $session->setError(Craft::t('webhook-notifier', 'Couldn’t render the card: {error}', ['error' => $e->getMessage()]));
                return $this->redirectToPostedUrl();
            }
        } else {
            // Re-POST the exact original body (works for Teams envelopes and raw payloads alike).
            $payload = ['format' => Cards::FORMAT_RAW, 'content' => (string)$delivery->requestPayload];
        }

        $new = $plugin->sender->send($connection, $payload, [
            'ruleId' => $delivery->ruleId,
            'sourceType' => $delivery->sourceType,
            'context' => $delivery->context,
            'contextSummary' => trim((string)$delivery->contextSummary . ' ' . Craft::t('webhook-notifier', '(resent)')),
        ]);

        if ($new->status === Deliveries::STATUS_SENT) {
            $session->setNotice($reRender
                ? Craft::t('webhook-notifier', 'Resent with the rule’s current card.')
                : Craft::t('webhook-notifier', 'Resent the original payload (no saved context to re-render with).'));
        } else {
            $session->setError(Craft::t('webhook-notifier', 'Resend failed: {error}', [
                'error' => $new->errorMessage ?: Craft::t('webhook-notifier', 'see the log.'),
            ]));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Shows a single delivery's detail.
     *
     * @param int $deliveryId
     * @return Response
     * @throws NotFoundHttpException if the delivery doesn't exist.
     */
    public function actionDetail(int $deliveryId): Response
    {
        $delivery = Plugin::getInstance()->deliveries->getDeliveryById($deliveryId);

        if ($delivery === null) {
            throw new NotFoundHttpException('Delivery not found.');
        }

        return $this->renderTemplate('webhook-notifier/logs/_detail', [
            'delivery' => $delivery,
        ]);
    }
}
