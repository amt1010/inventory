<?php

namespace App\Filament\Seller\Resources;

use App\Filament\Resources\ProductResource\RelationManagers as AdminRelationManagers;
use App\Filament\Seller\Resources\ProductResource\Pages;
use App\Filament\Seller\Resources\ProductResource\RelationManagers;
use App\Mail\ProductListingLive;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    private static function categoryOptionsQuery(): Builder
    {
        return Category::query()
            ->where(function (Builder $query) {
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
            Select::make('category_id')
                ->label('Category')
                ->options(fn () => static::categoryOptionsQuery()
                    ->whereDoesntHave('children')
                    ->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->helperText('Need a category that isn\'t listed? Create it under "My Categories" first.'),
            Placeholder::make('category_status_note')
                ->label('')
                ->content('Category status: Draft — an administrator needs to review and publish this category before your product can go live.')
                ->visible(fn (callable $get) => optional(Category::find($get('category_id')))->status === 'draft'),
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
            RichEditor::make('features')
                ->helperText('Use a bulleted list for individual features.'),
            RichEditor::make('applications')
                ->helperText('Use a bulleted list for individual applications.'),
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
            ])
            ->actions([
                Action::make('acceptChanges')
                    ->label('Accept Changes')
                    ->visible(fn (Product $record) => $record->status === 'pending_seller_acceptance')
                    ->requiresConfirmation()
                    ->modalHeading('Review Admin Changes')
                    ->modalContent(fn (Product $record) => view('filament.seller.partials.edit-diff', [
                        'trail' => $record->latestPendingEditTrail(),
                    ]))
                    ->modalSubmitActionLabel('Accept Changes')
                    ->action(function (Product $record) {
                        $trail = $record->latestPendingEditTrail();

                        if (! $record->publish()) {
                            $record->update(['status' => 'pending_review']);

                            return;
                        }

                        $trail?->update(['accepted_at' => now()]);

                        try {
                            Mail::to($record->seller->email)->send(new ProductListingLive($record));
                        } catch (\Throwable $exception) {
                            Log::error('Failed to send product listing live email.', [
                                'product_id' => $record->id,
                                'exception' => $exception->getMessage(),
                            ]);
                        }
                    }),
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
