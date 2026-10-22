<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Database-only bell notification delivered TO the portal user when they submit / resubmit
 * their KYC. The matching email is sent separately via PortalKycSubmittedMail.
 */
class PortalKycSubmittedNotification extends Notification
{
    public function __construct(public bool $isResubmission = false)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->isResubmission ? 'Updated KYC received' : 'KYC submitted',
            'message' => 'Our team is reviewing your details. We will let you know once your account is approved.',
            'url' => route('crm.app', 'profile'),
            'icon' => 'fa-file-circle-check',
            'tone' => 'teal',
        ];
    }
}
