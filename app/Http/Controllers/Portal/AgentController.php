<?php

namespace App\Http\Controllers\Portal;

use App\Models\AgencyAgent;
use App\Models\Lead;
use App\Models\PortalUser;
use App\Models\Property;
use App\Services\Agency\AgencyMembershipService;
use App\Services\Agency\AssignmentActor;
use App\Services\Agency\DuplicateAgentException;
use App\Services\Agency\LeadAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Agent roster: Super Admin (cms guard) sees every agent across every agency; a Company (portal
 * guard) manages only its own — every membership is looked up already scoped to that agency
 * (findMembership()) and then checked by AgencyAgentPolicy, so another agency's agent id 404s.
 * An Agent-type portal login has no access here (see AgencyController for the agent's side).
 *
 * Adding a brand-new agent or inviting an existing one both need Super Admin approval before the
 * agent becomes active; all state changes go through AgencyMembershipService.
 */
class AgentController extends Controller
{
    /** Roster tabs → the membership statuses each one lists. */
    private const TABS = [
        'active' => [AgencyAgent::APPROVED, AgencyAgent::SUSPENDED],
        'pending' => [AgencyAgent::PENDING, AgencyAgent::INVITED],
        'requests' => [AgencyAgent::REQUESTED],
        'history' => [AgencyAgent::INACTIVE, AgencyAgent::REJECTED, AgencyAgent::DECLINED, AgencyAgent::CANCELLED],
    ];

    public function __construct(private readonly AgencyMembershipService $memberships)
    {
    }

    protected function isAdmin(): bool
    {
        return !Auth::guard('portal')->check() && (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
    }

    protected function company(): ?PortalUser
    {
        $user = Auth::guard('portal')->user();

        return $user && $user->type === 'company' ? $user : null;
    }

    /** The signed-in agency, or 403 — for every write action. */
    protected function agency(): PortalUser
    {
        $agency = $this->company();
        abort_unless($agency, 403);
        abort_unless($agency->isApproved(), 403, 'Your agency must be approved first.');

        return $agency;
    }

    /** A membership of the signed-in agency only — any other id is a 404, whatever the request says. */
    protected function findMembership(PortalUser $agency, $id): AgencyAgent
    {
        $membership = AgencyAgent::where('agency_id', $agency->id)->with('agent')->findOrFail($id);
        Gate::forUser($agency)->authorize('manage', $membership);

        return $membership;
    }

    public function index(Request $request, LeadAssignmentService $leadAssignment)
    {
        abort_unless($this->isAdmin() || $this->company(), 403);

        if ($this->isAdmin()) {
            return view('portal.agents.admin-index', [
                'agents' => PortalUser::where('type', 'agent')->with('company')->latest()->paginate(15),
            ]);
        }

        $agency = $this->company();
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'active';

        $memberships = $agency->agencyMemberships()
            ->whereIn('status', self::TABS[$tab])
            ->with('agent')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $statusCounts = $agency->agencyMemberships()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $tabCounts = collect(self::TABS)->map(fn ($statuses) => (int) collect($statuses)->sum(fn ($s) => $statusCounts[$s] ?? 0));

        // Per-agent lead/listing numbers for the rows on this page (agency's own leads only).
        $agentIds = $memberships->pluck('agent_id');
        $leadCounts = Lead::where('portal_user_id', $agency->id)->whereIn('agent_id', $agentIds)
            ->selectRaw('agent_id, count(*) as total')->groupBy('agent_id')->pluck('total', 'agent_id');
        $propertyCounts = Property::where('portal_user_id', $agency->id)->whereIn('agent_id', $agentIds)
            ->selectRaw('agent_id, count(*) as total')->groupBy('agent_id')->pluck('total', 'agent_id');

        $eligible = $agency->eligibleAgentsQuery()->get(['portal_users.id', 'portal_users.name']);

        return view('portal.agents.index', [
            'tab' => $tab,
            'tabCounts' => $tabCounts,
            'memberships' => $memberships,
            'leadCounts' => $leadCounts,
            'propertyCounts' => $propertyCounts,
            'eligibleAgents' => $eligible,
            'roundRobin' => [
                'last' => $agency->leadAssignmentSetting?->lastAgent,
                'last_at' => $agency->leadAssignmentSetting?->last_assigned_at,
                'next' => $leadAssignment->peekNextAgent($agency),
                'unassigned' => Lead::where('portal_user_id', $agency->id)->whereNull('agent_id')->count(),
            ],
            'agentSlots' => [
                'used' => $agency->usedAgentSlots(),
                'limit' => $agency->plan?->agent_limit,
                'remaining' => $agency->remainingAgentSlots(),
            ],
        ]);
    }

    /** One agent of this agency: profile, membership history with this agency, their workload. */
    public function show($id)
    {
        $agency = $this->agency();
        $membership = $this->findMembership($agency, $id);
        $agent = $membership->agent;

        return view('portal.agents.show', [
            'membership' => $membership,
            'agent' => $agent,
            'history' => AgencyAgent::where('agency_id', $agency->id)->where('agent_id', $agent->id)->latest('id')->get(),
            'properties' => Property::where('portal_user_id', $agency->id)->where('agent_id', $agent->id)->latest('id')->paginate(10),
            'leadCount' => Lead::where('portal_user_id', $agency->id)->where('agent_id', $agent->id)->count(),
            'eligibleAgents' => $agency->eligibleAgentsQuery()->where('portal_users.id', '!=', $agent->id)->get(['portal_users.id', 'portal_users.name']),
        ]);
    }

    public function create()
    {
        $agency = $this->agency();
        if ($agency->remainingAgentSlots() === 0) {
            return redirect()->route('portal.agents.index')->with('error', $this->slotMessage($agency));
        }

        return view('portal.agents.create');
    }

    /** Add a brand-new agent (pending admin approval). An existing email/mobile offers an invitation instead. */
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

            return back()->withInput()->with('duplicateAgent', [
                'identifier' => $existing->email,
                'name' => $existing->name,
                'can_invite' => $canInvite,
                'message' => $canInvite
                    ? 'An agent with this email or mobile already exists. Send them an agency invitation instead?'
                    : ($existing->isAgent() && $existing->company_id === $agency->id
                        ? 'This agent is already part of your agency.'
                        : 'An account with this email or mobile already exists and cannot be added as a new agent.'),
            ]);
        }

        $documents = [];
        foreach (['emirates_id_document', 'passport_document', 'rera_card_document', 'rera_certificate_document', 'trade_license_document'] as $field) {
            if ($request->hasFile($field)) {
                $documents[$field] = app(\App\Services\ManagedFiles::class)->store($request->file($field), 'portal-kyc', 'kyc');
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

        return redirect()->route('portal.agents.index', ['tab' => 'pending'])
            ->with('success', 'Agent added — they will be able to log in once Super Admin approves them.');
    }

    /** Invite an existing agent, found by exact email / mobile / agent ID. */
    public function invite(Request $request)
    {
        $agency = $this->agency();
        $request->validate(['identifier' => 'required|string|max:255']);

        $agent = $this->memberships->findAgentByIdentifier($request->input('identifier'));
        if (!$agent) {
            return back()->withInput()->withErrors(['identifier' => 'No agent found with that email, mobile or agent ID.']);
        }

        $this->memberships->inviteExistingAgent($agency, $agent);

        return redirect()->route('portal.agents.index', ['tab' => 'pending'])
            ->with('success', "Invitation sent to {$agent->name}. Once they accept, Super Admin approves the move.");
    }

    public function acceptRequest($id)
    {
        $agency = $this->agency();
        $this->memberships->respondToJoinRequest($agency, $this->findMembership($agency, $id), true);

        return back()->with('success', 'Request accepted — now awaiting Super Admin approval.');
    }

    public function declineRequest($id)
    {
        $agency = $this->agency();
        $this->memberships->respondToJoinRequest($agency, $this->findMembership($agency, $id), false);

        return back()->with('success', 'Request declined.');
    }

    /** Withdraw an invitation or a pending add. */
    public function cancel($id)
    {
        $agency = $this->agency();
        $membership = $this->findMembership($agency, $id);
        abort_unless(in_array($membership->status, [AgencyAgent::INVITED, AgencyAgent::PENDING], true), 422);
        $this->memberships->cancel($membership, AssignmentActor::AGENCY);

        return back()->with('success', 'Cancelled.');
    }

    public function suspend($id)
    {
        $agency = $this->agency();
        $this->memberships->suspend($this->findMembership($agency, $id), AssignmentActor::AGENCY);

        return back()->with('success', 'Agent suspended — they won\'t receive new leads until reactivated.');
    }

    public function reactivate($id)
    {
        $agency = $this->agency();
        $this->memberships->reactivate($this->findMembership($agency, $id));

        return back()->with('success', 'Agent reactivated.');
    }

    /** Remove an agent: account and history stay; agency listings go to reassign_to or become unassigned. */
    public function remove(Request $request, $id)
    {
        $agency = $this->agency();
        $membership = $this->findMembership($agency, $id);
        $request->validate(['reassign_to' => 'nullable|integer']);

        $this->memberships->endMembership($membership, AssignmentActor::portal($agency),
            $request->filled('reassign_to') ? (int) $request->input('reassign_to') : null);

        return redirect()->route('portal.agents.index')->with('success', "{$membership->agent->name} was removed from your agency.");
    }

    private function slotMessage(PortalUser $agency): string
    {
        $limit = (int) $agency->plan?->agent_limit;

        return $limit > 0
            ? "Your plan allows up to {$limit} team agent" . ($limit === 1 ? '' : 's') . '. Upgrade your plan to add more.'
            : 'Team agent accounts are not included in your plan. Upgrade to add agents.';
    }
}
