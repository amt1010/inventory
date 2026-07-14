<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NavItemResource\Pages;
use App\Models\NavItem;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NavItemResource extends Resource
{
    protected static ?string $model = NavItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('label')->required(),
            TextInput::make('url')
                ->required()
                ->helperText('e.g. /about or /products/fiber-optic-cable'),
            Select::make('location')
                ->options(['header' => 'Header', 'footer' => 'Footer'])
                ->required()
                ->live(),
            Toggle::make('show_category_menu')
                ->label('Show live category mega-menu')
                ->helperText('When enabled, this item\'s dropdown shows the full published category tree instead of any manually-added sub-items below. Only meaningful for a top-level header item.')
                ->live()
                ->visible(fn (callable $get) => $get('location') === 'header' && ! $get('parent_id')),
            Select::make('parent_id')
                ->label('Parent Item')
                ->options(function (callable $get, ?NavItem $record) {
                    return NavItem::query()
                        ->whereNull('parent_id')
                        ->where('location', $get('location'))
                        ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                        ->pluck('label', 'id');
                })
                ->searchable()
                ->disabled(fn (?NavItem $record) => $record && $record->children()->exists())
                ->helperText(fn (?NavItem $record) => $record && $record->children()->exists()
                    ? 'This item has its own sub-items and cannot be nested under another item.'
                    : null)
                ->rule(function (callable $get, ?NavItem $record) {
                    return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                        if (! $value) {
                            return;
                        }

                        if ($record && $record->children()->exists()) {
                            $fail('This item has sub-items and cannot be nested under another item.');

                            return;
                        }

                        $validParentIds = NavItem::query()
                            ->whereNull('parent_id')
                            ->where('location', $get('location'))
                            ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                            ->pluck('id');

                        if (! $validParentIds->contains((int) $value)) {
                            $fail('Please choose a valid top-level item in the same location as the parent.');
                        }
                    };
                }),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable(),
                TextColumn::make('url'),
                TextColumn::make('location')->badge(),
                TextColumn::make('parent.label')->label('Parent')->placeholder('— Top level —'),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNavItems::route('/'),
            'create' => Pages\CreateNavItem::route('/create'),
            'edit' => Pages\EditNavItem::route('/{record}/edit'),
        ];
    }
}
