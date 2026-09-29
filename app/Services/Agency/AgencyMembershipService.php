<?php

namespace App\Services\Agency;

use App\Mail\AgencyMembershipMail;
use App\Models\AgencyAgent;
use App\Models\CmsKit\Admin;
use App\Models\PortalUser;
use App\Models\Property;
use App\Models\PropertyAssignmentHistory;
use App\Notifications\AgencyMembershipNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Every change to an agent's agency relationship goes through here, so the membership row
 * (agency_agents), the agent's current-agency pointer (portal_users.company_id) and the agency's
 * agent-slot limit can never drift apart. The one account an agent registered with is reused for
 * life — joining, leaving and re-joining only ever add membership rows.
 *
 *   agency adds new agent ─────────────────────────────┐
 *   agency invites existing agent → agent accepts ─────┼→ pending → admin approves → approved
 *   agent requests to join → agency accepts ───────────┘                    │
 *                                                        suspended ⇄ approved → inactive (left)
 */
class AgencyMembershipService
{
    /**
     * Agency creates a brand-new agent account. Refuses if the email or mobile already exists
     * (DuplicateAgentException) — that agent should be invited instead, never duplicated.
     */
    public function createAgentForAgency(PortalUser $agency, array $data): AgencyAgent
    {
        $this->assertAgency($agency);

        if ($existing = $this->findExistingAccount($data['email'] ?? null, $data['phone'] ?? null)) {
            throw new DuplicateAgentException($existing);
        }

        $membership = DB::transaction(function () use ($agency, $data) {
            $this->lockAgencyWithFreeSlot($agency);

            $agent = PortalUser::create([
                'type' => 'agent',
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'whatsapp_number' => $data['whatsapp_number'] ?? null,
                'years_of_experience' => $data['years_of_experience'] ?? null,
                'preferred_areas' => $data['preferred_areas'] ?? [],
                'features' => $data['features'] ?? [],
                'nationality' => $data['nationality'] ?? null,
                'emirates_id_no' => $data['emirates_id_no'] ?? null,
                'passport_no' => $data['passport_no'] ?? null,
                'passport_expiry' => $data['passport_expiry'] ?? null,
                'brn_number' => $data['brn_number'] ?? null,
                'trade_license_no' => $data['trade_license_no'] ?? null,
                'trade_license_expiry' => $data['trade_license_expiry'] ?? null,
                'trn_number' => $data['trn_number'] ?? null,
                'trn_expiry' => $data['trn_expiry'] ?? null,
                // No usable login until admin approval sends the set-password link.
                'password' => Hash::make(Str::random(40)),
                'status' => 'pending',
                'status_changed_at' => now(),
                'is_active' => true,
            ]);

            return AgencyAgent::create([
                'agency_id' => $agency->id,
                'agent_id' => $agent->id,
                'status' => AgencyAgent::PENDING,
                'initiated_by' => AssignmentActor::AGENCY,
                'account_created_by_agency' => true,
                'invited_at' => now(),
                'responded_at' => now(),
            ]);
        });

        $this->notifyAdminsPending($membership);

        return $membership;
    }

    /** Agency invites an agent who already has an account (usually an independent agent). */
    public function inviteExistingAgent(PortalUser $agency, PortalUser $agent): AgencyAgent
    {
        $this->assertAgency($agency);

        if (!$agent->isAgent()) {
            throw ValidationException::withMessages(['identifier' => 'That account is not an agent.']);
        }
        if ($agent->company_id === $agency->id) {
            throw ValidationException::withMessages(['identifier' => 'This agent is already part of your agency.']);
        }
        if ($agent->company_id !== null) {
            throw ValidationException::withMessages(['identifier' => 'This agent currently works with another agency.']);
        }
        if ($this->openBetween($agency, $agent)) {
            throw ValidationException::withMessages(['identifier' => 'There is already an open invitation or request with this agent.']);
        }

        $membership = DB::transaction(function () use ($agency, $agent) {
            $this->lockAgencyWithFreeSlot($agency);

            return AgencyAgent::create([
                'agency_id' => $agency->id,
                'agent_id' => $agent->id,
                'status' => AgencyAgent::INVITED,
                'initiated_by' => AssignmentActor::AGENCY,
                'invited_at' => now(),
            ]);
        });

        $this->tell($agent, 'Agency invitation', $agency->displayName() . ' invited you to join their agency.',
            route('portal.agency.index'), 'fa-envelope-open-text', mail: true, actionLabel: 'Review Invitation');

        return $membership;
    }

