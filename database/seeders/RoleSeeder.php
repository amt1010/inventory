<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    private const AREAS = ['dashboard', 'staff', 'roles', 'categories', 'products', 'sellers', 'quote_requests', 'subscribers', 'pages', 'nav_items', 'settings', 'audit_logs', 'email_templates'];

    private const TIERS = ['read', 'write', 'full'];

    private const ROLE_MATRIX = [
        'admin' => [
            'dashboard' => 'full', 'staff' => 'full',
            'roles' => 'full',
            'categories' => 'full', 'products' => 'full', 'sellers' => 'full',
            'quote_requests' => 'full', 'subscribers' => 'full', 'pages' => 'full', 'nav_items' => 'full', 'settings' => 'full',
            'audit_logs' => 'full', 'email_templates' => 'full',
        ],
        'content_editor' => [
            'dashboard' => 'read', 'staff' => null,
            'categories' => 'full', 'products' => 'write', 'sellers' => null,
            'quote_requests' => null, 'subscribers' => null, 'pages' => 'full', 'nav_items' => 'full', 'settings' => null,
            'audit_logs' => null, 'email_templates' => 'full',
        ],
        'sales' => [
            'dashboard' => 'read', 'staff' => null,
            'categories' => 'read', 'products' => 'read', 'sellers' => null,
            'quote_requests' => 'write', 'subscribers' => 'write', 'pages' => 'read', 'nav_items' => 'read', 'settings' => null,
            'audit_logs' => null, 'email_templates' => null,
        ],
    ];

    public function run(): void
    {
        $now = now();

        $permissionRows = [];
        foreach (self::AREAS as $area) {
            foreach (self::TIERS as $tier) {
                $permissionRows[] = [
                    'name' => "{$area}.{$tier}",
                    'guard_name' => 'staff',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Batch upsert all permissions in a single query/transaction instead of
        // one firstOrCreate() per row -- drastically reduces the number of
        // locks acquired against the permissions table during seeding.
        Permission::upsert(
            $permissionRows,
            ['name', 'guard_name'],
            ['updated_at']
        );

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

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
