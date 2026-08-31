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
     * Token names each system template key accepts — the single source
     * of truth for both the admin-facing token help text and the
     * preview's sample data, so the two can never drift again.
     *
     * @var array<string, array<int, string>>
     */
    private const TOKEN_MAP = [
        'product_listing_live' => ['product_name', 'product_url'],
        'quote_request_confirmation' => ['first_name', 'quote_number', 'product_name'],
        'quote_request_received' => ['reason', 'full_name', 'email', 'phone', 'company', 'admin_url', 'product_name', 'product_url', 'product_thumbnail_html', 'message_text'],
        'seller_activation_admin_created' => ['company_name', 'activation_url'],
        'seller_activation_self_registered' => ['company_name', 'activation_url'],
        'seller_approved' => ['company_name', 'activation_url'],
        'seller_rejected' => ['company_name', 'rejection_reason'],
        'staff_invitation' => ['staff_name', 'login_url', 'temporary_password'],
    ];

    /**
     * Sample values for each token name, keyed by name so the same
     * token (e.g. company_name) gets a consistent sample everywhere it
     * appears.
     *
     * @return array<string, string>
     */
    private static function tokenSampleValues(): array
    {
        return [
            'product_name' => 'Aerial Fiber Cable',
            'product_url' => url('/products/sample'),
            'first_name' => 'Asha',
            'quote_number' => 'QR-1001',
            'reason' => 'General Inquiry',
            'full_name' => 'Asha Rao',
            'email' => 'asha@example.com',
            'phone' => '9999999999',
            'company' => 'Acme Co',
            'admin_url' => url('/admin/quote-requests/1'),
            'product_thumbnail_html' => '<div style="width:132px;height:132px;background:#eee;display:flex;align-items:center;justify-content:center;color:#999;font-size:12px;">Sample Image</div>',
            'message_text' => 'Please share pricing for 500 meters.',
            'company_name' => 'Acme Co',
            'activation_url' => url('/seller/activate/1?signature=sample'),
            'rejection_reason' => 'Documents did not match business name.',
            'staff_name' => 'Priya',
            'login_url' => url('/admin/login'),
            'temporary_password' => 'Temp1234!',
        ];
    }

    public static function tokenHelpFor(string $key): string
    {
        $names = self::TOKEN_MAP[$key] ?? null;

        if ($names === null) {
            return 'No key-specific tokens (custom template) — {{site_name}} is always available.';
        }

        return collect($names)->map(fn (string $name) => "{{{$name}}}")->implode(', ');
    }

    /**
     * @return array<string, string>
     */
    public static function sampleTokensFor(string $key): array
    {
        $names = self::TOKEN_MAP[$key] ?? [];
        $samples = self::tokenSampleValues();

        return collect($names)->mapWithKeys(fn (string $name) => [$name => $samples[$name] ?? ''])->all();
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
            TextInput::make('draft_default_cc')
                ->label('Default CC')
                ->helperText('Comma-separated email addresses.')
                ->rule(function () {
                    return function (string $attribute, $value, \Closure $fail) {
                        if (blank($value)) {
                            return;
                        }

                        foreach (explode(',', $value) as $email) {
                            $email = trim($email);

                            if ($email === '') {
                                continue;
                            }

                            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $fail("Each address must be a valid email — \"{$email}\" is not.");
                            }
                        }
                    };
                }),
            TextInput::make('draft_default_bcc')
                ->label('Default BCC')
                ->helperText('Comma-separated email addresses.')
                ->rule(function () {
                    return function (string $attribute, $value, \Closure $fail) {
                        if (blank($value)) {
                            return;
                        }

                        foreach (explode(',', $value) as $email) {
                            $email = trim($email);

                            if ($email === '') {
                                continue;
                            }

                            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $fail("Each address must be a valid email — \"{$email}\" is not.");
                            }
                        }
                    };
                }),
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
