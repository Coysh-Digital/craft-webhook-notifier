<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\models;

use craft\base\Model;

/**
 * Webhook Notifier settings model.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var bool Whether notifications are sent at all. A global kill switch.
     */
    public bool $enabled = true;

    /**
     * @var int|null The connection used by rules that don't specify their own.
     */
    public ?int $defaultConnectionId = null;

    /**
     * @var string The default Adaptive Card schema version for new cards.
     */
    public string $defaultCardVersion = '1.5';

    /**
     * @var int How many days delivery-log rows are kept before garbage collection.
     */
    public int $logRetentionDays = 30;

    /**
     * @var int The HTTP timeout, in seconds, when POSTing to a webhook.
     */
    public int $httpTimeout = 10;

    /**
     * @var int The maximum number of times a failed send is retried.
     */
    public int $maxRetries = 5;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['enabled'], 'boolean'];
        $rules[] = [['defaultConnectionId'], 'integer'];
        $rules[] = [['logRetentionDays', 'httpTimeout', 'maxRetries'], 'integer', 'min' => 0];
        $rules[] = [['defaultCardVersion'], 'string'];
        $rules[] = [['defaultCardVersion', 'logRetentionDays', 'httpTimeout', 'maxRetries'], 'required'];

        return $rules;
    }
}
