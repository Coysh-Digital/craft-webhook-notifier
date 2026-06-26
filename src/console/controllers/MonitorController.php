<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\console\controllers;

use Craft;
use coyshdigital\webhooknotifier\Plugin;
use coyshdigital\webhooknotifier\sources\QueueSizeSource;
use craft\console\Controller;
use craft\helpers\Console;
use yii\console\ExitCode;

/**
 * Polled monitors that feed the rules engine on a schedule (run from cron).
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class MonitorController extends Controller
{
    // Public Properties
    // =========================================================================

    /**
     * @var int Minimum minutes between queue-size notifications. 0 disables
     * throttling (the cron interval then controls frequency).
     */
    public int $cooldown = 0;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'queue') {
            $options[] = 'cooldown';
        }

        return $options;
    }

    /**
     * Checks the queue size and notifies any "Queue size" rules whose
     * conditions match.
     *
     * Run from cron, e.g. every 15 minutes:
     *   php craft webhook-notifier/monitor/queue
     *
     * @return int
     */
    public function actionQueue(): int
    {
        $queue = Craft::$app->getQueue();
        $total = $queue->getTotalJobs();

        $this->stdout(Craft::t('webhook-notifier', 'Queue size: {total} job(s).', ['total' => $total]) . PHP_EOL);

        $cache = Craft::$app->getCache();
        $cooldownKey = 'webhook-notifier:monitor:queue:cooldown';

        if ($this->cooldown > 0 && $cache->get($cooldownKey)) {
            $this->stdout(Craft::t('webhook-notifier', 'Within cooldown window - skipping.') . PHP_EOL, Console::FG_GREY);
            return ExitCode::OK;
        }

        $queued = Plugin::getInstance()->rules->handle(
            QueueSizeSource::id(),
            QueueSizeSource::context($total, $queue->getHasWaitingJobs(), $queue->getHasReservedJobs())
        );

        if ($queued > 0) {
            $this->stdout(Craft::t('webhook-notifier', 'Queued {n} notification(s).', ['n' => $queued]) . PHP_EOL, Console::FG_GREEN);
            if ($this->cooldown > 0) {
                $cache->set($cooldownKey, true, $this->cooldown * 60);
            }
        }

        return ExitCode::OK;
    }
}
