<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Services\CategoryChainResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryChainResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_parent_only_row_creates_one_draft_category(): void
    {
        $result = (new CategoryChainResolver())->resolve(['parent_name' => 'Plastic']);

        $this->assertSame('Plastic', $result->name);
        $this->assertNull($result->parent_id);
        $this->assertSame('draft', $result->status);
        $this->assertSame('admin_bulk_upload', $result->created_by);
    }

    public function test_a_three_level_row_creates_the_full_chain(): void
    {
        $result = (new CategoryChainResolver())->resolve([
            'parent_name' => 'Metal',
            'sub1_name' => 'Hardware-Screw',
            'sub2_name' => 'Washer/Nut/Bolts',
        ]);

        $this->assertSame('Washer/Nut/Bolts', $result->name);
        $this->assertSame('Hardware-Screw', $result->parent->name);
        $this->assertSame('Metal', $result->parent->parent->name);
        $this->assertNull($result->parent->parent->parent_id);
    }

    public function test_an_existing_category_is_reused_unchanged(): void
    {
        $existing = Category::factory()->create([
            'name' => 'Plastic',
            'parent_id' => null,
            'status' => 'published',
            'description' => 'Original description',
        ]);

        $result = (new CategoryChainResolver())->resolve([
            'parent_name' => 'Plastic',
            'parent_description' => 'A different description from the sheet',
        ]);

        $this->assertTrue($result->is($existing));
        $this->assertSame('published', $result->fresh()->status);
        $this->assertSame('Original description', $result->fresh()->description);
    }

    public function test_two_different_parents_can_each_have_a_child_of_the_same_name(): void
    {
        $metalScrews = (new CategoryChainResolver())->resolve([
            'parent_name' => 'Metal',
            'sub1_name' => 'Screws',
        ]);
        $plasticScrews = (new CategoryChainResolver())->resolve([
            'parent_name' => 'Plastic',
            'sub1_name' => 'Screws',
        ]);

        $this->assertNotSame($metalScrews->id, $plasticScrews->id);
        $this->assertSame('Screws', $metalScrews->name);
        $this->assertSame('Screws', $plasticScrews->name);
    }

    public function test_a_blank_name_at_an_implied_level_becomes_the_placeholder(): void
    {
        $result = (new CategoryChainResolver())->resolve([
            'parent_name' => '',
            'sub1_name' => 'Cables and wires',
        ]);

        $this->assertSame('Cables and wires', $result->name);
        $this->assertSame(Category::PLACEHOLDER, $result->parent->name);
    }

    public function test_repeated_calls_reuse_the_same_chain_instead_of_duplicating_it(): void
    {
        (new CategoryChainResolver())->resolve(['parent_name' => 'Metal', 'sub1_name' => 'Screws']);
        (new CategoryChainResolver())->resolve(['parent_name' => 'Metal', 'sub1_name' => 'Screws']);

        $this->assertSame(1, Category::where('name', 'Metal')->count());
        $this->assertSame(1, Category::where('name', 'Screws')->count());
    }

    public function test_resolving_a_reversed_chain_never_reparents_an_existing_category(): void
    {
        // Row 1: "Electronics" > "Consumer Electronics".
        $consumerElectronics = (new CategoryChainResolver())->resolve([
            'parent_name' => 'Electronics',
            'sub1_name' => 'Consumer Electronics',
        ]);

        // Row 2 names the same two categories in the opposite order. The
        // resolver must never point an *existing* category's parent_id back
        // at one of its own descendants -- it can only create new rows or
        // reuse a category already sitting at that exact parent slot.
        (new CategoryChainResolver())->resolve([
            'parent_name' => 'Consumer Electronics',
            'sub1_name' => 'Electronics',
        ]);

        $this->assertSame(
            'Electronics',
            $consumerElectronics->fresh()->parent->name,
            'The original "Electronics" > "Consumer Electronics" chain must be untouched by the reversed row.'
        );
    }

    public function test_slugs_are_deduplicated_among_siblings(): void
    {
        Category::factory()->create(['parent_id' => null, 'name' => 'Existing Metal', 'slug' => 'metal']);

        $result = (new CategoryChainResolver())->resolve(['parent_name' => 'Metal']);

        $this->assertSame('metal-2', $result->slug);
    }
}
