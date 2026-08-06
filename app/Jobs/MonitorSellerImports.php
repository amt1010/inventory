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