    /** Independent agent asks to join an agency (registration form, or My Agency page). */
    public function requestToJoin(PortalUser $agent, PortalUser $agency): AgencyAgent
    {
        if (!$agent->isAgent() || $agent->company_id !== null) {
            throw ValidationException::withMessages(['agency_id' => 'Leave your current agency before requesting to join another.']);
        }
        if (!$agency->isAgency() || !$agency->isApproved() || !$agency->is_active) {
            throw ValidationException::withMessages(['agency_id' => 'That agency is not accepting agents right now.']);
        }
        if ($agent->memberships()->whereIn('status', [AgencyAgent::REQUESTED, AgencyAgent::PENDING])->exists()) {
            throw ValidationException::withMessages(['agency_id' => 'You already have a join request in progress.']);
        }
        if ($this->openBetween($agency, $agent)) {
            throw ValidationException::withMessages(['agency_id' => 'You already have an open invitation or request with this agency.']);
        }

        $membership = AgencyAgent::create([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'status' => AgencyAgent::REQUESTED,
            'initiated_by' => AssignmentActor::AGENT,
        ]);

        $this->tell($agency, 'Agent join request', $agent->name . ' asked to join your agency.',
            route('portal.agents.index'), 'fa-user-plus');

        return $membership;
    }

    public function respondToInvitation(PortalUser $agent, AgencyAgent $membership, bool $accept): AgencyAgent
    {
        abort_unless($membership->agent_id === $agent->id && $membership->status === AgencyAgent::INVITED, 404);

        if ($accept && $agent->company_id !== null) {
            throw ValidationException::withMessages(['invitation' => 'Leave your current agency before accepting another invitation.']);
        }

        $membership->update([
            'status' => $accept ? AgencyAgent::PENDING : AgencyAgent::DECLINED,
            'responded_at' => now(),
        ]);

        $this->tell($membership->agency, $accept ? 'Invitation accepted' : 'Invitation declined',
            $agent->name . ($accept ? ' accepted your invitation — now awaiting admin approval.' : ' declined your invitation.'),
            route('portal.agents.index'), $accept ? 'fa-user-check' : 'fa-user-xmark', $accept ? 'teal' : 'amber');

        if ($accept) {
            $this->notifyAdminsPending($membership);
        }

        return $membership;
    }

    public function respondToJoinRequest(PortalUser $agency, AgencyAgent $membership, bool $accept): AgencyAgent
    {
        abort_unless($membership->agency_id === $agency->id && $membership->status === AgencyAgent::REQUESTED, 404);

        DB::transaction(function () use ($agency, $membership, $accept) {
            if ($accept) {
                // Requests don't hold a slot (an agent can't spend an agency's plan), so check now.
                $this->lockAgencyWithFreeSlot($agency);
            }
            $membership->update([
                'status' => $accept ? AgencyAgent::PENDING : AgencyAgent::DECLINED,
                'responded_at' => now(),
            ]);
        });

        $this->tell($membership->agent, $accept ? 'Join request accepted' : 'Join request declined',
            $agency->displayName() . ($accept ? ' accepted your request — now awaiting admin approval.' : ' declined your request.'),
            route('portal.agency.index'), $accept ? 'fa-user-check' : 'fa-user-xmark', $accept ? 'teal' : 'amber');

        if ($accept) {
            $this->notifyAdminsPending($membership);
        }

        return $membership;
    }

    /** Withdraw an invitation / request / pending add before admin approval. */
    public function cancel(AgencyAgent $membership, string $by): AgencyAgent
    {
        abort_unless(in_array($membership->status, [AgencyAgent::INVITED, AgencyAgent::REQUESTED, AgencyAgent::PENDING], true), 422);

        $membership->update(['status' => AgencyAgent::CANCELLED, 'responded_at' => now(), 'ended_by' => $by]);

        return $membership;
    }

