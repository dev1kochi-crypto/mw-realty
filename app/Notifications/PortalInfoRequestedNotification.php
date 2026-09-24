<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Database-only bell notification delivered TO the portal user (agent/company) when
 * admin asks for some general additional information that isn't a document (e.g. a
 * missing TRN number, a clearer copy of something). See PortalUserController::requestInfo().
 * The matching email is sent separately via PortalInfoRequestedMail — this only feeds the bell.
 */
class PortalInfoRequestedNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Admin has requested more information',
            'message' => 'Please check your email for the requested profile or document updates.',
            'url' => route('portal.profile.edit'),
            'icon' => 'fa-comment-medical',
            'tone' => 'amber',
        ];
    }
}
