<?php

namespace App\Mail;

use App\Models\CmsKit\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the site admin when a landing page's form is submitted. */
class LandingPageEnquiryReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Enquiry $enquiry, public string $pageTitle)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New Enquiry — ' . $this->pageTitle);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.landing-pages.enquiry-received',
            with: [
                'enquiry' => $this->enquiry,
                'pageTitle' => $this->pageTitle,
                'reviewUrl' => route('cms.enquiries.index'),
            ],
        );
    }
}
