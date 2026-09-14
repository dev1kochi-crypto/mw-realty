<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Models\Lead;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PortalReportController extends Controller
{
    use ScopesPortalOwner;

    public function index(Request $request)
    {
        $ownerId = $this->ownerId();
        $rangeDays = in_array((int) $request->input('range'), [30, 90], true) ? (int) $request->input('range') : 30;

        $leadQuery = fn () => Lead::forOwner($ownerId);
        $propertyQuery = fn () => Property::when($ownerId, fn ($q) => $q->where('portal_user_id', $ownerId));

        $leadsByStatus = $leadQuery()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $leadsByStage = $leadQuery()
            ->join('lead_stages', 'lead_stages.id', '=', 'leads.stage_id')
            ->selectRaw('lead_stages.name, lead_stages.color, count(*) as total')
            ->groupBy('lead_stages.id', 'lead_stages.name', 'lead_stages.color')
            ->get();

        $propertiesByStatus = $propertyQuery()
            ->selectRaw("CASE WHEN status = 1 THEN 'active' ELSE 'inactive' END as status_label, count(*) as total")
            ->groupBy('status_label')
            ->pluck('total', 'status_label');

        $leadsOverTime = $leadQuery()
            ->where('created_at', '>=', now()->subDays($rangeDays)->startOfDay())
            ->selectRaw('DATE(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $totalLeads = $leadQuery()->count();
        // "Closed" here means the pipeline stage marks it done (Closed Won/Lost)
        // — the lead's own status is now just Active/Inactive, not a deal outcome.
        $closedLeads = $leadQuery()->whereHas('stage', fn ($s) => $s->where('is_closed', true))->count();
        $conversionRate = $totalLeads > 0 ? round($closedLeads / $totalLeads * 100, 1) : 0.0;

        return view('portal.crm.reports.index', [
            'leadsByStatus' => $leadsByStatus,
            'leadsByStage' => $leadsByStage,
            'propertiesByStatus' => $propertiesByStatus,
            'leadsOverTime' => $leadsOverTime,
            'totalLeads' => $totalLeads,
            'closedLeads' => $closedLeads,
            'conversionRate' => $conversionRate,
            'rangeDays' => $rangeDays,
            'activeProperties' => $propertyQuery()->where('status', true)->count(),
            'isAdmin' => $this->isAdmin(),
        ]);
    }
}
