<?php

namespace App\Filament\Seller\Resources\CategoryResource\Pages;

use App\Filament\Seller\Resources\CategoryResource;
use App\Filament\Support\CategoryTree;
use App\Models\Category;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $subcategories = $data['subcategories'] ?? [];
        unset($data['subcategories']);

        // A seller's category is always a draft proposal owned by them; the
        // approval journey (admin review, optional override, publish) is
        // unchanged. Every subcategory added inline inherits the same
        // ownership and draft status.
        $ownership = [
            'status' => 'draft',
            'proposed_by_seller_id' => auth('seller')->id(),
        ];

        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);

        /** @var Category $record */
        $record = static::getModel()::create(array_merge($data, $ownership));

        CategoryTree::persist($record, $subcategories, $ownership);

        return $record;
    }
}
