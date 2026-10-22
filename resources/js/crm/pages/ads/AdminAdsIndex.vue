<template>
    <div v-if="!page && tab !== 'placements'" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <div class="adm-eyebrow">Global view</div>
                <div class="portal-section-title mb-1">Advertisements</div>
                <p class="portal-muted small mb-0">MW Realty's own banners and the ads agencies and agents book, across every page of the website.</p>
            </div>
            <RouterLink :to="{ name: 'ads.create' }" class="btn btn-portal-primary btn-sm"><i class="fas fa-plus me-1"></i> Add MW Realty ad</RouterLink>
        </div>

        <div v-if="page" class="adm-kpis">
            <div class="adp-kpi">
                <span class="adp-kpi__icon is-live"><i class="fas fa-tower-broadcast"></i></span>
                <div><div class="adp-kpi__value">{{ page.summary.live }}</div><div class="adp-kpi__label">Live now</div></div>
            </div>
            <button type="button" class="adp-kpi adm-kpi-action" :class="{ 'is-alert': pending }" @click="go({ tab: 'client', state: 'pending' })">
                <span class="adp-kpi__icon is-pending"><i class="fas fa-hourglass-half"></i></span>
                <div class="text-start"><div class="adp-kpi__value">{{ pending }}</div><div class="adp-kpi__label">Waiting for review</div></div>
                <i class="fas fa-chevron-right adm-kpi-action__go"></i>
            </button>
            <div class="adp-kpi">
                <span class="adp-kpi__icon is-imp"><i class="far fa-eye"></i></span>
                <div><div class="adp-kpi__value">{{ number(page.summary.impressions) }}</div><div class="adp-kpi__label">Impressions · 30 days</div></div>
            </div>
            <div class="adp-kpi">
                <span class="adp-kpi__icon is-click"><i class="fas fa-arrow-pointer"></i></span>
                <div><div class="adp-kpi__value">{{ number(page.summary.clicks) }}</div><div class="adp-kpi__label">Clicks · 30 days</div></div>
            </div>
            <div class="adp-kpi">
                <span class="adp-kpi__icon is-ctr"><i class="fas fa-percent"></i></span>
                <div><div class="adp-kpi__value">{{ page.summary.ctr }}%</div><div class="adp-kpi__label">Click rate (CTR)</div></div>
            </div>
        </div>

        <div class="adm-tabs" role="tablist">
            <button v-for="t in tabs" :key="t.key" type="button" role="tab" class="adm-tab" :class="{ 'is-active': tab === t.key }" :aria-selected="tab === t.key ? 'true' : 'false'" @click="go({ tab: t.key })">
                <i class="fas" :class="t.icon"></i>{{ t.label }}
                <span v-if="t.badge !== null" class="adm-tab__badge" :class="{ 'is-alert': t.alert }">{{ t.badge }}</span>
            </button>
        </div>

        <!-- ================= MW Realty ads / Client ads ================= -->
        <template v-if="tab !== 'placements' && page">
            <div class="adm-toolbar">
                <div class="adm-pills">
                    <button v-for="s in stateTabs" :key="s.key" type="button" class="adm-pill" :class="{ 'is-active': page.state === s.key }" @click="go({ state: s.key })">
                        {{ s.label }} <span>{{ s.count }}</span>
                    </button>
                </div>
                <div class="adm-filters">
                    <select class="form-select form-select-sm" :value="route.query.placement ?? ''" aria-label="Page" @change="go({ placement: $event.target.value || undefined })">
                        <option value="">All pages</option>
                        <option v-for="p in page.placements" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                    <div class="adm-search">
                        <i class="fas fa-search"></i>
                        <input v-model="search" type="search" class="form-control form-control-sm" :placeholder="tab === 'client' ? 'Ad or client…' : 'Ad name…'" @keyup.enter="go({ q: search || undefined })" @search="go({ q: search || undefined })">
                    </div>
                </div>
            </div>

            <div v-if="!page.data.length" class="portal-card adp-empty">
                <i class="fas" :class="tab === 'client' ? 'fa-user-check' : 'fa-rectangle-ad'"></i>
                <template v-if="tab === 'client'">{{ page.state === 'pending' ? 'Nothing waiting for review — all caught up.' : 'No client ads here.' }}</template>
                <template v-else>No MW Realty ads here. <RouterLink :to="{ name: 'ads.create' }">Add one</RouterLink>.</template>
            </div>

            <!-- MW Realty ads: card grid -->
            <div v-else-if="tab === 'house'" class="adm-grid">
                <article v-for="row in page.data" :key="row.id" class="adm-card" :class="{ 'is-off': !row.status }">
                    <RouterLink :to="{ name: 'ads.show', params: { id: row.id } }" class="adm-card__media">
                        <img :src="row.thumb" :alt="row.name" loading="lazy">
                        <span class="adp-chip adm-card__state" :class="`tone-${state(row).tone}`"><i class="fas" :class="state(row).icon"></i>{{ state(row).label }}</span>
                        <span class="adm-card__order" title="Slide order">#{{ row.order }}</span>
                        <span class="adm-card__page"><i class="fas" :class="placementIcon(row.placement)"></i>{{ row.placement_label }}</span>
                    </RouterLink>
                    <div class="adm-card__body">
                        <RouterLink :to="{ name: 'ads.show', params: { id: row.id } }" class="adm-card__title">{{ row.name }}</RouterLink>
                        <div class="adm-card__meta"><i class="far fa-calendar"></i>{{ schedule(row) }}</div>
                        <div class="adm-card__meta text-truncate" :title="row.link.label"><i class="fas fa-link"></i>{{ row.link.label || 'No link (image only)' }}</div>
                    </div>
                    <div class="adm-card__stats">
                        <div><strong>{{ number(row.stats.impressions) }}</strong><span>Impressions</span></div>
                        <div><strong>{{ number(row.stats.clicks) }}</strong><span>Clicks</span></div>
                        <div><strong>{{ row.stats.ctr }}%</strong><span>CTR</span></div>
                    </div>
                    <div class="adm-card__foot">
                        <div class="form-check form-switch m-0" :title="row.status ? 'Switch off' : 'Switch on'">
                            <input :id="`adOn${row.id}`" class="form-check-input" type="checkbox" :checked="row.status" :disabled="busy === row.id" @change="toggle(row)">
                            <label class="form-check-label small fw-semibold" :for="`adOn${row.id}`">{{ row.status ? 'On' : 'Off' }}</label>
                        </div>
                        <div class="adm-card__actions">
                            <button type="button" class="adm-icon-btn" title="Show earlier" aria-label="Move up" :disabled="busy === row.id" @click="move(row, 'up')"><i class="fas fa-arrow-up"></i></button>
                            <button type="button" class="adm-icon-btn" title="Show later" aria-label="Move down" :disabled="busy === row.id" @click="move(row, 'down')"><i class="fas fa-arrow-down"></i></button>
                            <RouterLink :to="{ name: 'ads.edit', params: { id: row.id } }" class="adm-icon-btn" title="Edit" aria-label="Edit"><i class="fas fa-pen"></i></RouterLink>
                            <button type="button" class="adm-icon-btn is-danger" title="Delete" aria-label="Delete" :disabled="busy === row.id" @click="remove(row)"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Client ads: review cards -->
            <div v-else class="adm-reviews">
                <article v-for="row in page.data" :key="row.id" class="adm-review" :class="{ 'is-pending': row.state === 'pending' }">
                    <a :href="row.thumb" target="_blank" rel="noopener" class="adm-review__media" title="Open the full banner"><img :src="row.thumb" :alt="row.name" loading="lazy"></a>
                    <div class="adm-review__main">
                        <div class="adm-review__client">
                            <span class="adm-avatar">{{ (row.client?.name || '?').charAt(0).toUpperCase() }}</span>
                            <div class="min-w-0">
                                <div class="fw-bold text-truncate">{{ row.client?.name }} <span class="adm-type">{{ row.client?.type }}</span></div>
                                <RouterLink :to="{ name: 'ads.show', params: { id: row.id } }" class="adp-sub text-decoration-none">{{ row.name }}</RouterLink>
                            </div>
                        </div>
                        <div class="adm-facts">
                            <span><i class="fas" :class="placementIcon(row.placement)"></i>{{ row.placement_label }}</span>
                            <span><i class="far fa-clock"></i>{{ adDateTime(row.starts_at) }} · {{ row.duration_label }}</span>
                            <span><i class="fas" :class="row.paid_with === 'credits' ? 'fa-coins' : 'fa-layer-group'"></i>{{ row.paid_with === 'credits' ? `${number(row.credits)} credits` : 'Plan ad' }}</span>
                        </div>
                        <a v-if="row.link.url" :href="row.link.url" target="_blank" rel="noopener" class="adm-review__link"><i class="fas fa-link me-1"></i>{{ row.link.label }} <i class="fas fa-arrow-up-right-from-square small"></i></a>
                        <div v-else class="adm-review__link text-muted"><i class="fas fa-link me-1"></i>{{ row.link.label }}</div>
                        <div v-if="row.review_note && !row.can_approve" class="adp-note"><i class="fas fa-comment me-1"></i>{{ row.review_note }}<template v-if="row.reviewed_by"> — {{ row.reviewed_by }}</template></div>
                    </div>
                    <div class="adm-review__side">
                        <span class="adp-chip" :class="`tone-${state(row).tone}`"><i class="fas" :class="state(row).icon"></i>{{ state(row).label }}</span>
                        <div class="adp-perf"><span><i class="far fa-eye"></i><strong>{{ number(row.stats.impressions) }}</strong></span><span><i class="fas fa-arrow-pointer"></i><strong>{{ number(row.stats.clicks) }}</strong></span><span class="portal-muted">{{ row.stats.ctr }}%</span></div>
                        <div class="adm-review__actions">
                            <button v-if="row.can_approve" type="button" class="btn btn-sm adm-approve" :disabled="busy === row.id" @click="approve(row)"><i class="fas fa-check me-1"></i>Approve</button>
                            <button v-if="row.can_reject" type="button" class="btn btn-sm adm-reject" :disabled="busy === row.id" @click="openReject(row)"><i class="fas fa-xmark me-1"></i>{{ row.state === 'live' ? 'Take down' : 'Reject' }}</button>
                            <RouterLink :to="{ name: 'ads.show', params: { id: row.id } }" class="adm-icon-btn" title="Report" aria-label="Report"><i class="fas fa-chart-line"></i></RouterLink>
                        </div>
                    </div>
                </article>
            </div>

            <div class="mt-3"><CrmPagination :meta="page.meta" @change="(p) => go({ page: p }, false)" /></div>
        </template>

        <!-- ================= Placements & pricing ================= -->
        <AdPlacementsPanel v-else-if="tab === 'placements'" />

        <RejectAdModal :ad="rejecting" @close="rejecting = null" @done="rejecting = null; load()" />
    </div>
