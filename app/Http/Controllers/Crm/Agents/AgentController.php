<?php

namespace App\Http\Controllers\Crm\Agents;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Models\AgencyAgent;
use App\Models\Lead;
use App\Models\PortalUser;
use App\Models\Property;
use App\Services\Agency\AgencyMembershipService;
use App\Services\Agency\AssignmentActor;
use App\Services\Agency\DuplicateAgentException;
use App\Services\Agency\LeadAssignmentService;
use App\Services\ManagedFiles;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * @group CRM Agents
 *
 * Agent roster: Super Admin sees every agent across every agency; a Company manages only its own —
 * every membership is looked up already scoped to that agency (findMembership()) and then checked
 * by AgencyAgentPolicy, so another agency's agent id 404s. An Agent-type login has no access here
 * (its side is My Agency).
 *
 * Adding a brand-new agent or inviting an existing one both need Super Admin approval before the
 * agent becomes active; all state changes go through AgencyMembershipService. Lead assignment
 * settings and "distribute unassigned" are the Leads endpoints (/leads/assignment-settings, /leads/distribute).
 */
class AgentController extends Controller
{
    use ScopesPortalOwner;

    /** Roster tabs → the membership statuses each one lists. */
    public const TABS = [
        'active' => [AgencyAgent::APPROVED, AgencyAgent::SUSPENDED],
        'pending' => [AgencyAgent::PENDING, AgencyAgent::INVITED],
        'requests' => [AgencyAgent::REQUESTED],
        'history' => [AgencyAgent::INACTIVE, AgencyAgent::REJECTED, AgencyAgent::DECLINED, AgencyAgent::CANCELLED],
    ];

    private const DOCUMENTS = ['emirates_id_document', 'passport_document', 'rera_card_document', 'rera_certificate_document', 'trade_license_document'];

    public function __construct(private readonly AgencyMembershipService $memberships)
    {
    }

    /**
     * The roster
     *
     * Super Admin: every agent, 15 per page (`is_admin` true). Agency: the memberships of one tab,
     * 15 per page, with tab counts, round-robin state, lead assignment settings and agent slots.
     *
     * @queryParam tab string active | pending | requests | history. Example: active
     * @queryParam page integer Example: 1
     */
    public function index(Request $request, LeadAssignmentService $leadAssignment)
    {
        abort_unless($this->isAdmin() || $this->company(), 403);

        if ($this->isAdmin()) {
            $agents = PortalUser::where('type', 'agent')->with('company')->latest()->paginate(15);

            return response()->json([
                'is_admin' => true,
                'agency_approvals_url' => route('cms.agency-agents.index'),
                'data' => collect($agents->items())->map(fn (PortalUser $agent) => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'agency' => $agent->company?->displayName(),
                    'email' => $agent->email,
                    'phone' => $agent->phone,
                    'years_of_experience' => $agent->years_of_experience,
                    'preferred_areas' => array_values($agent->preferred_areas ?? []),
                    'is_active' => (bool) $agent->is_active,
                ]),
                'meta' => $this->meta($agents),
            ]);
        }

        $agency = $this->company();
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'active';

        $memberships = $agency->agencyMemberships()
            ->whereIn('status', self::TABS[$tab])
            // Plan column: the agent's own plan, whether the agency's covers them, the plan they dropped.
            ->with(['agent.plan', 'agent.currentMembership', 'agent.company.plan', 'previousPlan'])
            ->latest('id')
            ->paginate(15);

        $statusCounts = $agency->agencyMemberships()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $tabCounts = collect(self::TABS)->map(fn ($statuses) => (int) collect($statuses)->sum(fn ($s) => $statusCounts[$s] ?? 0));

        // Per-agent lead/listing numbers for the rows on this page (agency's own leads only).
        $agentIds = $memberships->pluck('agent_id');
        $leadCounts = Lead::where('portal_user_id', $agency->id)->whereIn('agent_id', $agentIds)
            ->selectRaw('agent_id, count(*) as total')->groupBy('agent_id')->pluck('total', 'agent_id');
        $propertyCounts = Property::where('portal_user_id', $agency->id)->whereIn('agent_id', $agentIds)
            ->selectRaw('agent_id, count(*) as total')->groupBy('agent_id')->pluck('total', 'agent_id');

