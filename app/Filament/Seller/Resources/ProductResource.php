<?php

namespace App\Filament\Seller\Resources;

use App\Filament\Resources\ProductResource\RelationManagers as AdminRelationManagers;
use App\Filament\Seller\Resources\ProductResource\Pages;
use App\Filament\Seller\Resources\ProductResource\RelationManagers;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'My Products';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('seller_id', auth('seller')->id());
    }

    // Deliberately bypassing static::can() / Laravel's Gate for this resource --
    // see Global Constraints in the plan for why reusing App\Policies\ProductPolicy
    // (typed to Staff) would throw a TypeError for a Seller user.
    public static function canViewAny(): bool
    {
        return auth('seller')->check();
    }

    public static function canCreate(): bool
    {
        return auth('seller')->check();
    }

    public static function canView(Model $record): bool
    {
        return $record->seller_id === auth('seller')->id();
    }

    public static function canEdit(Model $record): bool
    {
        return $record->seller_id === auth('seller')->id();
    }

    public static function canDelete(Model $record): bool
    {
        return $record->seller_id === auth('seller')->id();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('category_id')
                ->label('Category')
                ->options(fn () => Category::query()->whereDoesntHave('children')->pluck('name', 'id'))
                ->searchable()
                ->required(),
            TextInput::make('name')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
            TextInput::make('slug')->required(),
            TextInput::make('sku')->label('SKU / Product Number'),
            TextInput::make('quantity')
                ->numeric()
                ->minValue(0),
            TextInput::make('short_description'),
            RichEditor::make('description'),
            Repeater::make('features')->simple(TextInput::make('value')->required()),
            Repeater::make('applications')->simple(TextInput::make('value')->required()),
            FileUpload::make('spec_sheet_path')
                ->label('Specification Sheet (PDF)')
                ->directory('spec-sheets')
                ->acceptedFileTypes(['application/pdf']),
            Placeholder::make('status_display')
                ->label('Status')
                ->content(fn (?Product $record) => $record
                    ? ucfirst(str_replace('_', ' ', $record->status))
                    : 'Pending Review (assigned on submit)'),
            Placeholder::make('rejection_reason')
                ->label('Rejection Reason')
                ->content(fn (?Product $record) => $record?->rejection_reason)
                ->visible(fn (?Product $record) => $record?->status === 'rejected'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('quantity'),
                TextColumn::make('status')->badge(),
                TextColumn::make('quote_requests_count')
                    ->counts('quoteRequests')
                    ->label('Quote Requests'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            AdminRelationManagers\ImagesRelationManager::class,
            RelationManagers\CustomAttributesRelationManager::class,
        ];
    }
}
