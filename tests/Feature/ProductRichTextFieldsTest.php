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

class ProductRichTextFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_features_and_applications_render_as_html_on_the_product_page(): void
    {
        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'published',
            'features' => '<ul><li>Corrosion resistant</li><li>Low smoke</li></ul>',
            'applications' => '<ul><li>Data centres</li></ul>',
        ]);

        $response = $this->get('/products/'.$product->path());

        $response->assertOk();
        $response->assertSee('<li>Corrosion resistant</li>', false);
        $response->assertSee('<li>Low smoke</li>', false);
        $response->assertSee('<li>Data centres</li>', false);
    }

    public function test_admin_can_save_features_and_applications_as_rich_text(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $seller = Seller::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin, 'staff');

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'name' => 'Rich Product',
                'slug' => 'rich-product',
                'features' => '<ul><li>One</li><li>Two</li></ul>',
                'applications' => '<p>Broad use</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('slug', 'rich-product')->firstOrFail();

        $this->assertSame('<ul><li>One</li><li>Two</li></ul>', $product->features);
        $this->assertSame('<p>Broad use</p>', $product->applications);
    }
}
