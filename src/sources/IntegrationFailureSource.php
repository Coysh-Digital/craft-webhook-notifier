<?php
/**
 * Webhook Notifier plugin for Craft CMS 5.x
 *
 * @link      https://coysh.digital
 * @copyright Copyright (c) Coysh Digital
 */

namespace coyshdigital\webhooknotifier\sources;

use Craft;
use coyshdigital\webhooknotifier\jobs\SendNotification;
use craft\queue\JobInterface as CraftJobInterface;
use yii\base\Event;
use yii\queue\ExecEvent;
use yii\queue\Queue;

/**
 * Notification source: an integration/queue job failed.
 *
 * Fires for any failed queue job, and also exposes a programmatic reporting API
 * (see {@see \coyshdigital\webhooknotifier\services\Sources::reportFailure()}) so other
 * code - such as the site's Dynamics services - can raise failures directly.
 *
 * @author Coysh Digital
 * @since 1.0.0
 */
class IntegrationFailureSource extends BaseSource
{
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'integrationFailure';
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('webhook-notifier', 'Integration failure');
    }

    /**
     * Builds a context for a programmatically-reported failure.
     *
     * @param string $title
     * @param string $detail
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function reportContext(string $title, string $detail, array $data = []): array
    {
        return [
            'title' => $title,
            'detail' => $detail,
            'data' => $data,
            'jobClass' => null,
            'jobDescription' => null,
            'attempt' => null,
            'error' => $detail,
            'summary' => $title,
        ];
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function description(): string
    {
        return Craft::t('webhook-notifier', 'Fires when a background (queue) job fails after Craft has exhausted its retries - for example a Dynamics 365 sync, a mailer send, or any plugin’s queue job. It also fires when your own code calls the reporting API: Plugin::getInstance()->sources->reportFailure($title, $detail, $data). Use it to be alerted in Teams the moment something breaks behind the scenes. This plugin’s own delivery jobs are ignored, so a failed Teams send can never trigger another failure notification.');
    }

    /**
     * @inheritdoc
     */
    public function contextSchema(): array
    {
        return [
            'title' => Craft::t('webhook-notifier', 'Title'),
            'detail' => Craft::t('webhook-notifier', 'Detail'),
            'jobClass' => Craft::t('webhook-notifier', 'Job class'),
            'jobDescription' => Craft::t('webhook-notifier', 'Job description'),
            'attempt' => Craft::t('webhook-notifier', 'Attempt'),
            'error' => Craft::t('webhook-notifier', 'Error message'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attachListeners(): void
    {
        Event::on(
            Queue::class,
            Queue::EVENT_AFTER_ERROR,
            function(ExecEvent $event) {
                $job = $event->job;

                // Loop guard: never let a failed send trigger another send.
                if ($job instanceof SendNotification) {
                    return;
                }

                $description = $job instanceof CraftJobInterface
                    ? $job->getDescription()
                    : null;
                $jobClass = is_object($job) ? get_class($job) : (string)$job;
                $error = $event->error?->getMessage() ?? Craft::t('webhook-notifier', 'Unknown error');

                $this->dispatch([
                    'title' => Craft::t('webhook-notifier', 'Queue job failed'),
                    'detail' => $error,
                    'data' => ['jobId' => $event->id],
                    'jobClass' => $jobClass,
                    'jobDescription' => $description,
                    'attempt' => $event->attempt,
                    'error' => $error,
                    'summary' => $description
                        ? Craft::t('webhook-notifier', 'Job failed: {desc}', ['desc' => $description])
                        : Craft::t('webhook-notifier', 'Queue job failed'),
                ]);
            }
        );
    }
}
