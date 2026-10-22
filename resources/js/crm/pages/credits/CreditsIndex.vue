<template>
    <div v-if="!page" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else-if="unavailable" class="text-muted"><i class="fas fa-coins d-block fs-3 mb-2 opacity-50"></i>{{ loadError }}</div>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else class="crp">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <div class="portal-section-title mb-1">Credits</div>
                <p class="portal-muted small mb-0">Pay-as-you-go add-ons on top of your plan — an extra listing once your allowance is used, or Premium for 7, 15 or 30 days.</p>
            </div>
        </div>

        <div v-if="!page.enabled && !page.is_admin" class="alert alert-warning small"><i class="fas fa-info-circle me-1"></i> Buying credits is paused by MW Realty for now — you can still use the credits you have until they expire.</div>

        <!-- Balance + period totals -->
        <div class="crp-top">
            <div v-if="page.wallet" class="crp-balance">
                <div class="crp-balance__head">
                    <span class="crp-balance__icon"><i class="fas fa-coins"></i></span>
                    <div class="min-w-0">
                        <div class="crp-balance__label">Remaining credits</div>
                    </div>
                </div>
                <div class="crp-balance__value">{{ num(page.wallet.balance) }}<small v-if="page.wallet.active"> of {{ num(page.wallet.active) }}</small></div>
                <div class="crp-balance__bar"><span :style="{ width: `${page.wallet.percent}%` }"></span></div>
                <div class="crp-balance__meta">
                    <span v-if="page.wallet.active">{{ page.wallet.percent }}% left · {{ num(page.wallet.active - page.wallet.balance) }} used</span>
                    <span v-else>No active credits</span>
                    <span v-if="page.wallet.expiring" :class="{ 'is-soon': page.wallet.expiring.days <= 3 }">
                        <i class="far fa-clock me-1"></i>{{ num(page.wallet.expiring.credits) }} expire {{ page.wallet.expiring.days === 0 ? 'today' : `in ${page.wallet.expiring.days} days` }}
                    </span>
                </div>
                <div class="crp-balance__actions">
                    <button v-if="page.top_up.allowed" type="button" class="btn crp-btn-light btn-sm" @click="topUpOpen = true"><i class="fas fa-plus me-1"></i> Buy credits</button>
                    <button type="button" class="btn crp-btn-ghost btn-sm" @click="calculatorOpen = true"><i class="fas fa-calculator me-1"></i> Calculator</button>
                </div>
            </div>

            <!-- The plan next to the credits: what's left of this month's allowance; credits take over when it runs out. -->
            <div v-if="page.plan" class="crp-plan">
                <div class="crp-plan__head">
                    <span class="crp-plan__title">Your plan</span>
                    <span class="crp-plan__name">{{ page.plan.name || 'No plan' }}</span>
                    <RouterLink :to="{ name: 'plans.index' }" class="crp-plan__link">Plans <i class="fas fa-arrow-right"></i></RouterLink>
                </div>
                <div v-for="row in planRows" :key="row.key" class="crp-plan__row">
                    <div class="crp-plan__row-head">
                        <span><i class="fas me-1" :class="row.icon"></i>{{ row.label }}</span>
                        <strong :class="{ 'is-full': row.full }">{{ row.text }}</strong>
                    </div>
                    <div v-if="row.percent !== null" class="crp-plan__bar" :class="{ 'is-full': row.full, 'is-near': row.near }"><span :style="{ width: `${row.percent}%` }"></span></div>
                </div>
                <div v-if="planNote" class="crp-plan__note" :class="{ 'is-full': planFull }"><i class="fas" :class="planFull ? 'fa-circle-exclamation' : 'fa-circle-info'"></i> {{ planNote }}</div>
            </div>

            <div class="crp-stats">
                <div class="crp-stat">
                    <span class="crp-stat__icon is-in"><i class="fas fa-arrow-down"></i></span>
                    <div><div class="crp-stat__value">{{ num(page.totals.added) }}</div><div class="crp-stat__label">Credits added</div></div>
                </div>
                <div class="crp-stat">
                    <span class="crp-stat__icon is-out"><i class="fas fa-arrow-up"></i></span>
                    <div><div class="crp-stat__value">{{ num(page.totals.used) }}</div><div class="crp-stat__label">Used on add-ons</div></div>
                </div>
                <div class="crp-stat">
                    <span class="crp-stat__icon is-expired"><i class="far fa-hourglass"></i></span>
                    <div><div class="crp-stat__value">{{ num(page.totals.expired) }}</div><div class="crp-stat__label">Expired unused</div></div>
                </div>
                <div class="crp-stats__period">{{ formatDate(page.range.from) }} – {{ formatDate(page.range.to) }}</div>
            </div>
        </div>

        <!-- History -->
        <div class="portal-card p-0 overflow-hidden">
            <div class="crp-toolbar">
                <div class="crp-toolbar__title">Transaction history <span v-if="page.meta.total" class="crp-count">{{ page.meta.total }}</span></div>
                <form class="crp-toolbar__filters" @submit.prevent="applyFilters">
                    <div class="crp-search">
                        <i class="fas fa-search"></i>
                        <input v-model="filters.q" type="search" class="form-control form-control-sm" placeholder="Listing reference" aria-label="Search by reference" @search="applyFilters">
                    </div>
                    <div class="crp-ranges" role="group" aria-label="Period">
                        <button v-for="r in ranges" :key="r.days" type="button" class="crp-range" :class="{ 'is-active': activeRange === r.days }" @click="setRange(r.days)">{{ r.label }}</button>
                    </div>
                    <div class="crp-dates">
                        <input v-model="filters.from" type="date" class="form-control form-control-sm" aria-label="From" :max="filters.to" @change="applyFilters">
                        <span>→</span>
                        <input v-model="filters.to" type="date" class="form-control form-control-sm" aria-label="To" :min="filters.from" @change="applyFilters">
                    </div>
                    <button v-if="page.meta.total > 0" type="button" class="btn btn-portal-light btn-sm" :disabled="exporting" @click="exportCsv">
                        <span v-if="exporting" class="spinner-border spinner-border-sm me-1"></span><i v-else class="fas fa-download me-1"></i> Export CSV
                    </button>
                </form>
            </div>

            <div v-if="!page.data.length" class="crp-empty">
                <span class="crp-empty__icon"><i class="fas fa-receipt"></i></span>
                <div class="fw-bold">{{ filters.q ? `Nothing matches “${filters.q}”` : 'No credit activity in this period' }}</div>
                <div class="small text-muted">Purchases, add-ons you pay for and expiries show up here.</div>
                <button v-if="page.top_up.allowed && !page.wallet?.balance" type="button" class="btn btn-portal-primary btn-sm mt-3" @click="topUpOpen = true"><i class="fas fa-plus me-1"></i> Buy credits</button>
            </div>

            <ul v-else class="crp-list">
                <li v-for="t in page.data" :key="t.id" class="crp-row">
                    <span class="crp-row__icon" :class="`is-${t.type}`"><i class="fas" :class="icon(t)"></i></span>
                    <div class="crp-row__main">
                        <div class="crp-row__title">
                            {{ t.description }}
                            <RouterLink v-if="t.property_id && t.reference" :to="{ name: t.segment === 'commercial' ? 'commercial.show' : 'properties.show', params: { id: t.property_id } }" class="crp-row__ref">{{ t.reference }}</RouterLink>
                            <span v-else-if="t.reference" class="crp-row__ref is-plain">{{ t.reference }}</span>
                        </div>
                        <div class="crp-row__meta">
                            <span><img v-if="t.avatar" :src="t.avatar" alt="" class="crp-row__avatar">{{ t.originator }}</span>
                            <span v-if="page.is_admin && t.account">· {{ t.account }}</span>
                            <span>· {{ dateTime(t.date) }}</span>
                        </div>
                        <div v-if="t.property_type || t.category || t.location" class="crp-row__tags">
                            <span v-if="t.category" class="crp-tag">{{ t.category }}</span>
                            <span v-if="t.property_type" class="crp-tag">{{ t.property_type }}</span>
                            <span v-if="t.location" class="crp-tag"><i class="fas fa-location-dot me-1"></i>{{ t.location }}</span>
                        </div>
                    </div>
                    <div class="crp-row__amount">
                        <div class="crp-row__credits" :class="t.credits > 0 ? 'is-plus' : 'is-minus'">{{ t.credits > 0 ? '+' : '−' }}{{ num(Math.abs(t.credits)) }}</div>
                        <div class="crp-row__balance">Balance {{ num(t.balance) }}</div>
                        <div v-if="t.total !== null" class="crp-row__paid">AED {{ money(t.total) }} paid</div>
                        <div v-else-if="t.expires_at" class="crp-row__paid">valid until {{ formatDate(t.expires_at) }}</div>
                    </div>
                </li>
            </ul>
        </div>
        <div class="mt-3"><CrmPagination :meta="page.meta" @change="goToPage" /></div>

        <CreditCalculatorModal :open="calculatorOpen" :price-per-credit="page.top_up.price_per_credit" :can-top-up="page.top_up.allowed"
                               @close="calculatorOpen = false" @top-up="calculatorOpen = false; topUpOpen = true" />
        <TopUpModal :open="topUpOpen" :settings="page.top_up" @close="topUpOpen = false" @paid="load" />
    </div>
