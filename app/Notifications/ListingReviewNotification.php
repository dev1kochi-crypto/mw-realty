<?php

namespace App\Notifications;

use App\Models\Property;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Database-only bell notification TO the listing's agency / agent about Listing Review
 * (App\Services\ListingReview\ListingReviewService): waiting for review, approved, rejected.
 */
class ListingReviewNotification extends Notification
{
    public function __construct(public Property $property, public string $event, public ?string $reason = null)
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
            'held' => ['Listing waiting for review', "{$title} is waiting for MW Realty's review (the unit is already listed by another agency, or a detail needs checking). It goes on the website once approved.", 'fa-magnifying-glass', 'amber'],
            'rejected' => ['Listing rejected', "{$title}: " . Str::limit((string) $this->reason, 160) . ' Fix it and click Update to send it for review again.', 'fa-ban', 'red'],
            default => ['Listing approved', "MW Realty reviewed {$title} — it can be on the website now.", 'fa-circle-check', 'teal'],
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
