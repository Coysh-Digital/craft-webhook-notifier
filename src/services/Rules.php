<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\services;

use Craft;
use coyshdigital\webhooknotifier\jobs\SendNotification;
use coyshdigital\webhooknotifier\Plugin;
use coyshdigital\webhooknotifier\records\RuleRecord;
use craft\helpers\Json;
use craft\helpers\Queue;
use Throwable;
use yii\base\Component;

/**
 * Rules service - CRUD for notification rules, and the dispatch path that turns
 * a fired source event into queued notifications.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class Rules extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Returns all rules, ordered for display.
     *
     * @return RuleRecord[]
     */
    public function getAllRules(): array
    {
        return RuleRecord::find()
            ->orderBy(['sortOrder' => SORT_ASC, 'name' => SORT_ASC])
            ->all();
    }

    /**
     * Returns a rule by its ID.
     *
     * @param int $id
     * @return RuleRecord|null
     */
    public function getRuleById(int $id): ?RuleRecord
    {
        return RuleRecord::findOne($id);
    }

    /**
     * Returns the enabled rules for a given source type.
     *
     * @param string $sourceType
     * @return RuleRecord[]
     */
    public function getEnabledRulesForSource(string $sourceType): array
    {
        return RuleRecord::find()
            ->where(['sourceType' => $sourceType, 'enabled' => true])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();
    }

    /**
     * Validates and saves a rule.
     *
     * @param RuleRecord $rule
     * @return bool
     */
    public function saveRule(RuleRecord $rule): bool
    {
        return $rule->save();
    }

    /**
     * Deletes a rule by its ID.
     *
     * @param int $id
     * @return bool
     */
    public function deleteRuleById(int $id): bool
    {
        $rule = $this->getRuleById($id);

        if ($rule === null) {
            return false;
        }

        return (bool)$rule->delete();
    }

    /**
     * Handles a fired source event: finds the enabled rules for the source,
     * then dispatches them against the context.
     *
     * @param string $sourceType
     * @param array<string, mixed> $context The source's normalized context.
     * @return int The number of notifications queued.
     */
    public function handle(string $sourceType, array $context): int
    {
        return $this->dispatchRules($this->getEnabledRulesForSource($sourceType), $sourceType, $context);
    }

    /**
     * Dispatches a given set of rules against a context: evaluates each rule's
     * condition, renders its payload, and queues a notification.
     *
     * (The "Custom event" source calls this directly with the rules matching the
     * fired class + event.)
     *
     * @param RuleRecord[] $rules
     * @param string $sourceType
     * @param array<string, mixed> $context
     * @return int The number of notifications queued.
     */
    public function dispatchRules(array $rules, string $sourceType, array $context): int
    {
        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();

        if (!$settings->enabled) {
            return 0;
        }

        $queued = 0;

        // A serializable snapshot of the context, stored on each delivery so it
        // can be re-rendered and resent later (objects are dropped).
        $contextJson = Json::encode($this->_serializableContext($context));

        foreach ($rules as $rule) {
            try {
                if (!$this->_passesCondition($rule, $context)) {
                    continue;
                }

                $connectionId = $rule->connectionId ?: $settings->defaultConnectionId;

                if ($connectionId === null) {
                    $plugin->deliveries->log([
                        'ruleId' => $rule->id,
                        'sourceType' => $sourceType,
                        'context' => $contextJson,
                        'status' => Deliveries::STATUS_FAILED,
                        'contextSummary' => $this->_summary($context, $sourceType),
                        'errorMessage' => Craft::t('webhook-notifier', 'No connection set on the rule or in settings.'),
                    ]);
                    continue;
                }

                $cardConfig = Json::decodeIfJson($rule->cardConfig) ?: [];
                $payload = $plugin->cards->render($cardConfig, $context);

                Queue::push(new SendNotification([
                    'connectionId' => (int)$connectionId,
                    'payload' => $payload,
                    'ruleId' => $rule->id,
                    'sourceType' => $sourceType,
                    'context' => $contextJson,
                    'contextSummary' => $this->_summary($context, $sourceType),
                ]));

                $queued++;
            } catch (Throwable $e) {
                Craft::error("Rule {$rule->id} failed to dispatch: " . $e->getMessage(), 'webhook-notifier');
                $plugin->deliveries->log([
                    'ruleId' => $rule->id,
                    'sourceType' => $sourceType,
                    'context' => $contextJson,
                    'status' => Deliveries::STATUS_FAILED,
                    'contextSummary' => $this->_summary($context, $sourceType),
                    'errorMessage' => $e->getMessage(),
                ]);
            }
        }

        return $queued;
    }

    /**
     * Returns the JSON-serializable subset of a context (scalars and plain
     * arrays), dropping objects like elements and event objects so it can be
     * stored on a delivery and used to resend.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function _serializableContext(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            if ($value === null || is_scalar($value)) {
                $clean[$key] = $value;
            } elseif (is_array($value)) {
                try {
                    Json::encode($value);
                    $clean[$key] = $value;
                } catch (Throwable) {
                    // Skip arrays that contain non-encodable values.
                }
            }
        }

        return $clean;
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns whether a rule's condition passes for the given context.
     *
     * @param RuleRecord $rule
     * @param array<string, mixed> $context
     * @return bool
     */
    private function _passesCondition(RuleRecord $rule, array $context): bool
    {
        $config = trim((string)$rule->conditionConfig);

        if ($config === '') {
            return true;
        }

        return Plugin::getInstance()->conditions->matches($config, $context);
    }

    /**
     * Builds a short human-readable summary of a context for the delivery log.
     *
     * @param array<string, mixed> $context
     * @param string $sourceType
     * @return string
     */
    private function _summary(array $context, string $sourceType): string
    {
        if (isset($context['summary']) && is_string($context['summary'])) {
            return $context['summary'];
        }

        return $sourceType;
    }
}
