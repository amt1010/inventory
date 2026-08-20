<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['read', 'write', 'full'] as $tier) {
            Permission::findOrCreate("roles.{$tier}", 'staff');
        }

        $admin = Role::where('name', 'admin')->where('guard_name', 'staff')->first();

        if ($admin) {
            $admin->givePermissionTo('roles.full');
        }
    }

    public function down(): void
    {
        $admin = Role::where('name', 'admin')->where('guard_name', 'staff')->first();

        if ($admin) {
            $admin->revokePermissionTo('roles.full');
        }

        Permission::where('guard_name', 'staff')
            ->whereIn('name', ['roles.read', 'roles.write', 'roles.full'])
            ->delete();
    }
};