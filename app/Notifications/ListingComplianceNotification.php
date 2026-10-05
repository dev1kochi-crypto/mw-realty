<?php

namespace App\Notifications;

use App\Models\Property;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Database-only bell notification TO the listing's agency / agent about its DLD compliance review:
 * approved, changes requested, permit expiring soon, or permit expired (see ListingComplianceService).
 */
class ListingComplianceNotification extends Notification
{
    public function __construct(public Property $property, public string $event, public ?string $note = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $title = $this->property->getTranslation('title') ?: $this->property->reference_no;
        $prefix = $this->property->segment === Property::SEGMENT_COMMERCIAL ? 'portal.commercial' : 'portal.properties';

        [$heading, $message, $icon, $tone] = match ($this->event) {
            'submitted' => ['Listing sent for approval', "{$title} was sent to MW Realty for DLD permit approval. It goes live once approved.", 'fa-paper-plane', 'teal'],
            'approved' => ['Permit verified', "{$title}: MW Realty checked the permit — it can be live on the website.", 'fa-circle-check', 'teal'],
            'changes_requested' => ['Listing taken down', "{$title}: " . Str::limit((string) $this->note, 160), 'fa-triangle-exclamation', 'amber'],
            'expiring' => ['DLD permit expiring soon', "The permit for {$title} expires on " . $this->property->permit_expires_at?->format('d M Y') . '. Renew it with DLD and update the listing.', 'fa-hourglass-half', 'amber'],
            default => ['DLD permit expired', "{$title} was taken off the website because its permit expired. Add the renewed permit to re-list it.", 'fa-ban', 'red'],
        };

        return [
            'title' => $heading,
            'message' => $message,
            'url' => route($prefix . '.edit', $this->property->id),
            'icon' => $icon,
            'tone' => $tone,
        ];
    }
}
