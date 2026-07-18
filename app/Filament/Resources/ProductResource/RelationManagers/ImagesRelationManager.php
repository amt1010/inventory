<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use App\Models\ProductImage;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public function form(Form $form): Form
    {
        return $form->schema([
            FileUpload::make('path')
                ->label('Image')
                ->image()
                ->directory('product-images')
                ->required(),
            Toggle::make('is_primary')
                ->label('Primary image'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('path')->label('Image'),
                IconColumn::make('is_primary')->boolean()->label('Primary'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                // Designating the primary image is intentionally decoupled from
                // the edit form: the edit form's `path` FileUpload is required,
                // so flipping only the primary flag there is fragile (a save is
                // blocked if the existing file can't be re-validated). This
                // one-click action sets the flag directly; the ProductImage
                // `saved` hook unsets the flag on sibling images.
                Action::make('setPrimary')
                    ->label('Set as primary')
                    ->icon('heroicon-o-star')
                    ->hidden(fn (ProductImage $record) => $record->is_primary)
                    ->action(fn (ProductImage $record) => $record->update(['is_primary' => true])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
