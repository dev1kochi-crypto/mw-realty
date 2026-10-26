<?php

namespace App\Notifications;

use App\Models\LeadFollowUp;
use App\Services\Crm\LeadFollowUpService;
use Illuminate\Notifications\Notification;

/** Bell notification for whoever is responsible for a lead when one of its follow-ups is due (LeadFollowUpService). */
class LeadFollowUpReminderNotification extends Notification
{
    public function __construct(public LeadFollowUp $followUp)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $lead = $this->followUp->lead;

        return [
            // Lets a permanent delete of the lead remove this notification too (Lead::forceDeleting).
            'lead_id' => $lead->id,
            'title' => ($this->followUp->isOverdue() ? 'Overdue follow-up: ' : 'Follow-up due: ') . $this->followUp->typeLabel(),
            'message' => $this->followUp->typeLabel() . ' ' . ($lead->name ?: 'this lead') . ' — due '
                . LeadFollowUpService::label($this->followUp->due_at) . ($this->followUp->note ? '. ' . $this->followUp->note : '.'),
            'url' => route('crm.app', 'leads/' . $lead->id),
            'icon' => 'fa-calendar-check',
            'tone' => 'amber',
        ];
    }
}
