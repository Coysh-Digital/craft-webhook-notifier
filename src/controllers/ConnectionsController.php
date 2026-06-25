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
use coyshdigital\webhooknotifier\records\ConnectionRecord;
use coyshdigital\webhooknotifier\services\Deliveries;
use craft\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Manages connections (Teams channel webhook destinations).
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class ConnectionsController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @throws ForbiddenHttpException if the user lacks the connections permission.
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(Plugin::PERMISSION_MANAGE_CONNECTIONS);

        return true;
    }

    /**
     * Lists all connections.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $connectionsService = Plugin::getInstance()->connections;
        $connections = $connectionsService->getAllConnections();

        $maskedUrls = [];
        foreach ($connections as $connection) {
            $maskedUrls[$connection->id] = $connectionsService->maskUrl($connection);
        }

        return $this->renderTemplate('webhook-notifier/connections/index', [
            'connections' => $connections,
            'maskedUrls' => $maskedUrls,
        ]);
    }

    /**
     * Shows the connection edit form.
     *
     * @param int|null $connectionId
     * @param ConnectionRecord|null $connection
     * @return Response
     * @throws NotFoundHttpException if editing a connection that doesn't exist.
     */
    public function actionEdit(?int $connectionId = null, ?ConnectionRecord $connection = null): Response
    {
        if ($connection === null) {
            if ($connectionId !== null) {
                $connection = Plugin::getInstance()->connections->getConnectionById($connectionId);
                if ($connection === null) {
                    throw new NotFoundHttpException('Connection not found.');
                }
            } else {
                $connection = new ConnectionRecord(['enabled' => true]);
            }
        }

        $isNew = !$connection->id;

        return $this->renderTemplate('webhook-notifier/connections/_edit', [
            'connection' => $connection,
            'isNew' => $isNew,
            'title' => $isNew
                ? Craft::t('webhook-notifier', 'New connection')
                : $connection->name,
            'maskedUrl' => $isNew ? '' : Plugin::getInstance()->connections->maskUrl($connection),
        ]);
    }

    /**
     * Saves a connection.
     *
     * @return Response|null
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $connectionsService = Plugin::getInstance()->connections;

        $id = $request->getBodyParam('connectionId');
        $connection = $id ? $connectionsService->getConnectionById((int)$id) : null;
        $connection ??= new ConnectionRecord();

        $connection->name = (string)$request->getBodyParam('name');
        $connection->enabled = (bool)$request->getBodyParam('enabled', true);

        $urlInput = (string)$request->getBodyParam('urlInput', '');
        if (trim($urlInput) !== '') {
            $connectionsService->applyUrlInput($connection, $urlInput);
        }

        if (trim((string)$connection->url) === '') {
            $connection->addError('url', Craft::t('webhook-notifier', 'A webhook URL is required.'));
        }

        if ($connection->hasErrors() || !$connectionsService->saveConnection($connection)) {
            Craft::$app->getSession()->setError(Craft::t('webhook-notifier', 'Couldn’t save connection.'));
            Craft::$app->getUrlManager()->setRouteParams(['connection' => $connection]);
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('webhook-notifier', 'Connection saved.'));

        return $this->redirectToPostedUrl($connection);
    }

    /**
     * Deletes a connection.
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $id = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (Plugin::getInstance()->connections->deleteConnectionById($id)) {
            Craft::$app->getSession()->setNotice(Craft::t('webhook-notifier', 'Connection deleted.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('webhook-notifier', 'Couldn’t delete connection.'));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Sends a test card through a connection.
     *
     * @return Response
     */
    public function actionTest(): Response
    {
        $this->requirePostRequest();

        $id = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');
        $connection = Plugin::getInstance()->connections->getConnectionById($id);

        if ($connection === null) {
            throw new NotFoundHttpException('Connection not found.');
        }

        $plugin = Plugin::getInstance();
        $delivery = $plugin->sender->send($connection, $plugin->cards->testCard(), [
            'contextSummary' => Craft::t('webhook-notifier', 'Manual test send'),
        ]);

        if ($delivery->status === Deliveries::STATUS_SENT) {
            Craft::$app->getSession()->setNotice(Craft::t('webhook-notifier', 'Test card sent.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('webhook-notifier', 'Test send failed: {error}', [
                'error' => $delivery->errorMessage ?: Craft::t('webhook-notifier', 'see the delivery log.'),
            ]));
        }

        return $this->redirectToPostedUrl();
    }
}