    /**
     * Super Admin approval. Makes the agent a member (company_id), approves an agency-created
     * account and emails it a set-password link, and closes the agent's other open invitations.
     */
    public function approve(AgencyAgent $membership, ?int $adminId): AgencyAgent
    {
        DB::transaction(function () use ($membership, $adminId) {
            $membership = AgencyAgent::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            $agent = PortalUser::whereKey($membership->agent_id)->lockForUpdate()->firstOrFail();

            if ($membership->status !== AgencyAgent::PENDING) {
                throw ValidationException::withMessages(['membership' => 'Only requests awaiting approval can be approved.']);
            }
            if ($agent->company_id !== null && $agent->company_id !== $membership->agency_id) {
                throw ValidationException::withMessages(['membership' => 'This agent is currently a member of another agency.']);
            }

            $membership->update([
                'status' => AgencyAgent::APPROVED,
                'approved_at' => now(),
                'approved_by' => $adminId,
                'joined_at' => now(),
            ]);

            $agentUpdates = ['company_id' => $membership->agency_id];
            if ($membership->account_created_by_agency && $agent->status !== 'approved') {
                $agentUpdates += ['status' => 'approved', 'status_changed_at' => now(), 'kyc_review_status' => 'approved', 'rejection_reason' => null];
            }
            $agent->forceFill($agentUpdates)->save();

            AgencyAgent::where('agent_id', $agent->id)->whereKeyNot($membership->id)
                ->whereIn('status', [AgencyAgent::INVITED, AgencyAgent::REQUESTED, AgencyAgent::PENDING])
                ->update(['status' => AgencyAgent::CANCELLED, 'responded_at' => now(), 'ended_by' => AssignmentActor::SYSTEM]);
        });

        $membership->refresh();
        app(LeadAssignmentService::class)->ensureSettings($membership->agency);
        // Only an agency-brought agent (invited or created by it) moves onto the agency's plan.
        $agent = $membership->agent->fresh();
        if ($agent->isOnAgencyPlan()) {
            $this->moveOntoAgencyPlan($agent);
            $agent = $agent->fresh();
        }
        $agencyName = $membership->agency->displayName();

        if ($membership->account_created_by_agency) {
            $this->tell($agent, 'Your agent account is ready', "{$agencyName} added you as an agent on MW Realty. Set your password to log in.",
                $this->setupUrl($agent), 'fa-key', mail: true, actionLabel: 'Set Your Password', bell: false);
        } else {
            $this->tell($agent, 'You joined ' . $agencyName, "Your membership with {$agencyName} was approved. You'll now receive the agency's leads and listings assigned to you.",
                route('portal.agency.index'), 'fa-circle-check', 'green', mail: true, actionLabel: 'Open Portal');
        }
        $this->tell($membership->agency, 'Agent approved', $agent->name . ' is now an active agent in your agency. You can assign them listings and leads.',
            route('portal.agents.index'), 'fa-circle-check', 'green', mail: true, actionLabel: 'View My Agents');

        return $membership;
    }

    public function reject(AgencyAgent $membership, ?string $reason, ?int $adminId): AgencyAgent
    {
        if ($membership->status !== AgencyAgent::PENDING) {
            throw ValidationException::withMessages(['membership' => 'Only requests awaiting approval can be rejected.']);
        }

        $membership->update([
            'status' => AgencyAgent::REJECTED,
            'responded_at' => now(),
            'approved_by' => $adminId,
            'rejection_reason' => $reason,
            'ended_by' => AssignmentActor::ADMIN,
        ]);

        $message = $membership->agent->name . "'s membership was not approved" . ($reason ? ": {$reason}" : '.');
        $this->tell($membership->agency, 'Agent not approved', $message, route('portal.agents.index'), 'fa-circle-xmark', 'red', mail: true, actionLabel: 'View My Agents');
        if (!$membership->account_created_by_agency) {
            $this->tell($membership->agent, 'Agency membership not approved', $message, route('portal.agency.index'), 'fa-circle-xmark', 'red', mail: true, actionLabel: 'Open Portal');
        }

        return $membership;
    }

