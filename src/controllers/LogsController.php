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
use craft\web\Controller;
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
        ]);
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
