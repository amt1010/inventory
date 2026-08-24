<?php

namespace Tests\Feature;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_21_staff_guard_permissions(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertSame(33, Permission::where('guard_name', 'staff')->count());
    }

    public function test_admin_role_gets_full_permission_in_every_area(): void
    {
        $this->seed(RoleSeeder::class);

        $permissions = Role::findByName('admin', 'staff')->permissions->pluck('name')->sort()->values()->all();

        $this->assertSame([
            'audit_logs.full', 'categories.full', 'dashboard.full', 'nav_items.full', 'pages.full', 'products.full',
            'quote_requests.full', 'roles.full', 'sellers.full', 'settings.full', 'staff.full',
        ], $permissions);
    }

    public function test_content_editor_role_matches_the_migration_matrix(): void
    {
        $this->seed(RoleSeeder::class);

        $permissions = Role::findByName('content_editor', 'staff')->permissions->pluck('name')->sort()->values()->all();

        $this->assertSame([
            'categories.full', 'dashboard.read', 'nav_items.full', 'pages.full', 'products.write',
        ], $permissions);
    }

    public function test_sales_role_matches_the_migration_matrix(): void
    {
        $this->seed(RoleSeeder::class);

        $permissions = Role::findByName('sales', 'staff')->permissions->pluck('name')->sort()->values()->all();

        $this->assertSame([
            'categories.read', 'dashboard.read', 'nav_items.read', 'pages.read', 'products.read', 'quote_requests.write',
        ], $permissions);
    }
}
