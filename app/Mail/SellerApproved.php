<?php

namespace App\Mail;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Seller $seller)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your seller account has been approved');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.seller-approved', with: ['seller' => $this->seller]);
    }
}
