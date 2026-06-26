<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Rule record - one no-code notification rule.
 *
 * @property int $id
 * @property string $name
 * @property string $sourceType The notification source key (e.g. `entry`, `freeform`).
 * @property string|null $senderClass For the "Custom event" source: the class to listen on.
 * @property string|null $eventName For the "Custom event" source: the event name to listen for.
 * @property int|null $connectionId
 * @property string|null $conditionConfig JSON-encoded condition config.
 * @property string $cardConfig JSON-encoded card config.
 * @property bool $enabled
 * @property int|null $sortOrder
 * @property ConnectionRecord|null $connection
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class RuleRecord extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%webhooknotifier_rules}}';
    }

    // Public Methods
    // =========================================================================

    /**
     * Returns the rule's connection.
     *
     * @return ActiveQueryInterface
     */
    public function getConnection(): ActiveQueryInterface
    {
        return $this->hasOne(ConnectionRecord::class, ['id' => 'connectionId']);
    }
}
