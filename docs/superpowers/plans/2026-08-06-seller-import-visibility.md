# Seller Import Visibility & Stuck Detection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Admin live visibility into an in-progress seller CSV import on `/admin/sellers`, and a best-effort proactive alert when an import appears to have stalled — closing the gap where a queue-worker outage (like the one that caused a 500-row import to silently do nothing on 2026-08-06) is otherwise invisible anywhere in the app.

**Architecture:** A small Filament header widget on the Sellers list page reads the `imports` table directly (no queue dependency) to show live progress or a "stuck" banner. Separately, a self-rescheduling queued job — started by a listener on Filament's `ImportStarted` event — periodically checks for stalled imports and sends one email per stalled import. Both the widget and the job share one "stuck" definition, driven by a single config value.

**Tech Stack:** Laravel 11, Filament v3 (`filament/actions` `Importer`/`ImportAction`, `filament/widgets`), MySQL (SQLite in tests), existing Postmark-backed mail.

## Global Constraints

- Test-first: every behavior below gets a failing test before its implementation (per `CLAUDE.md`).
- `QUEUE_CONNECTION=sync` in the test environment (`phpunit.xml`) — any test that calls the monitor job's `handle()` directly, where `handle()` may re-dispatch itself, MUST wrap that call in `Bus::fake()`. Without it, the self-redispatch executes immediately and recursively under `sync`, since there's no queue to defer it.
- The monitor job's stuck-detection email is sent **synchronously** (`Mail::send`, not `Mail::queue`/`ShouldQueue`) — it must not add a second queue dependency to the one alert path meant to survive a degraded queue.
- Accepted limitation (from the approved spec, do not "fix" this as part of this plan): the monitor job runs through the same queue-worker as the import itself, so a *fully offline* worker means no email fires. The widget is unaffected by this since it's a passive read.
- Follow this repo's existing config-file convention: reuse `config('rfq.notification_email')` as the default for the new `imports.notification_email`, matching how `config/rfq.php` already centralizes an ops-notification address (see `app/Http/Controllers/QuoteRequestController.php:27`).
- Document new env vars in `.env.example` next to the existing `RFQ_NOTIFICATION_EMAIL` line (`.env.example:66`), per this repo's convention of keeping `.env.example` authoritative for every setting.

---

### Task 1: Migration + config

**Files:**
- Create: `database/migrations/2026_08_06_090000_add_stuck_notified_at_to_imports_table.php`
- Create: `config/imports.php`
- Modify: `.env.example:66` (add two new lines after `RFQ_NOTIFICATION_EMAIL=sales@example.com`)
- Test: `tests/Feature/SellerImportMonitorTest.php` (new file, first test only in this task)

