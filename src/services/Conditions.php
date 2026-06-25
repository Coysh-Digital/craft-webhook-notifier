<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\services;

use Craft;
use craft\helpers\Json;
use yii\base\Component;

/**
 * Conditions service — evaluates and normalizes a rule's no-code condition.
 *
 * A condition is a small set of `field / operator / value` tests combined with
 * "match all" or "match any" logic, evaluated against a source's context.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class Conditions extends Component
{
    // Constants
    // =========================================================================

    /**
     * @var string Every test must pass.
     */
    public const MATCH_ALL = 'all';

    /**
     * @var string Any test passing is enough.
     */
    public const MATCH_ANY = 'any';

    // Public Methods
    // =========================================================================

    /**
     * Returns the available operators as `[value => label]`.
     *
     * @return array<string, string>
     */
    public function getOperatorOptions(): array
    {
        return [
            'equals' => Craft::t('webhook-notifier', 'equals'),
            'notEquals' => Craft::t('webhook-notifier', 'does not equal'),
            'contains' => Craft::t('webhook-notifier', 'contains'),
            'notContains' => Craft::t('webhook-notifier', 'does not contain'),
            'in' => Craft::t('webhook-notifier', 'is one of (comma-separated)'),
            'greaterThan' => Craft::t('webhook-notifier', 'is greater than'),
            'greaterOrEqual' => Craft::t('webhook-notifier', 'is greater than or equal to'),
            'lessThan' => Craft::t('webhook-notifier', 'is less than'),
            'lessOrEqual' => Craft::t('webhook-notifier', 'is less than or equal to'),
            'isEmpty' => Craft::t('webhook-notifier', 'is empty'),
            'isNotEmpty' => Craft::t('webhook-notifier', 'is not empty'),
        ];
    }

    /**
     * Returns the match-mode options as `[value => label]`.
     *
     * @return array<string, string>
     */
    public function getMatchOptions(): array
    {
        return [
            self::MATCH_ALL => Craft::t('webhook-notifier', 'Match all'),
            self::MATCH_ANY => Craft::t('webhook-notifier', 'Match any'),
        ];
    }

    /**
     * Normalizes posted condition input into a storable config array.
     *
     * @param string $match
     * @param array<int, array<string, mixed>> $rows
     * @return array{match: string, rules: array<int, array{field: string, operator: string, value: string}>}
     */
    public function normalizeConfig(string $match, array $rows): array
    {
        $rules = [];

        foreach ($rows as $row) {
            $field = trim((string)($row['field'] ?? ''));
            if ($field === '') {
                continue;
            }
            $rules[] = [
                'field' => $field,
                'operator' => (string)($row['operator'] ?? 'equals'),
                'value' => (string)($row['value'] ?? ''),
            ];
        }

        return [
            'match' => $match === self::MATCH_ANY ? self::MATCH_ANY : self::MATCH_ALL,
            'rules' => $rules,
        ];
    }

    /**
     * Returns whether a stored condition config matches the given context.
     *
     * An empty or absent condition always matches.
     *
     * @param string $configJson
     * @param array<string, mixed> $context
     * @return bool
     */
    public function matches(string $configJson, array $context): bool
    {
        $config = Json::decodeIfJson($configJson);

        if (!is_array($config) || empty($config['rules'])) {
            return true;
        }

        $match = $config['match'] ?? self::MATCH_ALL;
        $results = [];

        foreach ($config['rules'] as $rule) {
            $field = (string)($rule['field'] ?? '');
            if ($field === '') {
                continue;
            }
            $results[] = $this->_evaluate(
                $this->_contextValue($context, $field),
                (string)($rule['operator'] ?? 'equals'),
                (string)($rule['value'] ?? '')
            );
        }

        if ($results === []) {
            return true;
        }

        return $match === self::MATCH_ANY
            ? in_array(true, $results, true)
            : !in_array(false, $results, true);
    }

    // Private Methods
    // =========================================================================

    /**
     * Resolves a context value to a comparable string.
     *
     * @param array<string, mixed> $context
     * @param string $field
     * @return string|null
     */
    private function _contextValue(array $context, string $field): ?string
    {
        if (!array_key_exists($field, $context)) {
            return null;
        }

        $value = $context[$field];

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return implode(',', array_map('strval', $value));
        }

        if ($value === null) {
            return null;
        }

        return (string)$value;
    }

    /**
     * Evaluates a single test.
     *
     * @param string|null $actual
     * @param string $operator
     * @param string $value
     * @return bool
     */
    private function _evaluate(?string $actual, string $operator, string $value): bool
    {
        $actualLower = mb_strtolower((string)$actual);
        $valueLower = mb_strtolower($value);

        return match ($operator) {
            'equals' => (string)$actual === $value,
            'notEquals' => (string)$actual !== $value,
            'contains' => $value !== '' && str_contains($actualLower, $valueLower),
            'notContains' => $value === '' || !str_contains($actualLower, $valueLower),
            'in' => in_array((string)$actual, array_map('trim', explode(',', $value)), true),
            'greaterThan' => $this->_numeric($actual, $value, fn($a, $b) => $a > $b),
            'greaterOrEqual' => $this->_numeric($actual, $value, fn($a, $b) => $a >= $b),
            'lessThan' => $this->_numeric($actual, $value, fn($a, $b) => $a < $b),
            'lessOrEqual' => $this->_numeric($actual, $value, fn($a, $b) => $a <= $b),
            'isEmpty' => $actual === null || $actual === '',
            'isNotEmpty' => $actual !== null && $actual !== '',
            default => false,
        };
    }

    /**
     * Compares two values numerically, returning false if either isn't numeric.
     *
     * @param string|null $actual
     * @param string $value
     * @param callable(float, float): bool $compare
     * @return bool
     */
    private function _numeric(?string $actual, string $value, callable $compare): bool
    {
        if (!is_numeric($actual) || !is_numeric($value)) {
            return false;
        }

        return $compare((float)$actual, (float)$value);
    }
}
