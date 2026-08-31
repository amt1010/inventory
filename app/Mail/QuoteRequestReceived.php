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

class QuoteRequestReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quoteRequest)
    {
    }

    private function tokens(): array
    {
        $product = $this->quoteRequest->product;

        return [
            'reason' => $this->quoteRequest->reason,
            'full_name' => $this->quoteRequest->fullName(),
            'email' => $this->quoteRequest->email,
            'phone' => $this->quoteRequest->phone,
            'company' => $this->quoteRequest->company,
            'product_name' => $product?->name,
            'product_url' => $product ? url('/products/'.$product->path()) : null,
            'product_thumbnail_html' => $product
                ? view('components.product-thumbnail', [
                    'path' => optional($product->primaryImage())->path,
                    'alt' => $product->name,
                ])->render()
                : null,
            'message_text' => $this->quoteRequest->message,
            'admin_url' => url('/admin/quote-requests/'.$this->quoteRequest->id),
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey('quote_request_received');

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens()),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('quote_request_received');

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
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
