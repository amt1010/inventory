<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    public static function form(Form $form): Form
    {
        $canSetPrice = auth('staff')->user()?->can('setPrice', Product::class) ?? false;

        return $form->schema([
            Select::make('seller_id')
                ->label('Seller')
                ->options(fn () => Seller::query()->pluck('company_name', 'id'))
                ->searchable()
                ->required(),
            Select::make('category_id')
                ->label('Category')
                ->options(fn () => Category::query()->pluck('name', 'id'))
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
            TextInput::make('price_display')
                ->label('Price Range (INR)')
                ->placeholder('e.g. ₹1,200 – ₹1,800 per reel')
                ->disabled(! $canSetPrice)
                ->dehydrated($canSetPrice),
            Select::make('status')
                ->options(function (?Product $record) {
                    $options = [
                        'pending_review' => 'Pending Review',
                        'rejected' => 'Rejected',
                        'archived' => 'Archived',
                    ];

                    // A product that is already published keeps showing
                    // "Published" as its current value when edited, but the
                    // option is disabled below -- it can never be *chosen*
                    // from this select. Publishing only ever happens through
                    // the table's `publish` action, which calls
                    // Product::publish() and enforces the price_display
                    // invariant.
                    if ($record?->status === 'published') {
                        $options['published'] = 'Published';
                    }

                    return $options;
                })
                ->disableOptionWhen(fn (string $value) => $value === 'published')
                ->in(function (?Product $record) {
                    $values = ['pending_review', 'rejected', 'archived'];

                    if ($record?->status === 'published') {
                        $values[] = 'published';
                    }

                    return $values;
                })
                ->default('pending_review')
                ->disabled(! $canSetPrice)
                ->dehydrated($canSetPrice)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('seller.company_name')->label('Seller'),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('quantity'),
                TextColumn::make('status')->badge(),
                TextColumn::make('price_display')->label('Price'),
            ])
            ->actions([
                Action::make('publish')
                    ->visible(fn () => auth('staff')->user()?->can('approve', Product::class) ?? false)
                    ->requiresConfirmation()
                    ->action(fn (Product $record) => $record->publish()),
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
            RelationManagers\ImagesRelationManager::class,
        ];
    }
}
