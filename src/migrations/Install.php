<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\migrations;

use craft\db\Migration;

/**
 * Install migration.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class Install extends Migration
{
    // Constants
    // =========================================================================

    /**
     * @var string The connections table.
     */
    public const CONNECTIONS = '{{%webhooknotifier_connections}}';

    /**
     * @var string The rules table.
     */
    public const RULES = '{{%webhooknotifier_rules}}';

    /**
     * @var string The delivery-log table.
     */
    public const DELIVERIES = '{{%webhooknotifier_deliveries}}';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->_createTables();
        $this->_createIndexes();
        $this->_addForeignKeys();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Drop in dependency order (deliveries reference rules + connections).
        $this->dropTableIfExists(self::DELIVERIES);
        $this->dropTableIfExists(self::RULES);
        $this->dropTableIfExists(self::CONNECTIONS);

        return true;
    }

    // Private Methods
    // =========================================================================

    /**
     * Creates the plugin's tables.
     *
     * @return void
     */
    private function _createTables(): void
    {
        $this->createTable(self::CONNECTIONS, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'url' => $this->text()->notNull(),
            'isEnvVar' => $this->boolean()->notNull()->defaultValue(false),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(self::RULES, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'sourceType' => $this->string()->notNull(),
            'senderClass' => $this->string(),
            'eventName' => $this->string(),
            'connectionId' => $this->integer(),
            'conditionConfig' => $this->text(),
            'cardConfig' => $this->text()->notNull(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'sortOrder' => $this->smallInteger()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable(self::DELIVERIES, [
            'id' => $this->primaryKey(),
            'ruleId' => $this->integer(),
            'connectionId' => $this->integer(),
            'sourceType' => $this->string(),
            'status' => $this->string()->notNull(),
            'httpStatus' => $this->smallInteger()->unsigned(),
            'attempt' => $this->smallInteger()->unsigned()->notNull()->defaultValue(0),
            'contextSummary' => $this->string(),
            'requestPayload' => $this->text(),
            'responseBody' => $this->text(),
            'errorMessage' => $this->text(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    /**
     * Creates indexes on the plugin's tables.
     *
     * @return void
     */
    private function _createIndexes(): void
    {
        $this->createIndex(null, self::RULES, ['sourceType']);
        $this->createIndex(null, self::RULES, ['enabled']);
        $this->createIndex(null, self::DELIVERIES, ['status', 'dateCreated']);
        $this->createIndex(null, self::DELIVERIES, ['ruleId']);
    }

    /**
     * Adds foreign keys to the plugin's tables.
     *
     * @return void
     */
    private function _addForeignKeys(): void
    {
        $this->addForeignKey(null, self::RULES, ['connectionId'], self::CONNECTIONS, ['id'], 'SET NULL');
        $this->addForeignKey(null, self::DELIVERIES, ['ruleId'], self::RULES, ['id'], 'SET NULL');
        $this->addForeignKey(null, self::DELIVERIES, ['connectionId'], self::CONNECTIONS, ['id'], 'SET NULL');
    }
}
