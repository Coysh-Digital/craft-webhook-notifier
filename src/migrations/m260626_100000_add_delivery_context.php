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
 * Adds the `context` column to deliveries, so a delivery can be re-rendered and
 * resent against its rule's current card/payload.
 *
 * @author Coysh Digital
 * @since 1.5.0
 */
class m260626_100000_add_delivery_context extends Migration
{
    // Constants
    // =========================================================================

    /**
     * @var string The deliveries table.
     */
    public const DELIVERIES = '{{%webhooknotifier_deliveries}}';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists(self::DELIVERIES, 'context')) {
            $this->addColumn(self::DELIVERIES, 'context', $this->text()->after('sourceType'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        if ($this->db->columnExists(self::DELIVERIES, 'context')) {
            $this->dropColumn(self::DELIVERIES, 'context');
        }

        return true;
    }
}
