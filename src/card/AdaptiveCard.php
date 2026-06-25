<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\card;

/**
 * A minimal, fluent Adaptive Card builder.
 *
 * Produces the `content` object expected inside a Teams message attachment. The
 * card is wrapped for delivery by {@see TeamsEnvelope}.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class AdaptiveCard
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The Adaptive Card schema version.
     */
    public string $version = '1.5';

    // Private Properties
    // =========================================================================

    /**
     * @var array<int, array<string, mixed>> The card body elements.
     */
    private array $_body = [];

    /**
     * @var array<int, array<string, mixed>> The card actions.
     */
    private array $_actions = [];

    // Public Methods
    // =========================================================================

    /**
     * Adds a text block to the card body.
     *
     * @param string $text The (Markdown-aware) text to display.
     * @param array<string, mixed> $options Extra Adaptive Card TextBlock properties
     * (e.g. `size`, `weight`, `color`, `isSubtle`).
     * @return self
     */
    public function addTextBlock(string $text, array $options = []): self
    {
        if ($text === '') {
            return $this;
        }

        $this->_body[] = array_merge([
            'type' => 'TextBlock',
            'text' => $text,
            'wrap' => true,
        ], $options);

        return $this;
    }

    /**
     * Adds a fact set (a list of key/value pairs) to the card body.
     *
     * @param array<int, array{title: string, value: string}> $facts
     * @return self
     */
    public function addFactSet(array $facts): self
    {
        $clean = [];

        foreach ($facts as $fact) {
            $title = trim((string)($fact['title'] ?? ''));
            $value = trim((string)($fact['value'] ?? ''));
            if ($title === '' && $value === '') {
                continue;
            }
            $clean[] = ['title' => $title, 'value' => $value];
        }

        if ($clean !== []) {
            $this->_body[] = ['type' => 'FactSet', 'facts' => $clean];
        }

        return $this;
    }

    /**
     * Adds an "Open URL" action button to the card.
     *
     * @param string $title The button label.
     * @param string $url The URL the button opens.
     * @return self
     */
    public function addOpenUrlAction(string $title, string $url): self
    {
        if ($title === '' || $url === '') {
            return $this;
        }

        $this->_actions[] = [
            'type' => 'Action.OpenUrl',
            'title' => $title,
            'url' => $url,
        ];

        return $this;
    }

    /**
     * Returns the Adaptive Card as the `content` array for a Teams attachment.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $card = [
            'type' => 'AdaptiveCard',
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'version' => $this->version,
            'body' => $this->_body,
        ];

        if ($this->_actions !== []) {
            $card['actions'] = $this->_actions;
        }

        return $card;
    }
}
