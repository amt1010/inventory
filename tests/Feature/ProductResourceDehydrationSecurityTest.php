<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductResourceDehydrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_set_price_and_publish_a_product(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $seller = Seller::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin, 'staff');

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'name' => 'Test Product',
                'slug' => 'test-product',
                'price_display' => '₹1,200 – ₹1,800 per reel',
                'status' => 'published',
                'features' => [],
                'applications' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'test-product')->firstOrFail();

        $this->assertSame('₹1,200 – ₹1,800 per reel', $product->price_display);
        $this->assertSame('published', $product->status);
    }

    public function test_content_editor_cannot_set_price_via_tampered_payload(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $seller = Seller::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($editor, 'staff');

        // Simulate a tampered Livewire payload: even though the field is
        // rendered disabled in the UI, fillForm() bypasses the UI and sets
        // the underlying Livewire component state directly -- exactly what
        // an attacker manipulating the wire:model payload would do.
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'name' => 'Editor Product',
                'slug' => 'editor-product',
                'price_display' => '₹9,999 – hacked',
                'status' => 'published',
                'features' => [],
                'applications' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'editor-product')->first();

        $this->assertNotNull($product, 'Product should still be created (editor can create content).');
        $this->assertNull($product->price_display, 'price_display must NOT be persisted for a non-admin, even if submitted.');
        $this->assertNotSame('published', $product->status, 'status must NOT be settable by a non-admin.');
    }

    public function test_content_editor_can_create_a_product(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $seller = Seller::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($editor, 'staff');

        // The status field is disabled/non-dehydrated for a content_editor,
        // so it is never submitted by this role -- the field must still
        // have a valid default value or the create request fails validation
        // even though ProductPolicy::create() explicitly authorizes this role.
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'name' => 'Editor Created Product',
                'slug' => 'editor-created-product',
                'features' => [],
                'applications' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'editor-created-product')->firstOrFail();

        $this->assertSame('pending_review', $product->status);
        $this->assertNull($product->price_display);
    }
}
