<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $permissions = RoleResource::permissionsFromFormData($data);

        $record = Role::create([
            'name' => $data['name'],
            'guard_name' => 'staff',
        ]);

        $record->syncPermissions($permissions);

        return $record;
    }
}
