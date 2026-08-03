<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QuoteRequestConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quoteRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Quote Request '.$this->quoteRequest->quote_number.' Has Been Received',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quote-request-confirmation',
            with: ['quoteRequest' => $this->quoteRequest],
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send quote request confirmation email.', [
            'quote_request_id' => $this->quoteRequest->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
