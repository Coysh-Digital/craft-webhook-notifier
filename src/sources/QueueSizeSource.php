<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\sources;

use Craft;

/**
 * Notification source: the queue size, checked on a schedule.
 *
 * Unlike the event-driven sources, this one is *polled*: run
 * `php craft webhook-notifier/monitor/queue` from cron, and it dispatches the current
 * queue size to this source's rules. Pair it with a numeric condition such as
 * "total is greater than 50".
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class QueueSizeSource extends BaseSource
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'queueSize';
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('webhook-notifier', 'Queue size');
    }

    /**
     * Builds the context for a queue-size check.
     *
     * @param int $total
     * @param bool $hasWaiting
     * @param bool $hasReserved
     * @return array<string, mixed>
     */
    public static function context(int $total, bool $hasWaiting, bool $hasReserved): array
    {
        return [
            'total' => $total,
            'hasWaiting' => $hasWaiting,
            'hasReserved' => $hasReserved,
            'summary' => Craft::t('webhook-notifier', 'Queue size: {total} job(s)', ['total' => $total]),
        ];
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function description(): string
    {
        return Craft::t('webhook-notifier', 'Checks how many jobs are in the queue, on a schedule. Run “php craft webhook-notifier/monitor/queue” from cron (e.g. every 15 minutes); pair this source with a condition like “total is greater than 50” to be alerted when the backlog grows. The cron frequency controls how often you’re alerted while the queue stays over the threshold.');
    }

    /**
     * @inheritdoc
     */
    public function contextSchema(): array
    {
        return [
            'total' => Craft::t('webhook-notifier', 'Total jobs in the queue'),
            'hasWaiting' => Craft::t('webhook-notifier', 'Has waiting jobs (true/false)'),
            'hasReserved' => Craft::t('webhook-notifier', 'Has reserved jobs (true/false)'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attachListeners(): void
    {
        // Polled, not event-driven — see the webhook-notifier/monitor/queue command.
    }
}
