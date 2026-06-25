<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\console\controllers;

use coyshdigital\webhooknotifier\Plugin;
use coyshdigital\webhooknotifier\records\RuleRecord;
use craft\console\Controller;
use craft\helpers\Console;
use craft\helpers\Json;
use yii\console\ExitCode;

/**
 * Maintenance commands: prune the delivery log and export/import rules.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class MaintenanceController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Prunes old delivery-log rows.
     *
     * @param int|null $days Override for the retention period, in days.
     * @return int
     */
    public function actionPrune(?int $days = null): int
    {
        $days ??= Plugin::getInstance()->getSettings()->logRetentionDays;
        $deleted = Plugin::getInstance()->deliveries->prune($days);

        $this->stdout("Pruned {$deleted} delivery-log row(s) older than {$days} days." . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Exports all rules as JSON to stdout (or a file).
     *
     * Rules carry no secrets; connections are referenced by name so the export
     * is portable between environments.
     *
     * @param string|null $file Optional path to write the JSON to.
     * @return int
     */
    public function actionExportRules(?string $file = null): int
    {
        $connectionsById = [];
        foreach (Plugin::getInstance()->connections->getAllConnections() as $connection) {
            $connectionsById[$connection->id] = $connection->name;
        }

        $export = [];
        foreach (Plugin::getInstance()->rules->getAllRules() as $rule) {
            $export[] = [
                'name' => $rule->name,
                'sourceType' => $rule->sourceType,
                'connectionName' => $rule->connectionId ? ($connectionsById[$rule->connectionId] ?? null) : null,
                'conditionConfig' => $rule->conditionConfig,
                'cardConfig' => $rule->cardConfig,
                'enabled' => (bool)$rule->enabled,
                'sortOrder' => $rule->sortOrder,
            ];
        }

        $json = Json::encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($file !== null) {
            file_put_contents($file, $json);
            $this->stdout("Exported " . count($export) . " rule(s) to {$file}." . PHP_EOL, Console::FG_GREEN);
        } else {
            $this->stdout($json . PHP_EOL);
        }

        return ExitCode::OK;
    }

    /**
     * Imports rules from a JSON file produced by `export-rules`.
     *
     * @param string $file Path to the JSON file.
     * @return int
     */
    public function actionImportRules(string $file): int
    {
        if (!is_file($file)) {
            $this->stderr("File not found: {$file}" . PHP_EOL, Console::FG_RED);
            return ExitCode::NOINPUT;
        }

        $data = Json::decodeIfJson((string)file_get_contents($file));

        if (!is_array($data)) {
            $this->stderr("Invalid JSON in {$file}." . PHP_EOL, Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $connectionsByName = [];
        foreach (Plugin::getInstance()->connections->getAllConnections() as $connection) {
            $connectionsByName[$connection->name] = $connection->id;
        }

        $imported = 0;
        foreach ($data as $row) {
            $rule = new RuleRecord();
            $rule->name = (string)($row['name'] ?? 'Imported rule');
            $rule->sourceType = (string)($row['sourceType'] ?? '');
            $rule->conditionConfig = $row['conditionConfig'] ?? null;
            $rule->cardConfig = (string)($row['cardConfig'] ?? '{}');
            $rule->enabled = (bool)($row['enabled'] ?? true);
            $rule->sortOrder = $row['sortOrder'] ?? null;

            $connectionName = $row['connectionName'] ?? null;
            $rule->connectionId = $connectionName !== null ? ($connectionsByName[$connectionName] ?? null) : null;

            if ($rule->sourceType === '' || !$rule->save()) {
                $this->stderr("Skipped a rule (missing source or save failed): {$rule->name}" . PHP_EOL, Console::FG_YELLOW);
                continue;
            }

            $imported++;
        }

        $this->stdout("Imported {$imported} rule(s)." . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }
}