</template>

<script setup>
/**
 * Credits — balance (with how much of the active credits is left), totals for the period, the
 * transaction history (search by reference, period, CSV export), the calculator and Buy credits.
 * GET /credits. `?buy=1` opens Buy credits (the sidebar meter's button).
 */
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import { useSession } from '../../composables/useSession';
import { useToast } from '../../composables/useToast';
import { download } from '../../utils/download';
import { formatDate } from '../../utils/format';
import CreditCalculatorModal from './CreditCalculatorModal.vue';
import TopUpModal from './TopUpModal.vue';

const route = useRoute();
const router = useRouter();
const { error: toastError } = useToast();
const { refreshNavigation } = useSession();

const page = ref(null);
const loadError = ref('');
const unavailable = ref(false); // credits switched off by MW Realty (API answers 404)
const filters = reactive({ q: '', from: '', to: '' });
const calculatorOpen = ref(false);
const topUpOpen = ref(false);
const exporting = ref(false);

const ranges = [{ days: 30, label: '30 days' }, { days: 90, label: '90 days' }, { days: 365, label: '12 months' }];
const ICONS = { topup: 'fa-plus', grant: 'fa-gift', refund: 'fa-rotate-left', expire: 'fa-hourglass-end' };

const num = (n) => Number(n || 0).toLocaleString('en-US');
const money = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const icon = (t) => ICONS[t.type] ?? (t.kind === 'listing' ? 'fa-building' : 'fa-star');
const pad = (n) => String(n).padStart(2, '0');
const isoDay = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const daysAgo = (n) => { const d = new Date(); d.setDate(d.getDate() - n); return isoDay(d); };
// "Your plan" panel: listings + premium against the allowance.
const planRows = computed(() => {
    const p = page.value?.plan;
    if (!p) return [];
    const row = (key, icon, label, u) => {
        if (!u) return null;
        const unlimited = u.limit === null;
        const percent = unlimited || !u.limit ? null : Math.min(100, Math.round((u.used / u.limit) * 100));
        return {
            key, icon, label, percent,
            text: unlimited ? `${u.used} · unlimited` : `${num(u.used)} / ${num(u.limit)}${u.per_month ? ' this month' : ''}`,
            full: !unlimited && u.remaining === 0,
            near: !unlimited && u.remaining > 0 && percent >= 90,
        };
    };
    return [row('listings', 'fa-building', 'New listings', p.listings), row('premium', 'fa-star', 'Premium', p.premium)].filter(Boolean);
});
const planFull = computed(() => planRows.value.some((r) => r.full));
const planNote = computed(() => {
    const resets = page.value?.plan?.resets ? formatDate(page.value.plan.resets) : null;
    const full = planRows.value.filter((r) => r.full).map((r) => r.label.toLowerCase());
    if (full.length) return `${ucfirst(full.join(' and '))} used up${resets ? ` until ${resets}` : ''} — add more with credits${page.value.wallet?.balance || !page.value.top_up.allowed ? '' : ' (buy some first)'}.`;
    if (planRows.value.some((r) => r.near)) return `Almost used up — once it is, credits let you keep going${resets ? ` until it resets on ${resets}` : ''}.`;
    return resets ? `Your allowance resets on ${resets}. Credits are only needed beyond it.` : 'Credits are only needed beyond your plan.';
});
const ucfirst = (s) => s.charAt(0).toUpperCase() + s.slice(1);
// Which quick period matches the dates (ending today), if any.
const activeRange = computed(() => (filters.to === isoDay(new Date()) ? (ranges.find((r) => filters.from === daysAgo(r.days))?.days ?? null) : null));
// "12 Oct 2026, 03:53 pm"
const dateTime = (iso) => {
    const d = new Date(iso);
    return `${formatDate(iso)}, ${d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }).toLowerCase()}`;
};

