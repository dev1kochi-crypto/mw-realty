@extends('portal.crm._layout')

@section('title', 'Revenue Report')

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
@endphp

@if($data['plans'])
@php $p = $data['plans']; @endphp
<div class="portal-card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <div class="portal-section-title mb-0">MW Realty Plan Revenue</div>
            <div class="text-muted small">Paid plan payments from agents &amp; agencies ({{ $rangeLabel }}). Visible to Super Admin only.</div>
        </div>
        <a href="{{ route('cms.payments.index') }}" class="btn btn-sm portal-btn-ghost">All Payments <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <div class="row g-3 mb-3">
        @foreach([
            ['Revenue', $aed($p['total']) . $change($p['change']), 'fa-coins', 'indigo'],
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
        <div class="col-lg-8"><div class="rp-chart rp-chart--sm"><canvas id="rpPlanRevenue"></canvas></div></div>
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
<div class="portal-section-title mt-4">Deal Revenue <span class="text-muted fw-normal small">— all agents &amp; agencies</span></div>
@endif

<div class="row g-3 mb-3">
    @foreach([
        ['Won Deal Value', $aed($k['won_value']) . $change($k['change']), 'fa-sack-dollar', 'indigo'],
        ['Deals Won', number_format($k['won_count']), 'fa-handshake', 'teal'],
        ['Average Deal', $aed($k['average']), 'fa-chart-line', 'amber'],
        ['Win Rate', $k['win_rate'] === null ? '—' : $k['win_rate'] . '%', 'fa-trophy', 'rose'],
        ['Open Pipeline', $aed($k['pipeline_value']), 'fa-filter-circle-dollar', 'indigo'],
    ] as [$label, $value, $icon, $tone])
    <div class="col-6 col-lg">
        <div class="portal-stat-card">
            <div class="portal-stat-icon {{ $tone }}"><i class="fas {{ $icon }}"></i></div>
            <div><div class="portal-stat-value">{!! $value !!}</div><div class="portal-stat-label">{{ $label }}</div></div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">Won Deal Value Over Time</div>
            <div class="rp-chart"><canvas id="rpRevenueOverTime"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">Sale vs Rent</div>
            @if($data['byListingType']->isEmpty())<div class="rp-empty">No deals won in this period.</div>
            @else<div class="rp-chart"><canvas id="rpRevenueByListing"></canvas></div>@endif
            <div class="text-muted small mt-2">Lost this period: <strong>{{ $aed($k['lost_value']) }}</strong> &middot; {{ number_format($k['pipeline_count']) }} open {{ \Illuminate\Support\Str::plural('deal', $k['pipeline_count']) }} in pipeline</div>
        </div>
    </div>

    @if($data['byAgent']->isNotEmpty())
    <div class="col-lg-5">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">Revenue by Agent</div>
            @php $maxAgent = max(1, (float) $data['byAgent']->max('total')); @endphp
            @foreach($data['byAgent'] as $row)
            <div class="py-2 @if(!$loop->last) border-bottom @endif">
                <div class="d-flex justify-content-between"><span class="fw-semibold">{{ $row->name }}</span><span class="fw-bold">{{ $aed($row->total) }}</span></div>
                <div class="d-flex align-items-center gap-2">
                    <div class="rp-bar flex-grow-1"><span style="width: {{ $row->total / $maxAgent * 100 }}%;"></span></div>
                    <span class="text-muted small">{{ $row->deals }} {{ \Illuminate\Support\Str::plural('deal', $row->deals) }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="{{ $data['byAgent']->isNotEmpty() ? 'col-lg-7' : 'col-12' }}">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">Recent Won Deals</div>
            @if($data['recentDeals']->isEmpty())
            <div class="rp-empty">No deals won in this period. Move a lead to a won stage (e.g. “Closed Won”) in the CRM and it shows up here.</div>
            @else
            <div class="table-responsive">
                <table class="table rp-table mb-0">
                    <thead><tr><th>Client</th><th>Property</th><th>Agent</th><th class="text-end">Value</th><th>Won</th></tr></thead>
                    <tbody>
                        @foreach($data['recentDeals'] as $deal)
                        @php $title = (json_decode($deal->property_translations, true) ?: [])[app()->getLocale()]['title'] ?? ((json_decode($deal->property_translations, true) ?: [])['en']['title'] ?? 'Property'); @endphp
                        <tr>
                            <td class="fw-semibold">{{ $deal->name }}</td>
                            <td>
                                <div class="text-truncate" style="max-width: 240px;">{{ $title }}</div>
                                <div class="text-muted small">{{ $deal->reference_no }} &middot; {{ ucfirst($deal->listing_type) }}</div>
                            </td>
                            <td class="small">{{ $deal->agent?->name ?? '—' }}</td>
                            <td class="text-end fw-bold text-nowrap">{{ $aed($deal->price) }}</td>
                            <td class="small text-muted text-nowrap">{{ $deal->closed_at?->format('d M Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

<div class="text-muted small mt-3">
    <i class="fas fa-info-circle me-1"></i>A won deal is a lead in a closed stage that isn't a “lost / dropped” stage, valued at its property's listed price (AED) on the date it was closed. Leads without a property aren't counted.
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const overTime = @json($data['overTime']);
    rpChart('rpRevenueOverTime', 'bar', Object.keys(overTime), Object.values(overTime));
    const listing = @json($data['byListingType']);
    rpChart('rpRevenueByListing', 'doughnut', Object.keys(listing), Object.values(listing), ['#264373', '#04a1cc', '#f59e0b']);
    @if($data['plans'])
    const plans = @json($data['plans']['overTime']);
    rpChart('rpPlanRevenue', 'line', Object.keys(plans), Object.values(plans));
    @endif
});
</script>
@endpush
@endsection
