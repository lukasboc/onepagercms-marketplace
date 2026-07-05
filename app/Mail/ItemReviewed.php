<?php

namespace App\Mail;

use App\Models\ItemVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ItemReviewed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ItemVersion $version,
        public string $decision,
        public string $note,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Your %s "%s" %s was %s',
                $this->version->item->type,
                $this->version->item->name,
                $this->version->version,
                $this->decision
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.item-reviewed',
        );
    }
}
