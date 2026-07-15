<?php

namespace Tests\Feature;

use App\Mail\ProductEditReadyForAcceptance;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEditReadyForAcceptanceMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_email_lists_the_changed_fields(): void
    {
        $product = Product::factory()->create(['name' => 'Aerial Fiber Cable']);
        $trail = $product->editTrails()->create([
            'changes' => [
                'short_description' => ['old' => 'Old text', 'new' => 'New text'],
            ],
        ]);

        $mailable = new ProductEditReadyForAcceptance($product, $trail);

        $mailable->assertSeeInHtml('Aerial Fiber Cable');
        $mailable->assertSeeInHtml('Old text');
        $mailable->assertSeeInHtml('New text');
    }
}
