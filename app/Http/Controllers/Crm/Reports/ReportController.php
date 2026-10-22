<?php

namespace App\Http\Controllers\Crm\Reports;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Models\PortalUser;
use App\Models\Property;
use App\Services\Crm\PortalReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;

/**
 * @group CRM Reports
 *
 * Reports — Leads, Properties, Sales and (agencies only) Agents. The whole section is a plan
 * entitlement (plans.reports_access): without it every report answers `locked` (with the plan to
 * mention) instead of data. Super Admin always has it and sees every account's data.
 * Chart series come as `{labels: [], values: []}`.
 */
class ReportController extends Controller
{
    use ScopesPortalOwner;

    public const REPORTS = ['leads' => 'Leads', 'properties' => 'Properties', 'sales' => 'Sales', 'agents' => 'Agents'];

    public function __construct(private readonly PortalReportService $reports)
    {
    }

    /**
     * A report
     *
     * @urlParam report string leads, properties, sales or agents (agencies / Super Admin). Example: leads
     * @queryParam range integer 7, 30, 90 or 365 days. Example: 30
     * @queryParam q string Sales only — client, email, phone, property, reference or agent. Example: marina
     * @queryParam outcome string Sales only — won, lost or all. Example: won
     * @queryParam listing string Sales only — sale or rent. Example: sale
     * @queryParam page integer Sales only — the deals page. Example: 1
     */
    public function show(Request $request, string $report = 'leads')
    {
        $viewer = $this->owner();
        $available = $this->available($viewer);

        $base = [
            'report' => $report,
            'reports' => $available->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'ranges' => collect(PortalReportService::RANGES)->map(fn ($label, $days) => ['value' => $days, 'label' => $label])->values(),
            'is_admin' => $this->isAdmin(),
            'owner_type' => $viewer?->type,
        ];

        if ($viewer && !$viewer->hasReportsAccess()) {
            $agency = $viewer->isOnAgencyPlan() ? $viewer->company : null;

            return response()->json($base + ['locked' => [
                'plan' => $viewer->effectivePlan()?->getTranslation('name'),
                'agency' => $agency?->displayName(),
            ]]);
        }

        abort_unless($available->has($report), 404);
        $days = $this->days($request);

        $data = match ($report) {
            'leads' => $this->leads($viewer, $days),
            'properties' => $this->properties($viewer, $days),
            'agents' => $this->agents($viewer, $days),
            'sales' => $this->sales($viewer, $days, $this->salesFilters($request)),
        };

        return response()->json($base + [
            'locked' => null,
            'days' => $days,
            'range_label' => PortalReportService::RANGES[$days],
            'data' => $data,
        ]);
    }

