<template>
    <!-- MW Realty's own plan sales — Super Admin only. -->
    <template v-if="d.plans">
        <div class="portal-card p-4 mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <div class="portal-section-title mb-0">MW Realty Plan Sales</div>
                    <div class="text-muted small">Paid plan subscriptions from agents &amp; agencies ({{ page.range_label }}). Visible to Super Admin only.</div>
                </div>
                <a :href="d.plans.payments_url" class="btn btn-sm portal-btn-ghost">All Payments <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <div class="row g-3 mb-3">
                <div v-for="stat in planStats" :key="stat.label" class="col-6 col-lg-3">
                    <div class="portal-stat-card">
                        <div class="portal-stat-icon" :class="stat.tone"><i class="fas" :class="stat.icon"></i></div>
                        <div><div class="portal-stat-value">{{ stat.value }} <RpChange v-if="stat.change !== undefined" :pct="stat.change" :days="page.days" /></div><div class="portal-stat-label">{{ stat.label }}</div></div>
                    </div>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-lg-8"><div class="rp-chart rp-chart--sm"><ChartCanvas :config="rpChart('line', d.plans.over_time)" /></div></div>
                <div class="col-lg-4">
                    <div v-for="(plan, i) in d.plans.by_plan" :key="i" class="d-flex justify-content-between py-2" :class="{ 'border-bottom': i < d.plans.by_plan.length - 1 }">
                        <span><span class="fw-semibold">{{ plan.name }}</span> <span class="text-muted small">&middot; {{ plan.payments }} {{ plural('payment', plan.payments) }}</span></span>
                        <span class="fw-bold">{{ aed(plan.total) }}</span>
                    </div>
                    <div v-if="!d.plans.by_plan.length" class="rp-empty">No paid plan payments in this period.</div>
                </div>
            </div>
        </div>
        <div class="portal-section-title mt-4">Property Sales <span class="text-muted fw-normal small">— all agents &amp; agencies</span></div>
    </template>

    <div class="row g-3 mb-3">
        <div v-for="stat in topStats" :key="stat.label" class="col-6 col-lg-3">
            <div class="portal-stat-card">
                <div class="portal-stat-icon" :class="stat.tone"><i class="fas" :class="stat.icon"></i></div>
                <div><div class="portal-stat-value">{{ stat.value }} <RpChange v-if="stat.change !== undefined" :pct="stat.change" :days="page.days" /></div><div class="portal-stat-label">{{ stat.label }}</div></div>
            </div>
        </div>
        <div v-for="stat in subStats" :key="stat.label" class="col-md-4">
            <div class="portal-stat-card">
                <div class="portal-stat-icon" :class="stat.tone"><i class="fas" :class="stat.icon"></i></div>
                <div><div class="portal-stat-value">{{ stat.value }}</div><div class="portal-stat-label">{{ stat.label }} &middot; {{ stat.sub }}</div></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="portal-card p-4 h-100">
                <div class="portal-section-title">Sales Over Time</div>
                <div class="rp-chart"><ChartCanvas :config="rpChart('bar', d.over_time)" /></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="portal-card p-4 h-100">
                <div class="portal-section-title">Sale vs Rent</div>
                <div v-if="!d.by_listing_type.labels.length" class="rp-empty">No deals closed in this period.</div>
                <div v-else class="rp-chart"><ChartCanvas :config="rpChart('doughnut', d.by_listing_type, ['#264373', '#04a1cc', '#f59e0b'])" /></div>
            </div>
        </div>

        <div v-for="block in d.breakdowns" :key="block.title" class="col-lg-6">
            <div class="portal-card p-4 h-100">
                <div class="portal-section-title">{{ block.title }}</div>
                <div v-for="(row, i) in block.rows" :key="i" class="py-2" :class="{ 'border-bottom': i < block.rows.length - 1 }">
                    <div class="d-flex justify-content-between gap-2"><span class="fw-semibold text-truncate">{{ row.name }}</span><span class="fw-bold text-nowrap">{{ aed(row.total) }}</span></div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="rp-bar flex-grow-1"><span :style="{ width: `${(row.total / maxOf(block.rows)) * 100}%` }"></span></div>
                        <span class="text-muted small text-nowrap">{{ row.deals }} {{ plural('deal', row.deals) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="portal-card p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <div class="portal-section-title mb-0">All Deals</div>
                        <div class="text-muted small">{{ number(d.deals.meta.total) }} {{ plural('deal', d.deals.meta.total) }} &middot; {{ page.range_label }}</div>
                    </div>
                    <button type="button" class="btn btn-sm portal-btn-ghost" :disabled="exporting" @click="exportCsv"><i class="fas fa-file-csv me-1"></i>Export CSV</button>
                </div>

                <form class="row g-2 mb-3" @submit.prevent="applyFilters">
                    <div class="col-md-5"><input v-model="form.q" type="search" class="form-control form-control-sm" placeholder="Search client, email, phone, property, reference or agent"></div>
                    <div class="col-6 col-md-2">
                        <select v-model="form.outcome" class="form-select form-select-sm">
                            <option v-for="o in d.outcomes" :key="o.key" :value="o.key">{{ o.label }}</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select v-model="form.listing" class="form-select form-select-sm">
                            <option value="">Sale &amp; Rent</option>
                            <option value="sale">Sale</option>
                            <option value="rent">Rent</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-sm btn-portal-primary flex-grow-1" type="submit"><i class="fas fa-search me-1"></i>Filter</button>
                        <RouterLink v-if="hasFilters" :to="{ query: { range: page.days } }" class="btn btn-sm portal-btn-ghost">Clear</RouterLink>
                    </div>
                </form>

                <div v-if="!d.deals.data.length" class="rp-empty">No deals match. Move a lead to a won stage (e.g. “Closed Won”) in the CRM and it shows up here.</div>
                <template v-else>
                    <div class="table-responsive">
                        <table class="table rp-table mb-0">
                            <thead><tr>
                                <th>Closed</th><th>Client</th><th>Property</th><th>Agent</th><th v-if="page.is_admin">Account</th><th>Outcome</th><th class="text-end">Value</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="deal in d.deals.data" :key="deal.id">
                                    <td class="small text-muted text-nowrap">{{ deal.closed_at ? formatDate(deal.closed_at) : '' }}</td>
                                    <td>
                                        <RouterLink :to="{ name: 'leads.show', params: { id: deal.id } }" class="fw-semibold text-decoration-none">{{ deal.name }}</RouterLink>
                                        <div class="text-muted small">{{ deal.contact }}</div>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 260px;">{{ deal.property }}</div>
                                        <div class="text-muted small">{{ deal.reference_no }} &middot; {{ deal.listing_type }}<template v-if="deal.location"> &middot; {{ deal.location }}</template></div>
                                    </td>
                                    <td class="small">{{ deal.agent ?? '—' }}</td>
                                    <td v-if="page.is_admin" class="small">{{ deal.account ?? '—' }}</td>
                                    <td><span class="badge" :style="{ background: deal.stage?.color ?? '#64748b' }">{{ deal.stage?.name }}</span></td>
                                    <td class="text-end fw-bold text-nowrap">{{ aed(deal.value) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3"><CrmPagination :meta="d.deals.meta" @change="goToPage" /></div>
                </template>
            </div>
        </div>
    </div>

    <div class="text-muted small mt-3">
        <i class="fas fa-info-circle me-1"></i>A sale is a lead in a closed stage that isn't a “lost / dropped” stage, valued at its property's listed price (AED) on the date it was closed. Leads without a property aren't counted.
    </div>
</template>

<script setup>
/** Sales report (resources/views/portal/crm/reports/sales) — filters and the deals page live in the URL. */
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ChartCanvas from '../../../components/ChartCanvas.vue';
import CrmPagination from '../../../components/CrmPagination.vue';
import { useToast } from '../../../composables/useToast';
import { download } from '../../../utils/download';
import { formatDate, number, plural } from '../../../utils/format';
import { aed, rpChart } from '../rpChart';
import RpChange from './RpChange.vue';

const props = defineProps({ page: { type: Object, required: true } });

const route = useRoute();
const router = useRouter();
const { error: toastError } = useToast();

const d = computed(() => props.page.data);
const form = reactive({ q: '', outcome: 'won', listing: '' });
const exporting = ref(false);
const hasFilters = computed(() => ['q', 'outcome', 'listing'].some((key) => route.query[key]));
const maxOf = (rows) => Math.max(1, ...rows.map((r) => r.total));

watch(() => d.value.filters, (filters) => Object.assign(form, filters), { immediate: true });

const planStats = computed(() => {
    const p = d.value.plans;
    return [
        { label: 'Plan Sales', value: aed(p.total), change: p.change, icon: 'fa-coins', tone: 'indigo' },
        { label: 'Payments', value: number(p.count), icon: 'fa-receipt', tone: 'teal' },
        { label: 'Paying Accounts', value: number(p.paying_accounts), icon: 'fa-users', tone: 'amber' },
        { label: 'Coupon Discounts', value: aed(p.discounts), icon: 'fa-tags', tone: 'rose' },
    ];
});

const topStats = computed(() => {
    const k = d.value.kpis;
    return [
        { label: 'Total Sales', value: aed(k.won_value), change: k.change, icon: 'fa-sack-dollar', tone: 'indigo' },
        { label: 'Deals Closed', value: number(k.won_count), icon: 'fa-handshake', tone: 'teal' },
        { label: 'Average Deal', value: aed(k.average), icon: 'fa-chart-line', tone: 'amber' },
        { label: 'Win Rate', value: k.win_rate === null ? '—' : `${k.win_rate}%`, icon: 'fa-trophy', tone: 'rose' },
    ];
});

const subStats = computed(() => {
    const k = d.value.kpis;
    return [
        { label: 'Lost Deals', value: aed(k.lost_value), sub: `${number(k.lost_count)} ${plural('deal', k.lost_count)}`, icon: 'fa-circle-xmark', tone: 'rose' },
        { label: 'Open Pipeline', value: aed(k.pipeline_value), sub: `${number(k.pipeline_count)} open ${plural('deal', k.pipeline_count)}`, icon: 'fa-filter-circle-dollar', tone: 'indigo' },
        { label: 'All-Time Sales', value: aed(k.all_time_value), sub: `${number(k.all_time_count)} ${plural('deal', k.all_time_count)} ever`, icon: 'fa-landmark', tone: 'teal' },
    ];
});

function applyFilters() {
    router.push({ query: { range: props.page.days, q: form.q.trim() || undefined, outcome: form.outcome, listing: form.listing || undefined } });
}

function goToPage(p) {
    router.push({ query: { ...route.query, page: p } });
}

function exportCsv() {
    const params = new URLSearchParams(Object.entries({ range: props.page.days, ...d.value.filters }).filter(([, v]) => v !== '' && v !== null));
    exporting.value = true;
    download('get', `/reports/sales/export?${params}`)
        .catch(() => toastError('Could not export the deals — please try again.'))
        .finally(() => { exporting.value = false; });
}
</script>
