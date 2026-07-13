<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuoteRequestResource\Pages;
use App\Filament\Resources\QuoteRequestResource\RelationManagers\NotesRelationManager;
use App\Models\QuoteRequest;
use App\Models\Staff;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuoteRequestResource extends Resource
{
    protected static ?string $model = QuoteRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Placeholder::make('contact')
                ->label('Contact')
                ->content(fn (QuoteRequest $record) => "{$record->first_name} {$record->last_name} — {$record->email} — {$record->phone}"),
            Placeholder::make('product')
                ->label('Product')
                ->content(fn (QuoteRequest $record) => $record->product?->name ?? 'General inquiry'),
            Placeholder::make('message')
                ->label('Message')
                ->content(fn (QuoteRequest $record) => $record->message ?? '—'),
            Select::make('status')
                ->options([
                    'new' => 'New',
                    'in_progress' => 'In Progress',
                    'closed' => 'Closed',
                ])
                ->required(),
            Select::make('assigned_to')
                ->label('Assigned To')
                ->options(fn () => Staff::query()->pluck('name', 'id'))
                ->searchable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Received')->dateTime()->sortable(),
                TextColumn::make('first_name')
                    ->label('Name')
                    ->formatStateUsing(fn (QuoteRequest $record) => "{$record->first_name} {$record->last_name}")
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('email')->searchable(),
                TextColumn::make('product.name')->label('Product')->placeholder('General inquiry'),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('assignee.name')->label('Assigned To')->placeholder('Unassigned'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'new' => 'New',
                    'in_progress' => 'In Progress',
                    'closed' => 'Closed',
                ]),
                SelectFilter::make('assigned_to')->label('Assigned To')->options(fn () => Staff::query()->pluck('name', 'id')),
                SelectFilter::make('product_id')->label('Product')->relationship('product', 'name'),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export CSV')
                    ->action(function () {
                        return response()->streamDownload(function () {
                            $handle = fopen('php://output', 'w');
                            fputcsv($handle, ['Received', 'First Name', 'Last Name', 'Email', 'Phone', 'Company', 'Product', 'Status', 'Assigned To', 'Message']);

                            QuoteRequest::query()
                                ->with(['product', 'assignee'])
                                ->orderByDesc('created_at')
                                ->each(function (QuoteRequest $quoteRequest) use ($handle) {
                                    fputcsv($handle, [
                                        $quoteRequest->created_at->toDateTimeString(),
                                        $quoteRequest->first_name,
                                        $quoteRequest->last_name,
                                        $quoteRequest->email,
                                        $quoteRequest->phone,
                                        $quoteRequest->company,
                                        $quoteRequest->product?->name,
                                        $quoteRequest->status,
                                        $quoteRequest->assignee?->name,
                                        $quoteRequest->message,
                                    ]);
                                });

                            fclose($handle);
                        }, 'quote-requests.csv');
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuoteRequests::route('/'),
            'view' => Pages\ViewQuoteRequest::route('/{record}'),
            'edit' => Pages\EditQuoteRequest::route('/{record}/edit'),
        ];
    }
}
