# Webhook Notifier for Craft CMS

Webhook Notifier sends a webhook whenever something happens on your Craft site,
using rules you set up in the control panel. It started life as a Microsoft Teams
notifier - Microsoft retired the old Incoming Webhook connectors in May 2026, and
the replacement (Power Automate Workflows) needs its Adaptive Cards wrapped in a
particular envelope, which this plugin handles for you. It has since grown into a
general webhook tool: it can post to **any** webhook - Zapier, Make, Slack, your
own endpoint - with whatever payload you define.

So there are two halves to it: a delivery side (Teams Adaptive Cards, or a raw
payload of your choosing) and a trigger side (built-in sources, or any Craft/Yii
event you name).

## What it does

- **Rules engine in the control panel.** Each rule pairs a trigger with a
  destination and a payload. No code required for the common cases.
- **Connections.** A named destination = a webhook URL, stored encrypted or as a
  reference to an environment variable so the secret stays out of your database
  and version control.
- **Two payload formats.** Build a Microsoft Teams Adaptive Card (structured
  fields or raw card JSON), or send a **raw payload** - a Twig template whose
  output is POSTed to the webhook as-is, for any non-Teams target.
- **Triggers.** Built-in sources for common cases, plus a **Custom event** source
  that listens on any class and event you specify.
- **Reliable delivery.** Notifications are queued and retried, and every attempt
  is recorded in a delivery log so you can see what was sent and what came back.
- **Extensible.** Register your own sources from another plugin or module via a
  single event.

## Requirements

- Craft CMS 5.0 or later
- PHP 8.2 or later

## Installation

```bash
composer require coysh-digital/craft-webhook-notifier
php craft plugin/install webhook-notifier
```

## Instant, event-driven webhooks

The **Custom event** source is the general-purpose trigger, and it works much
like Craft's first-party Webhook plugin: you give a rule a **Sender Class** (for
example `craft\elements\Entry`) and an **Event Name** (for example `afterSave`),
and the moment that event fires, the rule runs. There's no polling and no cron -
it's immediate.

When the event fires, the event object is handed to your payload as `event`, so
you can reference anything on it. A raw payload for a Zapier/Make webhook might
look like:

```twig
{{ {
    id: event.sender.id,
    title: event.sender.title,
    site: event.sender.site.handle
}|json_encode|raw }}
```

This makes it straightforward to forward Craft events to any automation tool, not
just Teams. Pick the **Raw payload** card mode for these, since you usually don't
want a Teams Adaptive Card wrapper around a generic webhook body.

## Sending to Microsoft Teams

Teams is still a first-class target, with proper Adaptive Card support.

1. In Teams, open the channel → **⋯ → Workflows**.
2. Choose the **“Post to a channel when a webhook request is received”** template.
3. Complete the wizard **signed in as a member of that team** (this matters - see
   Troubleshooting), and copy the **HTTP POST URL**.
4. In Craft: **Webhook Notifier → Connections → New**, paste the URL (or an
   `$ENV_VAR` reference), and use **Send test card** to confirm it works.

Then build a rule with a Teams card in either the **Structured** mode (title,
body, facts, a button) or the **Advanced** mode (full Adaptive Card JSON, with a
few starter examples to copy).

## The built-in sources

| Source | Fires when… | Trigger type |
| --- | --- | --- |
| **Custom event** | Any class + event you name (e.g. `craft\elements\Entry` / `afterSave`) | Instant |
| **Entry saved** | An entry is created or updated (drafts/revisions skipped) | Instant |
| **User event** | A user registers, is activated, or changes group | Instant |
| **Integration failure** | A queued job fails after Craft's retries, or your code calls `reportFailure()` | Instant |
| **Freeform submission** | A Freeform form is submitted (field values included) | Instant |
| **Queue size** | A scheduled check finds the queue over a threshold | Scheduled |

The instant sources fire as the event happens. The Queue size source is polled,
so it needs a cron (below). Each source lists the variables it exposes right in
the rule editor.

## Variables in payloads

Anything in `{curly braces}` is a Twig value from the triggering event, and full
Twig tags work too. Some examples:

- Entry source: `{title}`, `{section}`, `{url}`, `{authorName}`
- Freeform source: `{fields.email}` (any field handle), `{allFields}`,
  `{formName}`, `{formId}`
- Integration failure: `{jobDescription}`, `{error}`
- Custom event: `{{ event.sender.title }}`, `{{ event.sender.id }}`

## Scheduled monitor (Queue size)

The Queue size source is checked on a schedule, so add a cron entry:

```bash
php craft webhook-notifier/monitor/queue                # e.g. every 15 minutes
php craft webhook-notifier/monitor/queue --cooldown=30  # at most one alert / 30 min
```

Then create a rule with the **Queue size** source and a condition such as
`total is greater than 50`.

## Sending notifications from your own code

```php
use coyshdigital\webhooknotifier\Plugin;

Plugin::getInstance()->sources->reportFailure(
    'Dynamics sync failed',
    'Contact 1234 could not be reconciled.',
    ['contactId' => 1234]
);
```

## Troubleshooting

**`UnauthorizedSenderForChannelNotification` (HTTP 401) in the delivery log.**
The Power Automate flow ran, but Teams refused the post because the flow's
posting identity isn't a member of the target team or channel. Recreate the
workflow from inside the channel (channel → ⋯ → Workflows) while signed in as a
team member, or add the flow's connection account to the team. This is a Teams
permissions issue rather than a plugin error.

**Nothing arrives and nothing is logged.** Check the rule is enabled, its source
(and, for a Custom event, the Sender Class and Event Name) matches what happened,
and its conditions pass.

## Trademark notice

“Microsoft”, “Microsoft Teams”, and “Power Automate” are trademarks of the
Microsoft group of companies. This plugin is an independent product and is not
affiliated with, endorsed by, or sponsored by Microsoft.
