<?php

namespace App\Mail;

use App\Models\PortalUser;
use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a listing's agency / agent about its DLD permit review: submitted, approved, changes
 * requested, permit expiring soon, permit expired. The bell notification is ListingComplianceNotification.
 */
class ListingComplianceMail extends Mailable
{
    use Queueable, SerializesModels;

    public const SUBJECTS = [
        'submitted' => 'Listing submitted for approval',
        'approved' => 'Permit verified for your listing',
        'changes_requested' => 'Your listing was taken down',
        'expiring' => 'DLD permit expiring soon',
        'expired' => 'DLD permit expired — listing taken offline',
    ];

    public function __construct(public PortalUser $recipient, public Property $property, public string $event, public ?string $note = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: (self::SUBJECTS[$this->event] ?? 'Listing update') . ' — ' . $this->title());
    }

    public function content(): Content
    {
        $prefix = $this->property->segment === Property::SEGMENT_COMMERCIAL ? 'portal.commercial' : 'portal.properties';

        return new Content(
            view: 'emails.portal.listing-compliance',
            with: [
                'recipient' => $this->recipient,
                'property' => $this->property,
                'title' => $this->title(),
                'event' => $this->event,
                'note' => $this->note,
                'listingUrl' => route($prefix . '.edit', $this->property->id),
            ],
        );
    }

    private function title(): string
    {
        return $this->property->getTranslation('title') ?: (string) $this->property->reference_no;
    }
}
