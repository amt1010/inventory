<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Exceptions\CategoryWouldFormCycle;
use App\Filament\Resources\CategoryResource;
use App\Filament\Support\CategoryTree;
use App\Models\Category;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $subcategories = $data['subcategories'] ?? [];
        $linkExisting = $data['link_existing'] ?? [];
        unset($data['subcategories'], $data['link_existing']);

        // Wrapped in an explicit transaction (this panel doesn't opt into
        // Filament's own) so a cycle caught partway through -- e.g. after the
        // record and its subcategories already exist -- rolls back cleanly
        // instead of leaving a half-created branch behind.
        try {
            return DB::transaction(function () use ($data, $subcategories, $linkExisting) {
                /** @var Category $record */
                $record = static::getModel()::create($data);

                // Subcategories added inline inherit the top-level category's
                // status so an admin can build and publish (or draft) a whole
                // branch at once.
                CategoryTree::persist($record, $subcategories, [
                    'status' => $record->status,
                ]);

                // Re-parent existing orphan (parentless) categories under this one.
                CategoryTree::linkExisting(
                    $record,
                    $linkExisting,
                    fn (Builder $query) => $query->whereNull('parent_id')
                );

                return $record;
            });
        } catch (CategoryWouldFormCycle $exception) {
            Notification::make()
                ->danger()
                ->title('Category not created')
                ->body($exception->getMessage())
                ->send();

            throw new Halt();
        }
    }
}
