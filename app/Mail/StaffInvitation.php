<?php

namespace App\Mail;

use App\Models\Staff;
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

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your admin panel login');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.staff-invitation', with: [
            'staff' => $this->staff,
            'temporaryPassword' => $this->temporaryPassword,
            'loginUrl' => $this->loginUrl,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send staff invitation email.', [
            'staff_id' => $this->staff->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
