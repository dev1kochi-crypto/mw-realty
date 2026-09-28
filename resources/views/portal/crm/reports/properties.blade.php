@extends('portal.crm._layout')

@section('title', 'Properties Report')

@section('crm-content')
@include('portal.crm.reports._shell')

@php $k = $data['kpis']; @endphp
<div class="row g-3 mb-3">
    @foreach([
        ['Total Properties', $k['total'], 'fa-building', 'indigo'],
        ['Active', $k['active'], 'fa-eye', 'teal'],
        ['Inactive', $k['inactive'], 'fa-eye-slash', 'rose'],
        ['Featured Now', $k['featured'], 'fa-star', 'amber'],
        ['Added (' . \App\Services\Crm\PortalReportService::RANGES[$days] . ')', $k['new'], 'fa-plus-circle', 'indigo'],
    ] as [$label, $value, $icon, $tone])
    <div class="col-6 col-lg">
        <div class="portal-stat-card">
            <div class="portal-stat-icon {{ $tone }}"><i class="fas {{ $icon }}"></i></div>
            <div><div class="portal-stat-value">{{ number_format($value) }}</div><div class="portal-stat-label">{{ $label }}</div></div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">Listings Added Over Time</div>
            <div class="rp-chart"><canvas id="rpPropsOverTime"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">Sale vs Rent</div>
            @if($data['byListingType']->isEmpty())<div class="rp-empty">No properties yet.</div>
            @else<div class="rp-chart"><canvas id="rpPropsByListing"></canvas></div>@endif
        </div>
    </div>
    <div class="col-lg-8">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">By Property Type</div>
            @if($data['byType']->isEmpty())<div class="rp-empty">No properties yet.</div>
            @else<div class="rp-chart rp-chart--sm"><canvas id="rpPropsByType"></canvas></div>@endif
        </div>
    </div>
    <div class="col-lg-4">
        <div class="portal-card p-4 h-100">
            <div class="portal-section-title">Residential vs Commercial</div>
            @if($data['bySegment']->isEmpty())<div class="rp-empty">No properties yet.</div>
            @else<div class="rp-chart rp-chart--sm"><canvas id="rpPropsBySegment"></canvas></div>@endif
        </div>
    </div>
    <div class="col-12">
        <div class="portal-card p-4">
            <div class="portal-section-title">Top Properties by Leads</div>
            @if($data['topByLeads']->isEmpty())
            <div class="rp-empty">None of these properties have received leads yet.</div>
            @else
            <div class="table-responsive">
                <table class="table rp-table mb-0">
                    <thead><tr><th>Property</th><th>Type</th><th>Price</th><th>Status</th><th class="text-end">Leads ({{ \App\Services\Crm\PortalReportService::RANGES[$days] }})</th><th class="text-end">All-time Leads</th></tr></thead>
                    <tbody>
                        @foreach($data['topByLeads'] as $property)
                        <tr>
                            <td>
                                <a href="{{ route('portal.properties.show', $property->id) }}" class="fw-semibold text-decoration-none">{{ \Illuminate\Support\Str::limit($property->getTranslation('title') ?? 'Untitled', 55) }}</a>
                                <div class="text-muted small">{{ $property->reference_no }}</div>
                            </td>
                            <td>{{ ucfirst($property->listing_type) }}</td>
                            <td class="text-nowrap">{{ $property->currency ?: 'AED' }} {{ number_format((float) $property->price) }}</td>
                            <td><span class="badge {{ $property->status ? 'bg-success' : 'bg-secondary' }}">{{ $property->status ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-end fw-bold">{{ $property->leads_in_range }}</td>
                            <td class="text-end">{{ $property->leads_total }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const overTime = @json($data['overTime']);
    rpChart('rpPropsOverTime', 'bar', Object.keys(overTime), Object.values(overTime));
    const listing = @json($data['byListingType']);
    rpChart('rpPropsByListing', 'doughnut', Object.keys(listing), Object.values(listing));
    const types = @json($data['byType']);
    rpChart('rpPropsByType', 'bar', Object.keys(types), Object.values(types), null, true);
    const segments = @json($data['bySegment']);
    rpChart('rpPropsBySegment', 'doughnut', Object.keys(segments), Object.values(segments), ['#264373', '#04a1cc']);
});
</script>
@endpush
@endsection
