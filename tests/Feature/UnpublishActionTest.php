<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnpublishActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_an_admin_can_unpublish_a_product_from_the_list(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $product = Product::factory()->create(['status' => 'published', 'price_display' => '₹1,000']);

        Livewire::test(ListProducts::class)
            ->callTableAction('unpublish', $product);

        $this->assertSame('pending_review', $product->fresh()->status);
    }

    public function test_the_product_unpublish_action_is_hidden_once_already_unpublished(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $product = Product::factory()->create(['status' => 'pending_review']);

        Livewire::test(ListProducts::class)
            ->assertTableActionHidden('unpublish', $product);
    }

    public function test_a_content_editor_cannot_unpublish_a_product(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');
        $this->actingAs($editor, 'staff');

        $product = Product::factory()->create(['status' => 'published', 'price_display' => '₹1,000']);

        Livewire::test(ListProducts::class)
            ->assertTableActionHidden('unpublish', $product);
    }

    public function test_an_admin_can_unpublish_a_category_from_the_list(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $category = Category::factory()->create(['status' => 'published']);

        Livewire::test(ListCategories::class)
            ->callTableAction('unpublish', $category);

        $this->assertSame('draft', $category->fresh()->status);
    }

    public function test_a_content_editor_can_unpublish_a_category(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');
        $this->actingAs($editor, 'staff');

        $category = Category::factory()->create(['status' => 'published']);

        Livewire::test(ListCategories::class)
            ->callTableAction('unpublish', $category);

        $this->assertSame('draft', $category->fresh()->status);
    }

    public function test_an_admin_can_unpublish_a_page_from_the_list(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $page = Page::factory()->create(['status' => 'published']);

        Livewire::test(ListPages::class)
            ->callTableAction('unpublish', $page);

        $this->assertSame('draft', $page->fresh()->status);
    }
}
