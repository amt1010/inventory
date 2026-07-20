<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_nested_categorys_path_joins_every_ancestor_slug(): void
    {
        $root = Category::factory()->create(['parent_id' => null, 'slug' => 'fiber-optic-cable']);
        $child = Category::factory()->create(['parent_id' => $root->id, 'slug' => 'aerial']);
        $grandchild = Category::factory()->create(['parent_id' => $child->id, 'slug' => 'opgw']);

        $this->assertSame('fiber-optic-cable/aerial/opgw', $grandchild->path());
    }

    public function test_a_products_path_appends_its_slug_to_its_categorys_path(): void
    {
        $root = Category::factory()->create(['parent_id' => null, 'slug' => 'fiber-optic-cable']);
        $product = Product::factory()->create(['category_id' => $root->id, 'slug' => 'centracore-opgw-cable']);

        $this->assertSame('fiber-optic-cable/centracore-opgw-cable', $product->path());
    }

    public function test_path_terminates_on_a_self_referential_category(): void
    {
        $category = Category::factory()->create(['parent_id' => null, 'slug' => 'mobile-phone']);
        // Corrupt the tree the way bad/legacy data can: the category is its own parent.
        Category::withoutEvents(fn () => $category->newQuery()->whereKey($category->id)->update(['parent_id' => $category->id]));

        // Must return without exhausting execution time in an infinite parent walk.
        $this->assertSame('mobile-phone', $category->fresh()->path());
    }

    public function test_path_terminates_on_a_multi_node_cycle(): void
    {
        $a = Category::factory()->create(['parent_id' => null, 'slug' => 'a']);
        $b = Category::factory()->create(['parent_id' => $a->id, 'slug' => 'b']);
        // Close the loop: a's parent becomes b, so a -> b -> a -> b -> ...
        Category::withoutEvents(fn () => $a->newQuery()->whereKey($a->id)->update(['parent_id' => $b->id]));

        $path = $b->fresh()->path();

        // Terminates, and each category appears at most once in the emitted path.
        $this->assertStringContainsString('b', $path);
        $this->assertLessThanOrEqual(2, substr_count($path.'/', 'b/'));
    }
}
