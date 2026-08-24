<?php

namespace Tests\Feature;

use App\Filament\Resources\RoleResource;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_creating_a_role_with_checked_permissions_attaches_exactly_the_right_permissions(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'field_manager',
                'permissions' => ['categories.read', 'products.write'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::where('name', 'field_manager')->where('guard_name', 'staff')->firstOrFail();

        $this->assertSame(
            ['categories.read', 'products.write'],
            $role->permissions->pluck('name')->sort()->values()->all()
        );
    }

    public function test_changing_checked_permissions_and_resaving_updates_instead_of_accumulating_permissions(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $role = Role::create(['name' => 'field_manager', 'guard_name' => 'staff']);
        $role->syncPermissions(['categories.read']);

        Livewire::test(EditRole::class, ['record' => $role->id])
            ->fillForm([
                'name' => 'field_manager',
                'permissions' => ['categories.full'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['categories.full'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_admin_and_content_editor_roles_can_save_roles_access(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        foreach (['admin', 'content_editor'] as $roleName) {
            $role = Role::findByName($roleName, 'staff');

            Livewire::test(EditRole::class, ['record' => $role->id])
                ->fillForm([
                    'name' => $roleName,
                    'permissions' => array_merge(
                        $roleName === 'admin' ? ['roles.full'] : [],
                        ['dashboard.read'],
                    ),
                ])
                ->call('save')
                ->assertHasNoFormErrors();
        }

        $this->assertTrue(Role::findByName('admin', 'staff')->hasPermissionTo('roles.full'));
        $this->assertTrue(Role::findByName('content_editor', 'staff')->hasPermissionTo('dashboard.read'));
    }

    public function test_the_access_column_wraps_instead_of_truncating(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $column = Livewire::test(ListRoles::class)->instance()->getTable()->getColumn('access');

        $this->assertTrue($column->canWrap());
    }

    public function test_deleting_a_role_still_assigned_to_staff_is_rejected(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $staffMember = Staff::factory()->create();
        $staffMember->assignRole('sales');

        $role = Role::findByName('sales', 'staff');

        Livewire::test(EditRole::class, ['record' => $role->id])
            ->callAction('delete');

        $this->assertNotNull($role->fresh());
    }

    public function test_deleting_a_role_not_assigned_to_staff_succeeds_and_redirects(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $role = Role::create(['name' => 'field_manager', 'guard_name' => 'staff']);

        Livewire::test(EditRole::class, ['record' => $role->id])
            ->callAction('delete')
            ->assertRedirect(RoleResource::getUrl('index'));

        $this->assertNull($role->fresh());
    }
}
