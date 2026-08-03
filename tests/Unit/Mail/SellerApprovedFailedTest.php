<?php

namespace Tests\Unit\Mail;

use App\Mail\SellerApproved;
use App\Models\Seller;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SellerApprovedFailedTest extends TestCase
{
    public function test_it_logs_the_seller_id_and_exception_message_on_failure(): void
    {
        Log::spy();

        $seller = new Seller();
        $seller->id = 9;

        (new SellerApproved($seller))->failed(new \RuntimeException('smtp down'));

        Log::shouldHaveReceived('error')->once()->with(
            'Failed to send seller approval email.',
            \Mockery::on(fn (array $context) => $context['seller_id'] === 9
                && $context['exception'] === 'smtp down')
        );
    }
}
