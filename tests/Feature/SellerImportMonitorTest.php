<?php

namespace Tests\Feature;

use App\Filament\Imports\SellerImporter;
use App\Jobs\MonitorSellerImports;
use App\Listeners\StartSellerImportMonitor;
use App\Mail\SellerImportStuck;
use Filament\Actions\Imports\Events\ImportStarted;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerImportMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_table_has_a_stuck_notified_at_column(): void
    {
        $this->assertTrue(Schema::hasColumn('imports', 'stuck_notified_at'));
    }

    public function test_stuck_after_minutes_config_is_always_an_int(): void
    {
        // env() returns a string when IMPORT_STUCK_THRESHOLD_MINUTES is
        // actually set in .env, but the literal int fallback (15) when it's
        // absent — that inconsistency previously reached Carbon's
        // addMinutes(), which requires int|float and throws on a numeric
        // string. Assert the config always casts, regardless of which path
        // produced the value.
        $this->assertIsInt(config('imports.stuck_after_minutes'));
    }

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
}
