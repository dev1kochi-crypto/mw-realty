{{--
    Chart wiring for the portal dashboard:
      #dzTrend    — weekly enquiries (filled) vs listings, last 12 weeks
      #dzWeekday  — enquiries per weekday (last 90 days), busiest day highlighted
      .dz-spark   — tiny single-series line; data-values='[..]' data-color="#hex"
      .dz-count   — number that counts up on load; data-to="123" data-suffix="%"
--}}
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.querySelectorAll('.dz-count').forEach((el) => {
            const to = parseFloat(el.dataset.to || '0');
            const suffix = el.dataset.suffix || '';
            const start = performance.now();
            const step = (t) => {
                const p = Math.min(1, (t - start) / 900);
                el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3))).toLocaleString() + suffix;
                if (p < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        });
    }

    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
    Chart.defaults.color = '#7a7f9a';
    const tooltip = { backgroundColor: '#1f2340', padding: 10, cornerRadius: 8, boxPadding: 4, titleFont: { size: 11, weight: '700' }, bodyFont: { size: 12, weight: '600' } };
    const activity = @json($activity);
    const weeks = activity.leads.labels;

    const trend = document.getElementById('dzTrend');
    if (trend) {
        const leadColor = trend.dataset.leadColor || '#ca2844';
        const listingColor = trend.dataset.listingColor || '#244373';
        const fill = trend.getContext('2d').createLinearGradient(0, 0, 0, trend.parentElement.clientHeight || 200);
        fill.addColorStop(0, leadColor + '33');
        fill.addColorStop(1, leadColor + '00');
        const line = (label, data, color, area) => ({ label, data, borderColor: color, backgroundColor: area ? fill : 'transparent', fill: area, borderWidth: 2, cubicInterpolationMode: 'monotone', pointRadius: 0, pointHoverRadius: 5, pointBackgroundColor: color, pointBorderColor: '#fff', pointBorderWidth: 2 });
        new Chart(trend, {
            type: 'line',
            data: { labels: weeks, datasets: [line('Enquiries', activity.leads.data, leadColor, true), line('Listings', activity.properties.data, listingColor, false)] },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: { ...tooltip, callbacks: { title: (i) => 'Week of ' + i[0].label } } },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 }, maxRotation: 0, autoSkip: true } },
                    y: { beginAtZero: true, grid: { color: 'rgba(31,35,64,0.05)' }, border: { display: false }, ticks: { font: { size: 10 }, precision: 0 } },
                }
            }
        });
    }

    const wd = document.getElementById('dzWeekday');
    if (wd) {
        const values = @json($weekdayLeads);
        const max = Math.max(...values, 0);
        const base = wd.dataset.color || '#dfe4f0';
        const peak = wd.dataset.peak || '#ca2844';
        new Chart(wd, {
            type: 'bar',
            data: { labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], datasets: [{ label: 'Enquiries', data: values, backgroundColor: values.map(v => v === max && max > 0 ? peak : base), borderRadius: 5, borderSkipped: false, maxBarThickness: 22 }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { ...tooltip, displayColors: false } },
                scales: { x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 9 } } }, y: { display: false, beginAtZero: true } }
            }
        });
    }

    document.querySelectorAll('.dz-spark').forEach((el) => {
        const color = el.dataset.color || '#ca2844';
        const g = el.getContext('2d').createLinearGradient(0, 0, 0, el.parentElement.clientHeight || 32);
        g.addColorStop(0, color + '40');
        g.addColorStop(1, color + '00');
        new Chart(el, {
            type: 'line',
            data: { labels: weeks, datasets: [{ data: JSON.parse(el.dataset.values || '[]'), borderColor: color, backgroundColor: g, borderWidth: 2, fill: true, cubicInterpolationMode: 'monotone', pointRadius: 0, pointHoverRadius: 3, pointBackgroundColor: color }] },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: { ...tooltip, displayColors: false, callbacks: { title: (i) => 'Week of ' + i[0].label } } },
                scales: { x: { display: false }, y: { display: false, beginAtZero: true } },
            }
        });
    });
});
</script>
@endpush
