<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_can_have_children(): void
    {
        $parent = Category::factory()->create(['slug' => 'fiber-optic-cable']);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'slug' => 'aerial']);

        $this->assertTrue($parent->fresh()->children->contains($child));
        $this->assertTrue($child->parent->is($parent));
    }

    public function test_sibling_categories_cannot_share_a_slug_under_the_same_parent(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id, 'slug' => 'aerial']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Category::factory()->create(['parent_id' => $parent->id, 'slug' => 'aerial']);
    }
}
