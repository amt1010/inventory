<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const AREAS = [
        'dashboard', 'staff', 'categories', 'products', 'sellers',
        'quote_requests', 'pages', 'nav_items', 'settings',
    ];

    private const TIERS = ['read', 'write', 'full'];

    private const ROLE_PERMISSIONS = [
        'admin' => [
            'dashboard.full', 'staff.full', 'categories.full', 'products.full',
            'sellers.full', 'quote_requests.full', 'pages.full', 'nav_items.full', 'settings.full',
        ],
        'content_editor' => [
            'dashboard.read', 'categories.full', 'products.write', 'pages.full', 'nav_items.full',
        ],
        'sales' => [
            'dashboard.read', 'categories.read', 'products.read', 'quote_requests.write',
            'pages.read', 'nav_items.read',
        ],
    ];

    public function up(): void
    {
        foreach (self::AREAS as $area) {
            foreach (self::TIERS as $tier) {
                Permission::findOrCreate("{$area}.{$tier}", 'staff');
            }
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'staff')->first();

            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        Artisan::call('permission:cache-reset');
    }

    public function down(): void
    {
        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'staff')->first();

            if ($role) {
                $role->revokePermissionTo($permissions);
            }
        }

        Permission::where('guard_name', 'staff')
            ->where(function ($query) {
                foreach (self::AREAS as $area) {
                    $query->orWhere('name', 'like', "{$area}.%");
                }
            })
            ->delete();
    }
};