<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['dashboard', 'staff'] as $area) {
            foreach (['read', 'write', 'full'] as $tier) {
                Permission::findOrCreate("{$area}.{$tier}", 'staff');
            }
        }

        foreach (['admin' => ['dashboard.full', 'staff.full'], 'content_editor' => ['dashboard.read'], 'sales' => ['dashboard.read']] as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'staff')->first();

            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }
    }

    public function down(): void
    {
        foreach (['admin' => ['dashboard.full', 'staff.full'], 'content_editor' => ['dashboard.read'], 'sales' => ['dashboard.read']] as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'staff')->first();

            if ($role) {
                $role->revokePermissionTo($permissions);
            }
        }

        Permission::where('guard_name', 'staff')
            ->whereIn('name', ['dashboard.read', 'dashboard.write', 'dashboard.full', 'staff.read', 'staff.write', 'staff.full'])
            ->delete();
    }
};