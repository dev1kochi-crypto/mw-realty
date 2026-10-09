<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Database-only bell notification delivered TO the Agent/Company the morning before their
 * subscription auto-renews. The matching email is sent separately via SubscriptionRenewalReminderMail.
 */
class SubscriptionRenewalReminderNotification extends Notification
{
    public function __construct(
        public string $planName,
        public float $amount,
        public string $renewsOn,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Your plan renews tomorrow',
            'message' => $this->planName . ' auto-renews on ' . $this->renewsOn . ' for AED ' . number_format($this->amount, 2) . '.',
            'url' => route('crm.app', 'plans'),
            'icon' => 'fa-sync-alt',
            'tone' => 'amber',
        ];
    }
}
