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
