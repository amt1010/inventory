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
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 3;

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
                    Block::make('hero_carousel')
                        ->label('Hero Carousel')
                        ->schema([
                            Repeater::make('slides')
                                ->schema([
                                    Select::make('media_type')
                                        ->options(['image' => 'Image', 'video' => 'Video'])
                                        ->default('image')
                                        ->live()
                                        ->required(),
                                    FileUpload::make('image')
                                        ->image()
                                        ->directory('page-blocks')
                                        ->visible(fn (callable $get) => $get('media_type') === 'image'),
                                    TextInput::make('video_url')
                                        ->label('Video URL (direct .mp4 link)')
                                        ->url()
                                        ->visible(fn (callable $get) => $get('media_type') === 'video'),
                                    TextInput::make('heading')->required(),
                                    TextInput::make('subheading'),
                                    TextInput::make('cta_label'),
                                    TextInput::make('cta_url'),
                                    Toggle::make('active')
                                        ->label('Show this slide')
                                        ->default(true),
                                ])
                                ->required()
                                ->minItems(1),
                        ]),
                    Block::make('hero_banner')
                        ->label('Hero Banner (Modernist)')
                        ->schema([
                            TextInput::make('tag'),
                            TextInput::make('heading')->required(),
                            Textarea::make('body'),
                            TextInput::make('search_placeholder')
                                ->default('Search for item by keyword or product number'),
                            TextInput::make('cta_primary_label')->default('Browse Products'),
                            TextInput::make('cta_primary_url')->default('/products'),
                            TextInput::make('cta_secondary_label')->default('Request a Quote'),
                            TextInput::make('cta_secondary_url')->default('/#rfq'),
                            FileUpload::make('image')
                                ->image()
                                ->directory('page-blocks'),
                        ]),
                    Block::make('content_strip')
                        ->label('Content Strip (Image + Text)')
                        ->schema([
                            TextInput::make('heading'),
                            RichEditor::make('body')->required(),
                            FileUpload::make('image')
                                ->image()
                                ->directory('page-blocks'),
                            Select::make('image_position')
                                ->options(['left' => 'Image Left', 'right' => 'Image Right'])
                                ->default('left')
                                ->required(),
                        ]),
                    Block::make('trust_badges')
                        ->label('Trust Badges')
                        ->schema([
                            Repeater::make('items')
                                ->schema([
                                    Select::make('icon')
                                        ->options([
                                            'shield-check' => 'Shield Check',
                                            'package-check' => 'Package Check',
                                            'handshake' => 'Handshake',
                                            'message-square' => 'Message Square',
                                        ])
                                        ->required(),
                                    TextInput::make('label')->required(),
                                ])
                                ->minItems(1),
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
                    Block::make('deals_banner')
                        ->label('Deals Banner')
                        ->schema([
                            TextInput::make('heading')->default('Bulk Deals This Week'),
                            Textarea::make('body'),
                            TextInput::make('cta_label')->default('Shop Deals'),
                            TextInput::make('cta_url')->default('/products'),
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
                            TextInput::make('tag'),
                            TextInput::make('heading')->default('Request a Quote'),
                            Textarea::make('body'),
                        ]),
                    Block::make('newsletter_signup')
                        ->label('Newsletter Signup')
                        ->schema([
                            TextInput::make('heading')->default('Get sourcing updates & deals'),
                            TextInput::make('subheading'),
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
            ])
            ->actions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Page $record) => route('staff.preview.page', $record))->openUrlInNewTab(),
                Action::make('viewLive')
                    ->label('View live')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->visible(fn (Page $record) => $record->status === 'published')
                    ->url(fn (Page $record) => $record->slug === 'home' ? url('/') : url('/'.$record->slug))->openUrlInNewTab(),
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