    /** Temporarily takes an agent out of assignment without ending the membership. */
    public function suspend(AgencyAgent $membership, string $by): AgencyAgent
    {
        if ($membership->status !== AgencyAgent::APPROVED) {
            throw ValidationException::withMessages(['membership' => 'Only active agents can be suspended.']);
        }
        $membership->update(['status' => AgencyAgent::SUSPENDED, 'ended_by' => $by]);
        $this->tell($membership->agent, 'Agency access suspended', $membership->agency->displayName() . ' suspended your agent access. New leads will not be assigned to you.',
            route('portal.agency.index'), 'fa-pause', 'amber');

        return $membership;
    }

    public function reactivate(AgencyAgent $membership): AgencyAgent
    {
        if ($membership->status !== AgencyAgent::SUSPENDED) {
            throw ValidationException::withMessages(['membership' => 'Only suspended agents can be reactivated.']);
        }
        $membership->update(['status' => AgencyAgent::APPROVED, 'ended_by' => null]);
        $this->tell($membership->agent, 'Agency access restored', $membership->agency->displayName() . ' reactivated your agent access.',
            route('portal.agency.index'), 'fa-play', 'green');

        return $membership;
    }

    /**
     * Agent leaves / is removed. The account, their personal listings and every historical lead
     * assignment stay exactly as they are. Agency-owned listings assigned to them stay with the
     * agency — handed to $reassignToAgentId, or left unassigned so new enquiries round-robin.
     */
    public function endMembership(AgencyAgent $membership, AssignmentActor $actor, ?int $reassignToAgentId = null): AgencyAgent
    {
        $agency = $membership->agency;

        if ($reassignToAgentId !== null
            && ($reassignToAgentId === $membership->agent_id || !$agency->hasEligibleAgent($reassignToAgentId))) {
            throw ValidationException::withMessages(['reassign_to' => 'Choose another active agent from this agency.']);
        }

        DB::transaction(function () use ($membership, $actor, $reassignToAgentId) {
            $membership = AgencyAgent::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            if (!in_array($membership->status, AgencyAgent::MEMBER_STATUSES, true)) {
                throw ValidationException::withMessages(['membership' => 'This agent is not a current member.']);
            }

            $membership->update(['status' => AgencyAgent::INACTIVE, 'left_at' => now(), 'ended_by' => $actor->type]);

            PortalUser::whereKey($membership->agent_id)->where('company_id', $membership->agency_id)
                ->update(['company_id' => null]);

            Property::where('portal_user_id', $membership->agency_id)->where('agent_id', $membership->agent_id)
                ->orderBy('id')
                ->chunkById(200, function ($properties) use ($membership, $actor, $reassignToAgentId) {
                    foreach ($properties as $property) {
                        $property->update(['agent_id' => $reassignToAgentId]);
                        $this->recordPropertyChange($property, 'agent_left', $membership->agency_id, $membership->agency_id,
                            $membership->agent_id, $reassignToAgentId, $actor, 'Agent left the agency');
                    }
                });
        });

        $membership->refresh();
        $this->moveOffAgencyPlan($membership->agent_id);
        if ($actor->type === AssignmentActor::AGENT) {
            $this->tell($agency, 'Agent left your agency', $membership->agent->name . ' left your agency.', route('portal.agents.index'), 'fa-user-minus', 'amber');
        } else {
            $this->tell($membership->agent, 'Removed from agency', 'You are no longer an agent of ' . $agency->displayName() . '. Your account and personal listings are unchanged.',
                route('portal.agency.index'), 'fa-user-minus', 'amber', mail: true);
        }

        return $membership;
    }

    /**
     * Super Admin sets an agent's agency directly from the account screen — ends any current
     * membership and records an admin-approved one, so even a manual override keeps history.
     */
    public function adminAssignAgency(PortalUser $agent, ?PortalUser $agency, ?int $adminId): void
    {
        if ($agent->company_id === $agency?->id) {
            return;
        }
        if ($agency && !$agency->isAgency()) {
            throw ValidationException::withMessages(['company_id' => 'Choose an agency.']);
        }

        if ($current = $agent->currentMembership) {
            $this->endMembership($current, AssignmentActor::admin($adminId));
        } elseif ($agent->company_id) {
            // Legacy pointer without a membership row.
            $agent->forceFill(['company_id' => null])->save();
            $this->moveOffAgencyPlan($agent->id);
        }

        if ($agency) {
            DB::transaction(function () use ($agent, $agency, $adminId) {
                AgencyAgent::create([
                    'agency_id' => $agency->id,
                    'agent_id' => $agent->id,
                    'status' => AgencyAgent::APPROVED,
                    'initiated_by' => AssignmentActor::ADMIN,
                    'approved_at' => now(),
                    'approved_by' => $adminId,
                    'joined_at' => now(),
                ]);
                $agent->forceFill(['company_id' => $agency->id])->save();
            });
            app(LeadAssignmentService::class)->ensureSettings($agency);
            // Admin-linked, not agency-brought: the agent keeps their own plan (PortalUser::isOnAgencyPlan).
        }
    }

