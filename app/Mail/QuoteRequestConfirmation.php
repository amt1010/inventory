<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\QuoteRequest;
use App\Services\EmailTemplateRenderer;
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

    private function tokens(): array
    {
        return [
            'first_name' => $this->quoteRequest->first_name,
            'quote_number' => $this->quoteRequest->quote_number,
            'product_name' => $this->quoteRequest->product?->name,
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey('quote_request_confirmation');

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens()),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('quote_request_confirmation');

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
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
