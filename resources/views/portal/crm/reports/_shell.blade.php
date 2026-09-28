{{-- Shared header for the three reports: title, report tabs, date-range switch. --}}
@push('styles')
<style>
    .rp-tabs { display: flex; gap: 0.35rem; flex-wrap: wrap; padding: 0.3rem; border-radius: 14px; background: var(--portal-surface); border: 1px solid var(--portal-border); width: fit-content; max-width: 100%; }
    .rp-tab { display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.5rem 1rem; border-radius: 10px; font-weight: 700; font-size: 0.86rem; color: var(--portal-muted); text-decoration: none; }
    .rp-tab:hover { color: var(--portal-primary); }
    .rp-tab.is-active { background: var(--portal-primary); color: #fff; box-shadow: 0 6px 16px rgba(36, 67, 115, 0.2); }
    .rp-range .btn.active { background: var(--portal-primary); color: #fff; border-color: var(--portal-primary); }
    .rp-change { font-size: 0.72rem; font-weight: 700; margin-left: 0.35rem; }
    .rp-change--up { color: #16a34a; }
    .rp-change--down { color: #dc2626; }
    .rp-table th { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--portal-muted); font-weight: 700; white-space: nowrap; }
    .rp-table td { vertical-align: middle; font-size: 0.88rem; }
    .rp-bar { height: 6px; border-radius: 6px; background: var(--portal-bg); overflow: hidden; min-width: 70px; }
    .rp-bar > span { display: block; height: 100%; background: linear-gradient(90deg, var(--portal-accent, #04a1cc), var(--portal-primary)); }
    .rp-empty { text-align: center; color: var(--portal-muted); padding: 2rem 1rem; font-size: 0.88rem; }
    .rp-chart { position: relative; height: 260px; }
    .rp-chart--sm { height: 220px; }
</style>
@endpush

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <div class="portal-section-title mb-0">Reports</div>
        @if($isAdmin)<div class="text-muted small">Showing every account's data (Super Admin).</div>@endif
    </div>
    <div class="btn-group btn-group-sm rp-range" role="group" aria-label="Date range">
        @foreach(\App\Services\Crm\PortalReportService::RANGES as $value => $label)
        <a href="{{ route('portal.crm.reports.index', ['report' => $report, 'range' => $value]) }}" class="btn portal-btn-ghost @if($days === $value) active @endif">{{ $label }}</a>
        @endforeach
    </div>
</div>

<nav class="rp-tabs mb-4">
    @foreach($reports as $key => $label)
    <a href="{{ route('portal.crm.reports.index', ['report' => $key, 'range' => $days]) }}" class="rp-tab @if($report === $key) is-active @endif">
        <i class="fas {{ ['leads' => 'fa-address-book', 'properties' => 'fa-building', 'revenue' => 'fa-coins', 'agents' => 'fa-user-tie'][$key] }}"></i>{{ $label }}
    </a>
    @endforeach
</nav>

@once
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
    window.rpChart = function (id, type, labels, values, colors, horizontal) {
        const el = document.getElementById(id);
        if (!el || !window.Chart) return;
        const palette = ['#264373', '#04a1cc', '#f59e0b', '#16a34a', '#dc2626', '#8b5cf6', '#0ea5e9', '#ec4899', '#64748b', '#14b8a6'];
        const round = type === 'doughnut';
        new Chart(el, {
            type,
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors || (round ? palette : 'rgba(36,67,115,0.85)'),
                    borderColor: type === 'line' ? '#264373' : undefined,
                    fill: type === 'line' ? { target: 'origin', above: 'rgba(36,67,115,0.10)' } : undefined,
                    tension: 0.3,
                    borderRadius: type === 'bar' ? 6 : 0,
                    borderWidth: round ? 0 : (type === 'line' ? 2 : 0),
                    pointRadius: type === 'line' ? 2 : undefined,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: horizontal ? 'y' : 'x',
                plugins: { legend: { display: round, position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
                scales: round ? {} : { x: { grid: { display: false }, ticks: { precision: 0, maxTicksLimit: 12 } }, y: { beginAtZero: true, ticks: { precision: 0 } } },
                cutout: round ? '62%' : undefined,
            },
        });
    };
</script>
@endpush
@endonce
