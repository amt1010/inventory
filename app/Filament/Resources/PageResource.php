<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('title')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
            TextInput::make('slug')
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('meta_title'),
            Textarea::make('meta_description'),
            Select::make('status')
                ->options(['draft' => 'Draft', 'published' => 'Published'])
                ->default('draft')
                ->required(),
            Builder::make('content')
                ->blocks([
                    Block::make('hero')
                        ->schema([
                            TextInput::make('heading')->required(),
                            TextInput::make('subheading'),
                            FileUpload::make('background_image')
                                ->image()
                                ->directory('page-blocks'),
                            TextInput::make('cta_label'),
                            TextInput::make('cta_url'),
                        ]),
                    Block::make('rich_text')
                        ->label('Rich Text')
                        ->schema([
                            RichEditor::make('body')->required(),
                        ]),
                    Block::make('featured_categories')
                        ->label('Featured Categories Grid')
                        ->schema([
                            TextInput::make('heading'),
                            Select::make('category_ids')
                                ->label('Categories')
                                ->multiple()
                                ->searchable()
                                ->options(fn () => Category::query()->where('status', 'published')->pluck('name', 'id'))
                                ->required(),
                        ]),
                    Block::make('featured_products')
                        ->label('Featured Products Grid')
                        ->schema([
                            TextInput::make('heading'),
                            Select::make('product_ids')
                                ->label('Products')
                                ->multiple()
                                ->searchable()
                                ->options(fn () => Product::query()->where('status', 'published')->pluck('name', 'id'))
                                ->required(),
                        ]),
                    Block::make('rfq_form_embed')
                        ->label('RFQ Form Embed')
                        ->schema([
                            TextInput::make('heading')->default('Request a Quote'),
                        ]),
                    Block::make('resource_list')
                        ->label('Resource List')
                        ->schema([
                            TextInput::make('heading'),
                            Repeater::make('items')
                                ->schema([
                                    TextInput::make('title')->required(),
                                    Textarea::make('description'),
                                    TextInput::make('url')->label('Link URL')->url(),
                                    FileUpload::make('file')->directory('page-resources'),
                                ]),
                        ]),
                    Block::make('faq')
                        ->label('FAQ / Accordion')
                        ->schema([
                            TextInput::make('heading'),
                            Repeater::make('items')
                                ->schema([
                                    TextInput::make('question')->required(),
                                    Textarea::make('answer')->required(),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('slug'),
                TextColumn::make('status')->badge(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
