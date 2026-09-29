<?php

namespace App\Http\Controllers\Portal\Crm;

use App\Http\Controllers\Portal\Crm\Concerns\ScopesPortalOwner;
use App\Services\Crm\PortalReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Reports — Leads, Properties, Sales and (agencies only) Agents. The whole section is a plan
 * entitlement (plans.reports_access); Super Admin always has it and sees every account's data.
 */
class PortalReportController extends Controller
{
    use ScopesPortalOwner;

    public const REPORTS = ['leads' => 'Leads', 'properties' => 'Properties', 'sales' => 'Sales', 'agents' => 'Agents'];

    public function __construct(private readonly PortalReportService $reports)
    {
    }

    public function index(Request $request, string $report = 'leads')
    {
        // The Sales report used to be called Revenue — keep old links working.
        if ($report === 'revenue') {
            return redirect()->route('portal.crm.reports.index', ['report' => 'sales'] + $request->query());
        }

        $viewer = $this->owner();
        if ($viewer && !$viewer->hasReportsAccess()) {
            return view('portal.crm.reports.locked', ['plan' => $viewer->plan]);
        }

        // Only an agency has agents to report on.
        $available = collect(self::REPORTS)->when($viewer && $viewer->type !== 'company', fn ($r) => $r->except('agents'));
        abort_unless($available->has($report), 404);

        $days = array_key_exists((int) $request->input('range'), PortalReportService::RANGES) ? (int) $request->input('range') : 30;

        if ($report === 'sales') {
            $filters = $request->only(['q', 'outcome', 'listing']);
            if ($request->input('export') === 'csv') {
                return $this->exportSales($viewer, $days, $filters);
            }
            $data = $this->reports->sales($viewer, $days, $filters);
        }

        return view("portal.crm.reports.{$report}", [
            'report' => $report,
            'reports' => $available,
            'days' => $days,
            'isAdmin' => $this->isAdmin(),
            'filters' => $filters ?? [],
            'data' => $data ?? $this->reports->{$report}($viewer, $days),
        ]);
    }

    /** Every deal matching the Sales filters, streamed in chunks so a large export never sits in memory. */
    private function exportSales($viewer, int $days, array $filters)
    {
        $query = $this->reports->salesDeals($viewer, $days, $filters);
        $isAdmin = !$viewer;

        return response()->streamDownload(function () use ($query, $isAdmin) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_filter(['Closed', 'Outcome', 'Client', 'Email', 'Phone', 'Property', 'Reference', 'Listing', 'Type', 'Location',
                'Agent', $isAdmin ? 'Account' : null, 'Value (AED)'], fn ($v) => $v !== null));

            $query->chunk(500, function ($deals) use ($out, $isAdmin) {
                foreach ($deals as $deal) {
                    $t = json_decode($deal->property_translations, true) ?: [];
                    $row = [
                        $deal->closed_at?->format('Y-m-d'),
                        $deal->stage?->name,
                        $deal->name,
                        $deal->email,
                        trim(($deal->phone_country_code ? $deal->phone_country_code . ' ' : '') . $deal->phone),
                        $t[app()->getLocale()]['title'] ?? ($t['en']['title'] ?? ''),
                        $deal->reference_no,
                        ucfirst((string) $deal->listing_type),
                        ucwords(str_replace('-', ' ', (string) $deal->property_type)),
                        ucwords(str_replace('-', ' ', (string) $deal->location)),
                        $deal->agent?->name,
                    ];
                    if ($isAdmin) {
                        $row[] = $deal->owner?->displayName();
                    }
                    $row[] = (float) $deal->deal_value;
                    fputcsv($out, $row);
                }
            });
            fclose($out);
        }, 'sales-report-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
