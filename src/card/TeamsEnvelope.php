<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\card;

/**
 * Wraps an Adaptive Card in the message envelope that Power Automate Workflows
 * webhooks require.
 *
 * Since the May 2026 retirement of Office 365 connectors, Workflows is the only
 * supported delivery path, and it expects this exact shape:
 *
 * ```
 * { "type": "message", "attachments": [ {
 *     "contentType": "application/vnd.microsoft.card.adaptive",
 *     "contentUrl": null,
 *     "content": { ...AdaptiveCard... }
 * } ] }
 * ```
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class TeamsEnvelope
{
    // Constants
    // =========================================================================

    /**
     * @var string The Adaptive Card content type.
     */
    public const CONTENT_TYPE = 'application/vnd.microsoft.card.adaptive';

    // Static Methods
    // =========================================================================

    /**
     * Wraps an Adaptive Card (or any card content array) in a Workflows message
     * envelope.
     *
     * @param AdaptiveCard|array<string, mixed> $card
     * @return array<string, mixed>
     */
    public static function wrap(AdaptiveCard|array $card): array
    {
        $content = $card instanceof AdaptiveCard ? $card->toArray() : $card;

        return [
            'type' => 'message',
            'attachments' => [
                [
                    'contentType' => self::CONTENT_TYPE,
                    'contentUrl' => null,
                    'content' => $content,
                ],
            ],
        ];
    }
}
