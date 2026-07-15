<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductEditTrail;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_can_have_edit_trails(): void
    {
        $product = Product::factory()->create();
        $staff = Staff::factory()->create();

        $trail = $product->editTrails()->create([
            'staff_id' => $staff->id,
            'changes' => ['name' => ['old' => 'Old Name', 'new' => 'New Name']],
        ]);

        $this->assertInstanceOf(ProductEditTrail::class, $product->editTrails->first());
        $this->assertSame(['name' => ['old' => 'Old Name', 'new' => 'New Name']], $trail->fresh()->changes);
        $this->assertTrue($trail->staff->is($staff));
    }

    public function test_latest_pending_edit_trail_returns_only_the_most_recent_unaccepted_trail(): void
    {
        $product = Product::factory()->create();

        $product->editTrails()->create(['changes' => ['name' => ['old' => 'A', 'new' => 'B']], 'accepted_at' => now()]);
        $pending = $product->editTrails()->create(['changes' => ['name' => ['old' => 'B', 'new' => 'C']]]);

        $this->assertTrue($product->latestPendingEditTrail()->is($pending));
    }

    public function test_latest_pending_edit_trail_returns_null_when_there_is_none(): void
    {
        $product = Product::factory()->create();

        $this->assertNull($product->latestPendingEditTrail());
    }
}