    /**
     * Optional: an agency agent moves one of their personal listings into the agency (they stay
     * its agent). Counts against the agency's plan property limit.
     */
    public function transferPropertyToAgency(PortalUser $agent, Property $property): Property
    {
        if (!$agent->isAgencyAgent() || $property->portal_user_id !== $agent->id) {
            throw ValidationException::withMessages(['property' => 'Only your own personal listings can be transferred to your agency.']);
        }

        DB::transaction(function () use ($agent, $property) {
            $agency = PortalUser::whereKey($agent->company_id)->lockForUpdate()->firstOrFail();
            if ($agency->remainingPropertySlots() === 0) {
                throw ValidationException::withMessages(['property' => "Your agency has reached its plan's property limit."]);
            }

            $property->update(['portal_user_id' => $agency->id, 'agent_id' => $agent->id]);
            $this->recordPropertyChange($property, 'transferred_to_agency', null, $agency->id, $agent->id, $agent->id,
                AssignmentActor::portal($agent));
        });

        return $property;
    }

    /** One place property-history rows are written (property controller uses it too). */
    public function recordPropertyChange(Property $property, string $action, ?int $fromAgency, ?int $toAgency,
        ?int $fromAgent, ?int $toAgent, AssignmentActor $actor, ?string $note = null): void
    {
        PropertyAssignmentHistory::create([
            'property_id' => $property->id,
            'action' => $action,
            'from_agency_id' => $fromAgency,
            'to_agency_id' => $toAgency,
            'from_agent_id' => $fromAgent,
            'to_agent_id' => $toAgent,
            'changed_by_type' => $actor->type,
            'changed_by_id' => $actor->id,
            'note' => $note,
        ]);
    }

