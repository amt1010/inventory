<?php

namespace App\Filament\Seller\Resources;

use App\Filament\Seller\Resources\CategoryResource\Pages;
use App\Filament\Support\CategoryTree;
use App\Models\Category;
use App\Support\CategoryHierarchy;
use Filament\Forms\Components\Placeholder;
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

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'My Categories';

    // Sellers only ever see and manage their own proposals. Published
    // categories authored/approved by staff are read-only reference data,
    // reachable as parent options but not listed here for editing.
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('proposed_by_seller_id', auth('seller')->id());
    }

    // Deliberately bypassing static::can() / the Staff-typed CategoryPolicy --
    // same reasoning as the seller ProductResource: the policy is typed to Staff
    // and would throw for a Seller user.
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
        return $record->proposed_by_seller_id === auth('seller')->id();
    }

    // A proposal is editable/deletable by its proposing seller only until an
    // admin publishes it. Once published it becomes read-only reference data.
    public static function canEdit(Model $record): bool
    {
        return $record->proposed_by_seller_id === auth('seller')->id()
            && $record->status !== 'published';
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    // Sellers may nest under any published category or under one of their own
    // draft proposals -- never under another seller's pending proposal.
    private static function constrainToSelectable(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('status', 'published')
                ->orWhere(function (Builder $query) {
                    $query->where('status', 'draft')
                        ->where('proposed_by_seller_id', auth('seller')->id());
                });
        });
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('parent_id')
                ->label('Parent Category')
                ->options(fn () => CategoryHierarchy::options(fn (Builder $query) => static::constrainToSelectable($query)))
                ->searchable()
                ->placeholder('— Top level (no parent) —')
                ->helperText('Leave blank for a top-level category, or nest under any existing category to any depth.'),
            TextInput::make('name')
                ->label('Category / Sub-Category Name')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state)))
                ->rule(fn (callable $get, ?Category $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                    $slug = Str::slug($value);

                    if (Category::query()
                        ->where('parent_id', $get('parent_id'))
                        ->where('slug', $slug)
                        ->when($record, fn (Builder $query) => $query->whereKeyNot($record->id))
                        ->exists()) {
                        $fail('A category with a similar name already exists under the selected parent.');
                    }
                }),
            TextInput::make('slug')->required(),
            RichEditor::make('description'),
            CategoryTree::subcategoriesRepeater(),
            Placeholder::make('review_note')
                ->label('')
                ->content('New categories are submitted as drafts for administrator review before they appear on the public catalog. An administrator may adjust the name, slug, or parent before publishing.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('parent.name')->label('Parent')->placeholder('— Top level —'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->label('Proposed'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
