<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Bell notification for support-ticket activity — sent to the ticket's agent/company when admin
 * replies or changes the status, and to support admins when a ticket is raised or the client replies.
 */
class SupportTicketNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
        public string $url,
        public string $icon = 'fa-headset',
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
