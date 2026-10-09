/**
 * The Reports charts (was window.rpChart in resources/views/portal/crm/reports/_shell) — returns a
 * ChartCanvas `config` function. `series` is the API's { labels, values }.
 */
const palette = ['#264373', '#04a1cc', '#f59e0b', '#16a34a', '#dc2626', '#8b5cf6', '#0ea5e9', '#ec4899', '#64748b', '#14b8a6'];

export function rpChart(type, series, colors = null, horizontal = false) {
    return () => {
        const round = type === 'doughnut';
        return {
            type,
            data: {
                labels: series.labels,
                datasets: [{
                    data: series.values,
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
        };
    };
}

export const aed = (value) => `AED ${Math.round(Number(value || 0)).toLocaleString('en')}`;
