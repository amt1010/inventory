<?php

namespace App\Mail;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class SellerActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Seller $seller)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Activate your seller account');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seller-activation',
            with: [
                'seller' => $this->seller,
                'activationUrl' => URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $this->seller->id]),
            ],
        );
    }
}
