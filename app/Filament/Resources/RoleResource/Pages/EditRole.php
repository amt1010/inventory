<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Models\Staff;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action, Role $record) {
                    if (Staff::role($record->name)->exists()) {
                        Notification::make()
                            ->title('Cannot delete a role assigned to staff')
                            ->danger()
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, [
            'permissions' => $this->record->permissions->pluck('name')->all(),
        ]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $permissions = RoleResource::permissionsFromFormData($data);

        $record->update(['name' => $data['name']]);
        $record->syncPermissions($permissions);

        return $record;
    }
}
