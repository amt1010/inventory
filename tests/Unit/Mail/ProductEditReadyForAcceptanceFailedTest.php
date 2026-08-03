<?php

namespace Tests\Unit\Mail;

use App\Mail\ProductEditReadyForAcceptance;
use App\Models\Product;
use App\Models\ProductEditTrail;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProductEditReadyForAcceptanceFailedTest extends TestCase
{
    public function test_it_logs_the_product_id_and_exception_message_on_failure(): void
    {
        Log::spy();

        $product = new Product();
        $product->id = 21;

        (new ProductEditReadyForAcceptance($product, new ProductEditTrail()))
            ->failed(new \RuntimeException('smtp down'));

        Log::shouldHaveReceived('error')->once()->with(
            'Failed to send product edit acceptance email.',
            \Mockery::on(fn (array $context) => $context['product_id'] === 21
                && $context['exception'] === 'smtp down')
        );
    }
}
