@extends('portal.crm._layout')

@section('title', 'Agents Report')

@section('crm-content')
@include('portal.crm.reports._shell')

@php
    $k = $data['kpis'];
    $rangeLabel = \App\Services\Crm\PortalReportService::RANGES[$days];
    $maxLeads = max(1, (int) $data['rows']->max('leads_in_range'));
@endphp
<div class="row g-3 mb-3">
    @foreach(array_filter([
        ['Active Agents', $k['active'], 'fa-user-check', 'indigo'],
        ['Suspended', $k['suspended'], 'fa-user-clock', 'rose'],
        ['Pending Approval', $k['pending'], 'fa-hourglass-half', 'amber'],
        ['Assigned Properties', $k['properties'], 'fa-building', 'teal'],
        ['Leads to Agents (' . $rangeLabel . ')', $k['leads_in_range'], 'fa-address-book', 'indigo'],
    ]) as [$label, $value, $icon, $tone])
    <div class="col-6 col-lg">
        <div class="portal-stat-card">
            <div class="portal-stat-icon {{ $tone }}"><i class="fas {{ $icon }}"></i></div>
            <div><div class="portal-stat-value">{{ number_format($value) }}</div><div class="portal-stat-label">{{ $label }}</div></div>
        </div>
    </div>
    @endforeach
</div>

@if(($k['unassigned_properties'] ?? 0) > 0)
<div class="alert alert-warning d-flex align-items-center gap-2 py-2">
    <i class="fas fa-exclamation-triangle"></i>
    <span>{{ $k['unassigned_properties'] }} of your {{ \Illuminate\Support\Str::plural('property', $k['unassigned_properties']) }} {{ $k['unassigned_properties'] === 1 ? 'has' : 'have' }} no agent assigned — leads on {{ $k['unassigned_properties'] === 1 ? 'it' : 'them' }} stay with the agency.</span>
</div>
@endif

<div class="row g-3">
    @if($data['chart']->isNotEmpty())
    <div class="col-12">
        <div class="portal-card p-4">
            <div class="portal-section-title">Leads per Agent ({{ $rangeLabel }})</div>
            <div class="rp-chart rp-chart--sm"><canvas id="rpAgentLeads"></canvas></div>
        </div>
    </div>
    @endif

    <div class="col-12">
        <div class="portal-card p-4">
            <div class="portal-section-title">Agent Performance</div>
            @if($data['rows']->isEmpty())
            <div class="rp-empty">
                No agents in your agency yet.
                @if(!$isAdmin)<a href="{{ route('portal.agents.index') }}">Add or invite agents</a>.@endif
            </div>
            @else
            <div class="table-responsive">
                <table class="table rp-table mb-0">
                    <thead>
                        <tr>
                            <th>Agent</th>
                            @if($isAdmin)<th>Agency</th>@endif
                            <th>Status</th>
                            <th class="text-end">Properties</th>
                            <th>Leads ({{ $rangeLabel }})</th>
                            <th class="text-end">All-time Leads</th>
                            <th class="text-end">Closed</th>
                            <th class="text-end">Conversion</th>
                            <th>Last Lead</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['rows'] as $row)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $row->agent->name }}</div>
                                <div class="text-muted small">{{ $row->agent->email }}</div>
                            </td>
                            @if($isAdmin)<td class="small">{{ $row->agency?->displayName() }}</td>@endif
                            <td><span class="badge bg-{{ $row->membership->statusTone() }}">{{ $row->membership->statusLabel() }}</span></td>
                            <td class="text-end">
                                <span class="fw-semibold">{{ $row->active_properties }}</span>
                                <span class="text-muted small">/ {{ $row->properties }}</span>
                            </td>
                            <td style="min-width: 140px;">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold" style="min-width: 1.5rem;">{{ $row->leads_in_range }}</span>
                                    <div class="rp-bar flex-grow-1"><span style="width: {{ $row->leads_in_range / $maxLeads * 100 }}%;"></span></div>
                                </div>
                            </td>
                            <td class="text-end">{{ $row->leads }}</td>
                            <td class="text-end">{{ $row->closed }}</td>
                            <td class="text-end fw-semibold">{{ $row->conversion }}%</td>
                            <td class="small text-muted text-nowrap">{{ $row->last_lead_at?->diffForHumans() ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="text-muted small mt-2">Properties shows active / total listings assigned to the agent. Leads and conversion count only your agency's leads assigned to them.</div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const perAgent = @json($data['chart']);
    rpChart('rpAgentLeads', 'bar', Object.keys(perAgent), Object.values(perAgent), null, Object.keys(perAgent).length > 6);
});
</script>
@endpush
@endsection
