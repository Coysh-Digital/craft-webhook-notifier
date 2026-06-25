<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\events;

use yii\base\Event;

/**
 * Event fired so third parties can register their own notification sources.
 *
 * ```php
 * use coyshdigital\webhooknotifier\events\RegisterSourcesEvent;
 * use coyshdigital\webhooknotifier\services\Sources;
 * use yii\base\Event;
 *
 * Event::on(Sources::class, Sources::EVENT_REGISTER_SOURCES, function(RegisterSourcesEvent $e) {
 *     $e->sources[] = MyCustomSource::class;
 * });
 * ```
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class RegisterSourcesEvent extends Event
{
    // Public Properties
    // =========================================================================

    /**
     * @var string[] The registered source classes (each a {@see \coyshdigital\webhooknotifier\sources\SourceInterface}).
     */
    public array $sources = [];
}
