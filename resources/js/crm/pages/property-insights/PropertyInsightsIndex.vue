<template>
    <PlanLockedCard v-if="locked" title="Property Insights" heading="Unlock property insights" icon="fas fa-magnifying-glass-chart" feature="Property insights" :plural="true" :locked="locked"
                    description="See how every listing performs on the website — impressions, clicks, lead clicks and leads — and which ones nobody is seeing." />
    <div v-else>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <div class="portal-section-title mb-0">Property Insights</div>
                <p class="text-muted mb-0" style="font-size: 0.88rem; max-width: 760px;">
                    How {{ isAdmin ? 'every' : 'your' }} listing performs on the website. Open a listing to see its day-by-day performance, quality score and leads.
                </p>
            </div>
        </div>

        <div class="portal-lp-stats pi-stats portal-card p-3 mb-3">
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon"><i class="fas fa-building"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Listings</div><div class="portal-lp-stat-value">{{ number(totals.listings) }} <small class="text-muted fw-normal">· {{ number(totals.live) }} live</small></div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-accent"><i class="fas fa-eye"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Impressions</div><div class="portal-lp-stat-value">{{ number(totals.impressions) }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-accent"><i class="fas fa-arrow-pointer"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Listing clicks</div><div class="portal-lp-stat-value">{{ number(totals.clicks) }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-warning"><i class="fas fa-phone-volume"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Lead clicks</div><div class="portal-lp-stat-value">{{ number(totals.lead_clicks) }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-success"><i class="fas fa-user-plus"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Leads</div><div class="portal-lp-stat-value">{{ number(totals.leads) }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-warning"><i class="fas fa-eye-slash"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">No impressions</div><div class="portal-lp-stat-value" :class="{ 'text-danger': totals.no_views }">{{ number(totals.no_views) }}</div></div>
            </div>
        </div>

        <div class="portal-card p-3 p-md-4 mb-3">
            <div class="d-flex justify-content-between align-items-baseline mb-2">
                <div class="fw-bold">Impressions &amp; clicks · {{ periodLabel }}</div>
                <div class="small portal-muted">All listings in the list below</div>
            </div>
            <div class="pi-chart"><ChartCanvas :config="chartConfig" /></div>
        </div>

        <div class="portal-card portal-filter-bar mb-3">
            <form class="portal-filter-bar__form" @submit.prevent="load(1)">
                <div class="portal-filter-field portal-filter-field--search" :class="{ 'is-set': search }">
                    <div class="portal-filter-control">
                        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                        <input v-model="searchInput" type="search" class="form-control" placeholder="Title, reference or address" autocomplete="off" maxlength="100" aria-label="Search">
                    </div>
                </div>
                <div class="portal-filter-field" :class="{ 'is-set': segment }">
                    <div class="portal-filter-control">
                        <i class="fas fa-layer-group" aria-hidden="true"></i>
                        <select v-model="segment" class="form-select" aria-label="Listings" @change="load(1)">
                            <option v-for="option in segments" :key="option.key" :value="option.key">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="portal-filter-field" :class="{ 'is-set': days !== 30 }">
                    <div class="portal-filter-control">
                        <i class="fas fa-calendar" aria-hidden="true"></i>
                        <select v-model.number="days" class="form-select" aria-label="Period" @change="load(1)">
                            <option v-for="option in periods" :key="option.key" :value="option.key">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="portal-filter-field" :class="{ 'is-set': sort !== 'impressions' }">
                    <div class="portal-filter-control">
                        <i class="fas fa-arrow-down-wide-short" aria-hidden="true"></i>
                        <select v-model="sort" class="form-select" aria-label="Sort" @change="load(1)">
                            <option v-for="option in sorts" :key="option.key" :value="option.key">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="portal-filter-actions">
                    <button type="submit" class="portal-filter-submit" title="Apply" aria-label="Apply"><i class="fas fa-filter" aria-hidden="true"></i></button>
                    <button v-if="filtered" type="button" class="portal-filter-bar__clear" title="Clear" @click="clear"><i class="fas fa-xmark" aria-hidden="true"></i> Clear</button>
                </div>
            </form>
        </div>

        <div class="portal-card p-3 p-md-4">
            <div class="table-responsive">
                <table class="table portal-table mb-0 ai-table">
                    <thead>
                        <tr>
                            <th>Listing</th>
                            <th>Agent</th>
                            <th class="text-center" title="Times the listing was shown in search results and on the home page">Impressions</th>
                            <th class="text-center" title="Times the listing page was opened">Clicks</th>
                            <th class="text-center" title="Call, WhatsApp and email button clicks">Lead clicks</th>
                            <th class="text-center">Leads</th>
                            <th class="text-center">Quality</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody :class="{ 'opacity-50': loading }">
                        <tr v-for="listing in listings" :key="listing.id" class="li-row" tabindex="0" @click="open($event, listing)" @keydown.enter="open($event, listing)">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img v-if="listing.thumb" :src="listing.thumb" alt="" class="pi-thumb" loading="lazy">
                                    <span v-else class="pi-thumb pi-thumb--empty"><i class="fas fa-image"></i></span>
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-truncate" style="max-width: 280px;" :title="listing.title">{{ listing.title }}</div>
                                        <div class="text-muted d-flex flex-wrap gap-2" style="font-size: 0.78rem;">
                                            <span>{{ listing.reference_no || `#${listing.id}` }}</span>
                                            <span :class="listing.status ? 'text-success' : 'text-secondary'"><i class="fas fa-circle me-1" style="font-size: .45rem; vertical-align: middle;"></i>{{ listing.status ? 'Active' : 'Inactive' }}</span>
                                            <span v-if="listing.featured" class="text-warning"><i class="fas fa-star me-1"></i>Premium</span>
                                            <span v-if="listing.owner" class="text-truncate" style="max-width: 160px;">{{ listing.owner }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="text-truncate d-block" style="max-width: 160px;" :title="listing.agent">{{ listing.agent || '—' }}</span></td>
                            <td class="text-center"><span class="li-metric" :class="{ 'text-danger': !listing.impressions }">{{ number(listing.impressions) }}</span></td>
                            <td class="text-center"><span class="li-metric">{{ number(listing.clicks) }}</span></td>
                            <td class="text-center"><span class="li-metric">{{ number(listing.lead_clicks) }}</span></td>
                            <td class="text-center"><span class="li-metric">{{ number(listing.leads) }}</span></td>
                            <td class="text-center">
                                <span class="pq-ring pq-ring--inline ai-ring" :class="`is-${listing.quality.tone}`" :style="{ '--pq': listing.quality.score }" :title="`Quality score ${listing.quality.score} of 100`"><span>{{ listing.quality.score }}</span></span>
                            </td>
                            <td class="text-end"><button type="button" class="portal-lead-insights" @click="insightsId = listing.id"><i class="fas fa-chart-line" aria-hidden="true"></i>Details</button></td>
                        </tr>
                        <tr v-if="!loading && !listings.length">
                            <td colspan="8" class="portal-empty text-center text-muted py-5">
                                <i class="fas fa-magnifying-glass-chart fa-2x mb-2 d-block opacity-50"></i>
                                {{ search ? 'No listings match your search.' : 'No listings yet — add one from Properties or Commercial.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-3"><CrmPagination :meta="meta" @change="load" /></div>
        </div>

        <ListingPerformance :property-id="insightsId" @close="insightsId = null" />
    </div>
</template>

<script setup>
/** Property Insights — GET /property-insights; a row opens the listing's performance panel (ListingPerformance). */
import { computed, onMounted, ref, watch } from 'vue';
import http, { errorMessage } from '../../api/http';
import ChartCanvas from '../../components/ChartCanvas.vue';
import CrmPagination from '../../components/CrmPagination.vue';
import PlanLockedCard from '../../components/PlanLockedCard.vue';
import { useToast } from '../../composables/useToast';
import { number } from '../../utils/format';
import { planLocked } from '../../utils/planLocked';
import ListingPerformance from '../properties/components/ListingPerformance.vue';

const { error: toastError } = useToast();

const listings = ref([]);
const meta = ref(null);
const totals = ref({ listings: 0, live: 0, impressions: 0, clicks: 0, lead_clicks: 0, leads: 0, no_views: 0 });
const series = ref([]);
const periods = ref([{ key: 30, label: 'Last 30 days' }]);
const sorts = ref([{ key: 'impressions', label: 'Most impressions' }]);
const segments = ref([{ key: '', label: 'All listings' }]);
const isAdmin = ref(false);
const loading = ref(false);
const locked = ref(null); // plan without property insights → "Unlock property insights" card
const days = ref(30);
const sort = ref('impressions');
const segment = ref('');
const search = ref('');
const searchInput = ref('');
const insightsId = ref(null);
let timer = null;

const periodLabel = computed(() => (periods.value.find((p) => p.key === days.value)?.label ?? '').toLowerCase());
const filtered = computed(() => search.value || segment.value || days.value !== 30 || sort.value !== 'impressions');

const chartConfig = computed(() => {
    const labels = series.value.map((d) => new Date(`${d.date}T00:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'short' }));
    const line = (label, key, color, fill) => ({
        label, data: series.value.map((d) => d[key]), borderColor: color, backgroundColor: fill,
        fill: true, tension: 0.3, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4,
    });

    return () => ({
        type: 'line',
        data: { labels, datasets: [line('Impressions', 'impressions', '#264373', 'rgba(36,67,115,0.10)'), line('Clicks', 'clicks', '#04a1cc', 'rgba(4,161,204,0.10)')] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
            scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } }, y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });
});

function load(p = 1) {
    loading.value = true;
    return http.get('/property-insights', { params: { days: days.value, sort: sort.value, segment: segment.value || undefined, search: search.value || undefined, page: p } })
        .then((res) => {
            listings.value = res.data.data;
            meta.value = res.data.meta;
            totals.value = res.data.totals;
            series.value = res.data.series;
            periods.value = res.data.periods;
            sorts.value = res.data.sorts;
            segments.value = res.data.segments;
            isAdmin.value = res.data.is_admin;
        })
        .catch((e) => {
            locked.value = planLocked(e);
            if (!locked.value) toastError(errorMessage(e));
        })
        .finally(() => {
            loading.value = false;
        });
}

function clear() {
    searchInput.value = '';
    search.value = '';
    segment.value = '';
    days.value = 30;
    sort.value = 'impressions';
    load(1);
}

function open(event, listing) {
    if (event.target.closest('a, button, input')) return;
    insightsId.value = listing.id;
}

watch(searchInput, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        search.value = value.trim();
        load(1);
    }, 350);
});

onMounted(() => load());
</script>
