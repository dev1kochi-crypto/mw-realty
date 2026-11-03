<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One stint (or attempted stint) of an agent with an agency — see the
 * 2026_09_28_100000 migration. The row that is currently `approved` is what
 * portal_users.company_id points at; every other row is history or an open
 * handshake (invitation / join request / awaiting admin approval).
 */
class AgencyAgent extends Model
{
    public const INVITED = 'invited';       // agency invited an existing agent, awaiting the agent
    public const REQUESTED = 'requested';   // agent asked to join, awaiting the agency
    public const PENDING = 'pending';       // both sides agreed (or agency created the agent), awaiting admin
    public const APPROVED = 'approved';     // active member — eligible for property and lead assignment
    public const SUSPENDED = 'suspended';   // still a member, but skipped for assignment
    public const INACTIVE = 'inactive';     // left / removed — history only
    public const REJECTED = 'rejected';     // admin rejected
    public const DECLINED = 'declined';     // the invited agent / requested agency said no
    public const CANCELLED = 'cancelled';   // withdrawn by whoever started it

    /** Statuses that still hold a relationship open (and use one of the agency's agent slots). */
    public const OPEN_STATUSES = [self::INVITED, self::REQUESTED, self::PENDING, self::APPROVED, self::SUSPENDED];

    /** Statuses that make the agent part of the agency right now. */
    public const MEMBER_STATUSES = [self::APPROVED, self::SUSPENDED];

    // The agency's offer to put an admin-linked agent on its plan (plan_offer_status).
    public const PLAN_OFFERED = 'offered';
    public const PLAN_ACCEPTED = 'accepted';
    public const PLAN_DECLINED = 'declined';

    protected $fillable = [
        'agency_id', 'agent_id', 'status', 'initiated_by', 'account_created_by_agency',
        'invited_at', 'responded_at', 'approved_at', 'approved_by', 'joined_at', 'left_at',
        'ended_by', 'rejection_reason', 'previous_plan_id', 'plan_switched_at',
        'plan_offer_status', 'plan_offered_at', 'plan_offer_responded_at',
    ];

    protected $casts = [
        'account_created_by_agency' => 'boolean',
        'plan_switched_at' => 'datetime',
        'plan_offered_at' => 'datetime',
        'plan_offer_responded_at' => 'datetime',
        'invited_at' => 'datetime',
        'responded_at' => 'datetime',
        'approved_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function agency()
    {
        return $this->belongsTo(PortalUser::class, 'agency_id');
    }

    /** The agent's own plan dropped (no refund) when they moved onto the agency's plan. */
    public function previousPlan()
    {
        return $this->belongsTo(Plan::class, 'previous_plan_id');
    }

    public function agent()
    {
        return $this->belongsTo(PortalUser::class, 'agent_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('agency_agents.status', self::OPEN_STATUSES);
    }

    public function scopeMember($query)
    {
        return $query->whereIn('agency_agents.status', self::MEMBER_STATUSES);
    }

    /**
     * Brought in through My Agency / Agents (agency-created account, agency invitation, or the agent's
     * request the agency accepted) — such members move onto the agency's plan on approval by themselves.
     * Admin links and old backfilled brokerage links don't; those need an accepted plan offer.
     */
    public function isAgencyBrought(): bool
    {
        return $this->initiated_by === \App\Services\Agency\AssignmentActor::AGENT
            || ($this->initiated_by === \App\Services\Agency\AssignmentActor::AGENCY
                && ($this->account_created_by_agency || $this->invited_at !== null));
    }

    public function hasPendingPlanOffer(): bool
    {
        return $this->plan_offer_status === self::PLAN_OFFERED;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::INVITED => 'Invitation sent',
            self::REQUESTED => 'Join request',
            self::PENDING => 'Pending admin approval',
            self::APPROVED => 'Active',
            self::SUSPENDED => 'Suspended',
            self::INACTIVE => 'Left',
            self::REJECTED => 'Rejected',
            self::DECLINED => 'Declined',
            self::CANCELLED => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /** Bootstrap-ish tone for the status pill. */
    public function statusTone(): string
    {
        return match ($this->status) {
            self::APPROVED => 'success',
            self::PENDING, self::INVITED, self::REQUESTED => 'warning',
            self::SUSPENDED => 'secondary',
            self::REJECTED, self::DECLINED => 'danger',
            default => 'light',
        };
    }
}
