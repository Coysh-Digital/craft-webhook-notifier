<?php
/**
 * Webhook Notifier config
 *
 * Copy this file to your project's config/ directory as `webhook-notifier.php` and
 * uncomment any settings you'd like to override. Values set here take
 * precedence over what's configured in the control panel.
 */

use craft\helpers\App;

return [
    // Global on/off switch for all notifications.
    'enabled' => (bool)App::env('MS_TEAMS_ENABLED') ?: true,

    // The connection ID used by rules that don't specify their own.
    // 'defaultConnectionId' => null,

    // Default Adaptive Card schema version for new cards.
    'defaultCardVersion' => '1.5',

    // How many days delivery-log rows are kept before garbage collection.
    'logRetentionDays' => 30,

    // HTTP timeout, in seconds, when POSTing to a webhook.
    'httpTimeout' => 10,

    // Maximum number of times a failed send is retried.
    'maxRetries' => 5,
];
