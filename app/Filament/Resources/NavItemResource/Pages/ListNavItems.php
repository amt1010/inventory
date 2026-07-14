<?php

namespace App\Filament\Resources\NavItemResource\Pages;

use App\Filament\Resources\NavItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNavItems extends ListRecords
{
    protected static string $resource = NavItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
