<?php

namespace App\Http\Resources\Crm;

use App\Models\PortalUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in CRM account — an agent / company (PortalUser), or a Super Admin (the CMS admin,
 * web app only) browsing every account's data.
 */
class CrmUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (!$this->resource instanceof PortalUser) {
            return [
                'id' => $this->id,
                'type' => 'super_admin',
                'name' => $this->name,
                'email' => $this->email,
                'avatar_url' => null,
                'status' => 'approved',
                'approved' => true,
                'is_super_admin' => true,
                'is_agency' => false,
                'is_agency_agent' => false,
                'plan' => null,
                'can_upgrade' => false,
                'kyc' => null,
                'help_auto_open' => false,
                'two_factor' => null,
            ];
        }

        return [
            'id' => $this->id,
            'type' => $this->type, // agent | company
            'name' => $this->displayName(),
            'email' => $this->email,
            'avatar_url' => $this->avatar ? media_url($this->avatar) : null,
            'status' => $this->status, // pending | approved | rejected
            'approved' => $this->status === 'approved',
            'is_super_admin' => false,
            'is_agency' => $this->isAgency(),
            'is_agency_agent' => $this->isAgencyAgent(),
            'plan' => $this->plan ? ['id' => $this->plan->id, 'name' => $this->plan->getTranslation('name')] : null,
            // Top bar "Upgrade Plan": an agency agent is on the agency's plan, so nothing to upgrade themselves.
            'can_upgrade' => !$this->isOnAgencyPlan() && !$this->plan?->isTopTier(),
            // KYC banner while not approved: rejected (with reason) / submitted and waiting / not finished.
            'kyc' => $this->status === 'approved' ? null : [
                'state' => $this->status === 'rejected' ? 'rejected'
                    : ($this->kyc_review_status === 'submitted' && $this->kyc_user_submitted_at ? 'waiting' : 'incomplete'),
                'rejection_reason' => $this->rejection_reason,
            ],
            // The first help guide opens by itself once; then POST /api/crm/help/seen.
            'help_auto_open' => empty($this->seen_help_topics),
            'two_factor' => [
                'enabled' => $this->hasTwoFactorEnabled(),
                // true = every CRM call answers 403 until 2FA is set up (or, when not required, skipped).
                'setup_required' => !$this->hasTwoFactorEnabled() && ($this->twoFactorRequired() || $this->needsTwoFactorPrompt()),
            ],
        ];
    }
}
