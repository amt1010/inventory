<?php

namespace App\Filament\Seller\Resources\CategoryResource\Pages;

use App\Filament\Seller\Resources\CategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // A seller's category is always a draft proposal owned by them; the
        // approval journey (admin review, optional override, publish) is
        // unchanged from the inline product-form proposal path.
        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);
        $data['status'] = 'draft';
        $data['proposed_by_seller_id'] = auth('seller')->id();

        return $data;
    }
}
