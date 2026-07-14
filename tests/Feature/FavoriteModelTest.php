<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_have_many_favorites(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        $this->assertCount(1, $user->favorites);
    }

    public function test_a_product_knows_who_favorited_it(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();

        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        // favoritedBy() returns Favorite rows (not User rows), so assert on
        // the foreign key column, not the Favorite's own id.
        $this->assertTrue($product->favoritedBy->contains('user_id', $user->id));
    }
}
