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

        Category::factory()->create(['name' => 'Admin Made This', 'proposed_by_seller_id' => null]);

        Livewire::test(\App\Filament\Resources\CategoryResource\Pages\ListCategories::class)
            ->assertSeeText('—');
    }
}
