<?php

namespace Tests\Feature;

use App\Models\NavItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavItemModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_nav_item_has_ordered_children(): void
    {
        $parent = NavItem::factory()->create(['location' => 'header']);
        $second = NavItem::factory()->create(['parent_id' => $parent->id, 'sort_order' => 2]);
        $first = NavItem::factory()->create(['parent_id' => $parent->id, 'sort_order' => 1]);

        $this->assertSame([$first->id, $second->id], $parent->children->pluck('id')->all());
    }
}
