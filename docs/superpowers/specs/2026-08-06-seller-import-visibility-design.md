# Seller Import Visibility & Stuck Detection — Design Spec

Date: 2026-08-06
Status: Approved

## Purpose

On 2026-08-06, an Admin uploaded a 500-row seller CSV via `/admin/sellers`'
Import Sellers action. The upload succeeded and the `imports` row was
created correctly, but nothing further happened: the Railway queue-worker
service (which runs `php artisan queue:work` and processes Filament's
queued `ImportCsv` jobs) was offline, so all 500 row-jobs sat in Redis
untouched. Nothing in the admin UI indicated a problem — the import simply
looked like it had never been triggered.

Root cause investigation (see conversation history) found that Filament's
own completion notification and failed-rows-CSV-download link are both
wired to a `Bus::batch(...)->finally()` callback, which itself only runs
once every job in the batch has been processed by a worker. If the worker
never runs the batch, that callback never fires either — so there is no
existing failure signal anywhere, built-in or custom. This spec adds the
two things Filament has no concept of:

1. A live progress indicator on `/admin/sellers` while an import is in
   flight.
2. Detection of an import that has stopped making progress ("stuck"),
   surfaced both as a UI banner and a best-effort proactive email.

## Background: what Filament already provides (not being rebuilt)

- `ImportAction` dispatches a `Bus::batch()` of `ImportCsv` jobs.
  `->finally()` sets `completed_at`, fires `ImportCompleted`, and sends
  Filament's own database notification — including a "download failed
  rows CSV" action when `failed_import_rows` exist for that import.
- This is sufficient once the queue actually runs. This spec does not
  duplicate per-row failure reporting; it only covers the gap where the
  batch never runs at all.

## Data Model & Config

One additive migration on `imports`:

- **`stuck_notified_at`** — nullable timestamp. Set the first time a
  stuck-import email is sent for that row, so the same import doesn't
  trigger a repeat email on every subsequent check.

New config file `config/imports.php`:

- `stuck_after_minutes` — `env('IMPORT_STUCK_THRESHOLD_MINUTES', 15)`.
  Shared by both the widget banner and the monitor job so "stuck" means
  the same thing everywhere.
- `notification_email` — `env('IMPORT_NOTIFICATION_EMAIL',
  env('RFQ_NOTIFICATION_EMAIL', 'sales@example.com'))`. Reuses the
  existing ops-notification-address convention already used for RFQs
  (`config('rfq.notification_email')`), rather than introducing a new
  admin-recipient concept (e.g. querying all `admin`-role Staff).

## Live Progress Widget

`App\Filament\Resources\SellerResource\Widgets\SellerImportStatusWidget`,
registered via `ListSellers::getHeaderWidgets()`.

- Renders nothing when no `Import` row exists with `importer =
  SellerImporter::class` and `completed_at IS NULL`.
- When one exists, polls every 5 seconds (`wire:poll.5s`) and shows a
  progress bar: "Importing sellers: {processed_rows} of {total_rows}
  rows".
- If `updated_at` is older than `stuck_after_minutes`, the widget instead
  shows a danger-colored banner: "This import hasn't made progress in
  over {n} minutes. The queue worker may be offline." (same condition and
  copy the monitor job's email uses, so the two stay consistent).
- The widget reads the `imports` table directly on each poll — it does
  not depend on the queue in any way, so it works correctly even when the
  queue-worker is completely down (unlike the email alert below).
- Once `completed_at` is set, the widget stops rendering and Filament's
  own existing completion notification takes over, unchanged.

## Monitor Job (piggybacked on the queue-worker — accepted limitation)

- `App\Listeners\StartSellerImportMonitor`, listening on Filament's
  `Filament\Actions\Imports\Events\ImportStarted` (filtered to
  `SellerImporter`). Dispatches `App\Jobs\MonitorSellerImports` with a
  short delay, guarded by a cache lock (`import-monitor:seller-active`,
  e.g. via `Cache::add()`) so two imports starting close together don't
  spawn two parallel monitor loops.
- `MonitorSellerImports::handle()`:
  1. Query `Import` rows for `SellerImporter` where `completed_at IS
     NULL`.
  2. For each where `updated_at` is older than `stuck_after_minutes` and
     `stuck_notified_at IS NULL`: send `App\Mail\SellerImportStuck`
     **synchronously** (not queued — see Error Handling) to
     `config('imports.notification_email')`, then set
     `stuck_notified_at = now()`.
  3. If any queried rows are still incomplete, re-dispatch itself with a
     delay (e.g. 5 minutes) on the same queue connection.
  4. Otherwise, clear the cache lock and stop rescheduling — the loop
     ends naturally until the next import starts a fresh one.

**Explicitly accepted limitation:** this job runs through the same queue
connection/worker as the import itself. If the worker is completely
offline — today's actual failure mode — this monitor job cannot run
either, so no email will fire for that specific case. This was discussed
and deliberately accepted in favor of not standing up a new Railway Cron
Job service. The UI banner above is unaffected by this gap (it's a
passive read, independent of the queue), so an admin who opens
`/admin/sellers` will still see the problem even when no email arrives.

## Error Handling

- If sending `SellerImportStuck` throws (e.g. a mail-provider outage),
  catch it, log via `Log::error(...)` (matching the existing pattern in
  `SellerResource.php`'s seller-rejection email path), and still set
  `stuck_notified_at`. Rationale: a permanently broken mail config must
  not turn into an infinite per-cycle exception loop that's
  indistinguishable from "the job never ran" — the UI banner remains the
  reliable fallback regardless.

## Out of Scope (YAGNI)

- A full import-history page/table — Filament's own per-import
  notification already covers "what happened to my last import" once it
  completes.
- Any alerting path independent of this project's existing queue (e.g. a
  new Slack/webhook integration, a new Railway Cron Job service) — ruled
  out earlier in favor of piggybacking on the existing queue-worker, with
  the limitation above accepted.
- Automatically retrying a stuck import — this feature only detects and
  reports; an admin resolves it manually (e.g. restarting the
  queue-worker service).

## Testing

- Migration: `stuck_notified_at` column exists, nullable.
- Widget: renders nothing with no incomplete `SellerImporter` import;
  renders correct processed/total counts when one exists; shows the
  stuck banner once `updated_at` exceeds the threshold, the ordinary
  progress bar otherwise.
- `MonitorSellerImports`: sends exactly one email for a stuck import and
  sets `stuck_notified_at`; does not send a second email on a subsequent
  run of the same import; reschedules itself while incomplete imports
  remain; does not reschedule once none remain; a mail-send exception
  still results in `stuck_notified_at` being set rather than looping.
- `StartSellerImportMonitor`: dispatches the monitor job on
  `ImportStarted`; does not dispatch a second concurrent loop when
  another is already active (cache lock respected).
