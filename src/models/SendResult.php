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
 * The outcome of a single send attempt.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class SendResult extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var bool Whether the card was accepted by the webhook.
     */
    public bool $success = false;

    /**
     * @var int|null The HTTP status code returned by the webhook, if any.
     */
    public ?int $httpStatus = null;

    /**
     * @var string|null The (truncated) response body, if any.
     */
    public ?string $responseBody = null;

    /**
     * @var string|null A human-readable error message when the send failed.
     */
    public ?string $errorMessage = null;
}
