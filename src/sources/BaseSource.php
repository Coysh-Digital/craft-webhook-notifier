<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\sources;

use coyshdigital\webhooknotifier\Plugin;
use yii\base\BaseObject;

/**
 * Base class for notification sources.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
abstract class BaseSource extends BaseObject implements SourceInterface
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function isAvailable(): bool
    {
        return true;
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function description(): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function contextSchema(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function cardVariables(): array
    {
        return $this->contextSchema();
    }

    // Protected Methods
    // =========================================================================

    /**
     * Dispatches a normalized context to the rules engine for this source.
     *
     * @param array<string, mixed> $context
     * @return void
     */
    protected function dispatch(array $context): void
    {
        Plugin::getInstance()->rules->handle(static::id(), $context);
    }
}
