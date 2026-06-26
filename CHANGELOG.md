# Release Notes for Webhook Notifier

## 1.5.0 - 2026-06-26

### Added
- **Resend** a delivery from the log - it re-renders the rule's *current* card /
  payload against the delivery's saved context, so edits you've made since are
  picked up. (Deliveries now store a serializable snapshot of their context.)
- **"Freeform: basic fields"** card example - a fixed set of common contact
  fields (name / email / phone / message), no loop.

### Fixed
- Freeform card variables (`{fields...}`, `{allFields}`, the "all submitted
  fields" example) now read **every field across all pages** and skip HTML
  blocks / submit buttons. Previously only the last page's fields came through.
- The "Enabled" column on the Rules and Connections lists rendered the status
  dot's `<span>` as literal text instead of showing the dot.

## 1.4.1 - 2026-06-26

### Fixed
- Installing the plugin failed on a fresh install (e.g. via project-config apply
  on deploy) with "Table '...webhooknotifier_rules' doesn't exist". The Custom
  event source read its rules during `attachListeners()` before the install
  migration had created the table; it now skips when the table isn't there yet.

## 1.4.0 - 2026-06-26

### Added
- **Activity sparklines** on the rules list - a 14-day mini bar chart per rule
  (sent in green, failed in red) plus a sent/failed count, so you can see each
  rule's recent history at a glance.

### Changed
- Settings are now read-only wherever admin changes are disabled (they live in
  project config and deploy from there); editable where admin changes are
  allowed.
- Replaced em/en dashes with hyphens throughout.

### Fixed
- The control-panel sidebar icon now shows - added the `icon-mask.svg` Craft uses
  for the nav, and switched both icons to a filled silhouette.

## 1.3.1 - 2026-06-26

### Changed
- New plugin icon (a “fan-out” webhook mark).
- Control-panel wording is now webhook-agnostic - Microsoft Teams is still the
  lead example, but the Connections and Rules screens make clear any webhook
  (Zapier, Slack, custom endpoints) works.

## 1.3.0 - 2026-06-26

### Added
- **Custom event source.** Trigger a rule on any Yii/Craft event by naming a
  Sender Class (e.g. `craft\elements\Entry`) and an Event Name (e.g. `afterSave`),
  like Craft's built-in Webhook plugin. The event object is available in the
  payload as `{{ event }}`.
- **Raw payload mode.** A new card mode that POSTs a Twig-rendered body to the
  webhook as-is (Content-Type `application/json`) with no Teams wrapping - so the
  plugin now works with any webhook (Zapier, Make, Slack, custom endpoints), not
  just Microsoft Teams.

### Changed
- Card templates now expose context keys as top-level Twig variables, so full
  Twig such as `{{ event.sender.title }}` works alongside the `{shorthand}` form.
- Connection/“Card” wording generalised to reflect any-webhook use.

## 1.2.0 - 2026-06-25

> Renamed from “MS Teams Notifications” to **Webhook Notifier** - Teams is still
> the delivery target, but the name leaves room for more webhook destinations.

### Added
- **Freeform field values in cards.** Submissions now expose each field via
  `{fields.yourFieldHandle}` (e.g. `{fields.email}`), plus `{allFields}` for a
  ready-formatted list of everything submitted, and `{formId}`.
- **Advanced-mode example templates.** A “Start from an example” picker in the
  raw-JSON card mode inserts ready-made Adaptive Cards, including a “Freeform: all
  submitted fields” example that lists every field automatically.
- Sources can now declare card-only template variables (`cardVariables()`)
  separately from their condition fields.

### Improved
- The card “Available variables” hint now reflects each source’s full set of
  card variables (e.g. Freeform’s `{fields.<handle>}` and `{allFields}`), while
  the condition Field picker still lists only testable fields.

## 1.1.0 - 2026-06-25

### Added
- **Queue size** notification source: run `php craft webhook-notifier/monitor/queue`
  from cron and pair it with a numeric condition (e.g. “total is greater than 50”)
  to be alerted when the queue backs up. Supports an optional `--cooldown`
  (minutes) to throttle repeat alerts.
- Numeric condition operators: *is greater than / greater than or equal to / less
  than / less than or equal to*.
- Each source now has a description shown in the rule editor (with a detailed one
  for **Integration failure**).

### Improved
- The rule editor’s condition **Field** and **Operator** inputs are now dropdowns,
  populated from the selected source’s available fields and updated live when you
  change the source.

### Fixed
- Encrypted connection URLs are base64-encoded before storage, fixing an
  “Incorrect string value” error when saving a direct (non-env) webhook URL.

## 1.0.0 - 2026-06-25

### Added
- Initial release.
- No-code notification **Rules** engine, configured in the control panel.
- **Connections** for Power Automate Workflows channel webhook URLs, stored as
  encrypted values or environment-variable references.
- Native **Adaptive Card** builder targeting the Power Automate Workflows
  envelope (the supported path after the May 2026 Office 365 connector
  retirement).
- Built-in notification sources: Entry lifecycle, User events, Integration
  failures (queue job errors + a programmatic reporting API), and Freeform
  submissions (when Freeform is installed).
- Queued, retrying delivery with a control-panel delivery **Log**.
