<?php

namespace Tests\Unit\Mail;

use App\Mail\SellerRejected;
use App\Models\Seller;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SellerRejectedFailedTest extends TestCase
{
    public function test_it_logs_the_seller_id_and_exception_message_on_failure(): void
    {
        Log::spy();

        $seller = new Seller();
        $seller->id = 11;

        (new SellerRejected($seller))->failed(new \RuntimeException('smtp down'));

        Log::shouldHaveReceived('error')->once()->with(
            'Failed to send seller rejection email.',
            \Mockery::on(fn (array $context) => $context['seller_id'] === 11
                && $context['exception'] === 'smtp down')
        );
    }
}
