<?php

namespace App\Http\Controllers\Portal;

use App\Models\AgencyAgent;
use App\Models\PortalUser;
use App\Models\Property;
use App\Services\Agency\AgencyMembershipService;
use App\Services\Agency\AssignmentActor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * "My Agency" — the agent's side of agency membership: current agency, invitations to answer,
 * asking to join an agency, leaving, and optionally moving personal listings into the agency.
 * Agent-type portal logins only; every membership id is scoped to the signed-in agent.
 */
class AgencyController extends Controller
{
    public function __construct(private readonly AgencyMembershipService $memberships)
    {
    }

    protected function agent(): PortalUser
    {
        $agent = Auth::guard('portal')->user();
        abort_unless($agent && $agent->isAgent(), 403);

        return $agent;
    }

    protected function findMembership(PortalUser $agent, $id): AgencyAgent
    {
        $membership = AgencyAgent::where('agent_id', $agent->id)->with('agency')->findOrFail($id);
        Gate::forUser($agent)->authorize('respond', $membership);

        return $membership;
    }

    public function index()
    {
        $agent = $this->agent();

        return view('portal.agency.index', [
            'agent' => $agent,
            'current' => $agent->currentMembership()->with('agency')->first(),
            'invitations' => $agent->memberships()->where('status', AgencyAgent::INVITED)->with('agency')->latest('id')->get(),
            'openRequest' => $agent->memberships()->whereIn('status', [AgencyAgent::REQUESTED, AgencyAgent::PENDING])->with('agency')->latest('id')->first(),
            'history' => $agent->memberships()->whereNotIn('status', [AgencyAgent::INVITED])->with('agency')->latest('id')->paginate(10),
            'personalProperties' => $agent->isAgencyAgent()
                ? Property::where('portal_user_id', $agent->id)->latest('id')->paginate(10, ['*'], 'properties_page')
                : null,
        ]);
    }

    /** Agency picker for "Request to join" — server-side search, 20 per page (never the full list). */
    public function searchAgencies(Request $request)
    {
        $this->agent();
        $term = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $page = PortalUser::companies()->approved()->where('is_active', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('company_name', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")))
            ->orderBy('company_name')
            ->paginate(20, ['id', 'name', 'company_name']);

        return response()->json([
            'results' => collect($page->items())->map(fn ($agency) => ['id' => $agency->id, 'text' => $agency->displayName()]),
            'pagination' => ['more' => $page->hasMorePages()],
        ]);
    }

    public function requestToJoin(Request $request)
    {
        $agent = $this->agent();
        $request->validate(['agency_id' => 'required|integer']);
        $agency = PortalUser::companies()->findOrFail($request->integer('agency_id'));

        $this->memberships->requestToJoin($agent, $agency);

        return back()->with('success', "Request sent to {$agency->displayName()}.");
    }

    public function acceptInvitation($id)
    {
        $agent = $this->agent();
        $membership = $this->findMembership($agent, $id);
        $this->memberships->respondToInvitation($agent, $membership, true);

        return back()->with('success', "Invitation accepted — Super Admin will approve your move to {$membership->agency->displayName()}.");
    }

    public function declineInvitation($id)
    {
        $agent = $this->agent();
        $this->memberships->respondToInvitation($agent, $this->findMembership($agent, $id), false);

        return back()->with('success', 'Invitation declined.');
    }

    /** Withdraw the agent's own join request (before the agency / admin acts on it). */
    public function cancelRequest($id)
    {
        $agent = $this->agent();
        $membership = $this->findMembership($agent, $id);
        abort_unless($membership->initiated_by === AssignmentActor::AGENT
            && in_array($membership->status, [AgencyAgent::REQUESTED, AgencyAgent::PENDING], true), 422);
        $this->memberships->cancel($membership, AssignmentActor::AGENT);

        return back()->with('success', 'Request withdrawn.');
    }

    /** Leave the current agency — the account and personal listings stay; agency listings stay with the agency. */
    public function leave()
    {
        $agent = $this->agent();
        $membership = $agent->currentMembership;
        abort_unless($membership, 404);

        $this->memberships->endMembership($membership, AssignmentActor::portal($agent));

        return redirect()->route('portal.agency.index')->with('success', 'You have left ' . $membership->agency->displayName() . '. You are now an independent agent.');
    }

    public function transferProperty($id)
    {
        $agent = $this->agent();
        $property = Property::where('portal_user_id', $agent->id)->findOrFail($id);

        $this->memberships->transferPropertyToAgency($agent, $property);

        return back()->with('success', 'Listing transferred to your agency — you remain its agent.');
    }
}
