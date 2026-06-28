<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\sources;

use Craft;
use craft\elements\User;
use craft\events\ElementEvent;
use craft\events\UserEvent;
use craft\events\UserGroupsAssignEvent;
use craft\helpers\ElementHelper;
use craft\services\Elements;
use craft\services\Users;
use yii\base\Event;

/**
 * Notification source: user lifecycle events (registered, activated, group change).
 *
 * The `event` context key distinguishes which happened, so a rule can filter to
 * just one with a condition.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class UserSource extends BaseSource
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'user';
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('webhook-notifier', 'User event');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function description(): string
    {
        return Craft::t('webhook-notifier', 'Fires on user lifecycle events: registration, activation, and group changes. The “event” field is one of registered, activated, or groupChanged - add a condition on it to target just one (e.g. “event equals registered”).');
    }

    /**
     * @inheritdoc
     */
    public function contextSchema(): array
    {
        return [
            'event' => Craft::t('webhook-notifier', 'Event (registered, activated, groupChanged)'),
            'email' => Craft::t('webhook-notifier', 'Email'),
            'username' => Craft::t('webhook-notifier', 'Username'),
            'fullName' => Craft::t('webhook-notifier', 'Full name'),
            'groups' => Craft::t('webhook-notifier', 'Group handles'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attachListeners(): void
    {
        // Registered (a brand-new user element saved).
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(ElementEvent $event) {
                $user = $event->element;
                if (!$user instanceof User || !$event->isNew || ElementHelper::isDraftOrRevision($user)) {
                    return;
                }
                $this->dispatch($this->_buildContext($user, 'registered'));
            }
        );

        // Activated.
        Event::on(
            Users::class,
            Users::EVENT_AFTER_ACTIVATE_USER,
            function(UserEvent $event) {
                $this->dispatch($this->_buildContext($event->user, 'activated'));
            }
        );

        // Group assignment changed.
        Event::on(
            Users::class,
            Users::EVENT_AFTER_ASSIGN_USER_TO_GROUPS,
            function(UserGroupsAssignEvent $event) {
                $user = Craft::$app->getUsers()->getUserById($event->userId);
                if ($user !== null) {
                    $this->dispatch($this->_buildContext($user, 'groupChanged'));
                }
            }
        );
    }

    // Private Methods
    // =========================================================================

    /**
     * Builds the normalized context for a user event.
     *
     * @param User $user
     * @param string $event
     * @return array<string, mixed>
     */
    private function _buildContext(User $user, string $event): array
    {
        $groups = array_map(static fn($group) => $group->handle, $user->getGroups());

        return [
            'user' => $user,
            'event' => $event,
            'email' => $user->email,
            'username' => $user->username,
            'fullName' => $user->getName(),
            'groups' => $groups,
            'cpEditUrl' => $user->getCpEditUrl(),
            'summary' => Craft::t('webhook-notifier', 'User {name} {event}', [
                'name' => $user->email ?: $user->username,
                'event' => $event,
            ]),
        ];
    }
}
