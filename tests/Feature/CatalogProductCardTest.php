<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogProductCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_card_shows_price_and_moq(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'published',
            'price_display' => '₹45/meter',
            'quantity' => 500,
        ]);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertSee('₹45/meter', escape: false);
        $response->assertSee('MOQ: 500');
    }

    public function test_a_card_has_an_add_to_rfq_button_that_opens_the_products_own_modal(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertSee('Add to RFQ');
        $response->assertSee('data-bs-target="#quoteRequestModal-'.$product->id.'"', escape: false);
        $response->assertSee('id="quoteRequestModal-'.$product->id.'"', escape: false);
    }

    public function test_two_cards_on_one_page_do_not_produce_duplicate_modal_ids(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $first = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $second = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$category->slug);
        $html = $response->getContent();

        $response->assertOk();
        $this->assertSame(1, substr_count($html, 'id="quoteRequestModal-'.$first->id.'"'));
        $this->assertSame(1, substr_count($html, 'id="quoteRequestModal-'.$second->id.'"'));
    }

    public function test_no_supplier_or_seller_name_is_ever_rendered(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$category->slug);

        $response->assertOk();
        $response->assertDontSee($product->seller->company_name);
    }
}
