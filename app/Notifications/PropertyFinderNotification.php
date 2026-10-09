<?php

namespace App\Notifications;

use App\Models\PortalUser;
use Illuminate\Notifications\Notification;

/**
 * Database-only bell for Property Finder imports:
 *   'review'   → Super Admin: an account imported listings that wait for review.
 *   'approved' / 'rejected' → the account: the outcome of that review.
 */
class PropertyFinderNotification extends Notification
{
    public function __construct(public string $event, public ?PortalUser $account, public int $count, public ?string $note = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $listings = $this->count . ' Property Finder listing' . ($this->count === 1 ? '' : 's');

        return match ($this->event) {
            'review' => [
                'title' => 'Property Finder listings to review',
                'message' => ($this->account?->displayName() ?? 'An account') . " imported {$listings}. Review them before they can go on the website.",
                'url' => route('crm.app', 'integrations/property-finder/review'),
                'icon' => 'fa-file-import',
                'tone' => 'amber',
            ],
            'approved' => [
                'title' => 'Property Finder listings approved',
                'message' => "MW Realty approved {$listings}. Listings with complete permit details are live; complete the permit on the others to publish them.",
                'url' => route('crm.app', 'properties'),
                'icon' => 'fa-circle-check',
                'tone' => 'teal',
            ],
            default => [
                'title' => 'Property Finder listings rejected',
                'message' => "MW Realty rejected {$listings}" . ($this->note ? ": {$this->note}" : '.'),
                'url' => route('crm.app', 'integrations/property-finder'),
                'icon' => 'fa-circle-xmark',
                'tone' => 'rose',
            ],
        };
    }
}
