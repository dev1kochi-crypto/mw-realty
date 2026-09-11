@extends('portal.crm._layout')


@section('title', 'Reports')

@section('crm-content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="portal-section-title mb-0">Reports</div>
    <div class="d-flex gap-2">
        <a href="{{ route('portal.crm.reports.index', ['range' => 30]) }}" class="portal-btn-ghost btn btn-sm @if($rangeDays === 30) active @endif">30 Days</a>
        <a href="{{ route('portal.crm.reports.index', ['range' => 90]) }}" class="portal-btn-ghost btn btn-sm @if($rangeDays === 90) active @endif">90 Days</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="portal-stat-card">
            <div class="portal-stat-icon indigo"><i class="fas fa-address-book"></i></div>
            <div>
                <div class="portal-stat-value">{{ $totalLeads }}</div>
                <div class="portal-stat-label">Total Leads</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="portal-stat-card">
            <div class="portal-stat-icon teal"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="portal-stat-value">{{ $closedLeads }}</div>
                <div class="portal-stat-label">Closed Leads</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="portal-stat-card">
            <div class="portal-stat-icon amber"><i class="fas fa-percentage"></i></div>
            <div>
                <div class="portal-stat-value">{{ $conversionRate }}%</div>
                <div class="portal-stat-label">Conversion Rate</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="portal-stat-card">
            <div class="portal-stat-icon rose"><i class="fas fa-building"></i></div>
            <div>
                <div class="portal-stat-value">{{ $activeProperties }}</div>
                <div class="portal-stat-label">Active Properties</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="portal-card p-4">
            <div class="portal-section-title">Leads Over Time ({{ $rangeDays }} Days)</div>
            <canvas id="leadsOverTimeChart" height="110"></canvas>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="portal-card p-4">
            <div class="portal-section-title">Leads by Stage</div>
            <canvas id="leadsByStageChart" height="150"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="portal-card p-4">
            <div class="portal-section-title">Leads by Status</div>
            <canvas id="leadsByStatusChart" height="150"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="portal-card p-4">
            <div class="portal-section-title">Properties by Status</div>
            <canvas id="propertiesByStatusChart" height="150"></canvas>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const leadsOverTime = {!! json_encode($leadsOverTime) !!};
    const otCtx = document.getElementById('leadsOverTimeChart');
    if (otCtx) {
        new Chart(otCtx, {
            type: 'line',
            data: {
                labels: Object.keys(leadsOverTime),
                datasets: [{
                    data: Object.values(leadsOverTime),
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79,70,229,0.12)',
                    fill: true,
                    tension: 0.3,
                }],
            },
            options: { responsive: true, plugins: { legend: { display: false } } },
        });
    }

    const leadsByStage = {!! json_encode($leadsByStage) !!};
    const stageCtx = document.getElementById('leadsByStageChart');
    if (stageCtx) {
        new Chart(stageCtx, {
            type: 'doughnut',
            data: {
                labels: leadsByStage.map(s => s.name),
                datasets: [{ data: leadsByStage.map(s => s.total), backgroundColor: leadsByStage.map(s => s.color), borderWidth: 0 }],
            },
            options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } }, cutout: '65%' },
        });
    }

    const leadsByStatus = {!! json_encode($leadsByStatus) !!};
    const statusCtx = document.getElementById('leadsByStatusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(leadsByStatus).map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                datasets: [{ data: Object.values(leadsByStatus), backgroundColor: '#4f46e5', borderRadius: 6 }],
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
    }

    const propertiesByStatus = {!! json_encode($propertiesByStatus) !!};
    const propCtx = document.getElementById('propertiesByStatusChart');
    if (propCtx) {
        new Chart(propCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(propertiesByStatus).map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                datasets: [{ data: Object.values(propertiesByStatus), backgroundColor: '#14b8a6', borderRadius: 6 }],
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
    }
});
</script>
@endpush
@endsection
