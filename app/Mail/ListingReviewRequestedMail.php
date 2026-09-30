<?php

namespace App\Mail;

use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the site's notification address when an agency / agent submits a listing's DLD permit
 * for review (see ListingComplianceService). The bell notification is ListingReviewRequestedNotification.
 */
class ListingReviewRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Property $property, public bool $isResubmission = false)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->isResubmission ? 'Listing resubmitted for approval — ' : 'Listing waiting for approval — ') . $this->title());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal.listing-review-requested',
            with: [
                'property' => $this->property,
                'title' => $this->title(),
                'accountName' => $this->property->owner?->displayName() ?? 'MW Realty',
                'agentName' => $this->property->agent?->name,
                'isResubmission' => $this->isResubmission,
                'reviewUrl' => route('portal.listing-approvals.show', $this->property->id),
            ],
        );
    }

    private function title(): string
    {
        return $this->property->getTranslation('title') ?: (string) $this->property->reference_no;
    }
}