**Interfaces:**
- Produces: `imports.stuck_notified_at` column (nullable timestamp, no Eloquent cast needed — Laravel's query-binding layer auto-converts a `DateTimeInterface` value assigned to an uncasted attribute, so plain `$import->stuck_notified_at = now()` works).
- Produces: `config('imports.stuck_after_minutes')` (int, default `15`), `config('imports.notification_email')` (string, default falls back to `config('rfq.notification_email')`).
- Consumed by: Task 2 (mail), Task 3 (job), Task 5 (widget).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerImportMonitorTest extends TestCase
{
    public function test_imports_table_has_a_stuck_notified_at_column(): void
    {
        $this->assertTrue(Schema::hasColumn('imports', 'stuck_notified_at'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_imports_table_has_a_stuck_notified_at_column`
Expected: FAIL (column doesn't exist — the `imports` migration hasn't been extended yet)

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->timestamp('stuck_notified_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn('stuck_notified_at');
        });
    }
};
```

- [ ] **Step 4: Write the config file**

```php
<?php

return [
    // How long an incomplete import can go without progress before it's
    // considered stuck. Drives both the /admin/sellers UI banner and the
    // monitor job's email alert, and doubles as the monitor job's
    // re-check interval — one knob, not two, on purpose.
    'stuck_after_minutes' => env('IMPORT_STUCK_THRESHOLD_MINUTES', 15),

    'notification_email' => env('IMPORT_NOTIFICATION_EMAIL', env('RFQ_NOTIFICATION_EMAIL', 'sales@example.com')),
];
```

- [ ] **Step 5: Document the new env vars**

In `.env.example`, after line 66 (`RFQ_NOTIFICATION_EMAIL=sales@example.com`), add:

```
IMPORT_STUCK_THRESHOLD_MINUTES=15
IMPORT_NOTIFICATION_EMAIL=sales@example.com
```

- [ ] **Step 6: Apply the migration to the local dev database**

Run: `php artisan migrate`
Expected: `2026_08_06_090000_add_stuck_notified_at_to_imports_table ... DONE` — this is the safe, additive `migrate` command per `CLAUDE.md`, never `migrate:fresh`.

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=test_imports_table_has_a_stuck_notified_at_column`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_06_090000_add_stuck_notified_at_to_imports_table.php config/imports.php .env.example tests/Feature/SellerImportMonitorTest.php
git commit -m "feat: add stuck_notified_at column and imports config"
```

---

### Task 2: `SellerImportStuck` mailable

**Files:**
- Create: `app/Mail/SellerImportStuck.php`
- Create: `resources/views/emails/seller-import-stuck.blade.php`
- Test: `tests/Feature/SellerImportMonitorTest.php` (append)

**Interfaces:**
- Consumes: `Filament\Actions\Imports\Models\Import` (vendor model; relevant properties used: `file_name`, `total_rows`, `processed_rows`, `id`).
- Produces: `App\Mail\SellerImportStuck` — constructed as `new SellerImportStuck(Import $import)`. **Not** `ShouldQueue` — sent synchronously by Task 3's job so the alert path has no queue dependency of its own.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/SellerImportMonitorTest.php`:

```php
use App\Filament\Imports\SellerImporter;
use App\Mail\SellerImportStuck;
use Filament\Actions\Imports\Models\Import;
```

```php
    public function test_the_stuck_mail_names_the_file_and_shows_progress(): void
    {
        Import::polymorphicUserRelationship();

        $import = Import::create([
            'file_name' => 'sellers.csv',
            'file_path' => 'sellers.csv',
            'importer' => SellerImporter::class,
            'total_rows' => 500,
            'processed_rows' => 214,
        ]);

        $mail = new SellerImportStuck($import);

        $mail->assertHasSubject('Seller import appears stuck: sellers.csv');
        $mail->assertSeeInHtml('214');
        $mail->assertSeeInHtml('500');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_the_stuck_mail_names_the_file_and_shows_progress`
Expected: FAIL with "Class \"App\Mail\SellerImportStuck\" not found"

- [ ] **Step 3: Write the mailable**

```php
<?php

namespace App\Mail;

use Filament\Actions\Imports\Models\Import;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SellerImportStuck extends Mailable
{
    public function __construct(public Import $import)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Seller import appears stuck: '.$this->import->file_name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.seller-import-stuck', with: [
            'import' => $this->import,
        ]);
    }
}
```

- [ ] **Step 4: Write the email view**

```blade
<h1>A seller import appears stuck</h1>
<p>The import of <strong>{{ $import->file_name }}</strong> has processed
{{ $import->processed_rows }} of {{ $import->total_rows }} rows and hasn't
made progress recently.</p>
<p>This usually means the queue-worker service is offline or has stopped
consuming jobs. Check the queue-worker service in Railway, then check
<code>php artisan queue:failed</code> for anything related to this
import.</p>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=test_the_stuck_mail_names_the_file_and_shows_progress`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Mail/SellerImportStuck.php resources/views/emails/seller-import-stuck.blade.php tests/Feature/SellerImportMonitorTest.php
git commit -m "feat: add SellerImportStuck mailable"
```

---

### Task 3: `MonitorSellerImports` job

**Files:**
- Create: `app/Jobs/MonitorSellerImports.php`
- Test: `tests/Feature/SellerImportMonitorTest.php` (append)

**Interfaces:**
- Consumes: `App\Mail\SellerImportStuck` (Task 2), `config('imports.stuck_after_minutes')` / `config('imports.notification_email')` (Task 1), `App\Filament\Imports\SellerImporter::class` (existing), `Filament\Actions\Imports\Models\Import` (vendor).
- Produces: `App\Jobs\MonitorSellerImports` — a `ShouldQueue` job with a no-argument constructor, dispatched as `MonitorSellerImports::dispatch()->delay(...)`. Consumed by Task 4's listener.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/SellerImportMonitorTest.php`:

```php
use App\Jobs\MonitorSellerImports;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
```

```php
    private function makeIncompleteImport(int $minutesSinceUpdate, int $processed = 0, int $total = 500): Import
    {
        Import::polymorphicUserRelationship();

        $import = Import::create([
            'file_name' => 'sellers.csv',
            'file_path' => 'sellers.csv',
            'importer' => SellerImporter::class,
            'total_rows' => $total,
            'processed_rows' => $processed,
        ]);

        // updated_at is set by Eloquent on create; backdate it directly so
        // the "stuck" check (which compares against updated_at) sees an
        // old timestamp without needing to fake time globally.
        $import->timestamps = false;
        $import->update(['updated_at' => now()->subMinutes($minutesSinceUpdate)]);

        return $import;
    }

    public function test_it_emails_once_for_an_import_stuck_past_the_threshold(): void
    {
        config(['imports.stuck_after_minutes' => 15]);
        Mail::fake();
        Bus::fake(); // the import is still incomplete after processing, so handle()
                     // self-redispatches — fake it or this recurses under the sync
                     // queue connection used in tests

        $import = $this->makeIncompleteImport(minutesSinceUpdate: 20, processed: 214);

        (new MonitorSellerImports())->handle();

        Mail::assertSent(SellerImportStuck::class, fn ($mail) => $mail->import->is($import));
        $this->assertNotNull($import->fresh()->stuck_notified_at);
    }

    public function test_it_does_not_email_twice_for_the_same_stuck_import(): void
    {
        config(['imports.stuck_after_minutes' => 15]);
        Mail::fake();
        Bus::fake(); // avoid the self-redispatch actually running under the sync queue connection

        $import = $this->makeIncompleteImport(minutesSinceUpdate: 20);
        $import->update(['stuck_notified_at' => now()->subMinutes(5)]);

        (new MonitorSellerImports())->handle();

        Mail::assertNotSent(SellerImportStuck::class);
    }

    public function test_it_does_not_flag_an_import_that_is_within_the_threshold(): void
    {
        config(['imports.stuck_after_minutes' => 15]);
        Mail::fake();
        Bus::fake();

        $this->makeIncompleteImport(minutesSinceUpdate: 2);

        (new MonitorSellerImports())->handle();

        Mail::assertNotSent(SellerImportStuck::class);
    }

    public function test_it_reschedules_itself_while_incomplete_imports_remain(): void
    {
        config(['imports.stuck_after_minutes' => 15]);
        Mail::fake();
        Bus::fake();

        $this->makeIncompleteImport(minutesSinceUpdate: 2);

        (new MonitorSellerImports())->handle();

        Bus::assertDispatched(MonitorSellerImports::class);
    }

    public function test_it_stops_rescheduling_and_clears_the_cache_flag_once_nothing_is_incomplete(): void
    {
        Bus::fake();
        Cache::put('import-monitor:seller-active', true);

        // No imports at all, so nothing is incomplete.
        (new MonitorSellerImports())->handle();

        Bus::assertNotDispatched(MonitorSellerImports::class);
        $this->assertFalse(Cache::has('import-monitor:seller-active'));
    }

    public function test_a_mail_failure_still_marks_the_import_as_notified(): void
    {
        config(['imports.stuck_after_minutes' => 15]);
        Bus::fake();
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('Postmark unavailable'));

        $import = $this->makeIncompleteImport(minutesSinceUpdate: 20);

        (new MonitorSellerImports())->handle();

        $this->assertNotNull($import->fresh()->stuck_notified_at);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SellerImportMonitorTest`
Expected: FAIL with "Class \"App\Jobs\MonitorSellerImports\" not found" for all the new tests (Task 1/2 tests still pass)

- [ ] **Step 3: Write the job**

```php
<?php

namespace App\Jobs;

use App\Filament\Imports\SellerImporter;
use App\Mail\SellerImportStuck;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MonitorSellerImports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $thresholdMinutes = config('imports.stuck_after_minutes');
        $stuckBefore = now()->subMinutes($thresholdMinutes);

        Import::query()
            ->where('importer', SellerImporter::class)
            ->whereNull('completed_at')
            ->whereNull('stuck_notified_at')
            ->where('updated_at', '<', $stuckBefore)
            ->get()
            ->each(function (Import $import) {
                try {
                    Mail::to(config('imports.notification_email'))
                        ->send(new SellerImportStuck($import));
                } catch (Throwable $exception) {
                    Log::error('Failed to send stuck seller-import notification.', [
                        'import_id' => $import->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }

                $import->stuck_notified_at = now();
                $import->save();
            });

        $stillIncomplete = Import::query()
            ->where('importer', SellerImporter::class)
            ->whereNull('completed_at')
            ->exists();

        if ($stillIncomplete) {
            self::dispatch()->delay(now()->addMinutes($thresholdMinutes));

            return;
        }

        Cache::forget('import-monitor:seller-active');
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=SellerImportMonitorTest`
Expected: PASS (all tests in the file, including Task 1/2's)

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/MonitorSellerImports.php tests/Feature/SellerImportMonitorTest.php
git commit -m "feat: add MonitorSellerImports job for stuck-import alerting"
```

---

### Task 4: `StartSellerImportMonitor` listener

**Files:**
- Create: `app/Listeners/StartSellerImportMonitor.php`
- Test: `tests/Feature/SellerImportMonitorTest.php` (append)

**Interfaces:**
- Consumes: `Filament\Actions\Imports\Events\ImportStarted` (vendor event; `getImport(): Import`), `App\Jobs\MonitorSellerImports` (Task 3), `App\Filament\Imports\SellerImporter::class`.
- Produces: nothing consumed by later tasks — this is the wiring that starts the loop from Task 3. Laravel auto-discovers listeners under `app/Listeners` (confirmed by this app having no explicit `EventServiceProvider`/manual `Event::listen` calls, and `railway/init-app.sh` running `php artisan event:cache`), so no manual registration step is needed.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/SellerImportMonitorTest.php`:

```php
use App\Listeners\StartSellerImportMonitor;
use Filament\Actions\Imports\Events\ImportStarted;
```

```php
    public function test_it_dispatches_the_monitor_job_when_a_seller_import_starts(): void
    {
        Bus::fake();
        Cache::forget('import-monitor:seller-active');

        Import::polymorphicUserRelationship();
        $import = Import::create([
            'file_name' => 'sellers.csv',
            'file_path' => 'sellers.csv',
            'importer' => SellerImporter::class,
            'total_rows' => 500,
        ]);

        (new StartSellerImportMonitor())->handle(new ImportStarted($import, [], []));

        Bus::assertDispatched(MonitorSellerImports::class);
    }

    public function test_it_does_not_start_a_second_loop_while_one_is_already_active(): void
    {
        Bus::fake();
        Cache::forget('import-monitor:seller-active');

        Import::polymorphicUserRelationship();
        $import = Import::create([
            'file_name' => 'sellers.csv',
            'file_path' => 'sellers.csv',
            'importer' => SellerImporter::class,
            'total_rows' => 500,
        ]);

        $listener = new StartSellerImportMonitor();
        $listener->handle(new ImportStarted($import, [], []));
        $listener->handle(new ImportStarted($import, [], []));

        Bus::assertDispatchedTimes(MonitorSellerImports::class, 1);
    }

    public function test_it_ignores_imports_from_a_different_importer(): void
    {
        Bus::fake();
        Cache::forget('import-monitor:seller-active');

        Import::polymorphicUserRelationship();
        $import = Import::create([
            'file_name' => 'other.csv',
            'file_path' => 'other.csv',
            'importer' => 'App\\Filament\\Imports\\SomeOtherImporter',
            'total_rows' => 10,
        ]);

        (new StartSellerImportMonitor())->handle(new ImportStarted($import, [], []));

        Bus::assertNotDispatched(MonitorSellerImports::class);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SellerImportMonitorTest`
Expected: FAIL with "Class \"App\Listeners\StartSellerImportMonitor\" not found" for the three new tests

- [ ] **Step 3: Write the listener**

```php
<?php

namespace App\Listeners;

use App\Filament\Imports\SellerImporter;
use App\Jobs\MonitorSellerImports;
use Filament\Actions\Imports\Events\ImportStarted;
use Illuminate\Support\Facades\Cache;

class StartSellerImportMonitor
{
    public function handle(ImportStarted $event): void
    {
        $import = $event->getImport();

        if ($import->importer !== SellerImporter::class) {
            return;
        }

        // Cache::add is atomic — only the first caller to reach here (per
        // deploy, since two imports could start close together) actually
        // starts a loop. A generous TTL is a safety net in case a loop
        // ever dies without reaching MonitorSellerImports's own cleanup.
        if (! Cache::add('import-monitor:seller-active', true, now()->addDay())) {
            return;
        }

        MonitorSellerImports::dispatch()
            ->delay(now()->addMinutes(config('imports.stuck_after_minutes')));
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=SellerImportMonitorTest`
Expected: PASS (all tests in the file)

- [ ] **Step 5: Commit**

```bash
git add app/Listeners/StartSellerImportMonitor.php tests/Feature/SellerImportMonitorTest.php
git commit -m "feat: start the stuck-import monitor loop when a seller import begins"
```

---

### Task 5: `SellerImportStatusWidget`

**Files:**
- Create: `app/Filament/Resources/SellerResource/Widgets/SellerImportStatusWidget.php`
- Create: `resources/views/filament/resources/seller-resource/widgets/seller-import-status-widget.blade.php`
- Modify: `app/Filament/Resources/SellerResource/Pages/ListSellers.php`
- Test: `tests/Feature/SellerImportStatusWidgetTest.php` (new file)

**Interfaces:**
- Consumes: `Filament\Actions\Imports\Models\Import` (vendor), `App\Filament\Imports\SellerImporter::class` (existing), `config('imports.stuck_after_minutes')` (Task 1).
- Produces: `App\Filament\Resources\SellerResource\Widgets\SellerImportStatusWidget` — registered on `ListSellers::getHeaderWidgets()`, not globally discovered (lives outside `app/Filament/Widgets`, matching this project's existing pattern of resource-scoped, explicitly-registered pages/relation managers).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Filament\Imports\SellerImporter;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerImportStatusWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Staff
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        return $admin;
    }

    public function test_no_widget_content_when_there_is_no_incomplete_import(): void
    {
        $this->actingAsAdmin();

        $response = $this->get('/admin/sellers');

        $response->assertOk();
        $response->assertDontSee('Importing sellers:');
    }

    public function test_shows_live_progress_for_an_incomplete_import(): void
    {
        $this->actingAsAdmin();
        Import::polymorphicUserRelationship();

        Import::create([
            'file_name' => 'sellers.csv',
            'file_path' => 'sellers.csv',
            'importer' => SellerImporter::class,
            'total_rows' => 500,
            'processed_rows' => 214,
        ]);

        $response = $this->get('/admin/sellers');

        $response->assertOk();
        $response->assertSee('Importing sellers: 214 of 500 rows');
    }

    public function test_shows_a_stuck_banner_past_the_threshold(): void
    {
        config(['imports.stuck_after_minutes' => 15]);
        $this->actingAsAdmin();
        Import::polymorphicUserRelationship();

        $import = Import::create([
            'file_name' => 'sellers.csv',
            'file_path' => 'sellers.csv',
            'importer' => SellerImporter::class,
            'total_rows' => 500,
            'processed_rows' => 0,
        ]);
        $import->timestamps = false;
        $import->update(['updated_at' => now()->subMinutes(20)]);

        $response = $this->get('/admin/sellers');

        $response->assertOk();
        $response->assertSee('queue worker may be offline');
        $response->assertDontSee('Importing sellers:');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SellerImportStatusWidgetTest`
Expected: `test_no_widget_content_when_there_is_no_incomplete_import` passes trivially (nothing to see either way), the other two FAIL because the widget doesn't exist yet to render that text

- [ ] **Step 3: Write the widget class**

```php
<?php

namespace App\Filament\Resources\SellerResource\Widgets;

use App\Filament\Imports\SellerImporter;
use Filament\Actions\Imports\Models\Import;
use Filament\Widgets\Widget;

class SellerImportStatusWidget extends Widget
{
    protected static string $view = 'filament.resources.seller-resource.widgets.seller-import-status-widget';

    protected int | string | array $columnSpan = 'full';

    public function getImport(): ?Import
    {
        return Import::query()
            ->where('importer', SellerImporter::class)
            ->whereNull('completed_at')
            ->latest('id')
            ->first();
    }

    public function isStuck(Import $import): bool
    {
        return $import->updated_at->lt(now()->subMinutes(config('imports.stuck_after_minutes')));
    }
}
```

- [ ] **Step 4: Write the widget view**

```blade
<div wire:poll.5s>
    @php $import = $this->getImport(); @endphp

    @if ($import)
        <x-filament-widgets::widget>
            <x-filament::section>
                @if ($this->isStuck($import))
                    <div class="flex items-center gap-x-3">
                        <x-filament::badge color="danger">Stuck</x-filament::badge>
                        <p class="text-sm text-gray-950 dark:text-white">
                            This import hasn't made progress in over
                            {{ config('imports.stuck_after_minutes') }} minutes.
                            The queue worker may be offline.
                        </p>
                    </div>
                @else
                    <p class="mb-2 text-sm text-gray-950 dark:text-white">
                        Importing sellers: {{ $import->processed_rows }} of {{ $import->total_rows }} rows
                    </p>
                    <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                        <div
                            class="h-2 rounded-full bg-primary-600"
                            style="width: {{ $import->total_rows > 0 ? min(100, intdiv($import->processed_rows * 100, $import->total_rows)) : 0 }}%"
                        ></div>
                    </div>
                @endif
            </x-filament::section>
        </x-filament-widgets::widget>
    @endif
</div>
```

- [ ] **Step 5: Register the widget on the Sellers list page**

In `app/Filament/Resources/SellerResource/Pages/ListSellers.php`, add the import and the `getHeaderWidgets()` override:

```php
<?php

namespace App\Filament\Resources\SellerResource\Pages;

use App\Filament\Imports\SellerImporter;
use App\Filament\Resources\SellerResource;
use App\Filament\Resources\SellerResource\Widgets\SellerImportStatusWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSellers extends ListRecords
{
    protected static string $resource = SellerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ImportAction::make()
                ->importer(SellerImporter::class)
                ->label('Import Sellers'),
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SellerImportStatusWidget::class,
        ];
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=SellerImportStatusWidgetTest`
Expected: PASS (all three tests)

- [ ] **Step 7: Run the full test suite**

Run: `php artisan test`
Expected: PASS — confirms nothing in this feature broke the existing `SellerImporterTest`, `ApproveSellerTest`, or anything else

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/SellerResource/Widgets/SellerImportStatusWidget.php resources/views/filament/resources/seller-resource/widgets/seller-import-status-widget.blade.php app/Filament/Resources/SellerResource/Pages/ListSellers.php tests/Feature/SellerImportStatusWidgetTest.php
git commit -m "feat: show live import progress and stuck-import banner on /admin/sellers"
```

---

## Manual Verification (after all tasks)

This can't be exercised by the automated suite (it depends on real wall-clock delay and the actual queue-worker), so verify by hand against local dev once implementation is complete:

1. `php artisan migrate` on the dev database (already done in Task 1, confirm no pending migrations remain: `php artisan migrate:status`).
2. Trigger a seller CSV import from `/admin/sellers` locally (`QUEUE_CONNECTION=sync` locally per `CLAUDE.md`, so it'll actually complete immediately — the widget should flash briefly then disappear once `completed_at` is set).
3. To see the "stuck" UI state without waiting on a real outage: in `php artisan tinker`, create an `Import` row for `SellerImporter` with `completed_at` null and `updated_at` manually backdated past `config('imports.stuck_after_minutes')`, then load `/admin/sellers` and confirm the red banner appears.
