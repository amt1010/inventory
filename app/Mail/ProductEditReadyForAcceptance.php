<?php

namespace App\Mail;

use App\Models\Product;
use App\Models\ProductEditTrail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProductEditReadyForAcceptance extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Product $product, public ProductEditTrail $editTrail)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Review changes to your listing: '.$this->product->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.product-edit-ready-for-acceptance',
            with: [
                'product' => $this->product,
                'editTrail' => $this->editTrail,
            ],
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send product edit acceptance email.', [
            'product_id' => $this->product->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
