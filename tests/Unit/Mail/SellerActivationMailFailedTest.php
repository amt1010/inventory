<?php

namespace Tests\Unit\Mail;

use App\Mail\SellerActivationMail;
use App\Models\Seller;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SellerActivationMailFailedTest extends TestCase
{
    public function test_it_logs_the_seller_id_and_exception_message_on_failure(): void
    {
        Log::spy();

        $seller = new Seller();
        $seller->id = 7;

        (new SellerActivationMail($seller))->failed(new \RuntimeException('smtp down'));

        Log::shouldHaveReceived('error')->once()->with(
            'Failed to send seller activation email.',
            \Mockery::on(fn (array $context) => $context['seller_id'] === 7
                && $context['exception'] === 'smtp down')
        );
    }
}
