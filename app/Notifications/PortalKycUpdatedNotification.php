<?php

namespace App\Notifications;

use App\Models\PortalUser;
use Illuminate\Notifications\Notification;

/**
 * Database-only bell notification for every superadmin when a still-pending
 * (not yet approved) agent/company uploads or replaces a KYC document. The
 * matching email is sent separately via PortalKycUpdatedMail — this only feeds
 * the bell, same split as PortalRegistrationNotification/PortalAccountRegistered.
 */
class PortalKycUpdatedNotification extends Notification
{
    public function __construct(public PortalUser $portalUser, public string $field)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $displayName = $this->portalUser->displayName();
        $label = PortalUser::documentLabel($this->field);

        return [
            'title' => 'KYC document updated',
            'message' => $displayName . ' updated their ' . $label . '.',
            'url' => route('cms.portal-accounts.show', ['id' => $this->portalUser->id, 'type' => $this->portalUser->type]),
            'icon' => 'fa-file-upload',
            'tone' => 'teal',
        ];
    }
}
