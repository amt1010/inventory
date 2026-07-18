<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Models\Category;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_parent_category_field_explains_that_blank_means_top_level(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Livewire::test(CreateCategory::class)
            ->assertFormFieldExists('parent_id', function (Select $field) {
                return str_contains($field->getPlaceholder() ?? '', 'Top level');
            });
    }

    public function test_two_top_level_categories_cannot_share_a_slug(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Category::factory()->create(['parent_id' => null, 'slug' => 'fiber-optic-cable']);

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Fiber Optic Cable Duplicate',
                'slug' => 'fiber-optic-cable',
                'status' => 'draft',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_the_categories_table_shows_the_proposing_sellers_company_name(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $seller = \App\Models\Seller::factory()->create(['company_name' => 'Rao Traders']);
        Category::factory()->create(['status' => 'draft', 'proposed_by_seller_id' => $seller->id]);

        Livewire::test(\App\Filament\Resources\CategoryResource\Pages\ListCategories::class)
            ->assertSee('Rao Traders');
    }

    public function test_the_categories_table_shows_a_placeholder_for_admin_authored_categories(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        // Give it a parent so the pre-existing Parent column renders a real name
        // rather than its own "— Top level —" placeholder -- otherwise the '—'
        // asserted below could be satisfied by that column instead of proving
        // the new Proposed By column's placeholder actually renders.
        $parent = Category::factory()->create(['name' => 'Existing Parent', 'status' => 'published']);
        Category::factory()->create([
            'name' => 'Admin Made This',
            'parent_id' => $parent->id,
            'proposed_by_seller_id' => null,
        ]);

        Livewire::test(\App\Filament\Resources\CategoryResource\Pages\ListCategories::class)
            ->assertSeeText('—');
    }

    public function test_an_admin_can_create_a_category_with_nested_subcategories_inline(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Cables',
                'slug' => 'cables',
                'status' => 'published',
                'subcategories' => [
                    [
                        'name' => 'Indoor',
                        'subcategories' => [
                            ['name' => 'Riser', 'subcategories' => []],
                        ],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $top = Category::where('name', 'Cables')->firstOrFail();
        $indoor = Category::where('name', 'Indoor')->firstOrFail();
        $riser = Category::where('name', 'Riser')->firstOrFail();

        $this->assertSame($top->id, $indoor->parent_id);
        $this->assertSame($indoor->id, $riser->parent_id);

        // Inline subcategories inherit the top-level category's status.
        $this->assertSame('published', $indoor->status);
        $this->assertSame('published', $riser->status);
    }

    public function test_the_admin_category_list_shows_children_indented_under_their_parent(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $parent = Category::factory()->create(['name' => 'Parent Cat', 'status' => 'published', 'sort_order' => 0]);
        Category::factory()->create(['name' => 'Child Cat', 'parent_id' => $parent->id, 'status' => 'published', 'sort_order' => 0]);

        // The child renders after the parent (tree order) and its name is
        // indented to show the parent-child relationship.
        Livewire::test(\App\Filament\Resources\CategoryResource\Pages\ListCategories::class)
            ->assertSeeTextInOrder(['Parent Cat', '— Child Cat']);
    }
}
