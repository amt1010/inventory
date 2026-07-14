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
}
