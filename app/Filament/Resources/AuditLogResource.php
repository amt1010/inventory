<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Audit Logs';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime(),
                TextColumn::make('performedBy.name')->label('Imported By')->placeholder('—'),
                TextColumn::make('importer_label')->label('Type'),
                TextColumn::make('file_name')->label('File'),
                TextColumn::make('total_rows')->label('Total')->placeholder('—'),
                TextColumn::make('successful_rows')->label('Imported')->placeholder('—'),
                TextColumn::make('failed_rows')->label('Failed')->placeholder('—'),
                TextColumn::make('summary')->wrap(),
            ])
            ->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
