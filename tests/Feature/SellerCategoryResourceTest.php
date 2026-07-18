<?php

namespace Tests\Feature;

use App\Filament\Seller\Resources\CategoryResource;
use App\Filament\Seller\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Seller\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Category;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SellerCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seller_can_create_a_top_level_category_as_a_draft_proposal(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Submarine Cable',
                'parent_id' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::where('name', 'Submarine Cable')->firstOrFail();

        $this->assertNull($category->parent_id);
        $this->assertSame('submarine-cable', $category->slug);
        $this->assertSame('draft', $category->status);
        $this->assertSame($seller->id, $category->proposed_by_seller_id);
    }

    public function test_a_seller_can_create_a_sub_category_under_any_existing_category(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $parent = Category::factory()->create(['name' => 'Fibre Optic', 'status' => 'published']);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Armoured Distribution',
                'parent_id' => $parent->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::where('name', 'Armoured Distribution')->firstOrFail();

        $this->assertSame($parent->id, $category->parent_id);
        $this->assertSame('draft', $category->status);
        $this->assertSame($seller->id, $category->proposed_by_seller_id);
    }

    public function test_the_list_shows_only_the_sellers_own_proposals(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $otherSeller = Seller::factory()->create();

        Category::factory()->create(['name' => 'My Proposal', 'status' => 'draft', 'proposed_by_seller_id' => $seller->id]);
        Category::factory()->create(['name' => 'Another Sellers Proposal', 'status' => 'draft', 'proposed_by_seller_id' => $otherSeller->id]);
        Category::factory()->create(['name' => 'Admin Published Category', 'status' => 'published', 'proposed_by_seller_id' => null]);

        $this->actingAs($seller, 'seller');

        Livewire::test(ListCategories::class)
            ->assertSee('My Proposal')
            ->assertDontSee('Another Sellers Proposal')
            ->assertDontSee('Admin Published Category');
    }

    public function test_a_seller_can_edit_their_own_draft_proposal(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $category = Category::factory()->create(['status' => 'draft', 'proposed_by_seller_id' => $seller->id]);
        $this->actingAs($seller, 'seller');

        $this->assertTrue(CategoryResource::canEdit($category));
        $this->assertTrue(CategoryResource::canDelete($category));
    }

    public function test_a_seller_cannot_edit_a_proposal_once_it_is_published(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $category = Category::factory()->create(['status' => 'published', 'proposed_by_seller_id' => $seller->id]);
        $this->actingAs($seller, 'seller');

        $this->assertFalse(CategoryResource::canEdit($category));
        $this->assertFalse(CategoryResource::canDelete($category));
    }

    public function test_a_seller_cannot_edit_another_sellers_proposal(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $otherSeller = Seller::factory()->create();
        $category = Category::factory()->create(['status' => 'draft', 'proposed_by_seller_id' => $otherSeller->id]);
        $this->actingAs($seller, 'seller');

        $this->assertFalse(CategoryResource::canEdit($category));
        $this->assertFalse(CategoryResource::canView($category));
    }

    public function test_a_seller_proposed_category_is_invisible_on_the_public_catalog(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Hidden Until Reviewed', 'parent_id' => null])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::where('name', 'Hidden Until Reviewed')->firstOrFail();

        $response = $this->get('/products');
        $response->assertOk();
        $response->assertViewHas('children', fn ($children) => ! $children->contains($category));
    }
}
