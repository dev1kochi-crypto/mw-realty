<?php

namespace App\Notifications;

use App\Models\PortalUser;
use Illuminate\Notifications\Notification;

/**
 * Database-only bell notification for Super Admin when an agent/company
 * registers (or resubmits after rejection). The actual email for this event
 * is sent separately via PortalAccountRegistered — this only feeds the bell.
 */
class PortalRegistrationNotification extends Notification
{
    public function __construct(public PortalUser $portalUser, public bool $isResubmission = false)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $displayName = $this->portalUser->type === 'company'
            ? ($this->portalUser->company_name ?: $this->portalUser->name)
            : $this->portalUser->name;

        return [
            'title' => $this->isResubmission ? 'Account resubmitted for review' : 'New ' . ucfirst($this->portalUser->type) . ' registration',
            'message' => $displayName . ($this->isResubmission ? ' updated their profile and is asking for another review.' : ' just registered and is awaiting approval.'),
            'url' => route('cms.portal-accounts.show', ['id' => $this->portalUser->id, 'type' => $this->portalUser->type]),
            'icon' => $this->isResubmission ? 'fa-rotate-right' : 'fa-user-plus',
            'tone' => $this->isResubmission ? 'amber' : 'teal',
        ];
    }
}
