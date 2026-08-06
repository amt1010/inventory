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
