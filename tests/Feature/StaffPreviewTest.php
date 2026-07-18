<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): Staff
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_staff_can_preview_an_unpublished_product(): void
    {
        $category = Category::factory()->create(['status' => 'draft']);
        $product = Product::factory()->create([
            'name' => 'Unreleased Cable',
            'category_id' => $category->id,
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($this->admin(), 'staff')
            ->get(route('staff.preview.product', $product));

        $response->assertOk();
        $response->assertSee('Unreleased Cable');
        $response->assertSee('Staff preview');
    }

    public function test_staff_can_preview_an_unpublished_category(): void
    {
        $category = Category::factory()->create(['name' => 'Hidden Category', 'status' => 'draft']);

        $response = $this->actingAs($this->admin(), 'staff')
            ->get(route('staff.preview.category', $category));

        $response->assertOk();
        $response->assertSee('Hidden Category');
        $response->assertSee('Staff preview');
    }

    public function test_a_guest_cannot_access_the_product_preview(): void
    {
        $product = Product::factory()->create(['status' => 'pending_review']);

        $response = $this->get(route('staff.preview.product', $product));

        $response->assertRedirect();
        $this->assertGuest('staff');
    }

    public function test_the_products_list_links_to_preview_always_and_live_only_when_published(): void
    {
        $publishedCategory = Category::factory()->create(['status' => 'published']);
        $published = Product::factory()->create([
            'category_id' => $publishedCategory->id,
            'status' => 'published',
            'price_display' => '₹100',
        ]);
        $draft = Product::factory()->create(['status' => 'pending_review']);

        $this->actingAs($this->admin(), 'staff');

        Livewire::test(ListProducts::class)
            ->assertTableActionExists('preview')
            ->assertTableActionVisible('preview', $draft)
            ->assertTableActionVisible('viewLive', $published)
            ->assertTableActionHidden('viewLive', $draft);
    }
}
