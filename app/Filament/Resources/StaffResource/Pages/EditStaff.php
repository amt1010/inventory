<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use App\Mail\StaffInvitation;
use App\Models\Staff;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resetPassword')
                ->label('Reset Password')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (Staff $record) {
                    $temporaryPassword = Str::password(16);

                    $record->update([
                        'password' => Hash::make($temporaryPassword),
                        'must_change_password' => true,
                    ]);

                    Mail::to($record->email)->queue(new StaffInvitation(
                        $record,
                        $temporaryPassword,
                        Filament::getPanel('admin')->getLoginUrl(),
                    ));

                    Notification::make()
                        ->title('Temporary password sent')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->record->roles->pluck('name')->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $record->syncRoles($data['roles'] ?? []);

        return $record;
    }
}
