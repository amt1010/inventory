<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    private const AREAS = ['dashboard', 'staff', 'roles', 'categories', 'products', 'sellers', 'quote_requests', 'pages', 'nav_items', 'settings', 'audit_logs', 'email_templates'];

    private const TIERS = ['read', 'write', 'full'];

    private const ROLE_MATRIX = [
        'admin' => [
            'dashboard' => 'full', 'staff' => 'full',
            'roles' => 'full',
            'categories' => 'full', 'products' => 'full', 'sellers' => 'full',
            'quote_requests' => 'full', 'pages' => 'full', 'nav_items' => 'full', 'settings' => 'full',
            'audit_logs' => 'full', 'email_templates' => 'full',
        ],
        'content_editor' => [
            'dashboard' => 'read', 'staff' => null,
            'categories' => 'full', 'products' => 'write', 'sellers' => null,
            'quote_requests' => null, 'pages' => 'full', 'nav_items' => 'full', 'settings' => null,
            'audit_logs' => null, 'email_templates' => 'full',
        ],
        'sales' => [
            'dashboard' => 'read', 'staff' => null,
            'categories' => 'read', 'products' => 'read', 'sellers' => null,
            'quote_requests' => 'write', 'pages' => 'read', 'nav_items' => 'read', 'settings' => null,
            'audit_logs' => null, 'email_templates' => null,
        ],
    ];

    public function run(): void
    {
        foreach (self::AREAS as $area) {
            foreach (self::TIERS as $tier) {
                Permission::firstOrCreate(['name' => "{$area}.{$tier}", 'guard_name' => 'staff']);
            }
        }

        foreach (self::ROLE_MATRIX as $roleName => $areaTiers) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'staff']);

            $permissions = [];
            foreach ($areaTiers as $area => $tier) {
                if ($tier !== null) {
                    $permissions[] = "{$area}.{$tier}";
                }
            }

            $role->syncPermissions($permissions);
        }
    }
}
