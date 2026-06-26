<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\records;

use craft\db\ActiveRecord;

/**
 * Delivery record - one row in the delivery log.
 *
 * @property int $id
 * @property int|null $ruleId
 * @property int|null $connectionId
 * @property string|null $sourceType
 * @property string|null $context JSON-encoded serializable event context, for resends.
 * @property string $status
 * @property int|null $httpStatus
 * @property int $attempt
 * @property string|null $contextSummary
 * @property string|null $requestPayload
 * @property string|null $responseBody
 * @property string|null $errorMessage
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class DeliveryRecord extends ActiveRecord
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%webhooknotifier_deliveries}}';
    }
}
