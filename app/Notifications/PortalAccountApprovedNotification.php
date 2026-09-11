<?php

namespace App\Notifications;

use App\Models\PortalUser;
use Illuminate\Notifications\Notification;

/**
 * Database-only bell notification for the agent/company when their account is
 * approved. The email for this event is sent separately via PortalAccountApproved.
 */
class PortalAccountApprovedNotification extends Notification
{
    public function __construct(public PortalUser $portalUser)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Account approved',
            'message' => "You're approved! You can now list properties on the portal.",
            'url' => route('portal.dashboard'),
            'icon' => 'fa-check-circle',
            'tone' => 'green',
        ];
    }
}