    /** Exact-match lookup for "invite an existing agent" — email, mobile, or agent ID. Never a browsable list. */
    public function findAgentByIdentifier(string $identifier): ?PortalUser
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $query = PortalUser::where('type', 'agent');
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $query->where('email', $identifier)->first();
        }
        if (ctype_digit($identifier) && strlen($identifier) < 8) {
            return $query->whereKey((int) $identifier)->first();
        }

        $digits = preg_replace('/\D+/', '', $identifier);

        return $digits === '' ? null : $query->where(fn ($q) => $q->where('phone', $identifier)->orWhere('phone', $digits)->orWhere('phone', '+' . $digits))->first();
    }

    /** Duplicate check for new agent accounts (email or mobile, any account type). */
    public function findExistingAccount(?string $email, ?string $phone): ?PortalUser
    {
        if ($email && ($match = PortalUser::where('email', $email)->first())) {
            return $match;
        }

        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone);

        return PortalUser::where(fn ($q) => $q->where('phone', $phone)->orWhere('phone', $digits)->orWhere('phone', '+' . $digits))->first();
    }

    /** Signed, 7-day link to set the password on an agency-created agent account; dies once a password is set. */
    public function setupUrl(PortalUser $agent): string
    {
        return URL::temporarySignedRoute('portal.agent-setup.show', now()->addDays(7), [
            'agent' => $agent->id,
            'v' => substr(hash('sha256', (string) $agent->password), 0, 16),
        ]);
    }

    private function assertAgency(PortalUser $agency): void
    {
        if (!$agency->isAgency()) {
            throw ValidationException::withMessages(['agency' => 'Only agencies can manage agents.']);
        }
        if (!$agency->isApproved()) {
            throw ValidationException::withMessages(['agency' => 'Your agency must be approved before adding agents.']);
        }
    }

    /** Locks the agency row so two simultaneous adds can't both take the last agent slot. */
    private function lockAgencyWithFreeSlot(PortalUser $agency): void
    {
        $locked = PortalUser::whereKey($agency->id)->lockForUpdate()->with('plan')->firstOrFail();

        if ($locked->remainingAgentSlots() === 0) {
            $limit = (int) $locked->plan?->agent_limit;
            throw ValidationException::withMessages(['agent_limit' => $limit > 0
                ? "Your plan allows up to {$limit} team agent" . ($limit === 1 ? '' : 's') . '. Upgrade your plan to add more.'
                : 'Team agent accounts are not included in your plan. Upgrade to add agents.']);
        }
    }

    private function openBetween(PortalUser $agency, PortalUser $agent): bool
    {
        return AgencyAgent::where('agency_id', $agency->id)->where('agent_id', $agent->id)->open()->exists();
    }

    private function notifyAdminsPending(AgencyAgent $membership): void
    {
        $membership->loadMissing(['agency', 'agent']);
        try {
            Admin::role('superadmin')->get()->each(fn (Admin $admin) => $admin->notify(new AgencyMembershipNotification(
                'Agency agent awaiting approval',
                $membership->agent->name . ' → ' . $membership->agency->displayName(),
                route('cms.agency-agents.index'),
                'fa-user-clock',
                'amber',
            )));
        } catch (\Throwable $e) {
            Log::error('Failed to notify admins of pending agency agent: ' . $e->getMessage());
        }
    }

    /**
     * Joined an agency → the agency's plan covers them (PortalUser::effectivePlan). Their own paid
     * Stripe subscription is set not to renew (kept until the period already paid for ends, no
     * refund); a manually assigned own plan drops to Free; open plan requests are closed.
     */
    public function moveOntoAgencyPlan(PortalUser $agent): void
    {
        $agent->refresh();
        $free = \App\Models\Plan::defaultFree();

        // Remember the paid plan they gave up (no refund), once — shown as "switched from …".
        $membership = $agent->currentMembership;
        if ($membership && !$membership->plan_switched_at) {
            $membership->forceFill([
                'previous_plan_id' => $agent->plan_id && $agent->plan_id !== $free?->id ? $agent->plan_id : null,
                'plan_switched_at' => now(),
            ])->save();
        }

        if ($agent->hasStripeSubscription()) {
            if (!$agent->subscription_cancel_at_period_end) {
                try {
                    app(\App\Services\StripeBillingService::class)->cancelAtPeriodEnd($agent, \App\Models\Plan::defaultFree());
                } catch (\Throwable $e) {
                    Log::error("Could not stop agent #{$agent->id}'s own subscription after joining an agency: " . $e->getMessage());
                }
            }
        } elseif ($free && $agent->plan_id !== $free->id) {
            $agent->forceFill(['plan_id' => $free->id, 'scheduled_plan_id' => null, 'scheduled_interval' => null])->save();
        }

        $agent->planUpgradeRequests()->whereIn('status', ['pending', 'checkout'])->update(['status' => 'rejected']);
    }

    /** Left / removed from the agency → an independent agent on the Free plan (a still-running paid period is kept until it ends). */
    private function moveOffAgencyPlan(int $agentId): void
    {
        $agent = PortalUser::find($agentId);
        $free = \App\Models\Plan::defaultFree();
        if ($agent && $free && !$agent->company_id && !$agent->hasStripeSubscription() && $agent->plan_id !== $free->id) {
            $agent->forceFill(['plan_id' => $free->id, 'scheduled_plan_id' => null, 'scheduled_interval' => null])->save();
        }
    }

    private function tell(PortalUser $to, string $title, string $message, string $url, string $icon, string $tone = 'teal',
        bool $mail = false, ?string $actionLabel = null, bool $bell = true): void
    {
        if ($bell) {
            try {
                $to->notify(new AgencyMembershipNotification($title, $message, $url, $icon, $tone));
            } catch (\Throwable $e) {
                Log::error('Failed to create agency membership notification: ' . $e->getMessage());
            }
        }

        if ($mail && $to->email) {
            try {
                Mail::to($to->email)->queue((new AgencyMembershipMail($title, $to->displayName(), $message, $actionLabel, $url))->afterCommit());
            } catch (\Throwable $e) {
                Log::error('Failed to queue agency membership email: ' . $e->getMessage());
            }
        }
    }
}
