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
 * Adds the sender class / event name columns used by the "Custom event" source.
 *
 * @author Coysh Digital
 * @since 1.3.0
 */
class m260626_000000_add_custom_event_columns extends Migration
{
    // Constants
    // =========================================================================

    /**
     * @var string The rules table.
     */
    public const RULES = '{{%webhooknotifier_rules}}';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(self::RULES, 'senderClass')) {
            $this->addColumn(self::RULES, 'senderClass', $this->string()->after('sourceType'));
        }
        if (!$this->db->columnExists(self::RULES, 'eventName')) {
            $this->addColumn(self::RULES, 'eventName', $this->string()->after('senderClass'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->columnExists(self::RULES, 'eventName')) {
            $this->dropColumn(self::RULES, 'eventName');
        }
        if ($this->db->columnExists(self::RULES, 'senderClass')) {
            $this->dropColumn(self::RULES, 'senderClass');
        }

        return true;
    }
}
