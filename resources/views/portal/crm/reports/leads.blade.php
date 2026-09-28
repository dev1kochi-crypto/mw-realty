@extends('portal.crm._layout')

@section('title', 'Leads Report')

@section('crm-content')
@include('portal.crm.reports._shell')

@php $k = $data['kpis']; @endphp
<div class="row g-3 mb-3">
    <div class="col-6 col-lg">
        <div class="portal-stat-card">
            <div class="portal-stat-icon indigo"><i class="fas fa-address-book"></i></div>
            <div><div class="portal-stat-value">{{ number_format($k['total']) }}</div><div class="portal-stat-label">Total Leads</div></div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="portal-stat-card">
            <div class="portal-stat-icon teal"><i class="fas fa-user-plus"></i></div>
            <div>
                <div class="portal-stat-value">
                    {{ number_format($k['in_range']) }}
                    @if($k['change'] !== null)
                    <span class="rp-change {{ $k['change'] >= 0 ? 'rp-change--up' : 'rp-change--down' }}" title="vs the previous {{ $days }} days">
                        <i class="fas fa-arrow-{{ $k['change'] >= 0 ? 'up' : 'down' }}"></i>{{ abs($k['change']) }}%
                    </span>
                    @endif
                </div>
                <div class="portal-stat-label">New ({{ \App\Services\Crm\PortalReportService::RANGES[$days] }})</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="portal-stat-card">
            <div class="portal-stat-icon amber"><i class="fas fa-check-circle"></i></div>
            <div><div class="portal-stat-value">{{ number_format($k['closed']) }}</div><div class="portal-stat-label">Closed Leads</div></div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="portal-stat-card">
            <div class="portal-stat-icon rose"><i class="fas fa-percentage"></i></div>
            <div><div class="portal-stat-value">{{ $k['conversion'] }}%</div><div class="portal-stat-label">Conversion Rate</div></div>
        </div>
    </div>
    @if($k['unassigned'] !== null)
    <div class="col-6 col-lg">
        <a href="{{ route('portal.crm.leads.index') }}" class="portal-stat-card text-decoration-none">
            <div class="portal-stat-icon indigo"><i class="fas fa-user-slash"></i></div>
            <div><div class="portal-stat-value">{{ number_format($k['unassigned']) }}</div><div class="portal-stat-label">Not Assigned to an Agent</div></div>
        </a>
    </div>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">Leads Over Time</div>
            <div class="rp-chart"><canvas id="rpLeadsOverTime"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">Leads by Stage</div>
            @if($data['byStage']->isEmpty())<div class="rp-empty">No leads yet.</div>
            @else<div class="rp-chart"><canvas id="rpLeadsByStage"></canvas></div>@endif
        </div>
    </div>
    <div class="col-lg-6">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">Leads by Source</div>
            @if($data['bySource']->isEmpty())<div class="rp-empty">No leads yet.</div>
            @else<div class="rp-chart rp-chart--sm"><canvas id="rpLeadsBySource"></canvas></div>@endif
        </div>
    </div>
    <div class="col-lg-6">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">Most Enquired Properties <span class="text-muted fw-normal small">({{ \App\Services\Crm\PortalReportService::RANGES[$days] }})</span></div>
            @php $maxEnquiries = max(1, (int) $data['topProperties']->max('total')); @endphp
            @forelse($data['topProperties'] as $row)
            <div class="d-flex align-items-center gap-3 py-2 @if(!$loop->last) border-bottom @endif">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-truncate">{{ $row->property?->getTranslation('title') ?? 'Deleted property' }}</div>
                    <div class="text-muted small">{{ $row->property?->reference_no }} @if($row->property) &middot; {{ ucfirst($row->property->listing_type) }} @endif</div>
                    <div class="rp-bar mt-1"><span style="width: {{ $row->total / $maxEnquiries * 100 }}%;"></span></div>
                </div>
                <div class="fw-bold">{{ $row->total }}</div>
            </div>
            @empty
            <div class="rp-empty">No property enquiries in this period.</div>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const overTime = @json($data['overTime']);
    rpChart('rpLeadsOverTime', 'line', Object.keys(overTime), Object.values(overTime));
    const stages = @json($data['byStage']);
    rpChart('rpLeadsByStage', 'doughnut', stages.map(s => s.name), stages.map(s => s.total), stages.map(s => s.color || '#264373'));
    const sources = @json($data['bySource']);
    rpChart('rpLeadsBySource', 'bar', Object.keys(sources), Object.values(sources), null, true);
});
</script>
@endpush
@endsection
