<?php

namespace App\Mail;

use App\Models\CmsKit\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the site admin when the standalone /contact page form is submitted. */
class ContactEnquiryReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Enquiry $enquiry)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New Contact Us Enquiry');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact.enquiry-received',
            with: [
                'enquiry' => $this->enquiry,
                'reviewUrl' => route('cms.enquiries.index'),
            ],
        );
    }
}
