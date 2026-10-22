<template>
    <div v-if="!page" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>

    <PlanLockedCard v-else-if="!quota.included" title="Advertisements" heading="Advertise on the MW Realty website" icon="fas fa-rectangle-ad"
                    description="Put your banner in the advertisement slider on the website's pages for a few hours or days, and see how many people saw and clicked it."
                    feature="Website advertising" :locked="{ plan: quota.plan, agency: null }" />

    <div v-else>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <div class="portal-section-title mb-1">Advertisements</div>
                <p class="portal-muted small mb-0">Your banners in the advertisement slider on the MW Realty website. Each ad is reviewed by MW Realty, then runs by itself in the hours you booked (Dubai time).</p>
            </div>
            <RouterLink :to="{ name: 'ads.create' }" class="btn btn-portal-primary btn-sm"><i class="fas fa-plus me-1"></i> Book an ad</RouterLink>
        </div>

        <div class="adp-kpis">
            <div class="adp-kpi">
                <span class="adp-kpi__icon is-live"><i class="fas fa-tower-broadcast"></i></span>
                <div><div class="adp-kpi__value">{{ page.counts.live }}</div><div class="adp-kpi__label">Live now</div></div>
            </div>
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
            <div class="adp-kpi adp-allow">
                <div class="adp-allow__head"><span><i class="fas fa-layer-group me-1"></i>{{ quota.plan }} plan</span><span>Up to {{ quota.max_label }} per ad</span></div>
                <template v-if="quota.limit > 0">
                    <div class="adp-allow__value">{{ quota.remaining }} <small>of {{ quota.limit }} ads left this month</small></div>
                    <div class="adp-allow__bar"><span :style="{ width: `${Math.round((quota.remaining / quota.limit) * 100)}%` }"></span></div>
                </template>
                <div v-else class="adp-allow__value"><small>Ads are paid with credits on your plan</small></div>
                <div class="adp-allow__meta">
                    <span v-if="quota.limit > 0">{{ quota.used }} plan {{ quota.used === 1 ? 'ad' : 'ads' }} used · resets {{ quota.resets }}</span>
                    <span v-if="quota.credits"><i class="fas fa-coins me-1"></i>{{ number(quota.balance) }} credits</span>
                </div>
                <div v-if="quota.credit_ads" class="adp-allow__meta">
                    <span><i class="fas fa-circle-info me-1"></i>+ {{ quota.credit_ads }} {{ quota.credit_ads === 1 ? 'ad' : 'ads' }} this month paid with credits (not from your plan)</span>
                </div>
            </div>
        </div>

        <div class="portal-list-toolbar mb-3">
            <div class="portal-featured-tabs" role="tablist">
                <RouterLink v-for="tab in tabs" :key="tab.key" :to="{ query: tab.key === 'all' ? {} : { status: tab.key } }" class="portal-featured-tabs__tab" :class="{ 'is-active': page.filter === tab.key }"
                            role="tab" :aria-selected="page.filter === tab.key ? 'true' : 'false'">
                    {{ tab.label }} <span>{{ tab.count }}</span>
                </RouterLink>
            </div>
        </div>

        <div class="portal-card p-0 overflow-hidden">
            <div v-if="!page.data.length" class="p-5 text-center portal-empty">
                <i class="fas fa-rectangle-ad fa-2x mb-2 d-block" style="color:#ca2844"></i>
                {{ emptyText }}
                <RouterLink :to="{ name: 'ads.create' }">Book an ad</RouterLink>.
            </div>
            <div v-else class="table-responsive">
                <table class="table portal-featured-table adp-table mb-0">
                    <thead>
                        <tr>
                            <th>Ad</th>
                            <th>Page</th>
                            <th>When (Dubai)</th>
                            <th>Status</th>
                            <th>Performance</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in page.data" :key="row.id">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img :src="row.thumb" alt="" class="adp-thumb">
                                    <div class="min-w-0">
                                        <RouterLink :to="{ name: 'ads.show', params: { id: row.id } }" class="adp-name">{{ row.name }}</RouterLink>
                                        <div class="adp-sub text-truncate" style="max-width: 260px;"><i class="fas fa-link me-1"></i>{{ row.link.label }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="small">{{ row.placement_label }}</td>
                            <td class="small">
                                <div class="fw-semibold">{{ adDateTime(row.starts_at) }}</div>
                                <div class="portal-muted">{{ row.duration_label }} · {{ row.paid_with === 'credits' ? `paid ${number(row.credits)} credits` : 'from your plan' }}</div>
                            </td>
                            <td>
                                <span class="adp-chip" :class="`tone-${state(row).tone}`"><i class="fas" :class="state(row).icon"></i>{{ state(row).label }}</span>
                                <div v-if="row.review_note && ['rejected', 'expired'].includes(row.state)" class="adp-note">{{ row.review_note }}</div>
                            </td>
                            <td>
                                <div class="adp-perf">
                                    <span title="Impressions — times the ad was shown on screen"><i class="far fa-eye"></i><strong>{{ number(row.stats.impressions) }}</strong></span>
                                    <span title="Clicks"><i class="fas fa-arrow-pointer"></i><strong>{{ number(row.stats.clicks) }}</strong></span>
                                    <span class="portal-muted" title="Click rate">{{ row.stats.ctr }}%</span>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <RouterLink :to="{ name: 'ads.show', params: { id: row.id } }" class="portal-btn-ghost btn btn-sm" title="Report" aria-label="Report"><i class="fas fa-chart-line"></i></RouterLink>
                                <RouterLink v-if="row.editable" :to="{ name: 'ads.edit', params: { id: row.id } }" class="portal-btn-ghost btn btn-sm" title="Change banner, text or link" aria-label="Edit"><i class="fas fa-edit"></i></RouterLink>
                                <button v-if="row.cancellable" type="button" class="btn btn-sm portal-featured-stop" :disabled="busy === row.id" @click="cancel(row)">{{ row.state === 'live' ? 'Stop' : 'Cancel' }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3"><CrmPagination :meta="page.meta" @change="goToPage" /></div>
    </div>
</template>

<script setup>
/**
 * CRM › Advertisements — the account's website ads (live, upcoming, past) with impressions / clicks,
 * the last 30 days' totals and the plan's ad allowance. Plans without advertising get the upgrade card.
 */
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import PlanLockedCard from '../../components/PlanLockedCard.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { AD_STATES, adDateTime, number } from './adFormat';
import { cancelAdPrompt } from './cancelAd';

const route = useRoute();
const router = useRouter();
const { confirm } = useConfirm();
const { success, error: toastError } = useToast();

const page = ref(null);
const loadError = ref('');
const busy = ref(null);

const quota = computed(() => page.value?.quota ?? {});
const tabs = computed(() => [
    { key: 'all', label: 'All', count: page.value.counts.all },
    { key: 'live', label: 'Live', count: page.value.counts.live },
    { key: 'upcoming', label: 'Upcoming', count: page.value.counts.upcoming },
    { key: 'past', label: 'Past', count: page.value.counts.past },
]);
const emptyText = computed(() => ({
    live: 'None of your ads is running right now.',
    upcoming: 'Nothing booked or waiting for review.',
    past: 'No finished ads yet.',
}[page.value.filter] ?? 'You haven\'t booked an ad yet.'));

const state = (row) => AD_STATES[row.state] ?? AD_STATES.ended;

function load() {
    loadError.value = '';
    return http.get('/ads', { params: { status: route.query.status, page: route.query.page } })
        .then((res) => { page.value = res.data; })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function goToPage(p) {
    router.push({ query: { ...route.query, page: p } });
}

async function cancel(row) {
    if (!(await confirm(cancelAdPrompt(row)))) return;
    busy.value = row.id;
    http.delete(`/ads/${row.id}`)
        .then((res) => { success(res.data.message); load(); })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { busy.value = null; });
}

watch(() => route.query, () => {
    if (route.name === 'ads.index') load();
}, { immediate: true });
</script>
