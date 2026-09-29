<?php

namespace App\Services\Agency;

use App\Mail\NewLeadReceived;
use App\Models\AgencyLeadAssignmentSetting;
use App\Models\Lead;
use App\Models\LeadAssignmentHistory;
use App\Models\PortalUser;
use App\Models\Property;
use App\Notifications\NewLeadNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Decides which agent works each lead (enquiry). Property assignment (properties.agent_id) and
 * lead assignment (leads.agent_id) are separate: a property's agent only *suggests* the lead's
 * agent, and an agency property with no agent falls through to agency round-robin, decided when
 * the lead arrives — never when the property is created.
 *
 *   property has an eligible agent        → that agent                  (property_agent)
 *   owner is an agent (independent)       → the owner                   (property_agent)
 *   owner is an agency with active agents → next agent in rotation      (round_robin)
 *   owner is an agency with no agents     → agency-level, agent_id null (agency_unassigned)
 *
 * "Eligible" = approved membership + approved, active account in that same agency
 * (PortalUser::eligibleAgentsQuery()). A property still pointing at an agent who has since been
 * suspended or has left falls back to round-robin rather than routing to someone who can't act.
 */
class LeadAssignmentService
{
    /**
     * Retry count for the outermost transaction that creates + assigns a lead. InnoDB may still
     * pick a deadlock victim under heavy simultaneous load; Laravel only retries at the outermost
     * level, so callers wrap create+assign in DB::transaction(..., self::TRANSACTION_ATTEMPTS).
     */
    public const TRANSACTION_ATTEMPTS = 5;

    /** Assign a freshly captured lead. Call once, right after the lead is created. */
    public function assignNewLead(Lead $lead): Lead
    {
        DB::transaction(function () use ($lead) {
            $property = $lead->property;
            $owner = $lead->portal_user_id ? PortalUser::find($lead->portal_user_id) : null;

            // House listing (no owning account) that Super Admin pointed at a specific agent.
            if (!$owner && ($agent = $this->houseListingAgent($property))) {
                $lead->portal_user_id = $agent->company_id ?: $agent->id;
                $this->apply($lead, $agent->id, Lead::ASSIGN_PROPERTY_AGENT, AssignmentActor::system());
                return;
            }

            if (!$owner) {
                return; // Stays with Super Admin's Unassigned Leads screen, as before.
            }

            if ($owner->isAgent()) {
                $this->apply($lead, $owner->id, Lead::ASSIGN_PROPERTY_AGENT, AssignmentActor::system());
                return;
            }

            if ($property?->agent_id && $owner->hasEligibleAgent($property->agent_id)) {
                $this->apply($lead, $property->agent_id, Lead::ASSIGN_PROPERTY_AGENT, AssignmentActor::system());
                return;
            }

            if ($agentId = $this->nextRoundRobinAgent($owner)) {
                $this->apply($lead, $agentId, Lead::ASSIGN_ROUND_ROBIN, AssignmentActor::system());
                return;
            }

            $this->apply($lead, null, Lead::ASSIGN_AGENCY_UNASSIGNED, AssignmentActor::system());
        });

        return $lead;
    }

    /**
     * The account a new lead for this property will belong to — the listing's owner, or for a
     * house listing pointed at an agent, that agent's agency (or the agent if independent).
     * Null = Super Admin's unassigned pool. Mirrors assignNewLead(), so duplicate detection can
     * look in the right account before the lead exists.
     */
    public function resolveOwnerId(?Property $property, ?int $ownerId): ?int
    {
        if ($ownerId) {
            return $ownerId;
        }

        $agent = $this->houseListingAgent($property);

        return $agent ? ($agent->company_id ?: $agent->id) : null;
    }

    /** The eligible agent a no-owner (house) listing points at, if any. */
    private function houseListingAgent(?Property $property): ?PortalUser
    {
        if (!$property?->agent_id) {
            return null;
        }

        $agent = PortalUser::find($property->agent_id);

        return $agent && $agent->isAgent() && $agent->isApproved() && $agent->is_active
            && (!$agent->company_id || $agent->company?->hasEligibleAgent($agent->id))
            ? $agent
            : null;
    }

    /**
     * Next agent in the agency's rotation, advancing the persisted pointer. The settings row is
     * locked FOR UPDATE, so concurrent enquiries for the same agency queue up here and each one
     * reads the pointer the previous one wrote — no two simultaneous leads pick the same agent.
     * Ineligible agents (pending / suspended / inactive / left) are never in the candidate list,
     * so they are skipped automatically; a departed last_agent_id still orders correctly by id.
     */
    public function nextRoundRobinAgent(PortalUser $agency): ?int
    {
        return DB::transaction(function () use ($agency) {
            // Lock the existing row directly. Only insert when it's genuinely missing: an INSERT
            // IGNORE on an existing key takes a shared lock that FOR UPDATE then has to upgrade,
            // which deadlocks between concurrent enquiries. (The row is normally created up front
            // by ensureSettings() when the agency's first agent is approved.)
            $setting = AgencyLeadAssignmentSetting::where('agency_id', $agency->id)->lockForUpdate()->first();
            if (!$setting) {
                $this->ensureSettings($agency);
                $setting = AgencyLeadAssignmentSetting::where('agency_id', $agency->id)->lockForUpdate()->first();
            }

            $candidates = $agency->eligibleAgentsQuery()->pluck('portal_users.id');

            if ($candidates->isEmpty()) {
                return null;
            }

            $last = (int) $setting->last_agent_id;
            $next = $candidates->first(fn ($id) => $id > $last) ?? $candidates->first();

            $setting->update(['last_agent_id' => $next, 'last_assigned_at' => now()]);

            return (int) $next;
        });
    }

