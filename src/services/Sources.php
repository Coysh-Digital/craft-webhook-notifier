<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\services;

use coyshdigital\webhooknotifier\events\RegisterSourcesEvent;
use coyshdigital\webhooknotifier\Plugin;
use coyshdigital\webhooknotifier\sources\CustomEventSource;
use coyshdigital\webhooknotifier\sources\EntrySource;
use coyshdigital\webhooknotifier\sources\FreeformSource;
use coyshdigital\webhooknotifier\sources\IntegrationFailureSource;
use coyshdigital\webhooknotifier\sources\QueueSizeSource;
use coyshdigital\webhooknotifier\sources\SourceInterface;
use coyshdigital\webhooknotifier\sources\UserSource;
use yii\base\Component;

/**
 * Sources service — the registry of notification sources.
 *
 * Holds the built-in sources, lets third parties register their own via
 * {@see self::EVENT_REGISTER_SOURCES}, wires up their listeners, and exposes the
 * programmatic failure-reporting API.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class Sources extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event RegisterSourcesEvent The event fired when registering sources.
     */
    public const EVENT_REGISTER_SOURCES = 'registerSources';

    // Private Properties
    // =========================================================================

    /**
     * @var array<string, SourceInterface>|null The available sources, keyed by ID.
     */
    private ?array $_sources = null;

    // Public Methods
    // =========================================================================

    /**
     * Returns all available sources, keyed by ID.
     *
     * @return array<string, SourceInterface>
     */
    public function getAllSources(): array
    {
        if ($this->_sources !== null) {
            return $this->_sources;
        }

        $event = new RegisterSourcesEvent([
            'sources' => [
                EntrySource::class,
                UserSource::class,
                IntegrationFailureSource::class,
                QueueSizeSource::class,
                CustomEventSource::class,
                FreeformSource::class,
            ],
        ]);
        $this->trigger(self::EVENT_REGISTER_SOURCES, $event);

        $sources = [];
        foreach ($event->sources as $class) {
            if (!is_subclass_of($class, SourceInterface::class)) {
                continue;
            }
            if (!$class::isAvailable()) {
                continue;
            }
            $sources[$class::id()] = new $class();
        }

        return $this->_sources = $sources;
    }

    /**
     * Returns a source by its ID.
     *
     * @param string $id
     * @return SourceInterface|null
     */
    public function getSourceById(string $id): ?SourceInterface
    {
        return $this->getAllSources()[$id] ?? null;
    }

    /**
     * Returns the sources as `[id => displayName]` options for select fields.
     *
     * @return array<string, string>
     */
    public function getSourceOptions(): array
    {
        $options = [];
        foreach ($this->getAllSources() as $id => $source) {
            $options[$id] = $source::displayName();
        }

        return $options;
    }

    /**
     * Returns each source's context fields as `[sourceId => [key => label]]`.
     *
     * Used to populate the rule editor's field pickers.
     *
     * @return array<string, array<string, string>>
     */
    public function getSourceFields(): array
    {
        $fields = [];
        foreach ($this->getAllSources() as $id => $source) {
            $fields[$id] = $source->contextSchema();
        }

        return $fields;
    }

    /**
     * Returns each source's card-template variables as
     * `[sourceId => [key => label]]`.
     *
     * @return array<string, array<string, string>>
     */
    public function getSourceCardVariables(): array
    {
        $vars = [];
        foreach ($this->getAllSources() as $id => $source) {
            $vars[$id] = $source->cardVariables();
        }

        return $vars;
    }

    /**
     * Returns each source's description as `[sourceId => description]`.
     *
     * @return array<string, string>
     */
    public function getSourceDescriptions(): array
    {
        $descriptions = [];
        foreach ($this->getAllSources() as $id => $source) {
            $descriptions[$id] = $source->description();
        }

        return $descriptions;
    }

    /**
     * Attaches every available source's event listeners.
     *
     * @return void
     */
    public function attachListeners(): void
    {
        foreach ($this->getAllSources() as $source) {
            $source->attachListeners();
        }
    }

    /**
     * Reports an integration failure through the rules engine.
     *
     * Intended to be called from other plugins/modules:
     *
     * ```php
     * Plugin::getInstance()->sources->reportFailure('Dynamics sync failed', $message, ['contactId' => 1234]);
     * ```
     *
     * @param string $title
     * @param string $detail
     * @param array<string, mixed> $data
     * @return void
     */
    public function reportFailure(string $title, string $detail, array $data = []): void
    {
        Plugin::getInstance()->rules->handle(
            IntegrationFailureSource::id(),
            IntegrationFailureSource::reportContext($title, $detail, $data)
        );
    }
}
