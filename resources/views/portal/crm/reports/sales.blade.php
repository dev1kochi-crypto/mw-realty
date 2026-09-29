@extends('portal.crm._layout')

@section('title', 'Sales Report')

@section('crm-content')
@include('portal.crm.reports._shell')

@php
    $k = $data['kpis'];
    $rangeLabel = \App\Services\Crm\PortalReportService::RANGES[$days];
    $aed = fn ($value) => 'AED ' . number_format((float) $value);
    $change = function ($pct) use ($days) {
        if ($pct === null) return '';
        $up = $pct >= 0;
        return '<span class="rp-change ' . ($up ? 'rp-change--up' : 'rp-change--down') . '" title="vs the previous ' . $days . ' days"><i class="fas fa-arrow-' . ($up ? 'up' : 'down') . '"></i>' . abs($pct) . '%</span>';
    };
    $outcome = array_key_exists($filters['outcome'] ?? '', \App\Services\Crm\PortalReportService::SALE_OUTCOMES) ? $filters['outcome'] : 'won';
    $breakdown = fn ($rows) => max(1, (float) collect($rows)->max('total'));
@endphp

@if($data['plans'])
@php $p = $data['plans']; @endphp
<div class="portal-card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <div class="portal-section-title mb-0">MW Realty Plan Sales</div>
            <div class="text-muted small">Paid plan subscriptions from agents &amp; agencies ({{ $rangeLabel }}). Visible to Super Admin only.</div>
        </div>
        <a href="{{ route('cms.payments.index') }}" class="btn btn-sm portal-btn-ghost">All Payments <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <div class="row g-3 mb-3">
        @foreach([
            ['Plan Sales', $aed($p['total']) . $change($p['change']), 'fa-coins', 'indigo'],
            ['Payments', number_format($p['count']), 'fa-receipt', 'teal'],
            ['Paying Accounts', number_format($p['paying_accounts']), 'fa-users', 'amber'],
            ['Coupon Discounts', $aed($p['discounts']), 'fa-tags', 'rose'],
        ] as [$label, $value, $icon, $tone])
        <div class="col-6 col-lg-3">
            <div class="portal-stat-card">
                <div class="portal-stat-icon {{ $tone }}"><i class="fas {{ $icon }}"></i></div>
                <div><div class="portal-stat-value">{!! $value !!}</div><div class="portal-stat-label">{{ $label }}</div></div>
            </div>
        </div>
        @endforeach
    </div>
    <div class="row g-3">
        <div class="col-lg-8"><div class="rp-chart rp-chart--sm"><canvas id="rpPlanSales"></canvas></div></div>
        <div class="col-lg-4">
            @forelse($p['byPlan'] as $plan)
            <div class="d-flex justify-content-between py-2 @if(!$loop->last) border-bottom @endif">
                <span><span class="fw-semibold">{{ $plan->plan_name }}</span> <span class="text-muted small">&middot; {{ $plan->payments }} {{ \Illuminate\Support\Str::plural('payment', $plan->payments) }}</span></span>
                <span class="fw-bold">{{ $aed($plan->total) }}</span>
            </div>
            @empty
            <div class="rp-empty">No paid plan payments in this period.</div>
            @endforelse
        </div>
    </div>
</div>
<div class="portal-section-title mt-4">Property Sales <span class="text-muted fw-normal small">— all agents &amp; agencies</span></div>
@endif

