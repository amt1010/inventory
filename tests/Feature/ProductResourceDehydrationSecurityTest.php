<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
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

    public function test_admin_can_set_price_via_the_create_form(): void
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
                'features' => [],
                'applications' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'test-product')->firstOrFail();

        $this->assertSame('₹1,200 – ₹1,800 per reel', $product->price_display);
        $this->assertSame('pending_review', $product->status, 'Publishing must never happen as a side effect of the create form -- only via Product::publish().');
    }

    public function test_admin_cannot_set_status_to_published_via_the_create_form(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $seller = Seller::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin, 'staff');

        // Even an admin -- who is allowed to set price_display -- must not
        // be able to push a product straight to "published" through the
        // form. That would bypass Product::publish()'s guard requiring a
        // non-blank price_display. Only the table's `publish` action (which
        // calls $record->publish()) may transition a product to published.
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'name' => 'Sneaky Product',
                'slug' => 'sneaky-product',
                'price_display' => '₹1,200 – ₹1,800 per reel',
                'status' => 'published',
                'features' => [],
                'applications' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['status']);

        $this->assertNull(Product::where('slug', 'sneaky-product')->first());
    }

    public function test_admin_cannot_set_status_to_published_via_the_edit_form(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $product = Product::factory()->create([
            'status' => 'pending_review',
            'price_display' => '₹1,200 – ₹1,800 per reel',
        ]);

        $this->actingAs($admin, 'staff');

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['status' => 'published'])
            ->call('save')
            ->assertHasFormErrors(['status']);

        $this->assertSame('pending_review', $product->fresh()->status);
    }

    public function test_editing_an_already_published_product_does_not_error_when_status_is_left_unchanged(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $product = Product::factory()->create([
            'status' => 'published',
            'price_display' => '₹1,200 – ₹1,800 per reel',
        ]);

        $this->actingAs($admin, 'staff');

        // The edit form must still pre-fill and accept the current
        // "published" value for a product that is already published --
        // it just can't be *chosen* as a new value from another status.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormSet(['status' => 'published'])
            ->fillForm(['short_description' => 'Updated copy', 'status' => 'published'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('published', $product->fresh()->status);
        $this->assertSame('Updated copy', $product->fresh()->short_description);
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
        // an attacker manipulating the wire:model payload would do. The
        // "status" validation rule rejects "published" outright for a role
        // that has no legitimate route to it.
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
            ->assertHasFormErrors(['status']);

        $this->assertNull(Product::where('slug', 'editor-product')->first());
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
