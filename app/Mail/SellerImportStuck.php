<?php

namespace App\Mail;

use Filament\Actions\Imports\Models\Import;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SellerImportStuck extends Mailable
{
    public function __construct(public Import $import)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Seller import appears stuck: '.$this->import->file_name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.seller-import-stuck', with: [
            'import' => $this->import,
        ]);
    }
}
