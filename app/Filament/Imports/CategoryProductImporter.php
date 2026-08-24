<?php

namespace App\Filament\Imports;

use App\Models\AuditLog;
use App\Models\Product;
use App\Services\CategoryProductRowResolver;
use App\Support\MaterialType;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\ValidationException;

class CategoryProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    /**
     * Every column here is read from $this->data inside resolveRecord()/
     * beforeCreate() -- name/type/the six category-chain columns aren't real
     * Product attributes, so each needs a no-op fillRecordUsing() to stop
     * Filament's default fillRecord() step from trying (and failing) to
     * write them onto the model as if they were real columns.
     */
    public static function getColumns(): array
    {
        $noop = fn () => null;

        return [
            ImportColumn::make('product_name')->label('Product NAME')->fillRecordUsing($noop),
            ImportColumn::make('type')->label('TYPE')->fillRecordUsing($noop),
            ImportColumn::make('parent_name')->label('PARENT CATEGORY NAME')->requiredMapping()->fillRecordUsing($noop),
            ImportColumn::make('parent_description')->label('PARENT CATEGORY Description')->fillRecordUsing($noop),
            ImportColumn::make('sub1_name')->label('Sub-Category-1 Name')->fillRecordUsing($noop),
            ImportColumn::make('sub1_description')->label('Sub-Category-1 Description')->fillRecordUsing($noop),
            ImportColumn::make('sub2_name')->label('Sub-Category-2 Name')->fillRecordUsing($noop),
            ImportColumn::make('sub2_description')->label('Sub-Category-2 Description')->fillRecordUsing($noop),
            ImportColumn::make('sku')->label('SKU / Product Number'),
            ImportColumn::make('short_description')->label('Product Short Description'),
            ImportColumn::make('features')->label('Product Feature'),
            ImportColumn::make('applications')->label('Product Application'),
            ImportColumn::make('price_display')->label('Price Range (INR)'),
            ImportColumn::make('quantity')->label('Quantity'),
        ];
    }

    public function resolveRecord(): ?Product
    {
        return (new CategoryProductRowResolver())->resolveProduct($this->data);
    }

    protected function beforeCreate(): void
    {
        $materialType = MaterialType::normalize($this->data['type'] ?? null);

        if ($materialType === null) {
            throw ValidationException::withMessages([
                'type' => 'TYPE must be "Raw Material" or "Finished Good" (or "Finished Goods").',
            ]);
        }

        $this->record->material_type = $materialType;
        $this->record->status = 'pending_review';
        $this->record->seller_id = null;
        $this->record->created_by = 'admin_bulk_upload';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your category & product import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        $failedRowsCount = $import->getFailedRowsCount();

        if ($failedRowsCount > 0) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        AuditLog::recordCompletion($import, $body);

        return $body;
    }
}
