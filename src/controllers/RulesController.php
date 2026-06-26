<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\controllers;

use Craft;
use coyshdigital\webhooknotifier\Plugin;
use coyshdigital\webhooknotifier\records\RuleRecord;
use coyshdigital\webhooknotifier\services\Cards;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Manages notification rules.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class RulesController extends Controller
{
    // Constants
    // =========================================================================

    /**
     * @var int How many days of delivery history the rules-list sparklines show.
     */
    public const SPARK_DAYS = 14;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @throws ForbiddenHttpException if the user lacks the rules permission.
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(Plugin::PERMISSION_MANAGE_RULES);

        return true;
    }

    /**
     * Lists all rules.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $plugin = Plugin::getInstance();
        $rules = $plugin->rules->getAllRules();

        return $this->renderTemplate('webhook-notifier/rules/index', [
            'rules' => $rules,
            'sourceOptions' => $plugin->sources->getSourceOptions(),
            'sparkDays' => self::SPARK_DAYS,
            'sparklines' => $this->_sparklines($rules, self::SPARK_DAYS),
        ]);
    }

    /**
     * Shows the rule edit form.
     *
     * @param int|null $ruleId
     * @param RuleRecord|null $rule
     * @return Response
     * @throws NotFoundHttpException if editing a rule that doesn't exist.
     */
    public function actionEdit(?int $ruleId = null, ?RuleRecord $rule = null): Response
    {
        $plugin = Plugin::getInstance();

        if ($rule === null) {
            if ($ruleId !== null) {
                $rule = $plugin->rules->getRuleById($ruleId);
                if ($rule === null) {
                    throw new NotFoundHttpException('Rule not found.');
                }
            } else {
                $rule = new RuleRecord(['enabled' => true]);
            }
        }

        $sourceOptions = $plugin->sources->getSourceOptions();
        $source = $rule->sourceType ? $plugin->sources->getSourceById($rule->sourceType) : null;

        $connectionOptions = ['' => Craft::t('webhook-notifier', 'Use default connection')];
        foreach ($plugin->connections->getAllConnections() as $connection) {
            $connectionOptions[$connection->id] = $connection->name;
        }

        $card = Json::decodeIfJson((string)$rule->cardConfig) ?: ['mode' => Cards::MODE_STRUCTURED];
        $condition = Json::decodeIfJson((string)$rule->conditionConfig) ?: ['match' => 'all', 'rules' => []];

        $isNew = !$rule->id;

        return $this->renderTemplate('webhook-notifier/rules/_edit', [
            'rule' => $rule,
            'isNew' => $isNew,
            'title' => $isNew ? Craft::t('webhook-notifier', 'New rule') : $rule->name,
            'sourceOptions' => $sourceOptions,
            'connectionOptions' => $connectionOptions,
            'card' => $card,
            'condition' => $condition,
            'contextSchema' => $source?->contextSchema() ?? [],
            'sourceFields' => $plugin->sources->getSourceFields(),
            'sourceCardVariables' => $plugin->sources->getSourceCardVariables(),
            'sourceDescriptions' => $plugin->sources->getSourceDescriptions(),
            'cardExamples' => $this->_cardExamples(),
            'operatorOptions' => $plugin->conditions->getOperatorOptions(),
            'matchOptions' => $plugin->conditions->getMatchOptions(),
        ]);
    }

    /**
     * Saves a rule.
     *
     * @return Response|null
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $plugin = Plugin::getInstance();
        $request = Craft::$app->getRequest();

        $id = $request->getBodyParam('ruleId');
        $rule = $id ? $plugin->rules->getRuleById((int)$id) : null;
        $rule ??= new RuleRecord();

        $rule->name = (string)$request->getBodyParam('name');
        $rule->sourceType = (string)$request->getBodyParam('sourceType');
        $rule->enabled = (bool)$request->getBodyParam('enabled', true);

        // Only meaningful for the "Custom event" source; stored regardless.
        $rule->senderClass = trim((string)$request->getBodyParam('senderClass')) ?: null;
        $rule->eventName = trim((string)$request->getBodyParam('eventName')) ?: null;

        $connectionId = $request->getBodyParam('connectionId');
        $rule->connectionId = $connectionId !== '' && $connectionId !== null ? (int)$connectionId : null;

        $rule->cardConfig = Json::encode($this->_buildCardConfig($request->getBodyParam('card', [])));

        $condition = $plugin->conditions->normalizeConfig(
            (string)$request->getBodyParam('conditionMatch', 'all'),
            $request->getBodyParam('conditionRules', []) ?: []
        );
        $rule->conditionConfig = Json::encode($condition);

        if (trim($rule->name) === '') {
            $rule->addError('name', Craft::t('webhook-notifier', 'A name is required.'));
        }
        if (trim($rule->sourceType) === '') {
            $rule->addError('sourceType', Craft::t('webhook-notifier', 'A source is required.'));
        }

        if ($rule->hasErrors() || !$plugin->rules->saveRule($rule)) {
            Craft::$app->getSession()->setError(Craft::t('webhook-notifier', 'Couldn’t save rule.'));
            Craft::$app->getUrlManager()->setRouteParams(['rule' => $rule]);
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('webhook-notifier', 'Rule saved.'));

        return $this->redirectToPostedUrl($rule);
    }

    /**
     * Deletes a rule.
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        $id = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (Plugin::getInstance()->rules->deleteRuleById($id)) {
            Craft::$app->getSession()->setNotice(Craft::t('webhook-notifier', 'Rule deleted.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('webhook-notifier', 'Couldn’t delete rule.'));
        }

        return $this->redirectToPostedUrl();
    }

    // Private Methods
    // =========================================================================

    /**
     * Builds a stored card config array from posted input.
     *
     * @param array<string, mixed> $posted
     * @return array<string, mixed>
     */
    private function _buildCardConfig(array $posted): array
    {
        $mode = $posted['mode'] ?? Cards::MODE_STRUCTURED;
        if (!in_array($mode, [Cards::MODE_STRUCTURED, Cards::MODE_ADVANCED, Cards::MODE_RAW], true)) {
            $mode = Cards::MODE_STRUCTURED;
        }

        $facts = [];
        foreach ((array)($posted['facts'] ?? []) as $row) {
            $title = trim((string)($row['title'] ?? ''));
            $value = trim((string)($row['value'] ?? ''));
            if ($title === '' && $value === '') {
                continue;
            }
            $facts[] = ['title' => $title, 'value' => $value];
        }

        return [
            'mode' => $mode,
            'title' => (string)($posted['title'] ?? ''),
            'bodyMarkdown' => (string)($posted['bodyMarkdown'] ?? ''),
            'facts' => $facts,
            'button' => [
                'label' => (string)($posted['buttonLabel'] ?? ''),
                'url' => (string)($posted['buttonUrl'] ?? ''),
            ],
            'advancedJson' => (string)($posted['advancedJson'] ?? ''),
            'payloadJson' => (string)($posted['payloadJson'] ?? ''),
        ];
    }

    /**
     * Returns starter templates for the advanced (raw JSON) card mode.
     *
     * @return array<int, array{label: string, template: string}>
     */
    private function _cardExamples(): array
    {
        $heading = <<<'JSON'
{
  "type": "AdaptiveCard",
  "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
  "version": "1.5",
  "body": [
    { "type": "TextBlock", "size": "Large", "weight": "Bolder", "wrap": true, "text": "{title}" },
    { "type": "TextBlock", "wrap": true, "text": "{summary}" }
  ]
}
JSON;

        $factsButton = <<<'JSON'
{
  "type": "AdaptiveCard",
  "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
  "version": "1.5",
  "body": [
    { "type": "TextBlock", "size": "Large", "weight": "Bolder", "wrap": true, "text": "{title}" },
    { "type": "FactSet", "facts": [
      { "title": "Status", "value": "{status}" },
      { "title": "Section", "value": "{section}" }
    ]}
  ],
  "actions": [
    { "type": "Action.OpenUrl", "title": "Open", "url": "{url}" }
  ]
}
JSON;

        // Loops every storable field that has a value (HTML blocks etc. are
        // already excluded by FreeformSource), with no empty rows or trailing comma.
        $freeformAll = <<<'JSON'
{
  "type": "AdaptiveCard",
  "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
  "version": "1.5",
  "body": [
    { "type": "TextBlock", "size": "Large", "weight": "Bolder", "wrap": true, "text": "New {formName} submission" },
    { "type": "FactSet", "facts": [
      {% set rows = object.fieldList|filter(f => f.value != '') %}{% for f in rows %}{ "title": {{ f.label|json_encode|raw }}, "value": {{ f.value|json_encode|raw }} }{% if not loop.last %},{% endif %}{% endfor %}
    ]}
  ]
}
JSON;

        // A fixed set of common contact-form fields (edit the handles to match
        // your form). Missing fields just render empty rather than erroring.
        $freeformBasic = <<<'JSON'
{
  "type": "AdaptiveCard",
  "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
  "version": "1.5",
  "body": [
    { "type": "TextBlock", "size": "Large", "weight": "Bolder", "wrap": true, "text": "New {formName} submission" },
    { "type": "FactSet", "facts": [
      { "title": "Name", "value": {{ (fields.name ?? fields.fullName ?? '')|json_encode|raw }} },
      { "title": "Email", "value": {{ (fields.email ?? '')|json_encode|raw }} },
      { "title": "Phone", "value": {{ (fields.phone ?? '')|json_encode|raw }} },
      { "title": "Message", "value": {{ (fields.message ?? '')|json_encode|raw }} }
    ]}
  ]
}
JSON;

        return [
            ['label' => Craft::t('webhook-notifier', 'Heading + message'), 'template' => $heading],
            ['label' => Craft::t('webhook-notifier', 'Heading + facts + button'), 'template' => $factsButton],
            ['label' => Craft::t('webhook-notifier', 'Freeform: all submitted fields'), 'template' => $freeformAll],
            ['label' => Craft::t('webhook-notifier', 'Freeform: basic fields'), 'template' => $freeformBasic],
        ];
    }

    /**
     * Builds per-rule activity sparkline data for the rules list.
     *
     * @param RuleRecord[] $rules
     * @param int $days
     * @return array<int, array{days: array<int, array{date: string, sent: int, failed: int, total: int}>, max: int, sent: int, failed: int}>
     */
    private function _sparklines(array $rules, int $days): array
    {
        $counts = Plugin::getInstance()->deliveries->getDailyCountsByRule($days);

        // Day keys (UTC, to match the SQL DATE()), oldest to newest.
        $today = DateTimeHelper::currentUTCDateTime()->setTime(0, 0);
        $dayKeys = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dayKeys[] = (clone $today)->modify("-$i days")->format('Y-m-d');
        }

        $sparklines = [];

        foreach ($rules as $rule) {
            $ruleCounts = $counts[$rule->id] ?? [];
            $series = [];
            $max = 0;
            $sent = 0;
            $failed = 0;

            foreach ($dayKeys as $dayKey) {
                $day = $ruleCounts[$dayKey] ?? ['sent' => 0, 'failed' => 0, 'total' => 0];
                $series[] = ['date' => $dayKey] + $day;
                $max = max($max, $day['total']);
                $sent += $day['sent'];
                $failed += $day['failed'];
            }

            $sparklines[$rule->id] = ['days' => $series, 'max' => $max, 'sent' => $sent, 'failed' => $failed];
        }

        return $sparklines;
    }
}
