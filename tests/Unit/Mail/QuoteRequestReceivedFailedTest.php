<?php

namespace Tests\Unit\Mail;

use App\Mail\QuoteRequestReceived;
use App\Models\QuoteRequest;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class QuoteRequestReceivedFailedTest extends TestCase
{
    public function test_it_logs_the_quote_request_id_and_exception_message_on_failure(): void
    {
        Log::spy();

        $quoteRequest = new QuoteRequest();
        $quoteRequest->id = 42;

        (new QuoteRequestReceived($quoteRequest))->failed(new \RuntimeException('smtp down'));

        Log::shouldHaveReceived('error')->once()->with(
            'Failed to send quote request notification email.',
            \Mockery::on(fn (array $context) => $context['quote_request_id'] === 42
                && $context['exception'] === 'smtp down')
        );
    }
}
