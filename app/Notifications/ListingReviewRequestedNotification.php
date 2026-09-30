<?php

namespace App\Notifications;

use App\Models\Property;
use Illuminate\Notifications\Notification;

/** Database-only bell notification for Super Admin: a listing was submitted for permit review. */
class ListingReviewRequestedNotification extends Notification
{
    public function __construct(public Property $property, public bool $isResubmission = false)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $account = $this->property->owner?->displayName() ?? 'An account';

        return [
            'title' => $this->isResubmission ? 'Listing resubmitted for approval' : 'Listing waiting for approval',
            'message' => $account . ($this->isResubmission ? ' resubmitted ' : ' submitted ') . ($this->property->getTranslation('title') ?: $this->property->reference_no) . ' (permit ' . $this->property->permit_number . ') for review.',
            'url' => route('portal.listing-approvals.show', $this->property->id),
            'icon' => 'fa-file-shield',
            'tone' => $this->isResubmission ? 'amber' : 'teal',
        ];
    }
}
