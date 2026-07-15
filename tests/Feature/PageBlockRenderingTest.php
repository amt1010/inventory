<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageBlockRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_hero_block_renders_its_heading_and_cta(): void
    {
        Page::factory()->create([
            'slug' => 'about',
            'status' => 'published',
            'content' => [
                ['type' => 'hero', 'data' => ['heading' => 'Welcome to AFL Marketplace', 'cta_label' => 'Browse Products', 'cta_url' => '/products']],
            ],
        ]);

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('Welcome to AFL Marketplace');
        $response->assertSee('Browse Products');
    }

    public function test_a_featured_categories_block_links_to_the_full_nested_path(): void
    {
        $root = Category::factory()->create(['parent_id' => null, 'slug' => 'fiber-optic-cable', 'status' => 'published']);
        $child = Category::factory()->create(['parent_id' => $root->id, 'slug' => 'aerial', 'status' => 'published']);

        Page::factory()->create([
            'slug' => 'about',
            'status' => 'published',
            'content' => [
                ['type' => 'featured_categories', 'data' => ['heading' => 'Popular Categories', 'category_ids' => [$child->id]]],
            ],
        ]);

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('/products/fiber-optic-cable/aerial', escape: false);
    }

    public function test_a_featured_products_block_only_shows_published_products(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $published = Product::factory()->create(['category_id' => $category->id, 'status' => 'published', 'name' => 'Published Widget']);
        $rejected = Product::factory()->create(['category_id' => $category->id, 'status' => 'rejected', 'name' => 'Rejected Widget']);

        Page::factory()->create([
            'slug' => 'about',
            'status' => 'published',
            'content' => [
                ['type' => 'featured_products', 'data' => ['product_ids' => [$published->id, $rejected->id]]],
            ],
        ]);

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('Published Widget');
        $response->assertDontSee('Rejected Widget');
    }

    public function test_an_rfq_form_embed_block_renders_the_form_inline_without_a_modal(): void
    {
        Page::factory()->create([
            'slug' => 'contact-us',
            'status' => 'published',
            'content' => [
                ['type' => 'rfq_form_embed', 'data' => ['heading' => 'Get in Touch']],
            ],
        ]);

        $response = $this->get('/contact-us');

        $response->assertOk();
        $response->assertSee('Get in Touch');
        $response->assertSee(route('quote-requests.store'), escape: false);
        // Inline embed, not the Product Detail page's modal wrapper.
        $response->assertDontSee('modal fade', escape: false);
    }

    public function test_a_faq_block_renders_each_question_and_answer(): void
    {
        Page::factory()->create([
            'slug' => 'about',
            'status' => 'published',
            'content' => [
                ['type' => 'faq', 'data' => ['items' => [['question' => 'Do you ship internationally?', 'answer' => 'Yes, globally.']]]],
            ],
        ]);

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('Do you ship internationally?');
        $response->assertSee('Yes, globally.');
    }

    public function test_the_product_detail_pages_existing_modal_form_still_renders_after_the_extraction(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $response = $this->get('/products/'.$product->path());

        $response->assertOk();
        $response->assertSee('id="quoteRequestModal-'.$product->id.'"', escape: false);
    }

    public function test_featured_categories_render_in_the_order_the_editor_chose_them(): void
    {
        $first = Category::factory()->create(['status' => 'published']);
        $second = Category::factory()->create(['status' => 'published']);

        Page::factory()->create([
            'slug' => 'about',
            'status' => 'published',
            'content' => [
                ['type' => 'featured_categories', 'data' => ['category_ids' => [$second->id, $first->id]]],
            ],
        ]);

        $response = $this->get('/about');

        $response->assertOk();
        $positionOfSecond = strpos($response->getContent(), $second->name);
        $positionOfFirst = strpos($response->getContent(), $first->name);
        $this->assertLessThan($positionOfFirst, $positionOfSecond, 'Category chosen second in category_ids order should render before the one chosen first.');
    }

    public function test_two_rfq_form_embed_blocks_on_one_page_do_not_produce_duplicate_ids(): void
    {
        Page::factory()->create([
            'slug' => 'about',
            'status' => 'published',
            'content' => [
                ['type' => 'rfq_form_embed', 'data' => ['heading' => 'First Form']],
                ['type' => 'rfq_form_embed', 'data' => ['heading' => 'Second Form']],
            ],
        ]);

        $response = $this->get('/about');
        $response->assertOk();

        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'id="contact-email-embed-0"'));
        $this->assertSame(1, substr_count($html, 'id="contact-email-embed-1"'));
    }

    public function test_a_featured_products_block_renders_a_fixed_size_thumbnail(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);
        $product->images()->create(['path' => 'product-images/featured.jpg', 'sort_order' => 0, 'is_primary' => true]);

        Page::factory()->create([
            'slug' => 'about',
            'status' => 'published',
            'content' => [
                ['type' => 'featured_products', 'data' => ['product_ids' => [$product->id]]],
            ],
        ]);

        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('featured.jpg', false);
        $response->assertSee('width="132"', false);
    }
}
