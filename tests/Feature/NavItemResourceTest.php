<?php

namespace Tests\Feature;

use App\Filament\Resources\NavItemResource\Pages\CreateNavItem;
use App\Filament\Resources\NavItemResource\Pages\EditNavItem;
use App\Models\NavItem;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NavItemResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_create_a_top_level_header_nav_item(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Livewire::test(CreateNavItem::class)
            ->fillForm([
                'label' => 'Products',
                'url' => '/products',
                'location' => 'header',
                'sort_order' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('nav_items', ['label' => 'Products', 'url' => '/products']);
    }

    public function test_an_item_with_children_cannot_be_nested_under_another_item(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $grandparent = NavItem::factory()->create(['location' => 'header']);
        $parentWithChildren = NavItem::factory()->create(['location' => 'header']);
        NavItem::factory()->create(['location' => 'header', 'parent_id' => $parentWithChildren->id]);

        Livewire::test(EditNavItem::class, ['record' => $parentWithChildren->getRouteKey()])
            ->fillForm(['parent_id' => $grandparent->id])
            ->call('save')
            ->assertHasFormErrors(['parent_id']);
    }

    public function test_an_item_cannot_be_nested_under_an_item_that_is_itself_not_top_level(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $topLevel = NavItem::factory()->create(['location' => 'header']);
        $midLevel = NavItem::factory()->create(['location' => 'header', 'parent_id' => $topLevel->id]);
        $target = NavItem::factory()->create(['location' => 'header']);

        Livewire::test(EditNavItem::class, ['record' => $target->getRouteKey()])
            ->fillForm(['parent_id' => $midLevel->id])
            ->call('save')
            ->assertHasFormErrors(['parent_id']);
    }
}
