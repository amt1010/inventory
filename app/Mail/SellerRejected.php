<?php

namespace App\Mail;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerRejected extends Mailable
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
}
