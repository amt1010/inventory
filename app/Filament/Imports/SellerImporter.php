<?php

namespace App\Filament\Imports;

use App\Models\Seller;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SellerImporter extends Importer
{
    protected static ?string $model = Seller::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('company_name')
                ->label('Company Name')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->company_name = filled($state) ? $state : Seller::PLACEHOLDER),
            ImportColumn::make('manufacturing_activity')
                ->label('Manufacturing Activity')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->manufacturing_activity = filled($state) ? $state : Seller::PLACEHOLDER),
            ImportColumn::make('business_address')
                ->label('Address')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->business_address = filled($state) ? $state : Seller::PLACEHOLDER),
            ImportColumn::make('phone')
                ->label('Phone')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->phone = filled($state) ? $state : Seller::PLACEHOLDER),
            ImportColumn::make('email')
                ->label('Email')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->email = filled($state) ? $state : Seller::PLACEHOLDER),
            ImportColumn::make('availability_hours')
                ->label('Availability Hours')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->availability_hours = filled($state) ? $state : Seller::PLACEHOLDER),
            ImportColumn::make('contact_person')
                ->label('Contact Person')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->contact_person = filled($state) ? $state : Seller::PLACEHOLDER),
            ImportColumn::make('gst_number')
                ->label('GST Number')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->gst_number = filled($state) ? $state : Seller::PLACEHOLDER),
        ];
    }

    public function resolveRecord(): ?Seller
    {
        return new Seller();
    }

    protected function beforeCreate(): void
    {
        $this->record->status = 'pending_admin_approval';
        $this->record->created_by = 'admin_bulk_upload';
        $this->record->password = Hash::make(Str::random(40));
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your seller import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        $failedRowsCount = $import->getFailedRowsCount();

        if ($failedRowsCount > 0) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
