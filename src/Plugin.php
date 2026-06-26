<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier;

use Craft;
use coyshdigital\webhooknotifier\models\Settings;
use coyshdigital\webhooknotifier\services\Cards;
use coyshdigital\webhooknotifier\services\Conditions;
use coyshdigital\webhooknotifier\services\Connections;
use coyshdigital\webhooknotifier\services\Deliveries;
use coyshdigital\webhooknotifier\services\Rules;
use coyshdigital\webhooknotifier\services\Sender;
use coyshdigital\webhooknotifier\services\Sources;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Gc;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use yii\base\Event;

/**
 * Webhook Notifier plugin.
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @property-read Connections $connections
 * @property-read Rules $rules
 * @property-read Sources $sources
 * @property-read Conditions $conditions
 * @property-read Cards $cards
 * @property-read Sender $sender
 * @property-read Deliveries $deliveries
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class Plugin extends BasePlugin
{
    // Constants
    // =========================================================================

    /**
     * @var string The permission for managing notification rules.
     */
    public const PERMISSION_MANAGE_RULES = 'webhook-notifier:manageRules';

    /**
     * @var string The permission for managing connections (channel webhook URLs).
     */
    public const PERMISSION_MANAGE_CONNECTIONS = 'webhook-notifier:manageConnections';

    /**
     * @var string The permission for viewing the delivery log.
     */
    public const PERMISSION_VIEW_LOGS = 'webhook-notifier:viewLogs';

    // Static Properties
    // =========================================================================

    /**
     * @var Plugin|null
     */
    public static ?Plugin $plugin = null;

    // Public Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public string $schemaVersion = '1.5.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'coyshdigital\\webhooknotifier\\console\\controllers';
        }

        $this->_registerCpRoutes();
        $this->_registerPermissions();
        $this->_registerGarbageCollection();

        // Wait until the whole app (and other plugins, e.g. Freeform) has
        // initialised before wiring up the notification sources.
        Craft::$app->onInit(function() {
            if (Craft::$app->getIsInstalled() && !Craft::$app->getProjectConfig()->getIsApplyingExternalChanges()) {
                $this->sources->attachListeners();
            }
        });

        Craft::info(
            Craft::t('webhook-notifier', '{name} plugin loaded', ['name' => $this->name]),
            __METHOD__
        );
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $user = Craft::$app->getUser();

        $item['subnav'] = [];

        if ($user->checkPermission(self::PERMISSION_MANAGE_RULES)) {
            $item['subnav']['rules'] = [
                'label' => Craft::t('webhook-notifier', 'Rules'),
                'url' => 'webhook-notifier/rules',
            ];
        }

        if ($user->checkPermission(self::PERMISSION_MANAGE_CONNECTIONS)) {
            $item['subnav']['connections'] = [
                'label' => Craft::t('webhook-notifier', 'Connections'),
                'url' => 'webhook-notifier/connections',
            ];
        }

        if ($user->checkPermission(self::PERMISSION_VIEW_LOGS)) {
            $item['subnav']['logs'] = [
                'label' => Craft::t('webhook-notifier', 'Logs'),
                'url' => 'webhook-notifier/logs',
            ];
        }

        if ($user->getIsAdmin()) {
            $item['subnav']['settings'] = [
                'label' => Craft::t('webhook-notifier', 'Settings'),
                'url' => 'webhook-notifier/settings',
            ];
        }

        return $item;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        // Defer to the dedicated, multi-screen settings controller.
        return Craft::$app->getResponse()->redirect('webhook-notifier/settings');
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    // Private Methods
    // =========================================================================

    /**
     * Registers the plugin's control panel routes.
     *
     * @return void
     */
    private function _registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['webhook-notifier'] = 'webhook-notifier/rules/index';

                $event->rules['webhook-notifier/rules'] = 'webhook-notifier/rules/index';
                $event->rules['webhook-notifier/rules/new'] = 'webhook-notifier/rules/edit';
                $event->rules['webhook-notifier/rules/<ruleId:\d+>'] = 'webhook-notifier/rules/edit';

                $event->rules['webhook-notifier/connections'] = 'webhook-notifier/connections/index';
                $event->rules['webhook-notifier/connections/new'] = 'webhook-notifier/connections/edit';
                $event->rules['webhook-notifier/connections/<connectionId:\d+>'] = 'webhook-notifier/connections/edit';

                $event->rules['webhook-notifier/logs'] = 'webhook-notifier/logs/index';
                $event->rules['webhook-notifier/logs/<deliveryId:\d+>'] = 'webhook-notifier/logs/detail';

                $event->rules['webhook-notifier/settings'] = 'webhook-notifier/settings/index';
            }
        );
    }

    /**
     * Prunes old delivery-log rows when Craft runs garbage collection.
     *
     * @return void
     */
    private function _registerGarbageCollection(): void
    {
        Event::on(
            Gc::class,
            Gc::EVENT_RUN,
            function() {
                Plugin::getInstance()->deliveries->prune($this->getSettings()->logRetentionDays);
            }
        );
    }

    /**
     * Registers the plugin's user permissions.
     *
     * @return void
     */
    private function _registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('webhook-notifier', 'Webhook Notifier'),
                    'permissions' => [
                        self::PERMISSION_MANAGE_RULES => [
                            'label' => Craft::t('webhook-notifier', 'Manage notification rules'),
                        ],
                        self::PERMISSION_MANAGE_CONNECTIONS => [
                            'label' => Craft::t('webhook-notifier', 'Manage connections'),
                            'info' => Craft::t('webhook-notifier', 'Connections store channel webhook URLs (secrets).'),
                        ],
                        self::PERMISSION_VIEW_LOGS => [
                            'label' => Craft::t('webhook-notifier', 'View the delivery log'),
                        ],
                    ],
                ];
            }
        );
    }
}
