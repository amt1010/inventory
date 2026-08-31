<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Models\EmailTemplate;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?int $navigationSort = 9;

    /**
     * @return array<string, string>
     */
    public static function tokenHelpFor(string $key): string
    {
        $tokens = [
            'product_listing_live' => '{{product_name}}, {{product_url}}',
            'quote_request_confirmation' => '{{first_name}}, {{quote_number}}, optional section {{#product_name}}...{{/product_name}}',
            'quote_request_received' => '{{reason}}, {{full_name}}, {{email}}, {{phone}}, {{company}}, {{admin_url}}, optional sections {{#product_name}}...{{/product_name}} and {{#message_text}}...{{/message_text}}',
            'seller_activation_admin_created' => '{{company_name}}, {{activation_url}}',
            'seller_activation_self_registered' => '{{company_name}}, {{activation_url}}',
            'seller_approved' => '{{company_name}}, optional section {{#activation_url}}...{{/activation_url}}',
            'seller_rejected' => '{{company_name}}, optional section {{#rejection_reason}}...{{/rejection_reason}}',
            'staff_invitation' => '{{staff_name}}, {{login_url}}, {{temporary_password}}',
        ];

        return $tokens[$key] ?? 'No key-specific tokens (custom template) — {{site_name}} is always available.';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('label')
                ->required()
                ->disabled(fn (?EmailTemplate $record) => $record?->is_system === true)
                ->afterStateUpdated(fn ($state, callable $set, ?EmailTemplate $record) => $record === null ? $set('key', \Illuminate\Support\Str::slug($state, '_')) : null)
                ->live(onBlur: true),
            TextInput::make('key')
                ->required()
                ->disabled(fn (?EmailTemplate $record) => $record?->is_system === true || $record !== null)
                ->rule(fn (?EmailTemplate $record) => Rule::unique('email_templates', 'key')->ignore($record?->id)),
            Placeholder::make('tokens_help')
                ->label('Available tokens')
                ->content(fn (?EmailTemplate $record) => $record ? static::tokenHelpFor($record->key) : 'Save first to see available tokens.'),
            TextInput::make('draft_subject')->label('Subject (draft)')->required(),
            RichEditor::make('draft_body')->label('Body (draft)')->required(),
            TextInput::make('draft_default_cc')->label('Default CC')->helperText('Comma-separated email addresses.'),
            TextInput::make('draft_default_bcc')->label('Default BCC')->helperText('Comma-separated email addresses.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label'),
                TextColumn::make('key'),
                IconColumn::make('is_system')->boolean()->label('System'),
                TextColumn::make('subject')->limit(40),
                IconColumn::make('modified')
                    ->label('Modified')
                    ->state(fn (EmailTemplate $record) => $record->isModified())
                    ->boolean(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTemplates::route('/'),
            'create' => Pages\CreateEmailTemplate::route('/create'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
