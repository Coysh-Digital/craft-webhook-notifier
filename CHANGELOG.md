# Release Notes for Webhook Notifier

## 1.2.0 - 2026-06-25

> Renamed from “MS Teams Notifications” to **Webhook Notifier** — Teams is still
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