<div class="row g-3 mb-3">
    @foreach([
        ['Total Sales', $aed($k['won_value']) . $change($k['change']), 'fa-sack-dollar', 'indigo'],
        ['Deals Closed', number_format($k['won_count']), 'fa-handshake', 'teal'],
        ['Average Deal', $aed($k['average']), 'fa-chart-line', 'amber'],
        ['Win Rate', $k['win_rate'] === null ? '—' : $k['win_rate'] . '%', 'fa-trophy', 'rose'],
    ] as [$label, $value, $icon, $tone])
    <div class="col-6 col-lg-3">
        <div class="portal-stat-card">
            <div class="portal-stat-icon {{ $tone }}"><i class="fas {{ $icon }}"></i></div>
            <div><div class="portal-stat-value">{!! $value !!}</div><div class="portal-stat-label">{{ $label }}</div></div>
        </div>
    </div>
    @endforeach
    @foreach([
        ['Lost Deals', $aed($k['lost_value']), number_format($k['lost_count']) . ' ' . \Illuminate\Support\Str::plural('deal', $k['lost_count']), 'fa-circle-xmark', 'rose'],
        ['Open Pipeline', $aed($k['pipeline_value']), number_format($k['pipeline_count']) . ' open ' . \Illuminate\Support\Str::plural('deal', $k['pipeline_count']), 'fa-filter-circle-dollar', 'indigo'],
        ['All-Time Sales', $aed($k['all_time_value']), number_format($k['all_time_count']) . ' ' . \Illuminate\Support\Str::plural('deal', $k['all_time_count']) . ' ever', 'fa-landmark', 'teal'],
    ] as [$label, $value, $sub, $icon, $tone])
    <div class="col-md-4">
        <div class="portal-stat-card">
            <div class="portal-stat-icon {{ $tone }}"><i class="fas {{ $icon }}"></i></div>
            <div><div class="portal-stat-value">{{ $value }}</div><div class="portal-stat-label">{{ $label }} &middot; {{ $sub }}</div></div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">Sales Over Time</div>
            <div class="rp-chart"><canvas id="rpSalesOverTime"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">Sale vs Rent</div>
            @if($data['byListingType']->isEmpty())<div class="rp-empty">No deals closed in this period.</div>
            @else<div class="rp-chart"><canvas id="rpSalesByListing"></canvas></div>@endif
        </div>
    </div>

    @foreach(array_filter([
        'Sales by Agent' => $data['byAgent']->isNotEmpty() ? $data['byAgent'] : null,
        'Sales by Account' => $data['byAccount']->isNotEmpty() ? $data['byAccount'] : null,
        'Sales by Property Type' => $data['byPropertyType']->isNotEmpty() ? $data['byPropertyType'] : null,
        'Sales by Location' => $data['byLocation']->isNotEmpty() ? $data['byLocation'] : null,
    ]) as $title => $rows)
    <div class="col-lg-6">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">{{ $title }}</div>
            @php $max = $breakdown($rows); @endphp
            @foreach($rows as $row)
            @php $name = isset($row->type) ? ($row->type === 'company' ? ($row->company_name ?: $row->name) : $row->name) : $row->name; @endphp
            <div class="py-2 @if(!$loop->last) border-bottom @endif">
                <div class="d-flex justify-content-between gap-2"><span class="fw-semibold text-truncate">{{ $name }}</span><span class="fw-bold text-nowrap">{{ $aed($row->total) }}</span></div>
                <div class="d-flex align-items-center gap-2">
                    <div class="rp-bar flex-grow-1"><span style="width: {{ $row->total / $max * 100 }}%;"></span></div>
                    <span class="text-muted small text-nowrap">{{ $row->deals }} {{ \Illuminate\Support\Str::plural('deal', $row->deals) }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach

    <div class="col-12">
        <div class="portal-card p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <div class="portal-section-title mb-0">All Deals</div>
                    <div class="text-muted small">{{ number_format($data['deals']->total()) }} {{ \Illuminate\Support\Str::plural('deal', $data['deals']->total()) }} &middot; {{ $rangeLabel }}</div>
                </div>
                <a href="{{ route('portal.crm.reports.index', ['report' => 'sales', 'range' => $days, 'export' => 'csv'] + array_filter($filters)) }}" class="btn btn-sm portal-btn-ghost"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
            </div>

            <form method="GET" action="{{ route('portal.crm.reports.index', ['report' => 'sales']) }}" class="row g-2 mb-3">
                <input type="hidden" name="range" value="{{ $days }}">
                <div class="col-md-5"><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Search client, email, phone, property, reference or agent"></div>
                <div class="col-6 col-md-2">
                    <select name="outcome" class="form-select form-select-sm">
                        @foreach(\App\Services\Crm\PortalReportService::SALE_OUTCOMES as $value => $label)
                        <option value="{{ $value }}" @selected($outcome === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="listing" class="form-select form-select-sm">
                        <option value="">Sale &amp; Rent</option>
                        <option value="sale" @selected(($filters['listing'] ?? '') === 'sale')>Sale</option>
                        <option value="rent" @selected(($filters['listing'] ?? '') === 'rent')>Rent</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-sm btn-portal-primary flex-grow-1" type="submit"><i class="fas fa-search me-1"></i>Filter</button>
                    @if(array_filter($filters))<a href="{{ route('portal.crm.reports.index', ['report' => 'sales', 'range' => $days]) }}" class="btn btn-sm portal-btn-ghost">Clear</a>@endif
                </div>
            </form>

            @if($data['deals']->isEmpty())
            <div class="rp-empty">No deals match. Move a lead to a won stage (e.g. “Closed Won”) in the CRM and it shows up here.</div>
            @else
            <div class="table-responsive">
                <table class="table rp-table mb-0">
                    <thead><tr>
                        <th>Closed</th><th>Client</th><th>Property</th><th>Agent</th>@if($isAdmin)<th>Account</th>@endif<th>Outcome</th><th class="text-end">Value</th>
                    </tr></thead>
                    <tbody>
                        @foreach($data['deals'] as $deal)
                        @php
                            $t = json_decode($deal->property_translations, true) ?: [];
                            $title = $t[app()->getLocale()]['title'] ?? ($t['en']['title'] ?? 'Property');
                        @endphp
                        <tr>
                            <td class="small text-muted text-nowrap">{{ $deal->closed_at?->format('d M Y') }}</td>
                            <td>
                                <a href="{{ route('portal.crm.leads.show', $deal->id) }}" class="fw-semibold text-decoration-none">{{ $deal->name }}</a>
                                <div class="text-muted small">{{ $deal->email ?: $deal->formatted_phone }}</div>
                            </td>
                            <td>
                                <div class="text-truncate" style="max-width: 260px;">{{ $title }}</div>
                                <div class="text-muted small">{{ $deal->reference_no }} &middot; {{ ucfirst($deal->listing_type) }}@if($deal->location) &middot; {{ ucwords(str_replace('-', ' ', $deal->location)) }}@endif</div>
                            </td>
                            <td class="small">{{ $deal->agent?->name ?? '—' }}</td>
                            @if($isAdmin)<td class="small">{{ $deal->owner?->displayName() ?? '—' }}</td>@endif
                            <td><span class="badge" style="background: {{ $deal->stage?->color ?? '#64748b' }};">{{ $deal->stage?->name }}</span></td>
                            <td class="text-end fw-bold text-nowrap">{{ $aed($deal->deal_value) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $data['deals']->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
</div>

<div class="text-muted small mt-3">
    <i class="fas fa-info-circle me-1"></i>A sale is a lead in a closed stage that isn't a “lost / dropped” stage, valued at its property's listed price (AED) on the date it was closed. Leads without a property aren't counted.
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const overTime = @json($data['overTime']);
    rpChart('rpSalesOverTime', 'bar', Object.keys(overTime), Object.values(overTime));
    const listing = @json($data['byListingType']);
    rpChart('rpSalesByListing', 'doughnut', Object.keys(listing), Object.values(listing), ['#264373', '#04a1cc', '#f59e0b']);
    @if($data['plans'])
    const plans = @json($data['plans']['overTime']);
    rpChart('rpPlanSales', 'line', Object.keys(plans), Object.values(plans));
    @endif
});
</script>
@endpush
@endsection