    /**
     * Export the Sales deals (CSV)
     *
     * Every deal matching the Sales filters, streamed in chunks so a large export never sits in memory.
     */
    public function exportSales(Request $request)
    {
        $viewer = $this->owner();
        abort_if($viewer && !$viewer->hasReportsAccess(), 403);

        $query = $this->reports->salesDeals($viewer, $this->days($request), $this->salesFilters($request));
        $isAdmin = !$viewer;

        return response()->streamDownload(function () use ($query, $isAdmin) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_filter(['Closed', 'Outcome', 'Client', 'Email', 'Phone', 'Property', 'Reference', 'Listing', 'Type', 'Location',
                'Agent', $isAdmin ? 'Account' : null, 'Value (AED)'], fn ($v) => $v !== null));

            $query->chunk(500, function ($deals) use ($out, $isAdmin) {
                foreach ($deals as $deal) {
                    $row = [
                        $deal->closed_at?->format('Y-m-d'),
                        $deal->stage?->name,
                        $deal->name,
                        $deal->email,
                        trim(($deal->phone_country_code ? $deal->phone_country_code . ' ' : '') . $deal->phone),
                        $this->dealTitle($deal, ''),
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

    private function leads(?PortalUser $viewer, int $days): array
    {
        $data = $this->reports->leads($viewer, $days);

        return [
            'kpis' => $data['kpis'],
            'over_time' => $this->series($data['overTime']),
            'by_stage' => $data['byStage']->map(fn ($s) => ['name' => $s->name, 'color' => $s->color, 'total' => (int) $s->total])->values(),
            'by_source' => $this->series($data['bySource']),
            'top_properties' => $data['topProperties']->map(fn ($row) => [
                'title' => $row->property?->getTranslation('title') ?? 'Deleted property',
                'reference_no' => $row->property?->reference_no,
                'listing_type' => $row->property ? ucfirst((string) $row->property->listing_type) : null,
                'total' => (int) $row->total,
            ])->values(),
        ];
    }

    private function properties(?PortalUser $viewer, int $days): array
    {
        $data = $this->reports->properties($viewer, $days);

        return [
            'kpis' => $data['kpis'],
            'over_time' => $this->series($data['overTime']),
            'by_listing_type' => $this->series($data['byListingType']),
            'by_segment' => $this->series($data['bySegment']),
            'by_type' => $this->series($data['byType']),
            'top_by_leads' => $data['topByLeads']->map(fn (Property $p) => [
                'id' => $p->id,
                'title' => $p->getTranslation('title') ?? 'Untitled',
                'reference_no' => $p->reference_no,
                'listing_type' => ucfirst((string) $p->listing_type),
                'currency' => $p->currency ?: 'AED',
                'price' => (float) $p->price,
                'active' => (bool) $p->status,
                'leads_in_range' => (int) $p->leads_in_range,
                'leads_total' => (int) $p->leads_total,
            ])->values(),
        ];
    }

    private function agents(?PortalUser $viewer, int $days): array
    {
        $data = $this->reports->agents($viewer, $days);

        return [
            'kpis' => $data['kpis'],
            'chart' => $this->series($data['chart']),
            'rows' => $data['rows']->map(fn ($row) => [
                'agent' => ['name' => $row->agent->name, 'email' => $row->agent->email],
                'agency' => $row->agency?->displayName(),
                'status_label' => $row->membership->statusLabel(),
                'status_tone' => $row->membership->statusTone(),
                'properties' => $row->properties,
                'active_properties' => $row->active_properties,
                'leads_in_range' => $row->leads_in_range,
                'leads' => $row->leads,
                'closed' => $row->closed,
                'conversion' => $row->conversion,
                'last_lead_at' => $row->last_lead_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function sales(?PortalUser $viewer, int $days, array $filters): array
    {
        $data = $this->reports->sales($viewer, $days, $filters);
        $deals = $data['deals'];
        $breakdown = fn (Collection $rows) => $rows->map(fn ($row) => [
            // Sales by Account rows carry the account type — an agency shows its company name.
            'name' => isset($row->type) && $row->type === 'company' ? ($row->company_name ?: $row->name) : $row->name,
            'total' => (float) $row->total,
            'deals' => (int) $row->deals,
        ])->values();

        return [
            'kpis' => $data['kpis'],
            'over_time' => $this->series($data['overTime']),
            'by_listing_type' => $this->series($data['byListingType']),
            'breakdowns' => collect([
                'Sales by Agent' => $data['byAgent'],
                'Sales by Account' => $data['byAccount'],
                'Sales by Property Type' => $data['byPropertyType'],
                'Sales by Location' => $data['byLocation'],
            ])->filter(fn ($rows) => $rows->isNotEmpty())->map(fn ($rows, $title) => ['title' => $title, 'rows' => $breakdown($rows)])->values(),
            'deals' => [
                'data' => collect($deals->items())->map(fn ($deal) => [
                    'id' => $deal->id,
                    'closed_at' => $deal->closed_at?->toIso8601String(),
                    'name' => $deal->name,
                    'contact' => $deal->email ?: $deal->formatted_phone,
                    'property' => $this->dealTitle($deal, 'Property'),
                    'reference_no' => $deal->reference_no,
                    'listing_type' => ucfirst((string) $deal->listing_type),
                    'location' => $deal->location ? ucwords(str_replace('-', ' ', $deal->location)) : null,
                    'agent' => $deal->agent?->name,
                    'account' => $deal->owner?->displayName(),
                    'stage' => $deal->stage ? ['name' => $deal->stage->name, 'color' => $deal->stage->color] : null,
                    'value' => (float) $deal->deal_value,
                ]),
                'meta' => [
                    'current_page' => $deals->currentPage(), 'last_page' => $deals->lastPage(), 'total' => $deals->total(),
                    'from' => $deals->firstItem(), 'to' => $deals->lastItem(),
                ],
            ],
            'filters' => [
                'q' => (string) ($filters['q'] ?? ''),
                'outcome' => array_key_exists($filters['outcome'] ?? '', PortalReportService::SALE_OUTCOMES) ? $filters['outcome'] : 'won',
                'listing' => in_array($filters['listing'] ?? '', ['sale', 'rent'], true) ? $filters['listing'] : '',
            ],
            'outcomes' => collect(PortalReportService::SALE_OUTCOMES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            // MW Realty's own plan sales — Super Admin only.
            'plans' => $data['plans'] ? [
                'total' => $data['plans']['total'],
                'change' => $data['plans']['change'],
                'count' => $data['plans']['count'],
                'discounts' => $data['plans']['discounts'],
                'paying_accounts' => $data['plans']['paying_accounts'],
                'over_time' => $this->series($data['plans']['overTime']),
                'by_plan' => collect($data['plans']['byPlan'])->map(fn ($plan) => [
                    'name' => $plan->plan_name, 'total' => (float) $plan->total, 'payments' => (int) $plan->payments,
                ])->values(),
                'payments_url' => route('cms.payments.index'),
            ] : null,
        ];
    }

    /** Only an agency (or Super Admin) has agents to report on. */
    private function available(?PortalUser $viewer): Collection
    {
        return collect(self::REPORTS)->when($viewer && $viewer->type !== 'company', fn ($r) => $r->except('agents'));
    }

    private function days(Request $request): int
    {
        return array_key_exists((int) $request->input('range'), PortalReportService::RANGES) ? (int) $request->input('range') : 30;
    }

    private function salesFilters(Request $request): array
    {
        return $request->only(['q', 'outcome', 'listing']);
    }

    /** label => value pairs as two arrays, so the order survives JSON (numeric labels would be re-sorted). */
    private function series(Collection $pairs): array
    {
        return ['labels' => $pairs->keys()->map(fn ($k) => (string) $k)->values(), 'values' => $pairs->values()->map(fn ($v) => (float) $v)];
    }

    private function dealTitle($deal, string $fallback): string
    {
        $t = json_decode($deal->property_translations, true) ?: [];

        return $t[app()->getLocale()]['title'] ?? ($t['en']['title'] ?? $fallback);
    }
}