        $setting = $agency->leadAssignmentSetting;
        $next = $leadAssignment->peekNextAgent($agency);

        return response()->json([
            'is_admin' => false,
            'tab' => $tab,
            'tab_counts' => $tabCounts,
            'data' => collect($memberships->items())->map(fn (AgencyAgent $m) => $this->membershipRow($m, $tab) + [
                'listings' => (int) ($propertyCounts[$m->agent_id] ?? 0),
                'leads' => (int) ($leadCounts[$m->agent_id] ?? 0),
            ]),
            'meta' => $this->meta($memberships),
            'round_robin' => [
                'eligible_count' => $agency->eligibleAgentsQuery()->count(),
                'last' => $setting?->lastAgent?->name,
                'last_at' => $setting?->last_assigned_at?->toIso8601String(),
                'next' => $next?->name,
                'unassigned' => Lead::where('portal_user_id', $agency->id)->whereNull('agent_id')->count(),
            ],
            'assignment' => $leadAssignment->settingsSummary($agency),
            'agent_slots' => [
                'used' => $agency->usedAgentSlots(),
                'limit' => $agency->plan?->agent_limit,
                'remaining' => $agency->remainingAgentSlots(),
            ],
        ]);
    }

    /**
     * One agent of this agency
     *
     * Profile, membership history with this agency, their agency listings (10 per page) and lead count.
     *
     * @queryParam page integer Listings page. Example: 1
     */
    public function show($id)
    {
        $agency = $this->agency();
        $membership = $this->findMembership($agency, $id);
        $agent = $membership->agent;
        $properties = Property::where('portal_user_id', $agency->id)->where('agent_id', $agent->id)->latest('id')->paginate(10);

        return response()->json([
            'membership' => $this->membershipRow($membership, 'active'),
            'agent' => ['id' => $agent->id, 'name' => $agent->name, 'email' => $agent->email, 'phone' => $agent->phone],
            'joined_at' => $membership->joined_at?->toIso8601String(),
            'lead_count' => Lead::where('portal_user_id', $agency->id)->where('agent_id', $agent->id)->count(),
            'properties' => [
                'data' => collect($properties->items())->map(fn (Property $p) => [
                    'id' => $p->id,
                    'segment' => $p->segment,
                    'reference_no' => $p->reference_no,
                    'title' => $p->getTranslation('title'),
                    'active' => (bool) $p->status,
                ]),
                'meta' => $this->meta($properties),
            ],
            'history' => AgencyAgent::where('agency_id', $agency->id)->where('agent_id', $agent->id)->latest('id')->get()
                ->map(fn (AgencyAgent $row) => [
                    'id' => $row->id,
                    'status_label' => $row->statusLabel(),
                    'status_tone' => $row->statusTone(),
                    'initiated_by' => $row->initiated_by,
                    'created_at' => $row->created_at?->toIso8601String(),
                    'joined_at' => $row->joined_at?->toIso8601String(),
                    'left_at' => $row->left_at?->toIso8601String(),
                ]),
            'reassign_options' => $agency->eligibleAgentsQuery()->where('portal_users.id', '!=', $agent->id)->get(['portal_users.id', 'portal_users.name']),
        ]);
    }

    /**
     * Can another agent be added?
     *
     * For the Add / Invite screen: `can_add` false (with the reason) once the plan's agent slots are used up.
     */
    public function create()
    {
        $agency = $this->agency();
        $canAdd = $agency->remainingAgentSlots() !== 0;

        return response()->json(['can_add' => $canAdd, 'message' => $canAdd ? null : $this->slotMessage($agency)]);
    }

    /**
     * Add a brand-new agent
     *
     * Multipart. Pending Super Admin approval; the agent gets a set-password link once approved.
     * An email / mobile that already exists answers 409 with `duplicate` (and whether they can be invited instead).
     */
    public function store(Request $request)
    {
        $agency = $this->agency();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'whatsapp_number' => 'nullable|string|max:50',
            'years_of_experience' => 'nullable|integer|min:0|max:80',
            'preferred_areas' => 'nullable|array',
            'preferred_areas.*' => 'nullable|string|max:100',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:100',
            'nationality' => 'nullable|string|max:100',
            'emirates_id_no' => 'nullable|string|max:50',
            'emirates_id_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'passport_no' => 'nullable|string|max:50',
            'passport_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'passport_expiry' => 'nullable|date',
            'brn_number' => 'nullable|string|max:50',
            'rera_card_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'rera_certificate_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'trade_license_no' => 'nullable|string|max:50',
            'trade_license_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'trade_license_expiry' => 'nullable|date',
            'trn_number' => 'nullable|string|max:50',
            'trn_expiry' => 'nullable|date',
        ]);
        $data['preferred_areas'] = array_values(array_filter($data['preferred_areas'] ?? [], fn ($v) => trim((string) $v) !== ''));
        $data['features'] = array_values(array_filter($data['features'] ?? [], fn ($v) => trim((string) $v) !== ''));

        try {
            $membership = $this->memberships->createAgentForAgency($agency, $data);
        } catch (DuplicateAgentException $e) {
            $existing = $e->existing;
            $canInvite = $existing->isAgent() && $existing->company_id === null;

            return response()->json([
                'message' => 'An account with this email or mobile already exists.',
                'duplicate' => [
                    'identifier' => $existing->email,
                    'name' => $existing->name,
                    'can_invite' => $canInvite,
                    'message' => $canInvite
                        ? 'An agent with this email or mobile already exists. Send them an agency invitation instead?'
                        : ($existing->isAgent() && $existing->company_id === $agency->id
                            ? 'This agent is already part of your agency.'
                            : 'An account with this email or mobile already exists and cannot be added as a new agent.'),
                ],
            ], 409);
        }

        $documents = [];
        foreach (self::DOCUMENTS as $field) {
            if ($request->hasFile($field)) {
                $documents[$field] = app(ManagedFiles::class)->store($request->file($field), 'portal-kyc', 'kyc');
            }
        }
        $profileData = array_merge($request->only([
            'nationality', 'emirates_id_no', 'passport_no', 'passport_expiry', 'brn_number',
            'trade_license_no', 'trade_license_expiry', 'trn_number', 'trn_expiry',
        ]), $documents);
        if ($documents) {
            $profileData['kyc_review_status'] = 'submitted';
            $profileData['kyc_submitted_at'] = now();
        }
        $membership->agent->update($profileData);

        return response()->json([
            'success' => true,
            'message' => 'Agent added — they will be able to log in once Super Admin approves them.',
            'id' => $membership->id,
        ], 201);
    }

    /**
     * Invite an existing agent
     *
     * Found by exact email, mobile or agent ID. Once they accept, Super Admin approves the move.
     *
     * @bodyParam identifier string required Example: sara@example.com
     */
    public function invite(Request $request)
    {
        $agency = $this->agency();
        $request->validate(['identifier' => 'required|string|max:255']);

        $agent = $this->memberships->findAgentByIdentifier($request->input('identifier'));
        if (!$agent) {
            return response()->json([
                'message' => 'No agent found with that email, mobile or agent ID.',
                'errors' => ['identifier' => ['No agent found with that email, mobile or agent ID.']],
            ], 422);
        }

        $this->memberships->inviteExistingAgent($agency, $agent);

        return response()->json(['success' => true, 'message' => "Invitation sent to {$agent->name}. Once they accept, Super Admin approves the move."]);
    }

    /** Accept a join request (then awaits Super Admin approval) */
    public function acceptRequest($id)
    {
        $agency = $this->agency();
        $this->memberships->respondToJoinRequest($agency, $this->findMembership($agency, $id), true);

        return response()->json(['success' => true, 'message' => 'Request accepted — now awaiting Super Admin approval.']);
    }

    /** Decline a join request */
    public function declineRequest($id)
    {
        $agency = $this->agency();
        $this->memberships->respondToJoinRequest($agency, $this->findMembership($agency, $id), false);

        return response()->json(['success' => true, 'message' => 'Request declined.']);
    }

    /** Withdraw an invitation or a pending add */
    public function cancel($id)
    {
        $agency = $this->agency();
        $membership = $this->findMembership($agency, $id);
        abort_unless(in_array($membership->status, [AgencyAgent::INVITED, AgencyAgent::PENDING], true), 422);
        $this->memberships->cancel($membership, AssignmentActor::AGENCY);

        return response()->json(['success' => true, 'message' => 'Cancelled.']);
    }

    /** Suspend — no new leads until reactivated */
    public function suspend($id)
    {
        $agency = $this->agency();
        $this->memberships->suspend($this->findMembership($agency, $id), AssignmentActor::AGENCY);

        return response()->json(['success' => true, 'message' => 'Agent suspended — they won\'t receive new leads until reactivated.']);
    }

    /** Reactivate a suspended agent */
    public function reactivate($id)
    {
        $agency = $this->agency();
        $this->memberships->reactivate($this->findMembership($agency, $id));

        return response()->json(['success' => true, 'message' => 'Agent reactivated.']);
    }

    /**
     * Remove from the agency
     *
     * Account and history stay; agency listings go to `reassign_to` or become unassigned.
     *
     * @bodyParam reassign_to integer Another active agent of this agency. Example: 42
     */
    public function remove(Request $request, $id)
    {
        $agency = $this->agency();
        $membership = $this->findMembership($agency, $id);
        $request->validate(['reassign_to' => 'nullable|integer']);

        $this->memberships->endMembership($membership, AssignmentActor::portal($agency),
            $request->filled('reassign_to') ? (int) $request->input('reassign_to') : null);

        return response()->json(['success' => true, 'message' => "{$membership->agent->name} was removed from your agency."]);
    }

    private function company(): ?PortalUser
    {
        $user = $this->owner();

        return $user && $user->type === 'company' ? $user : null;
    }

    /** The signed-in agency, or 403 — for every agency action. */
    private function agency(): PortalUser
    {
        $agency = $this->company();
        abort_unless($agency, 403);
        abort_unless($agency->isApproved(), 403, 'Your agency must be approved first.');

        return $agency;
    }

    /** A membership of the signed-in agency only — any other id is a 404, whatever the request says. */
    private function findMembership(PortalUser $agency, $id): AgencyAgent
    {
        $membership = AgencyAgent::where('agency_id', $agency->id)->with('agent')->findOrFail($id);
        Gate::forUser($agency)->authorize('manage', $membership);

        return $membership;
    }

    private function membershipRow(AgencyAgent $membership, string $tab): array
    {
        $agent = $membership->agent;
        $isMember = in_array($membership->status, AgencyAgent::MEMBER_STATUSES, true);
        $ownPlan = $agent->plan;
        $date = $tab === 'history'
            ? ($membership->left_at ?? $membership->responded_at ?? $membership->updated_at)
            : ($membership->joined_at ?? $membership->created_at);

        return [
            'id' => $membership->id,
            'status' => $membership->status,
            'status_label' => $membership->statusLabel(),
            'status_tone' => $membership->statusTone(),
            'rejection_reason' => $membership->rejection_reason,
            'is_member' => $isMember,
            'agent' => ['id' => $agent->id, 'name' => $agent->name, 'email' => $agent->email, 'phone' => $agent->phone],
            // Plan column — member: on the agency's plan (and which one they dropped) or still their own;
            // not yet a member: the plan they're on now, which the agency's replaces on approval.
            'plan' => [
                'on_agency_plan' => $isMember && $agent->isOnAgencyPlan(),
                'previous' => $membership->previousPlan?->getTranslation('name'),
                'own' => $ownPlan?->getTranslation('name') ?? 'Free',
                'own_paid' => $ownPlan && (float) $ownPlan->price > 0,
            ],
            'date' => $date?->toIso8601String(),
        ];
    }

    private function meta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total(),
            'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
        ];
    }

    private function slotMessage(PortalUser $agency): string
    {
        $limit = (int) $agency->plan?->agent_limit;

        return $limit > 0
            ? "Your plan allows up to {$limit} team agent" . ($limit === 1 ? '' : 's') . '. Upgrade your plan to add more.'
            : 'Team agent accounts are not included in your plan. Upgrade to add agents.';
    }
}
