<?php

namespace App\Mail;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductListingLive extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Product $product)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your listing is now live: '.$this->product->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.product-listing-live',
            with: ['product' => $this->product],
        );
    }
}
