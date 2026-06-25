<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\sources;

use Craft;
use craft\elements\Entry;
use craft\events\ElementEvent;
use craft\helpers\ElementHelper;
use craft\services\Elements;
use yii\base\Event;

/**
 * Notification source: an entry was saved (created or updated).
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class EntrySource extends BaseSource
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'entry';
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('webhook-notifier', 'Entry saved');
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function description(): string
    {
        return Craft::t('webhook-notifier', 'Fires when an entry is created or updated. Drafts and revisions are ignored. Add a condition such as “section equals news” to target specific sections, or “isNew equals true” to notify only on first publish.');
    }

    /**
     * @inheritdoc
     */
    public function contextSchema(): array
    {
        return [
            'section' => Craft::t('webhook-notifier', 'Section handle'),
            'type' => Craft::t('webhook-notifier', 'Entry type handle'),
            'title' => Craft::t('webhook-notifier', 'Title'),
            'url' => Craft::t('webhook-notifier', 'Public URL'),
            'cpEditUrl' => Craft::t('webhook-notifier', 'Control panel edit URL'),
            'authorName' => Craft::t('webhook-notifier', 'Author name'),
            'status' => Craft::t('webhook-notifier', 'Status'),
            'isNew' => Craft::t('webhook-notifier', 'Is newly created'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attachListeners(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(ElementEvent $event) {
                $entry = $event->element;

                if (!$entry instanceof Entry || ElementHelper::isDraftOrRevision($entry)) {
                    return;
                }

                $this->dispatch($this->_buildContext($entry, $event->isNew));
            }
        );
    }

    // Private Methods
    // =========================================================================

    /**
     * Builds the normalized context for an entry.
     *
     * @param Entry $entry
     * @param bool $isNew
     * @return array<string, mixed>
     */
    private function _buildContext(Entry $entry, bool $isNew): array
    {
        $section = $entry->getSection();

        try {
            $authorName = $entry->getAuthor()?->getName();
        } catch (\Throwable) {
            $authorName = null;
        }

        return [
            'entry' => $entry,
            'section' => $section?->handle,
            'sectionName' => $section?->name,
            'type' => $entry->getType()->handle,
            'title' => $entry->title,
            'url' => $entry->getUrl(),
            'cpEditUrl' => $entry->getCpEditUrl(),
            'authorName' => $authorName,
            'status' => $entry->getStatus(),
            'isNew' => $isNew,
            'summary' => Craft::t('webhook-notifier', 'Entry “{title}” saved', ['title' => (string)$entry->title]),
        ];
    }
}
