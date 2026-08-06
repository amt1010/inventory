<?php

namespace App\Filament\Resources\SellerResource\Widgets;

use App\Filament\Imports\SellerImporter;
use Filament\Actions\Imports\Models\Import;
use Filament\Widgets\Widget;

class SellerImportStatusWidget extends Widget
{
    protected static string $view = 'filament.resources.seller-resource.widgets.seller-import-status-widget';

    protected int | string | array $columnSpan = 'full';

    // Filament widgets lazy-load by default (an extra round trip after
    // first paint). This widget exists specifically to surface a stuck
    // import fast, so it must render its real content immediately.
    protected static bool $isLazy = false;

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
