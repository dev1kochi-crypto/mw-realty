<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Notifications\Notification;

/** Bell notification for the property's owning company/agent when a visitor submits a lead. */
class NewLeadNotification extends Notification
{
    public function __construct(public Lead $lead)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $propertyLabel = $this->lead->property?->getTranslation('title') ?? 'your listing';

        return [
            'title' => 'New lead received',
            'message' => $this->lead->name . ' enquired about ' . $propertyLabel . '.',
            'url' => route('portal.crm.leads.show', $this->lead->id),
            'icon' => 'fa-address-book',
            'tone' => 'teal',
        ];
    }
}
