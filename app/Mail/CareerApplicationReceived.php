<?php

namespace App\Mail;

use App\Models\CmsKit\CareerCandidate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the site admin when someone applies through the public /careers pages. */
class CareerApplicationReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CareerCandidate $candidate)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New Career Application' . ($this->candidate->apply_for ? " — {$this->candidate->apply_for}" : ''));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.careers.application-received',
            with: [
                'candidate' => $this->candidate,
                'reviewUrl' => route('cms.careers.candidates.show', $this->candidate->id),
            ],
        );
    }
}