</template>

<script setup>
/**
 * Super Admin › Advertisements (replaced CMS › Ad Management): MW Realty's own ads as cards (on/off,
 * slide order, edit, delete), the client ads to review (approve / reject with a reason, take down),
 * and per-page client slots + credit prices. Tab, filters and page live in the URL query.
 */
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useSession } from '../../composables/useSession';
import { useToast } from '../../composables/useToast';
import AdPlacementsPanel from './AdPlacementsPanel.vue';
import RejectAdModal from './RejectAdModal.vue';
import { AD_STATES, adDate, adDateTime, number } from './adFormat';
import { placementIcon } from './placements';

const route = useRoute();
const router = useRouter();
const { confirm } = useConfirm();
const { success, error: toastError } = useToast();
const { refreshNavigation } = useSession();

const page = ref(null);
const loadError = ref('');
const busy = ref(null);
const rejecting = ref(null);
const search = ref(route.query.q ?? '');

const tab = computed(() => (['client', 'placements'].includes(route.query.tab) ? route.query.tab : 'house'));
const pending = computed(() => page.value?.counts.client.pending ?? 0);
const tabs = computed(() => [
    { key: 'house', label: 'MW Realty ads', icon: 'fa-bullhorn', badge: page.value?.counts.house.all ?? null },
    { key: 'client', label: 'Client ads', icon: 'fa-user-tie', badge: pending.value || null, alert: true },
    { key: 'placements', label: 'Placements & pricing', icon: 'fa-table-cells-large', badge: null },
]);
const stateTabs = computed(() => {
    const c = page.value.counts[tab.value];
    return tab.value === 'client'
        ? [['pending', 'Waiting for review'], ['upcoming', 'Approved · upcoming'], ['live', 'Live'], ['past', 'Past'], ['all', 'All']].map(([key, label]) => ({ key, label, count: c[key] }))
        : [['all', 'All'], ['live', 'Live'], ['scheduled', 'Scheduled'], ['ended', 'Ended'], ['off', 'Off']].map(([key, label]) => ({ key, label, count: c[key] }));
});

