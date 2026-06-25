<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\errors;

use Throwable;
use yii\base\Exception;

/**
 * Thrown when a send fails transiently (HTTP 429 or 5xx) and should be retried
 * by the queue.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class RetryableSendException extends Exception
{
    // Public Properties
    // =========================================================================

    /**
     * @var int|null The number of seconds the endpoint asked us to wait
     * (from a `Retry-After` header), if any.
     */
    public ?int $retryAfter = null;

    // Public Methods
    // =========================================================================

    /**
     * @param string $message
     * @param int|null $retryAfter
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = '', ?int $retryAfter = null, int $code = 0, ?Throwable $previous = null)
    {
        $this->retryAfter = $retryAfter;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Retryable send failure';
    }
}