function load() {
    loadError.value = '';
    return http.get('/credits', { params: { q: route.query.q, from: route.query.from, to: route.query.to, page: route.query.page } })
        .then((res) => {
            page.value = res.data;
            filters.q = route.query.q || '';
            filters.from = res.data.range.from;
            filters.to = res.data.range.to;
            refreshNavigation().catch(() => {}); // keep the sidebar meter in step
        })
        .catch((e) => {
            unavailable.value = e.response?.status === 404;
            loadError.value = errorMessage(e);
        });
}

function applyFilters() {
    router.push({ query: { q: filters.q.trim() || undefined, from: filters.from || undefined, to: filters.to || undefined } });
}

function setRange(days) {
    filters.from = daysAgo(days);
    filters.to = isoDay(new Date());
    applyFilters();
}

function goToPage(p) {
    router.push({ query: { ...route.query, page: p > 1 ? p : undefined } });
}

function exportCsv() {
    exporting.value = true;
    const query = new URLSearchParams(Object.entries({ q: filters.q.trim(), from: filters.from, to: filters.to }).filter(([, v]) => v)).toString();
    download('get', `/credits/export${query ? `?${query}` : ''}`)
        .catch((e) => toastError(errorMessage(e, 'Could not export the history.')))
        .finally(() => { exporting.value = false; });
}

let openBuy = false;
watch(() => route.query, (query) => {
    if (route.name !== 'credits.index') return;
    // Sidebar "Buy credits": drop ?buy from the URL (that change reloads the page), then open the popup.
    if (query.buy) {
        openBuy = true;
        router.replace({ query: { ...query, buy: undefined } });
        return;
    }
    load().then(() => {
        if (openBuy && page.value?.top_up.allowed) topUpOpen.value = true;
        openBuy = false;
    });
}, { immediate: true });
</script>