const state = (row) => (row.is_client ? AD_STATES[row.state] : ({
    live: { label: 'Live', icon: 'fa-circle', tone: 'green' },
    scheduled: { label: 'Scheduled', icon: 'fa-calendar', tone: 'blue' },
    ended: { label: 'Ended', icon: 'fa-flag-checkered', tone: 'grey' },
    off: { label: 'Off', icon: 'fa-pause', tone: 'grey' },
}[row.state])) ?? AD_STATES.ended;
const schedule = (row) => (!row.starts_at && !row.ends_at ? 'Always on' : `${row.starts_at ? adDate(row.starts_at) : '…'} → ${row.ends_at ? adDate(row.ends_at) : 'no end'}`);

/** Change tab / filters (keeps the others; a new tab starts on its default filter). */
function go(changes, resetPage = true) {
    const base = 'tab' in changes ? {} : { ...route.query };
    const query = { ...base, ...changes };
    if (resetPage && !('page' in changes)) delete query.page;
    if (query.tab === 'house') delete query.tab;
    Object.keys(query).forEach((k) => query[k] === undefined && delete query[k]);
    router.push({ query });
}

function load() {
    if (tab.value === 'placements' && page.value) return Promise.resolve();
    loadError.value = '';
    const { tab: t, ...params } = route.query;
    return http.get('/ads/manage', { params: { ...params, tab: tab.value === 'placements' ? 'house' : tab.value } })
        .then((res) => { page.value = res.data; })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function act(row, request, after) {
    busy.value = row.id;
    return request()
        .then((res) => { if (res.data.message) success(res.data.message); after?.(res); return load(); })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { busy.value = null; });
}

const toggle = (row) => act(row, () => http.post(`/ads/manage/${row.id}/toggle`));
const move = (row, direction) => act(row, () => http.post(`/ads/manage/${row.id}/move`, { direction }));

async function remove(row) {
    const ok = await confirm({ title: 'Delete this ad?', message: `“${row.name}” and its report are removed for good.`, confirmText: 'Delete', tone: 'danger' });
    if (ok) act(row, () => http.delete(`/ads/manage/${row.id}`));
}

async function approve(row) {
    const ok = await confirm({
        title: 'Approve this ad?',
        message: `“${row.name}” by ${row.client?.name} runs on ${row.placement_label} from ${adDateTime(row.starts_at)} for ${row.duration_label}.`,
        confirmText: 'Approve',
        tone: 'primary',
    });
    if (ok) act(row, () => http.post(`/ads/manage/${row.id}/approve`), refreshNavigation);
}

function openReject(row) {
    rejecting.value = row;
}

watch(() => route.query, () => {
    search.value = route.query.q ?? '';
    if (route.name === 'ads.index') load();
}, { immediate: true });
</script>
