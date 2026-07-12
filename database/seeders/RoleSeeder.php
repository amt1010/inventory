<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'content_editor', 'sales'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'staff']);
        }
    }
}
