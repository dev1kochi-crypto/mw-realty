<?php

namespace App\Notifications;

use App\Models\PortalUser;
use Illuminate\Notifications\Notification;

/**
 * Database-only bell notification for the agent/company when their account is
 * rejected. The email for this event is sent separately via PortalAccountRejected.
 */
class PortalAccountRejectedNotification extends Notification
{
    public function __construct(public PortalUser $portalUser, public ?string $reason = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Account rejected',
            'message' => $this->reason ? 'Reason: ' . $this->reason : 'Your account application was rejected.',
            'url' => route('portal.profile.edit'),
            'icon' => 'fa-times-circle',
            'tone' => 'red',
        ];
    }
}
