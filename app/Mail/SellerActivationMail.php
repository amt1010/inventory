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
use Illuminate\Support\Facades\URL;

class SellerActivationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Seller $seller)
    {
    }

    private function templateKey(): string
    {
        return $this->seller->created_by === 'admin'
            ? 'seller_activation_admin_created'
            : 'seller_activation_self_registered';
    }

    private function tokens(): array
    {
        return [
            'company_name' => $this->seller->company_name,
            'activation_url' => URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $this->seller->id]),
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey($this->templateKey());

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens()),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey($this->templateKey());

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send seller activation email.', [
            'seller_id' => $this->seller->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
