<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SellerResource\Pages;
use App\Filament\Resources\SellerResource\RelationManagers\DocumentsRelationManager;
use App\Mail\SellerApproved;
use App\Mail\SellerRejected;
use App\Models\Seller;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SellerResource extends Resource
{
    protected static ?string $model = Seller::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static function statusOptions(): array
    {
        return [
            'pending_email_verification' => 'Pending Email Verification',
            'pending_admin_approval' => 'Pending Admin Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'suspended' => 'Suspended',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('company_name')->required(),
            TextInput::make('contact_person')->required(),
            TextInput::make('phone')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('business_address'),
            TextInput::make('gst_number')->label('GST Number'),
            Select::make('status')
                ->options(function (?Seller $record): array {
                    $options = static::statusOptions();

                    if ($record && $record->status === 'pending_email_verification') {
                        unset($options['approved']);
                    }

                    return $options;
                })
                ->in(function (?Seller $record): array {
                    $values = array_keys(static::statusOptions());

                    if ($record && $record->status === 'pending_email_verification') {
                        $values = array_values(array_diff($values, ['approved']));
                    }

                    return $values;
                })
                ->required()
                ->visible(fn (string $operation): bool => $operation !== 'create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')->searchable(),
                TextColumn::make('contact_person'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('gst_number')->label('GST Number'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_by')->label('Created By'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(static::statusOptions()),
                SelectFilter::make('created_by')->options([
                    'self' => 'Self-registered',
                    'admin' => 'Admin-created',
                ]),
            ])
            ->actions([
                Action::make('approve')
                    ->visible(fn (Seller $record) => $record->status === 'pending_admin_approval')
                    ->requiresConfirmation()
                    ->action(function (Seller $record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_at' => now(),
                            'approved_by' => auth('staff')->id(),
                        ]);

                        try {
                            Mail::to($record->email)->send(new SellerApproved($record));
                        } catch (\Throwable $exception) {
                            Log::error('Failed to send seller approval email.', [
                                'seller_id' => $record->id,
                                'exception' => $exception->getMessage(),
                            ]);
                        }
                    }),
                Action::make('reject')
                    ->visible(fn (Seller $record) => $record->status === 'pending_admin_approval')
                    ->form([
                        Textarea::make('rejection_reason')->label('Reason')->required(),
                    ])
                    ->action(function (Seller $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        try {
                            Mail::to($record->email)->send(new SellerRejected($record));
                        } catch (\Throwable $exception) {
                            Log::error('Failed to send seller rejection email.', [
                                'seller_id' => $record->id,
                                'exception' => $exception->getMessage(),
                            ]);
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellers::route('/'),
            'create' => Pages\CreateSeller::route('/create'),
            'edit' => Pages\EditSeller::route('/{record}/edit'),
        ];
    }
}
