<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterContentUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $contentType,
        public string $title,
        public string $summary,
        public string $articleUrl,
        public ?string $imageUrl,
        public string $unsubscribeUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title . ' | MW Realty');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.content-update',
            with: [
                'contentType' => $this->contentType,
                'title' => $this->title,
                'summary' => $this->summary,
                'articleUrl' => $this->articleUrl,
                'imageUrl' => $this->imageUrl,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }
}
