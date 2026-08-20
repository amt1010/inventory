<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use App\Mail\StaffInvitation;
use App\Models\Staff;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $temporaryPassword = Str::password(16);
        $roles = $data['roles'] ?? [];

        $record = Staff::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        $record->syncRoles($roles);

        Mail::to($record->email)->queue(new StaffInvitation(
            $record,
            $temporaryPassword,
            Filament::getPanel('admin')->getLoginUrl(),
        ));

        return $record;
    }
}
