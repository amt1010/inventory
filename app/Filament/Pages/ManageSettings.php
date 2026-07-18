<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Site Settings';

    protected static string $view = 'filament.pages.manage-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth('staff')->user()?->can('manage', Setting::class) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(Setting::current()->only([
            'site_name', 'logo_path',
            'footer_copyright', 'footer_address', 'footer_phone', 'footer_email',
            'social_facebook', 'social_twitter', 'social_linkedin', 'social_instagram', 'social_youtube',
        ]));
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('site_name')->required(),
            FileUpload::make('logo_path')
                ->label('Logo')
                ->image()
                ->directory('branding'),
            Section::make('Footer')
                ->description('Content shown in the site footer.')
                ->schema([
                    TextInput::make('footer_copyright')
                        ->label('Copyright text')
                        ->helperText('Use {year} for the current year, e.g. "© {year} Acme Corp".'),
                    Textarea::make('footer_address')->label('Address')->rows(3),
                    TextInput::make('footer_phone')->label('Phone')->tel(),
                    TextInput::make('footer_email')->label('Email')->email(),
                ])
                ->columns(2),
            Section::make('Social media links')
                ->description('Only platforms with a URL are shown in the footer.')
                ->schema([
                    TextInput::make('social_facebook')->label('Facebook')->url()->prefixIcon('heroicon-o-link'),
                    TextInput::make('social_twitter')->label('X / Twitter')->url()->prefixIcon('heroicon-o-link'),
                    TextInput::make('social_linkedin')->label('LinkedIn')->url()->prefixIcon('heroicon-o-link'),
                    TextInput::make('social_instagram')->label('Instagram')->url()->prefixIcon('heroicon-o-link'),
                    TextInput::make('social_youtube')->label('YouTube')->url()->prefixIcon('heroicon-o-link'),
                ])
                ->columns(2),
        ])->statePath('data');
    }

    public function save(): void
    {
        Setting::current()->update($this->form->getState());

        Notification::make()->title('Settings saved')->success()->send();
    }
}
