# Webhook Notifier for Craft CMS

Hey 👋 — this plugin pings a webhook (right now, a **Microsoft Teams** channel)
whenever something happens on your Craft site, with rules you set up in the
control panel. No code, no cron-wrangling, no “where do I even put this webhook
URL” faff.

I built it after Microsoft pulled the rug on the old “Incoming Webhook”
connectors in May 2026. Those used to be lovely and simple — paste a URL, POST
some JSON, done. The supported way into Teams now is **Power Automate
Workflows**, which wants its Adaptive Cards wrapped up just so. This plugin does
that wrapping for you and puts a proper rules engine on top.

It’s called *Webhook Notifier* rather than *Teams Notifier* on purpose — Teams is
the first thing it speaks, but the guts (sources → rules → cards → delivery) are
built so other webhook targets can slot in later.

## What you get

- **A no-code rules engine.** Pick an event, filter it with conditions, design a
  card, choose where it goes. All in the CP.
- **Connections.** Named destinations holding a Teams Workflows webhook URL.
  Stored encrypted, or as a reference to an env var (`$TEAMS_OPS`) so your secret
  never touches the database or git.
- **Cards that don’t look like a robot wrote them.** A friendly builder (title,
  body, facts, a button) plus a raw-JSON “advanced” mode with ready-made
  examples to crib from.
- **Sources out of the box:** entries saved, user events, integration/queue
  failures, queue size, and Freeform submissions (incl. the actual field values).
- **Reliable delivery.** Everything’s queued and retried, with a full delivery
  log so you can see exactly what went out and what came back.
- **Extensible.** Bolt on your own sources from another plugin/module with a
  single event.

## Requirements

- Craft CMS 5.0+
- PHP 8.2+
- A Teams channel with a Power Automate **Workflows** webhook (2-minute setup, below)

## Install

From the Plugin Store, or with Composer:

```bash
composer require coysh-digital/craft-webhook-notifier
php craft plugin/install webhook-notifier
```

## Getting a Teams webhook (the bit everyone gets stuck on)

1. In Teams, open the channel → **⋯ → Workflows**.
2. Pick the **“Post to a channel when a webhook request is received”** template.
3. Run through the wizard **signed in as someone who’s actually a member of that
   team** (this matters — see Troubleshooting), and copy the **HTTP POST URL**.
4. In Craft: **Webhook Notifier → Connections → New**, paste the URL (or an
   `$ENV_VAR` reference), hit **Send test card**, and watch it land in Teams. 🎉

## The sources

| Source | Fires when… |
| --- | --- |
| **Entry saved** | An entry is created or updated (drafts/revisions skipped). |
| **User event** | Someone registers, gets activated, or changes group. |
| **Integration failure** | A queued job dies after Craft’s retries, or your own code calls `reportFailure()`. (The plugin’s own send jobs are ignored, so a failed notification can’t spiral into more notifications.) |
| **Queue size** | A scheduled check finds the queue backing up (pair with a numeric condition). |
| **Freeform submission** | A Freeform form is submitted — with the field values available in the card. |

Each source tells you, right there in the rule editor, which variables it exposes
— so you’re never guessing what `{thing}` you can drop into a card.

## Cards: showing real values

In the structured builder or advanced JSON, anything in `{curly braces}` is a
Twig variable from the event. A few favourites:

- Entry: `{title}`, `{section}`, `{url}`, `{authorName}`
- Freeform: `{fields.email}` (any field handle), or `{allFields}` to dump the lot,
  plus `{formName}` / `{formId}`
- Failures: `{jobDescription}`, `{error}`

Switch the card to **Advanced** mode and there’s a “Start from an example”
picker — including a *Freeform: all submitted fields* template that lists every
field automatically. Steal it, tweak it, ship it.

## Watching the queue (scheduled monitor)

The Queue size source is polled, so give it a cron:

```bash
php craft webhook-notifier/monitor/queue               # e.g. every 15 minutes
php craft webhook-notifier/monitor/queue --cooldown=30 # don't alert more than every 30 min
```

Then make a rule: source **Queue size**, condition `total is greater than 50`,
and a card that shows `{total}`. Your cron interval (and `--cooldown`) decide how
often you’re nudged while it stays over the line.

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
Classic first-run snag. The Workflow ran fine, but Teams refused the post because
the flow’s posting identity isn’t a member of the target team/channel. Fix:
recreate the workflow *from inside the channel* (channel → ⋯ → Workflows) while
signed in as a team member, or add the flow’s connection account to the team.
It’s a Teams permissions thing, not a plugin bug.

**Nothing in Teams, nothing in the log.** Check the rule is enabled, its source
matches the thing you actually did, and its conditions genuinely pass.

## Trademark notice

“Microsoft”, “Microsoft Teams”, and “Power Automate” are trademarks of the
Microsoft group of companies. This plugin is an independent product and isn’t
affiliated with, endorsed by, or sponsored by Microsoft.
