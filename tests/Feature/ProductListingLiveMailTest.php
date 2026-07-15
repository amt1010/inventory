<?php

namespace Tests\Feature;

use App\Mail\ProductListingLive;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListingLiveMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_email_links_to_the_live_product_page(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Aerial Fiber Cable', 'status' => 'published']);

        $mailable = new ProductListingLive($product);

        $mailable->assertSeeInHtml('Aerial Fiber Cable');
        $mailable->assertSeeInHtml(url('/products/'.$product->path()), escape: false);
    }
}
