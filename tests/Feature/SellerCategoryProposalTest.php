<?php

namespace Tests\Feature;

use App\Filament\Seller\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Category;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SellerCategoryProposalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_product_form_no_longer_offers_inline_category_creation(): void
    {
        // Category creation was moved to the dedicated "My Categories" section
        // (issue #2 reopen); the product form must only select existing ones.
        $seller = Seller::factory()->create(['status' => 'approved']);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->assertFormComponentActionDoesNotExist('category_id', 'createOption');
    }

    public function test_a_sellers_own_pending_proposal_appears_in_their_own_dropdown(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $ownProposal = Category::factory()->create([
            'name' => 'Own Draft Category',
            'status' => 'draft',
            'proposed_by_seller_id' => $seller->id,
        ]);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->assertFormFieldExists('category_id', function (\Filament\Forms\Components\Select $field) use ($ownProposal) {
                return array_key_exists($ownProposal->id, $field->getOptions());
            });
    }

    public function test_another_sellers_pending_proposal_does_not_appear_in_the_dropdown(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $otherSeller = Seller::factory()->create();
        $othersProposal = Category::factory()->create([
            'name' => 'Other Sellers Draft',
            'status' => 'draft',
            'proposed_by_seller_id' => $otherSeller->id,
        ]);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->assertFormFieldExists('category_id', function (\Filament\Forms\Components\Select $field) use ($othersProposal) {
                return ! array_key_exists($othersProposal->id, $field->getOptions());
            });
    }

    public function test_the_draft_category_note_is_visible_only_when_the_selected_category_is_still_draft(): void
    {
        // NOTE: This does not use Filament's assertFormFieldIsVisible()/
        // assertFormFieldIsHidden() helpers. Those helpers (and
        // assertFormFieldExists(), which they both call first) require the
        // component to be an instance of Filament\Forms\Components\Field.
        // Placeholder does not extend Field, so this inspects the component
        // tree directly via getComponent(), which works for any Component.
        $seller = Seller::factory()->create(['status' => 'approved']);
        $draftCategory = Category::factory()->create(['status' => 'draft', 'proposed_by_seller_id' => $seller->id]);
        $publishedCategory = Category::factory()->create(['status' => 'published']);
        $this->actingAs($seller, 'seller');

        $findNote = fn ($test) => $test->instance()->form->getComponent(
            fn (\Filament\Forms\Components\Component $component) => method_exists($component, 'getName')
                && $component->getName() === 'category_status_note',
            withHidden: true,
        );

        $test = Livewire::test(CreateProduct::class)
            ->fillForm(['category_id' => $draftCategory->id]);

        $this->assertTrue($findNote($test)->isVisible());

        $test->fillForm(['category_id' => $publishedCategory->id]);

        $this->assertFalse($findNote($test)->isVisible());
    }

    public function test_a_sellers_proposed_draft_category_is_invisible_on_the_public_catalog(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $category = Category::factory()->create([
            'slug' => 'seller-proposed-category',
            'status' => 'draft',
            'proposed_by_seller_id' => $seller->id,
        ]);

        $response = $this->get('/products');

        $response->assertOk();
        $response->assertViewHas('children', fn ($children) => ! $children->contains($category));
    }
}
