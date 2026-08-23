<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Imports\CategoryProductImporter;
use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ImportAction::make()
                ->importer(CategoryProductImporter::class)
                ->label('Import Categories & Products'),
            Actions\CreateAction::make(),
        ];
    }
}
