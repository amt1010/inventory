<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Favorite;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login_when_favoriting(): void
    {
        $product = Product::factory()->create();

        $response = $this->post('/favorites', ['product_id' => $product->id]);

        $response->assertRedirect(route('login'));
    }

    public function test_a_logged_in_buyer_can_favorite_a_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->post('/favorites', ['product_id' => $product->id]);

        $response->assertRedirect();
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_favoriting_the_same_product_twice_does_not_duplicate(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs($user, 'web');

        $this->post('/favorites', ['product_id' => $product->id]);
        $this->post('/favorites', ['product_id' => $product->id]);

        $this->assertSame(1, Favorite::where('user_id', $user->id)->where('product_id', $product->id)->count());
    }

    public function test_a_buyer_can_remove_their_own_favorite(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);
        $this->actingAs($user, 'web');

        $response = $this->delete("/favorites/{$product->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_a_buyer_cannot_remove_another_buyers_favorite(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create();
        Favorite::factory()->create(['user_id' => $owner->id, 'product_id' => $product->id]);
        $this->actingAs($other, 'web');

        $this->delete("/favorites/{$product->id}");

        $this->assertDatabaseHas('favorites', ['user_id' => $owner->id, 'product_id' => $product->id]);
    }

    public function test_the_favorites_page_only_lists_the_current_buyers_favorites(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = Category::factory()->create();
        $ownFavorite = Product::factory()->create(['category_id' => $category->id, 'status' => 'published', 'name' => 'My Favorite']);
        $othersFavorite = Product::factory()->create(['category_id' => $category->id, 'status' => 'published', 'name' => 'Not Mine']);
        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $ownFavorite->id]);
        Favorite::factory()->create(['user_id' => $other->id, 'product_id' => $othersFavorite->id]);
        $this->actingAs($user, 'web');

        $response = $this->get('/favorites');

        $response->assertOk();
        $response->assertSee('My Favorite');
        $response->assertDontSee('Not Mine');
    }
}
