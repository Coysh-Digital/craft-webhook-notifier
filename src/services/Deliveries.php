<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\services;

use Craft;
use coyshdigital\webhooknotifier\records\DeliveryRecord;
use craft\db\Query;
use craft\helpers\Db;
use DateTime;
use yii\base\Component;

/**
 * Deliveries service — writes, reads, and prunes the delivery log.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class Deliveries extends Component
{
    // Constants
    // =========================================================================

    /**
     * @var string A delivery that has been queued but not yet attempted.
     */
    public const STATUS_QUEUED = 'queued';

    /**
     * @var string A delivery accepted by the webhook.
     */
    public const STATUS_SENT = 'sent';

    /**
     * @var string A delivery that failed permanently (will not be retried).
     */
    public const STATUS_FAILED = 'failed';

    /**
     * @var string A delivery that failed transiently and will be retried.
     */
    public const STATUS_RETRYING = 'retrying';

    /**
     * @var int The maximum number of characters stored for a payload/response.
     */
    public const MAX_BODY_LENGTH = 16000;

    // Public Methods
    // =========================================================================

    /**
     * Creates and saves a delivery-log row.
     *
     * @param array<string, mixed> $attributes
     * @return DeliveryRecord
     */
    public function log(array $attributes): DeliveryRecord
    {
        if (isset($attributes['requestPayload'])) {
            $attributes['requestPayload'] = $this->_truncate((string)$attributes['requestPayload']);
        }
        if (isset($attributes['responseBody'])) {
            $attributes['responseBody'] = $this->_truncate((string)$attributes['responseBody']);
        }
        if (isset($attributes['contextSummary'])) {
            $attributes['contextSummary'] = mb_substr((string)$attributes['contextSummary'], 0, 255);
        }

        $record = new DeliveryRecord($attributes);
        $record->save();

        return $record;
    }

    /**
     * Returns a delivery-log row by its ID.
     *
     * @param int $id
     * @return DeliveryRecord|null
     */
    public function getDeliveryById(int $id): ?DeliveryRecord
    {
        return DeliveryRecord::findOne($id);
    }

    /**
     * Returns recent delivery-log rows, newest first.
     *
     * @param string|null $status Optionally filter by status.
     * @param int $limit
     * @return DeliveryRecord[]
     */
    public function getRecentDeliveries(?string $status = null, int $limit = 200): array
    {
        $query = DeliveryRecord::find()
            ->orderBy(['dateCreated' => SORT_DESC, 'id' => SORT_DESC])
            ->limit($limit);

        if ($status !== null) {
            $query->where(['status' => $status]);
        }

        return $query->all();
    }

    /**
     * Deletes delivery-log rows older than the given number of days.
     *
     * @param int $days
     * @return int The number of rows deleted.
     */
    public function prune(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        $cutoff = (new DateTime())->modify("-{$days} days");

        return Craft::$app->getDb()->createCommand()
            ->delete(DeliveryRecord::tableName(), ['<', 'dateCreated', Db::prepareDateForDb($cutoff)])
            ->execute();
    }

    /**
     * Counts delivery-log rows, optionally by status.
     *
     * @param string|null $status
     * @return int
     */
    public function getCount(?string $status = null): int
    {
        $query = (new Query())->from(DeliveryRecord::tableName());

        if ($status !== null) {
            $query->where(['status' => $status]);
        }

        return (int)$query->count();
    }

    // Private Methods
    // =========================================================================

    /**
     * Truncates a string to the maximum stored body length.
     *
     * @param string $value
     * @return string
     */
    private function _truncate(string $value): string
    {
        if (mb_strlen($value) <= self::MAX_BODY_LENGTH) {
            return $value;
        }

        return mb_substr($value, 0, self::MAX_BODY_LENGTH) . '…';
    }
}