    /** Creates the agency's round-robin row if it doesn't exist yet (safe to call repeatedly). */
    public function ensureSettings(PortalUser $agency): void
    {
        DB::table('agency_lead_assignment_settings')->insertOrIgnore([
            'agency_id' => $agency->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Preview of whose turn is next, without advancing the pointer (dashboard display only). */
    public function peekNextAgent(PortalUser $agency): ?PortalUser
    {
        $candidates = $agency->eligibleAgentsQuery()->get();
        if ($candidates->isEmpty()) {
            return null;
        }
        $last = (int) $agency->leadAssignmentSetting?->last_agent_id;

        return $candidates->first(fn ($agent) => $agent->id > $last) ?? $candidates->first();
    }

    /**
     * Agency / Super Admin hands a lead to a specific agent (or back to agency-level with null).
     * The agent must be an eligible member of the agency that owns the lead.
     */
    public function assignManually(Lead $lead, ?int $agentId, AssignmentActor $actor, ?string $note = null): Lead
    {
        $owner = $lead->portal_user_id ? PortalUser::find($lead->portal_user_id) : null;

        if (!$owner || !$owner->isAgency()) {
            throw ValidationException::withMessages(['agent_id' => 'Only agency leads can be assigned to an agent.']);
        }
        if ($agentId !== null && !$owner->hasEligibleAgent($agentId)) {
            throw ValidationException::withMessages(['agent_id' => 'Choose an active, approved agent from this agency.']);
        }

        DB::transaction(function () use ($lead, $agentId, $actor, $note) {
            $locked = Lead::whereKey($lead->id)->lockForUpdate()->first();
            if ((int) $locked->agent_id === (int) $agentId) {
                return;
            }

            $type = match (true) {
                $agentId === null => Lead::ASSIGN_AGENCY_UNASSIGNED,
                $locked->agent_id === null => Lead::ASSIGN_MANUAL,
                default => Lead::ASSIGN_REASSIGNED,
            };

            $this->apply($locked, $agentId, $type, $actor, $note);
            $lead->setRawAttributes($locked->getAttributes(), true);
        });

        return $lead;
    }

    /**
     * "Distribute unassigned leads": explicitly round-robins every agency-level unassigned lead
     * across the agency's current agents. Never runs on its own — existing unassigned leads only
     * move when the agency asks. Returns how many were assigned.
     */
    public function distributeUnassigned(PortalUser $agency, AssignmentActor $actor): int
    {
        if (!$agency->eligibleAgentsQuery()->exists()) {
            return 0;
        }

        $assigned = 0;
        Lead::where('portal_user_id', $agency->id)->whereNull('agent_id')
            ->orderBy('id')
            ->chunkById(200, function ($leads) use ($agency, $actor, &$assigned) {
                foreach ($leads as $lead) {
                    DB::transaction(function () use ($lead, $agency, $actor, &$assigned) {
                        $locked = Lead::whereKey($lead->id)->lockForUpdate()->first();
                        if ($locked->agent_id !== null) {
                            return;
                        }
                        if ($agentId = $this->nextRoundRobinAgent($agency)) {
                            $this->apply($locked, $agentId, Lead::ASSIGN_ROUND_ROBIN, $actor, 'Distributed from unassigned');
                            $assigned++;
                        }
                    });
                }
            });

        return $assigned;
    }

    /** Writes the assignment on the lead plus its history row, and notifies the new agent after commit. */
    private function apply(Lead $lead, ?int $agentId, string $type, AssignmentActor $actor, ?string $note = null): void
    {
        $previousAgentId = $lead->exists ? $lead->getOriginal('agent_id') : null;

        $lead->forceFill([
            'agent_id' => $agentId,
            'assignment_type' => $type,
            'assigned_at' => $agentId ? now() : null,
        ])->save();

        $owner = $lead->portal_user_id ? PortalUser::find($lead->portal_user_id) : null;

        LeadAssignmentHistory::create([
            'lead_id' => $lead->id,
            'agency_id' => $owner?->isAgency() ? $owner->id : null,
            'agent_id' => $agentId,
            'previous_agent_id' => $previousAgentId,
            'assignment_type' => $type,
            'assigned_by_type' => $actor->type,
            'assigned_by_id' => $actor->id,
            'note' => $note,
            'assigned_at' => now(),
        ]);

        // The owning account is notified by the capture flow; this is for the agent who now works it.
        if ($agentId && $agentId !== $lead->portal_user_id) {
            DB::afterCommit(fn () => $this->notifyAgent($lead, $agentId));
        }
    }

    private function notifyAgent(Lead $lead, int $agentId): void
    {
        $agent = PortalUser::find($agentId);
        if (!$agent) {
            return;
        }

        try {
            $agent->notify(new NewLeadNotification($lead));
        } catch (\Throwable $e) {
            Log::error('Failed to create lead-assigned bell notification: ' . $e->getMessage());
        }

        if ($agent->email) {
            try {
                Mail::to($agent->email)->queue(new NewLeadReceived($lead));
            } catch (\Throwable $e) {
                Log::error('Failed to queue lead-assigned email: ' . $e->getMessage());
            }
        }
    }
}
