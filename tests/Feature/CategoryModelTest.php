<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposed_by_seller_id_is_nullable_and_fillable(): void
    {
        $category = Category::factory()->create(['proposed_by_seller_id' => null]);

        $this->assertNull($category->fresh()->proposed_by_seller_id);
    }

    public function test_a_category_can_belong_to_the_seller_who_proposed_it(): void
    {
        $seller = Seller::factory()->create();
        $category = Category::factory()->create(['proposed_by_seller_id' => $seller->id]);

        $this->assertTrue($category->proposedBy->is($seller));
    }

    public function test_proposed_by_is_null_when_no_seller_proposed_it(): void
    {
        $category = Category::factory()->create(['proposed_by_seller_id' => null]);

        $this->assertNull($category->proposedBy);
    }
}
