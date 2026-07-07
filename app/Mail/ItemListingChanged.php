<?php

namespace App\Mail;

use App\Models\Item;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ItemListingChanged extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Item $item,
        public string $decision,
        public string $note,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Your %s "%s" was %s',
                $this->item->type,
                $this->item->name,
                $this->decision
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.item-listing-changed',
        );
    }
}
