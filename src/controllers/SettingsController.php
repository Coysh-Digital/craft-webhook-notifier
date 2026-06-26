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
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Manages the plugin settings.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class SettingsController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @throws ForbiddenHttpException if the user is not an admin.
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireAdmin();

        return true;
    }

    /**
     * Shows the settings form.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $plugin = Plugin::getInstance();

        $connectionOptions = ['' => Craft::t('webhook-notifier', 'None')];
        foreach ($plugin->connections->getAllConnections() as $connection) {
            $connectionOptions[$connection->id] = $connection->name;
        }

        return $this->renderTemplate('webhook-notifier/settings/index', [
            'plugin' => $plugin,
            'settings' => $plugin->getSettings(),
            'connectionOptions' => $connectionOptions,
            'overrides' => array_keys(Craft::$app->getConfig()->getConfigFromFile('webhook-notifier')),
            // Settings live in project config, so they're read-only wherever admin
            // changes are disabled (typically staging/production).
            'readOnly' => !Craft::$app->getConfig()->getGeneral()->allowAdminChanges,
        ]);
    }

    /**
     * Saves the settings.
     *
     * @return Response|null
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        // Plugin settings are stored in project config; saving them is blocked
        // wherever admin changes are disabled.
        if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            throw new ForbiddenHttpException('Settings are read-only when admin changes are disabled. Edit them in project config or via config/webhook-notifier.php.');
        }

        $plugin = Plugin::getInstance();
        $settings = (array)Craft::$app->getRequest()->getBodyParam('settings', []);

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings)) {
            Craft::$app->getSession()->setError(Craft::t('webhook-notifier', 'Couldn’t save settings.'));
            Craft::$app->getUrlManager()->setRouteParams([
                'settings' => $plugin->getSettings(),
            ]);
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('webhook-notifier', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
