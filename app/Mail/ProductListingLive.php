<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Product;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProductListingLive extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Product $product)
    {
    }

    private function tokens(): array
    {
        return [
            'product_name' => $this->product->name,
            'product_url' => url('/products/'.$this->product->path()),
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey('product_listing_live');

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens()),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('product_listing_live');

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send product listing live email.', [
            'product_id' => $this->product->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
