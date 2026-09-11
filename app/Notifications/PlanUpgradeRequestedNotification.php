<?php

namespace App\Notifications;

use App\Models\PlanUpgradeRequest;
use Illuminate\Notifications\Notification;

/** Bell notification for every superadmin when an agent/company requests a plan upgrade. */
class PlanUpgradeRequestedNotification extends Notification
{
    public function __construct(public PlanUpgradeRequest $upgradeRequest)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $portalUser = $this->upgradeRequest->portalUser;
        $displayName = $portalUser->type === 'company' ? ($portalUser->company_name ?: $portalUser->name) : $portalUser->name;
        $planName = $this->upgradeRequest->plan->getTranslation('name');

        return [
            'title' => 'Plan upgrade requested',
            'message' => $displayName . ' requested to switch to the ' . $planName . ' plan.',
            'url' => route('cms.portal-accounts.plan-upgrade-requests'),
            'icon' => 'fa-arrow-up',
            'tone' => 'teal',
        ];
    }
}
