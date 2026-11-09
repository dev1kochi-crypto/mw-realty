<template>
    <PlanLockedCard v-if="locked" title="Market Demand" heading="Unlock market demand" icon="fas fa-chart-area" feature="Market demand insights" :plural="true" :locked="locked"
                    description="See where buyers and tenants are looking across MW Realty, which price ranges and bedroom counts have more interest than listings, and how demand changes month by month." />
    <div v-else>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <div class="portal-section-title mb-0">Market Demand</div>
                <p class="text-muted mb-0" style="font-size: 0.88rem; max-width: 780px;">
                    What visitors on MW Realty search for, view, save and enquire about in an area, set against the listings live there today.
                    Use it to decide where to list, how to price and where marketing is worth the spend. Anonymised and aggregated: no visitor or lead is shown.
                </p>
            </div>
        </div>

        <div class="portal-card portal-filter-bar mb-3">
            <form class="portal-filter-bar__form" @submit.prevent="load">
                <div class="portal-filter-field" :class="{ 'is-set': area }" style="min-width: 260px;">
                    <RemoteSelect v-model="area" endpoint="/market-demand/areas" placeholder="All of MW Realty" :initial="areaInitial" fixed @change="load" />
                </div>
                <div class="portal-filter-field" :class="{ 'is-set': purpose !== 'sale' }">
                    <div class="portal-filter-control">
                        <i class="fas fa-tag" aria-hidden="true"></i>
                        <select v-model="purpose" class="form-select" aria-label="Purpose" @change="load">
                            <option v-for="option in purposes" :key="option.key" :value="option.key">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="portal-filter-field" :class="{ 'is-set': type }">
                    <div class="portal-filter-control">
                        <i class="fas fa-building" aria-hidden="true"></i>
                        <select v-model="type" class="form-select" aria-label="Property type" @change="load">
                            <option value="">All property types</option>
                            <option v-for="option in types" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="portal-filter-actions">
                    <button v-if="area || type || purpose !== 'sale'" type="button" class="portal-filter-bar__clear" title="Clear" @click="clear"><i class="fas fa-xmark" aria-hidden="true"></i> Clear</button>
                </div>
            </form>
        </div>

        <template v-if="report">
            <div class="portal-lp-stats portal-card p-3 mb-3" :class="{ 'opacity-50': loading }">
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon"><i class="fas fa-gauge-high"></i></span>
                    <div class="min-w-0">
                        <div class="portal-lp-stat-label">Demand level</div>
                        <div class="portal-lp-stat-value">
                            <span v-if="d.level" class="md-crm-level" :class="`is-${d.level.key}`">{{ levelLabel(d.level.key) }}</span>
                            <span v-else class="text-muted fw-normal" style="font-size: .9rem;">Not enough activity</span>
                        </div>
                        <div v-if="d.level" class="small text-muted">{{ d.level.ratio }}× {{ d.level.baseline ? `${d.level.baseline} average` : 'MW Realty average' }}</div>
                    </div>
                </div>
                <div v-for="tile in tiles" :key="tile.key" class="portal-lp-stat">
                    <span class="portal-lp-stat-icon" :class="tile.tone"><i :class="tile.icon"></i></span>
                    <div class="min-w-0">
                        <div class="portal-lp-stat-label">{{ tile.label }}</div>
                        <div class="portal-lp-stat-value">{{ count(d[tile.key]) }}</div>
                        <div v-if="tile.change !== undefined" class="small" :class="changeClass(d.change[tile.change])">{{ changeText(d.change[tile.change]) }}</div>
                    </div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon"><i class="fas fa-house"></i></span>
                    <div class="min-w-0">
                        <div class="portal-lp-stat-label">Live listings</div>
                        <div class="portal-lp-stat-value">{{ number(d.listings) }}</div>
                        <div class="small text-muted">{{ d.interest_per_listing }} interest / listing</div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-5">
                    <div class="portal-card p-3 p-md-4 h-100">
                        <div class="fw-bold mb-2">Key findings · {{ areaName }}</div>
                        <ul v-if="findings.length" class="md-crm-findings">
                            <li v-for="(item, i) in findings" :key="i" :class="`is-${item.tone}`"><i class="fas" :class="item.tone === 'positive' ? 'fa-arrow-trend-up' : item.tone === 'caution' ? 'fa-arrow-trend-down' : 'fa-circle-info'"></i>{{ item.text }}</li>
                        </ul>
                        <p v-else class="text-muted small mb-0">Not enough activity in this area yet to draw conclusions. Try a wider area, or all of MW Realty.</p>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="portal-card p-3 p-md-4 h-100">
                        <div class="d-flex justify-content-between align-items-baseline mb-2">
                            <div class="fw-bold">Interest over time</div>
                            <div class="small portal-muted">Per month · different visitors per day</div>
                        </div>
                        <div style="height: 240px;"><ChartCanvas :config="chartConfig" /></div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div v-for="split in splits" :key="split.key" class="col-lg-6">
                    <div class="portal-card p-3 p-md-4 h-100">
                        <div class="d-flex justify-content-between align-items-baseline mb-2">
                            <div class="fw-bold">{{ split.title }}</div>
                            <div class="small portal-muted">Last {{ report.share_days }} days</div>
                        </div>
                        <div v-if="split.data.enough" class="table-responsive">
                            <table class="table portal-table mb-0">
                                <thead><tr><th>{{ split.column }}</th><th>Interest vs listings</th><th class="text-end">Interest</th><th class="text-end">Listings</th><th class="text-end">Searches</th><th></th></tr></thead>
                                <tbody>
                                    <tr v-for="item in split.data.items.filter((i) => i.demand_share || i.listings)" :key="item.key">
                                        <td class="fw-semibold text-nowrap">{{ split.label(item) }}</td>
                                        <td style="min-width: 120px;">
                                            <div class="md-crm-bar"><span class="is-demand" :style="{ width: `${item.demand_share}%` }"></span></div>
                                            <div class="md-crm-bar"><span class="is-supply" :style="{ width: `${item.supply_share}%` }"></span></div>
                                        </td>
                                        <td class="text-end">{{ item.demand_share }}%</td>
                                        <td class="text-end">{{ item.supply_share }}% <span class="text-muted small">({{ item.listings }})</span></td>
                                        <td class="text-end small" :class="changeClass(item.searches_change)">{{ item.searches_change === null ? '—' : `${item.searches_change > 0 ? '+' : ''}${item.searches_change}%` }}</td>
                                        <td><span v-if="item.underserved" class="badge rounded-pill text-bg-success">Underserved</span></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="small portal-muted mt-2">Interest = share of searches, views, saves and enquiries. Underserved = at least {{ underservedRatio }}× more interest share than listing share. Searches = change vs the previous {{ report.window_days }} days.</div>
                        </div>
                        <p v-else class="text-muted small mb-0">Not enough activity yet to compare interest with listings here.</p>
                    </div>
                </div>
            </div>

            <div class="portal-card p-3 p-md-4 mb-3">
                <div class="fw-bold mb-2">Most wanted amenities</div>
                <div v-if="report.amenities.length" class="table-responsive">
                    <table class="table portal-table mb-0">
                        <thead><tr><th>Amenity</th><th class="text-end">Searches asking for it</th><th class="text-end">Views of listings with it</th><th class="text-end">Live listings with it</th></tr></thead>
                        <tbody>
                            <tr v-for="amenity in report.amenities" :key="amenity.key">
                                <td class="fw-semibold">{{ amenity.label?.en || ucfirst(String(amenity.key).replace(/[_-]+/g, ' ')) }}</td>
                                <td class="text-end">{{ count(amenity.searches) }}</td>
                                <td class="text-end">{{ count(amenity.views) }}</td>
                                <td class="text-end">{{ number(amenity.listings) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-muted small mb-0">No amenity activity recorded here yet.</p>
            </div>

            <p class="small portal-muted mb-0">
                Built only from activity on MW Realty — searches, saved searches, listing views, favourites, contact clicks and enquiry forms — each counted once per visitor per day,
                compared with listings live now. Counts below {{ report.fewer_than }} are hidden. Last {{ report.window_days }} days vs the {{ report.window_days }} before. Updated every hour.
            </p>
        </template>
        <div v-else-if="loading" class="portal-card p-5 text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading…</div>
    </div>
</template>

<script setup>
/** Market Demand — GET /market-demand (MarketDemandService::forArea), area picker /market-demand/areas. */
import { computed, onMounted, ref } from 'vue';
import http, { errorMessage } from '../../api/http';
import ChartCanvas from '../../components/ChartCanvas.vue';
import PlanLockedCard from '../../components/PlanLockedCard.vue';
import RemoteSelect from '../../components/RemoteSelect.vue';
import { useToast } from '../../composables/useToast';
import { number, ucfirst } from '../../utils/format';
import { planLocked } from '../../utils/planLocked';

const { error: toastError } = useToast();

const report = ref(null);
const loading = ref(false);
const locked = ref(null);
const area = ref(null);
const areaInitial = ref(null);
const purpose = ref('sale');
const type = ref('');
const purposes = ref([{ key: 'sale', label: 'For sale' }, { key: 'rent', label: 'For rent' }]);
const types = ref([]);
const underservedRatio = 1.25;

const d = computed(() => report.value?.area_demand || { change: {} });
const areaName = computed(() => report.value?.area?.name || 'All of MW Realty');
const isRent = computed(() => purpose.value === 'rent');

const tiles = [
    { key: 'searches', label: 'Searches', icon: 'fas fa-magnifying-glass', tone: 'is-accent', change: 'searches' },
    { key: 'views', label: 'Listing views', icon: 'fas fa-eye', tone: 'is-accent', change: 'views' },
    { key: 'favorites', label: 'Favourites', icon: 'fas fa-heart', tone: 'is-warning' },
    { key: 'enquiries', label: 'Enquiries', icon: 'fas fa-phone-volume', tone: 'is-success', change: 'enquiries' },
];

const levelLabel = (key) => ({ high: 'High', medium: 'Medium', low: 'Low' }[key] || key);
const count = (n) => (n === null || n === undefined ? `< ${report.value?.fewer_than ?? 3}` : number(n));
const changeText = (pct) => (pct === null || pct === undefined ? 'Too few to compare' : `${pct > 0 ? '+' : ''}${pct}% vs previous ${report.value.window_days} days`);
const changeClass = (pct) => (pct > 0 ? 'text-success' : pct < 0 ? 'text-danger' : 'text-muted');

const money = (n) => (n >= 1e6 ? `${+(n / 1e6).toFixed(2)}M` : `${Math.round(n / 1e3)}K`);
const bandLabel = (range) => {
    if (!range) return '—';
    const [from, to] = range;
    if (!from) return `Under AED ${money(to)}`;
    return to ? `AED ${money(from)} – ${money(to)}` : `AED ${money(from)}+`;
};
const bedsLabel = (beds) => (beds === 0 ? 'Studio' : `${beds}${beds >= (report.value?.max_bedrooms ?? 99) ? '+' : ''} bed`);

const splits = computed(() => [
    { key: 'price', title: `By price range${isRent.value ? ' (rent a year)' : ''}`, column: 'Price range', data: report.value.price_bands, label: (item) => bandLabel(item.range) },
    { key: 'beds', title: 'By bedrooms', column: 'Bedrooms', data: report.value.bedrooms, label: (item) => bedsLabel(item.key) },
]);

// The same findings as the listing page (MarketDemandService::insightList), worded for agencies.
const findings = computed(() => (report.value?.insights || []).map((item) => {
    const area = areaName.value;
    const days = report.value.window_days;
    const text = {
        area_searches_up: `Searches in ${area} are up ${item.value}% on the previous ${days} days.`,
        area_searches_down: `Searches in ${area} are down ${item.value}% on the previous ${days} days.`,
        area_views_up: `Listing views in ${area} are up ${item.value}% on the previous ${days} days.`,
        area_views_down: `Listing views in ${area} are down ${item.value}% on the previous ${days} days.`,
        level_high: `Each listing here gets ${item.value}× the interest of the ${item.place} average — demand is ahead of supply.`,
        level_low: `Each listing here gets ${item.value}× the interest of the ${item.place} average — supply is ahead of demand.`,
        level_high_platform: `Each listing here gets ${item.value}× the MW Realty average — demand is ahead of supply.`,
        level_low_platform: `Each listing here gets ${item.value}× the MW Realty average — supply is ahead of demand.`,
        enquiries_up: `Enquiries are up ${item.value}% on the previous ${days} days.`,
        enquiries_down: `Enquiries are down ${item.value}% on the previous ${days} days.`,
        band_underserved: `${bandLabel(item.range)} is underserved: ${item.demand}% of interest but only ${item.supply}% of listings.`,
        beds_underserved: `${bedsLabel(item.beds)} homes are underserved: ${item.demand}% of interest but only ${item.supply}% of listings.`,
        beds_searches_up: `Searches for ${bedsLabel(item.beds)} homes are up ${item.value}%.`,
        top_amenity: `Most wanted amenity: ${item.label?.en || ucfirst(String(item.amenity).replace(/[_-]+/g, ' '))}.`,
    }[item.key];
    return text ? { tone: item.tone, text } : null;
}).filter(Boolean));

const chartConfig = computed(() => {
    const months = report.value?.trend || [];
    const labels = months.map((m) => new Date(`${m.month}-01T00:00:00`).toLocaleDateString(undefined, { month: 'short', year: '2-digit' }));
    const bar = (label, key, color) => ({ label, data: months.map((m) => m[key]), backgroundColor: color, borderRadius: 3, maxBarThickness: 22 });
    return () => ({
        type: 'bar',
        data: { labels, datasets: [bar('Searches', 'searches', '#264373'), bar('Listing views', 'views', '#7fa2d9'), bar('Enquiries & favourites', 'enquiries', '#1f9d6b')] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
            scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });
});

function load() {
    loading.value = true;
    return http.get('/market-demand', { params: { area: area.value || undefined, purpose: purpose.value, type: type.value || undefined } })
        .then((res) => {
            report.value = res.data.report;
            purposes.value = res.data.purposes;
            types.value = res.data.types;
            areaInitial.value = res.data.filters.area;
        })
        .catch((e) => {
            locked.value = planLocked(e);
            if (!locked.value) toastError(errorMessage(e));
        })
        .finally(() => { loading.value = false; });
}

function clear() {
    area.value = null;
    areaInitial.value = null;
    purpose.value = 'sale';
    type.value = '';
    load();
}

onMounted(load);
</script>

<style>
.md-crm-level { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: .95rem; }
.md-crm-level.is-high { background: rgba(31, 157, 107, .12); color: #17774f; }
.md-crm-level.is-medium { background: rgba(36, 67, 115, .08); color: #264373; }
.md-crm-level.is-low { background: rgba(201, 138, 40, .14); color: #9a6514; }
.md-crm-findings { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; font-size: .9rem; }
.md-crm-findings li { display: flex; gap: 10px; align-items: flex-start; }
.md-crm-findings i { margin-top: 3px; width: 16px; text-align: center; }
.md-crm-findings .is-positive i { color: #1f9d6b; }
.md-crm-findings .is-caution i { color: #c98a28; }
.md-crm-findings .is-neutral i { color: #264373; }
.md-crm-bar { height: 6px; margin: 3px 0; border-radius: 3px; background: #f1f3f8; overflow: hidden; }
.md-crm-bar span { display: block; height: 100%; min-width: 2px; border-radius: 3px; }
.md-crm-bar .is-demand { background: #264373; }
.md-crm-bar .is-supply { background: #b9c6dc; }
</style>
