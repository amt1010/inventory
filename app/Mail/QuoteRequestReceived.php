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

class QuoteRequestReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quoteRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Quote Request from '.$this->quoteRequest->fullName(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quote-request-received',
            with: ['quoteRequest' => $this->quoteRequest],
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send quote request notification email.', [
            'quote_request_id' => $this->quoteRequest->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
