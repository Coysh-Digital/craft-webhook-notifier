<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\sources;

use Craft;
use coyshdigital\webhooknotifier\Plugin;
use coyshdigital\webhooknotifier\records\RuleRecord;
use yii\base\Event;

/**
 * Notification source: any Yii/Craft event on any class.
 *
 * Each rule using this source names a **Sender Class** (e.g. `craft\elements\Entry`)
 * and an **Event Name** (e.g. `afterSave`) — exactly like Craft's first-party
 * Webhook plugin. At boot we read those rules, group them by class + event, and
 * attach one listener per unique pair. When the event fires, the event object is
 * handed to the card/payload as `event` (so you can use `{{ event.sender.title }}`).
 *
 * @author Coysh Digital
 * @since 1.3.0
 */
class CustomEventSource extends BaseSource
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'event';
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('webhook-notifier', 'Custom event (any class)');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function description(): string
    {
        return Craft::t('webhook-notifier', 'Fires on any Yii/Craft event you name — give it a Sender Class (e.g. craft\\elements\\Entry) and an Event Name (e.g. afterSave), just like Craft’s built-in Webhook plugin. The triggering event is available in the card/payload as {{ event }} (e.g. {{ event.sender.title }}). Best paired with the “Raw payload” card mode when you’re posting to a non-Teams webhook.');
    }

    /**
     * @inheritdoc
     */
    public function cardVariables(): array
    {
        return [
            'event' => Craft::t('webhook-notifier', 'The event object, e.g. {{ event.sender.title }}'),
            'sender' => Craft::t('webhook-notifier', 'Shortcut for the event sender'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attachListeners(): void
    {
        // One listener per unique (class, event) pair across all enabled rules.
        $groups = [];

        foreach (Plugin::getInstance()->rules->getEnabledRulesForSource(self::id()) as $rule) {
            $class = trim((string)$rule->senderClass);
            $event = trim((string)$rule->eventName);
            if ($class === '' || $event === '') {
                continue;
            }
            $groups[$class . "\n" . $event][] = $rule;
        }

        foreach ($groups as $key => $rules) {
            [$class, $eventName] = explode("\n", $key, 2);
            Event::on($class, $eventName, function(Event $e) use ($rules, $eventName) {
                $this->_dispatch($e, $eventName, $rules);
            });
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Hands a fired event to its rules.
     *
     * @param Event $event
     * @param string $eventName
     * @param RuleRecord[] $rules
     * @return void
     */
    private function _dispatch(Event $event, string $eventName, array $rules): void
    {
        $sender = $event->sender ?? null;
        $senderClass = is_object($sender) ? get_class($sender) : (string)$sender;

        Plugin::getInstance()->rules->dispatchRules($rules, self::id(), [
            'event' => $event,
            'sender' => $sender,
            'eventName' => $eventName,
            'summary' => Craft::t('webhook-notifier', 'Event {event} on {class}', [
                'event' => $eventName,
                'class' => $senderClass,
            ]),
        ]);
    }
}
