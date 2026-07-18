<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Support\CategoryTree;
use App\Models\Category;
use App\Support\CategoryHierarchy;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    /**
     * Depth-first ordering of every category and its indentation depth, rebuilt
     * once per table render so the list reads as a parent → child tree. Kept on
     * the class (not a persistent cache) and refreshed in modifyQueryUsing so it
     * never goes stale between renders.
     *
     * @var array{ordered: list<int>, depth: array<int, int>}
     */
    protected static array $tree = ['ordered' => [], 'depth' => []];

    protected static function rebuildTree(): void
    {
        $all = Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'parent_id']);

        $children = [];
        foreach ($all as $category) {
            $children[$category->parent_id ?? 0][] = $category->id;
        }

        $ordered = [];
        $depth = [];

        $walk = function (int $parentId, int $level) use (&$walk, $children, &$ordered, &$depth): void {
            foreach ($children[$parentId] ?? [] as $id) {
                $ordered[] = $id;
                $depth[$id] = $level;
                $walk($id, $level + 1);
            }
        };

        $walk(0, 0);

        static::$tree = ['ordered' => $ordered, 'depth' => $depth];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('parent_id')
                ->label('Parent Category')
                ->options(fn (?Category $record) => CategoryHierarchy::options(
                    $record
                        ? fn (Builder $query) => $query->whereNotIn('id', CategoryHierarchy::descendantAndSelfIds($record))
                        : null
                ))
                ->searchable()
                ->placeholder('— Top level (no parent) —'),
            TextInput::make('name')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
            TextInput::make('slug')
                ->required()
                ->rule(fn (callable $get, $record) => Rule::unique('categories', 'slug')
                    ->where(fn ($query) => $query->where('parent_id', $get('parent_id')))
                    ->ignore($record?->id)),
            CategoryTree::subcategoriesRepeater(),
            Select::make('link_existing')
                ->label('Link to existing category')
                ->helperText('Attach existing top-level categories that have no parent as subcategories of this one.')
                ->multiple()
                ->searchable()
                ->options(fn () => CategoryHierarchy::options(fn (Builder $query) => $query->whereNull('parent_id')))
                ->visibleOn('create'),
            RichEditor::make('description'),
            FileUpload::make('image')
                ->image()
                ->directory('categories'),
            Select::make('status')
                ->options(['draft' => 'Draft', 'published' => 'Published'])
                ->default('draft')
                ->required(),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->formatStateUsing(fn (string $state, Category $record) => str_repeat('— ', static::$tree['depth'][$record->id] ?? 0).$state),
                TextColumn::make('proposedBy.company_name')
                    ->label('Proposed By')
                    ->placeholder('—'),
                TextColumn::make('parent.name')->label('Parent')->placeholder('— Top level —'),
                TextColumn::make('status')->badge(),
                TextColumn::make('sort_order'),
            ])
            ->actions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Category $record) => route('staff.preview.category', $record))->openUrlInNewTab(),
                Action::make('viewLive')
                    ->label('View live')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->visible(fn (Category $record) => $record->status === 'published')
                    ->url(fn (Category $record) => url('/products/'.$record->path()))->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->paginated(false)
            ->modifyQueryUsing(function (Builder $query) {
                static::rebuildTree();

                if (static::$tree['ordered'] !== []) {
                    $cases = collect(static::$tree['ordered'])
                        ->map(fn (int $id, int $position) => "WHEN {$id} THEN {$position}")
                        ->implode(' ');

                    $query->orderByRaw("CASE id {$cases} END");
                }
            });
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
