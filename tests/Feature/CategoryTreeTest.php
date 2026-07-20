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

    public function test_a_category_cannot_be_saved_as_its_own_parent(): void
    {
        $category = Category::factory()->create();

        $this->expectException(\App\Exceptions\CategoryWouldFormCycle::class);
        $category->update(['parent_id' => $category->id]);
    }

    public function test_a_category_cannot_be_moved_under_one_of_its_own_descendants(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);
        $grandchild = Category::factory()->create(['parent_id' => $child->id]);

        $this->expectException(\App\Exceptions\CategoryWouldFormCycle::class);
        $root->update(['parent_id' => $grandchild->id]);
    }

    public function test_the_self_parent_cycle_is_rejected_before_it_reaches_the_database(): void
    {
        $category = Category::factory()->create();

        try {
            $category->update(['parent_id' => $category->id]);
        } catch (\App\Exceptions\CategoryWouldFormCycle $e) {
            // The corrupt value must never be persisted.
        }

        $this->assertNull($category->fresh()->parent_id);
    }

    public function test_a_category_can_still_be_re_parented_to_an_unrelated_branch(): void
    {
        $branchA = Category::factory()->create();
        $branchB = Category::factory()->create();
        $movable = Category::factory()->create(['parent_id' => $branchA->id]);

        $movable->update(['parent_id' => $branchB->id]);

        $this->assertSame($branchB->id, $movable->fresh()->parent_id);
    }

    public function test_saving_a_normal_root_and_leaf_category_is_unaffected(): void
    {
        $root = Category::factory()->create(['parent_id' => null]);
        $child = Category::factory()->create(['parent_id' => $root->id]);

        $this->assertNull($root->fresh()->parent_id);
        $this->assertSame($root->id, $child->fresh()->parent_id);
    }
}
