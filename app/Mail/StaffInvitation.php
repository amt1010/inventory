<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Staff;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StaffInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Staff $staff, public string $temporaryPassword, public string $loginUrl)
    {
    }

    private function tokens(): array
    {
        return [
            'staff_name' => $this->staff->name,
            'login_url' => $this->loginUrl,
            'temporary_password' => $this->temporaryPassword,
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey('staff_invitation');

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens(), escapeHtml: false),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('staff_invitation');

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send staff invitation email.', [
            'staff_id' => $this->staff->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
