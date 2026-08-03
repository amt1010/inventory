<?php

namespace Tests\Unit\Mail;

use App\Mail\ProductListingLive;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProductListingLiveFailedTest extends TestCase
{
    public function test_it_logs_the_product_id_and_exception_message_on_failure(): void
    {
        Log::spy();

        $product = new Product();
        $product->id = 13;

        (new ProductListingLive($product))->failed(new \RuntimeException('smtp down'));

        Log::shouldHaveReceived('error')->once()->with(
            'Failed to send product listing live email.',
            \Mockery::on(fn (array $context) => $context['product_id'] === 13
                && $context['exception'] === 'smtp down')
        );
    }
}
