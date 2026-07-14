<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\FileUpload;
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
        $this->form->fill(Setting::current()->only(['site_name', 'logo_path']));
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('site_name')->required(),
            FileUpload::make('logo_path')
                ->label('Logo')
                ->image()
                ->directory('branding'),
        ])->statePath('data');
    }

    public function save(): void
    {
        Setting::current()->update($this->form->getState());

        Notification::make()->title('Settings saved')->success()->send();
    }
}
