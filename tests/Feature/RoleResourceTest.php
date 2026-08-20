<?php

namespace Tests\Feature;

use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
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

    public function test_creating_a_role_with_chosen_tiers_attaches_exactly_the_right_permissions(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'field_manager',
                'tier_categories' => 'read',
                'tier_products' => 'write',
                'tier_sellers' => 'none',
                'tier_quote_requests' => 'none',
                'tier_pages' => 'none',
                'tier_nav_items' => 'none',
                'tier_settings' => 'none',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::where('name', 'field_manager')->where('guard_name', 'staff')->firstOrFail();

        $this->assertSame(
            ['categories.read', 'products.write'],
            $role->permissions->pluck('name')->sort()->values()->all()
        );
    }

    public function test_changing_a_tier_and_resaving_updates_instead_of_accumulating_permissions(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $role = Role::create(['name' => 'field_manager', 'guard_name' => 'staff']);
        $role->syncPermissions(['categories.read']);

        Livewire::test(EditRole::class, ['record' => $role->id])
            ->fillForm([
                'name' => 'field_manager',
                'tier_categories' => 'full',
                'tier_products' => 'none',
                'tier_sellers' => 'none',
                'tier_quote_requests' => 'none',
                'tier_pages' => 'none',
                'tier_nav_items' => 'none',
                'tier_settings' => 'none',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['categories.full'], $role->fresh()->permissions->pluck('name')->all());
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
}
