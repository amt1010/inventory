<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Filament\Support\CategoryTree;
use App\Models\Category;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $subcategories = $data['subcategories'] ?? [];
        unset($data['subcategories']);

        /** @var Category $record */
        $record = static::getModel()::create($data);

        // Subcategories added inline inherit the top-level category's status so
        // an admin can build and publish (or draft) a whole branch at once.
        CategoryTree::persist($record, $subcategories, [
            'status' => $record->status,
        ]);

        return $record;
    }
}
