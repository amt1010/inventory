<?php

namespace App\Filament\Resources\SellerResource\Pages;

use App\Filament\Resources\SellerResource;
use App\Mail\SellerActivationMail;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateSeller extends CreateRecord
{
    protected static string $resource = SellerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = 'admin';
        $data['status'] = 'pending_email_verification';
        $data['password'] = Hash::make(Str::random(40));

        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            Mail::to($this->record->email)->send(new SellerActivationMail($this->record));
        } catch (\Throwable $exception) {
            Log::error('Failed to send seller activation email.', [
                'seller_id' => $this->record->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
