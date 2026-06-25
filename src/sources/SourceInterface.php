<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\sources;

/**
 * A notification source: something that fires events the rules engine can act on.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
interface SourceInterface
{
    /**
     * Returns the source's stable identifier (stored on rules).
     *
     * @return string
     */
    public static function id(): string;

    /**
     * Returns the source's human-readable name.
     *
     * @return string
     */
    public static function displayName(): string;

    /**
     * Returns a longer description of when the source fires and how to use it,
     * shown in the rule editor.
     *
     * @return string
     */
    public function description(): string;

    /**
     * Returns whether the source can be used in this installation (e.g. a
     * required plugin is installed).
     *
     * @return bool
     */
    public static function isAvailable(): bool;

    /**
     * Returns the context keys this source exposes to conditions, as
     * `[key => label]`.
     *
     * @return array<string, string>
     */
    public function contextSchema(): array;

    /**
     * Returns the variables available to card templates, as `[key => label]`.
     *
     * Defaults to {@see contextSchema()}, but a source may expose extra
     * template-only variables (e.g. Freeform's per-field values).
     *
     * @return array<string, string>
     */
    public function cardVariables(): array;

    /**
     * Wires up the underlying Yii/Craft events. Called once, after the app has
     * fully initialised.
     *
     * @return void
     */
    public function attachListeners(): void;
}
