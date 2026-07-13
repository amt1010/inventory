<?php

namespace App\Filament\Seller\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Profile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static string $view = 'filament.seller.pages.profile';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(auth('seller')->user()->only([
            'company_name', 'contact_person', 'phone', 'business_address', 'gst_number',
        ]));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('company_name')->required(),
                TextInput::make('contact_person')->required(),
                TextInput::make('phone')->required(),
                TextInput::make('business_address'),
                TextInput::make('gst_number')->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        auth('seller')->user()->update($this->form->getState());

        Notification::make()->title('Profile updated')->success()->send();
    }
}
