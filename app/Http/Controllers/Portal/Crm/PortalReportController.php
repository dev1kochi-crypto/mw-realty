<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Services\Crm\PortalReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Reports — Leads, Properties, Revenue and (agencies only) Agents. The whole section is a plan
 * entitlement (plans.reports_access); Super Admin always has it and sees every account's data.
 */
class PortalReportController extends Controller
{
    use ScopesPortalOwner;

    public const REPORTS = ['leads' => 'Leads', 'properties' => 'Properties', 'revenue' => 'Revenue', 'agents' => 'Agents'];

    public function __construct(private readonly PortalReportService $reports)
    {
    }

    public function index(Request $request, string $report = 'leads')
    {
        $viewer = $this->owner();
        if ($viewer && !$viewer->hasReportsAccess()) {
            return view('portal.crm.reports.locked', ['plan' => $viewer->plan]);
        }

        // Only an agency has agents to report on.
        $available = collect(self::REPORTS)->when($viewer && $viewer->type !== 'company', fn ($r) => $r->except('agents'));
        abort_unless($available->has($report), 404);

        $days = array_key_exists((int) $request->input('range'), PortalReportService::RANGES) ? (int) $request->input('range') : 30;

        return view("portal.crm.reports.{$report}", [
            'report' => $report,
            'reports' => $available,
            'days' => $days,
            'isAdmin' => $this->isAdmin(),
            'data' => $this->reports->{$report}($viewer, $days),
        ]);
    }
}
