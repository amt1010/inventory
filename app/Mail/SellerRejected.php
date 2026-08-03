<?php

namespace App\Mail;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SellerRejected extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Seller $seller)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Update on your seller application');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.seller-rejected', with: ['seller' => $this->seller]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send seller rejection email.', [
            'seller_id' => $this->seller->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
