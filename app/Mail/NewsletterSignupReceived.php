<?php

namespace App\Mail;

use App\Models\CmsKit\NewsletterSignup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the site admin when a visitor subscribes via the footer newsletter form. */
class NewsletterSignupReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public NewsletterSignup $signup)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New Newsletter Signup');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.signup-received',
            with: [
                'signup' => $this->signup,
                'reviewUrl' => route('cms.newsletter-signups.index'),
            ],
        );
    }
}
