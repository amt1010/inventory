<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Mail\ProductEditReadyForAcceptance;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    private const TRACKED_FIELDS = [
        'name', 'slug', 'sku', 'short_description', 'description',
        'features', 'applications', 'spec_sheet_path', 'category_id', 'quantity',
    ];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->status !== 'pending_review') {
            return $data;
        }

        $changes = [];

        foreach (self::TRACKED_FIELDS as $field) {
            $old = $this->record->getAttribute($field);
            $new = $data[$field] ?? null;

            if ($this->valuesDiffer($old, $new)) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        if ($changes === []) {
            return $data;
        }

        $trail = $this->record->editTrails()->create([
            'staff_id' => auth('staff')->id(),
            'changes' => $changes,
        ]);

        $data['status'] = 'pending_seller_acceptance';

        try {
            Mail::to($this->record->seller->email)->send(new ProductEditReadyForAcceptance($this->record, $trail));
        } catch (\Throwable $exception) {
            Log::error('Failed to send product edit acceptance email.', [
                'product_id' => $this->record->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $data;
    }

    private function valuesDiffer(mixed $old, mixed $new): bool
    {
        if (is_array($old) || is_array($new)) {
            return json_encode(array_values((array) $old)) !== json_encode(array_values((array) $new));
        }

        return (string) $old !== (string) $new;
    }
}
