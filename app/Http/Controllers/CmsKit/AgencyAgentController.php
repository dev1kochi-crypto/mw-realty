<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\AgencyAgent;
use App\Services\Agency\AgencyMembershipService;
use App\Services\Agency\AssignmentActor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Super Admin moderation of agency ⇄ agent memberships (Clients › Agency Agents): approve or
 * reject agents an agency added / invited, and suspend, reactivate or remove active ones.
 * Paged and searched on the server — this table grows with every invitation ever sent.
 */
class AgencyAgentController extends Controller
{
    private const FILTERS = [
        'pending' => [AgencyAgent::PENDING],
        'active' => [AgencyAgent::APPROVED],
        'suspended' => [AgencyAgent::SUSPENDED],
        'awaiting' => [AgencyAgent::INVITED, AgencyAgent::REQUESTED],
        'closed' => [AgencyAgent::INACTIVE, AgencyAgent::REJECTED, AgencyAgent::DECLINED, AgencyAgent::CANCELLED],
    ];

    public function __construct(private readonly AgencyMembershipService $memberships)
    {
    }

    public function index(Request $request)
    {
        $filter = array_key_exists($request->query('status'), self::FILTERS) ? $request->query('status') : 'pending';
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $memberships = AgencyAgent::with(['agency:id,name,company_name,type', 'agent:id,name,email,phone,status,type'])
            ->whereIn('status', self::FILTERS[$filter])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('agent', fn ($a) => $a->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                ->orWhereHas('agency', fn ($a) => $a->where('company_name', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $counts = AgencyAgent::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('agency-agents.index', [
            'memberships' => $memberships,
            'filter' => $filter,
            'search' => $search,
            'filterCounts' => collect(self::FILTERS)->map(fn ($statuses) => (int) collect($statuses)->sum(fn ($s) => $counts[$s] ?? 0)),
        ]);
    }

    public function approve($id)
    {
        $membership = $this->memberships->approve(AgencyAgent::findOrFail($id), Auth::guard('cms')->id());

        return back()->with('success', "{$membership->agent->name} approved for {$membership->agency->displayName()}.");
    }

    public function reject(Request $request, $id)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);
        $this->memberships->reject(AgencyAgent::findOrFail($id), $request->input('reason'), Auth::guard('cms')->id());

        return back()->with('success', 'Membership rejected.');
    }

    public function suspend($id)
    {
        $this->memberships->suspend(AgencyAgent::findOrFail($id), AssignmentActor::ADMIN);

        return back()->with('success', 'Agent suspended.');
    }

    public function reactivate($id)
    {
        $this->memberships->reactivate(AgencyAgent::findOrFail($id));

        return back()->with('success', 'Agent reactivated.');
    }

    public function remove($id)
    {
        $this->memberships->endMembership(AgencyAgent::findOrFail($id), AssignmentActor::admin(Auth::guard('cms')->id()));

        return back()->with('success', 'Agent removed from the agency.');
    }
}
