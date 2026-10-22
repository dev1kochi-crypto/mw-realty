<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Bell notification for every agency ⇄ agent membership event (invitation, join request,
 * awaiting approval, approved, rejected, suspended, left) — the wording is decided by
 * AgencyMembershipService, so there's one class instead of one per event.
 */
class AgencyMembershipNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
        public string $url,
        public string $icon = 'fa-people-group',
        public string $tone = 'teal',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'icon' => $this->icon,
            'tone' => $this->tone,
        ];
    }
}
