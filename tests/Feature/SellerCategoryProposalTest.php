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

    public function test_a_seller_can_propose_a_new_top_level_category(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->callFormComponentAction('category_id', 'createOption', data: [
                'name' => 'Submarine Cable',
                'parent_id' => null,
            ]);

        $category = Category::where('name', 'Submarine Cable')->firstOrFail();

        $this->assertNull($category->parent_id);
        $this->assertSame('draft', $category->status);
        $this->assertSame($seller->id, $category->proposed_by_seller_id);
    }

    public function test_a_seller_can_propose_a_new_sub_category_under_an_existing_parent(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $parent = Category::factory()->create(['name' => 'Fiber Optic Cable', 'status' => 'published']);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->callFormComponentAction('category_id', 'createOption', data: [
                'name' => 'Submarine Cable',
                'parent_id' => $parent->id,
            ]);

        $category = Category::where('name', 'Submarine Cable')->firstOrFail();

        $this->assertSame($parent->id, $category->parent_id);
        $this->assertSame('draft', $category->status);
        $this->assertSame($seller->id, $category->proposed_by_seller_id);
    }

    public function test_proposing_a_category_immediately_selects_it_on_the_product_form(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->callFormComponentAction('category_id', 'createOption', data: [
                'name' => 'Submarine Cable',
                'parent_id' => null,
            ]);

        $category = Category::where('name', 'Submarine Cable')->firstOrFail();

        Livewire::test(CreateProduct::class)
            ->callFormComponentAction('category_id', 'createOption', data: [
                'name' => 'Submarine Cable Two',
                'parent_id' => null,
            ])
            ->assertFormSet(['category_id' => Category::where('name', 'Submarine Cable Two')->firstOrFail()->id]);
    }

    public function test_proposing_a_category_with_a_name_that_collides_under_the_same_parent_is_rejected(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $parent = Category::factory()->create(['status' => 'published']);
        Category::factory()->create(['parent_id' => $parent->id, 'name' => 'OPGW', 'slug' => 'opgw', 'status' => 'published']);
        $this->actingAs($seller, 'seller');

        Livewire::test(CreateProduct::class)
            ->callFormComponentAction('category_id', 'createOption', data: [
                'name' => 'OPGW',
                'parent_id' => $parent->id,
            ])
            ->assertHasFormComponentActionErrors(['name']);

        $this->assertSame(1, Category::where('parent_id', $parent->id)->where('slug', 'opgw')->count());
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
        // component to be an instance of Filament\Forms\Components\Field
        // (see vendor/filament/forms/src/Testing/TestsForms.php:248, which
        // asserts `Field::class` before ever checking visibility).
        // Placeholder deliberately does NOT extend Field — it extends the
        // base Component class directly (vendor/filament/forms/src/
        // Components/Placeholder.php) — so it can never satisfy that
        // assertion, regardless of its actual visibility state. This was
        // confirmed by running the field-helper version of this test and
        // observing it fail with "Failed asserting that null is an
        // instance of class Filament\Forms\Components\Field" even before
        // any visibility check ran.
        //
        // Placeholder is nonetheless the correct component for a static,
        // read-only note (it matches the existing `status_display` and
        // `rejection_reason` Placeholders already used in this resource),
        // so instead of changing the field type, this test inspects the
        // component tree directly via getComponent(), which works for any
        // Component subclass.
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
