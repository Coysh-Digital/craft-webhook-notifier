<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\services;

use Craft;
use coyshdigital\webhooknotifier\card\AdaptiveCard;
use coyshdigital\webhooknotifier\Plugin;
use craft\helpers\Json;
use Throwable;
use yii\base\Component;
use yii\base\InvalidArgumentException;

/**
 * Cards service - renders a stored card config against an event context into the
 * Adaptive Card content array.
 *
 * A card config has a `mode` of either `structured` (title / body / facts /
 * button fields, each a Twig object template) or `advanced` (a single raw
 * Adaptive Card JSON body that is itself a Twig template).
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class Cards extends Component
{
    // Constants
    // =========================================================================

    /**
     * @var string The structured (no-code) card mode.
     */
    public const MODE_STRUCTURED = 'structured';

    /**
     * @var string The advanced (raw Adaptive Card JSON) card mode.
     */
    public const MODE_ADVANCED = 'advanced';

    /**
     * @var string The raw-payload mode - sends a custom body to any webhook,
     * with no Teams wrapping.
     */
    public const MODE_RAW = 'raw';

    /**
     * @var string Delivery format: a Teams Adaptive Card (wrapped in the
     * Workflows envelope).
     */
    public const FORMAT_TEAMS = 'teams';

    /**
     * @var string Delivery format: a raw body POSTed to the webhook as-is.
     */
    public const FORMAT_RAW = 'raw';

    // Public Methods
    // =========================================================================

    /**
     * Renders a card config against an event context into a delivery descriptor.
     *
     * Returns `['format' => 'teams', 'content' => <Adaptive Card array>]` for the
     * structured/advanced modes, or `['format' => 'raw', 'content' => <body string>]`
     * for the raw mode.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $context
     * @return array{format: string, content: array<string, mixed>|string}
     * @throws InvalidArgumentException if an advanced card produces invalid JSON.
     */
    public function render(array $config, array $context = []): array
    {
        $mode = $config['mode'] ?? self::MODE_STRUCTURED;

        if ($mode === self::MODE_RAW) {
            return [
                'format' => self::FORMAT_RAW,
                'content' => $this->_renderTemplate((string)($config['payloadJson'] ?? ''), $context),
            ];
        }

        if ($mode === self::MODE_ADVANCED) {
            return [
                'format' => self::FORMAT_TEAMS,
                'content' => $this->_renderAdvanced((string)($config['advancedJson'] ?? ''), $context),
            ];
        }

        return [
            'format' => self::FORMAT_TEAMS,
            'content' => $this->_renderStructured($config, $context),
        ];
    }

    /**
     * Returns a simple, hard-coded card for connection test sends.
     *
     * @return array<string, mixed>
     */
    public function testCard(): array
    {
        $card = new AdaptiveCard();
        $card->version = $this->_defaultVersion();
        $card->addTextBlock(Craft::t('webhook-notifier', 'Test notification'), [
            'size' => 'Large',
            'weight' => 'Bolder',
        ]);
        $card->addTextBlock(Craft::t('webhook-notifier', 'If you can see this card, your connection is working. 🎉'));
        $card->addFactSet([
            ['title' => Craft::t('webhook-notifier', 'Site'), 'value' => Craft::$app->getSites()->getPrimarySite()->name],
            ['title' => Craft::t('webhook-notifier', 'Sent'), 'value' => (new \DateTime())->format('Y-m-d H:i:s')],
        ]);

        return $card->toArray();
    }

    // Private Methods
    // =========================================================================

    /**
     * Renders the structured card fields into Adaptive Card content.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function _renderStructured(array $config, array $context): array
    {
        $card = new AdaptiveCard();
        $card->version = (string)($config['version'] ?? $this->_defaultVersion());

        $title = $this->_renderTemplate((string)($config['title'] ?? ''), $context);
        $card->addTextBlock($title, ['size' => 'Large', 'weight' => 'Bolder']);

        $body = $this->_renderTemplate((string)($config['bodyMarkdown'] ?? ''), $context);
        $card->addTextBlock($body);

        $facts = [];
        foreach ((array)($config['facts'] ?? []) as $fact) {
            $facts[] = [
                'title' => $this->_renderTemplate((string)($fact['title'] ?? ''), $context),
                'value' => $this->_renderTemplate((string)($fact['value'] ?? ''), $context),
            ];
        }
        $card->addFactSet($facts);

        $button = $config['button'] ?? null;
        if (is_array($button)) {
            $label = $this->_renderTemplate((string)($button['label'] ?? ''), $context);
            $url = $this->_renderTemplate((string)($button['url'] ?? ''), $context);
            $card->addOpenUrlAction($label, $url);
        }

        return $card->toArray();
    }

    /**
     * Renders an advanced raw-JSON card into Adaptive Card content.
     *
     * @param string $template
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     * @throws InvalidArgumentException if the rendered string is not valid JSON.
     */
    private function _renderAdvanced(string $template, array $context): array
    {
        $rendered = $this->_renderTemplate($template, $context);

        try {
            $decoded = Json::decode($rendered);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('The advanced card did not produce valid JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('The advanced card did not produce a JSON object.');
        }

        return $decoded;
    }

    /**
     * Renders a single Twig object template against the context.
     *
     * @param string $template
     * @param array<string, mixed> $context
     * @return string
     */
    private function _renderTemplate(string $template, array $context): string
    {
        if (trim($template) === '') {
            return '';
        }

        try {
            // Pass the context as both the object (so `{shorthand}` works) and as
            // top-level variables (so full Twig like `{{ event.sender.title }}` works).
            return trim(Craft::$app->getView()->renderObjectTemplate($template, $context, $context));
        } catch (Throwable $e) {
            Craft::warning('Failed to render card template: ' . $e->getMessage(), 'webhook-notifier');
            return $template;
        }
    }

    /**
     * Returns the default Adaptive Card version from the plugin settings.
     *
     * @return string
     */
    private function _defaultVersion(): string
    {
        return Plugin::getInstance()->getSettings()->defaultCardVersion;
    }
}
