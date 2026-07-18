<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Category;
use App\Models\Staff;
use App\Support\CategoryHierarchy;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function tree(): array
    {
        $top = Category::factory()->create(['name' => 'Television', 'status' => 'published']);
        $sub = Category::factory()->create(['name' => 'LED', 'parent_id' => $top->id, 'status' => 'published']);
        $leaf = Category::factory()->create(['name' => 'HD', 'parent_id' => $sub->id, 'status' => 'published']);

        return compact('top', 'sub', 'leaf');
    }

    public function test_options_label_each_category_with_its_full_ancestor_path(): void
    {
        ['leaf' => $leaf] = $this->tree();

        $options = CategoryHierarchy::options();

        $this->assertSame('Television › LED › HD', $options[$leaf->id]);
    }

    public function test_descendant_and_self_ids_covers_the_whole_subtree(): void
    {
        ['top' => $top, 'sub' => $sub, 'leaf' => $leaf] = $this->tree();

        $ids = CategoryHierarchy::descendantAndSelfIds($top);

        sort($ids);
        $expected = [$top->id, $sub->id, $leaf->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_admin_product_category_select_shows_the_hierarchy_path(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        ['leaf' => $leaf] = $this->tree();

        Livewire::test(CreateProduct::class)
            ->assertFormFieldExists('category_id', fn (Select $field) => ($field->getOptions()[$leaf->id] ?? null) === 'Television › LED › HD');
    }

    public function test_admin_category_parent_select_excludes_the_category_and_its_descendants(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        ['top' => $top, 'sub' => $sub, 'leaf' => $leaf] = $this->tree();

        Livewire::test(EditCategory::class, ['record' => $top->getRouteKey()])
            ->assertFormFieldExists('parent_id', function (Select $field) use ($top, $sub, $leaf) {
                $options = $field->getOptions();

                return ! array_key_exists($top->id, $options)
                    && ! array_key_exists($sub->id, $options)
                    && ! array_key_exists($leaf->id, $options);
            });
    }
}
