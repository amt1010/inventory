<?php

namespace App\Filament\Seller\Resources\CategoryResource\Pages;

use App\Exceptions\CategoryWouldFormCycle;
use App\Filament\Seller\Resources\CategoryResource;
use App\Filament\Support\CategoryTree;
use App\Models\Category;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $subcategories = $data['subcategories'] ?? [];
        $linkExisting = $data['link_existing'] ?? [];
        unset($data['subcategories'], $data['link_existing']);

        // A seller's category is always a draft proposal owned by them; the
        // approval journey (admin review, optional override, publish) is
        // unchanged. Every subcategory added inline inherits the same
        // ownership and draft status.
        $ownership = [
            'status' => 'draft',
            'proposed_by_seller_id' => auth('seller')->id(),
        ];

        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);

        // Wrapped in an explicit transaction (this panel doesn't opt into
        // Filament's own) so a cycle caught partway through -- e.g. after the
        // record and its subcategories already exist -- rolls back cleanly
        // instead of leaving a half-created branch behind.
        try {
            return DB::transaction(function () use ($data, $ownership, $subcategories, $linkExisting) {
                /** @var Category $record */
                $record = static::getModel()::create(array_merge($data, $ownership));

                CategoryTree::persist($record, $subcategories, $ownership);

                // Re-parent the seller's own orphan (parentless) draft categories
                // under this one -- scoped to their own drafts so they can't touch
                // another seller's proposals or the published taxonomy.
                CategoryTree::linkExisting(
                    $record,
                    $linkExisting,
                    fn (Builder $query) => $query
                        ->whereNull('parent_id')
                        ->where('status', 'draft')
                        ->where('proposed_by_seller_id', $record->proposed_by_seller_id)
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
