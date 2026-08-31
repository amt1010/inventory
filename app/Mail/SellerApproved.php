<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Seller;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SellerApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Seller $seller, public ?string $activationUrl = null)
    {
    }

    private function tokens(): array
    {
        return [
            'company_name' => $this->seller->company_name,
            'activation_url' => $this->activationUrl,
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey('seller_approved');

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens(), escapeHtml: false),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('seller_approved');

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send seller approval email.', [
            'seller_id' => $this->seller->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
