<?php

namespace App\Notifications;

use App\Models\PortalUser;
use Illuminate\Notifications\Notification;

/**
 * Database-only bell notification delivered TO the portal user (agent/company) when admin
 * flags one of their KYC documents as rejected. The matching email is sent separately via
 * PortalDocumentFlaggedMail — this only feeds the bell.
 */
class PortalDocumentFlaggedNotification extends Notification
{
    public function __construct(public string $field)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $label = PortalUser::documentLabel($this->field);

        return [
            'title' => 'Document needs your attention',
            'message' => $label . ' needs to be updated. Please check your email for details.',
            'url' => route('portal.profile.edit'),
            'icon' => 'fa-flag',
            'tone' => 'red',
        ];
    }
}
