<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the property's owning agent/company (the CRM user) when a visitor submits a property enquiry. */
class NewLeadReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead)
    {
    }

    public function envelope(): Envelope
    {
        $propertyLabel = $this->lead->property?->getTranslation('title') ?? 'your listing';

        return new Envelope(subject: 'New Enquiry — ' . $propertyLabel);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.leads.new-lead-received',
            with: [
                'lead' => $this->lead,
                'propertyLabel' => $this->lead->property?->getTranslation('title') ?? 'your listing',
                'reviewUrl' => route('portal.crm.leads.show', $this->lead->id),
            ],
        );
    }
}
