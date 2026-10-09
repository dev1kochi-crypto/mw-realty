<template>
    <div v-if="!page" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <div class="portal-section-title mb-0">Sold Listings</div>
                <div class="text-muted small">Listings marked sold or rented. They are off the website; revert one to put it back on the market.</div>
            </div>
            <RouterLink :to="{ name: 'reports.show', params: { report: 'sales' } }" class="btn btn-sm portal-btn-ghost"><i class="fas fa-chart-line me-1"></i>Sales Report</RouterLink>
        </div>

        <div class="row g-3 mb-3">
            <div v-for="stat in stats" :key="stat.label" class="col-6 col-lg-3">
                <div class="portal-stat-card">
                    <div class="portal-stat-icon" :class="stat.tone"><i class="fas" :class="stat.icon"></i></div>
                    <div><div class="portal-stat-value">{{ stat.value }}</div><div class="portal-stat-label">{{ stat.label }}</div></div>
                </div>
            </div>
        </div>

        <div class="portal-card p-4">
            <form class="row g-2 mb-3" @submit.prevent="applyFilters">
                <div class="col-md-7"><input v-model="searchInput" type="search" class="form-control form-control-sm" maxlength="100" placeholder="Search title, ref no, address, city or buyer name"></div>
                <div class="col-6 col-md-2">
                    <select v-model="typeInput" class="form-select form-select-sm">
                        <option value="">Sold &amp; Rented</option>
                        <option value="sold">Sold</option>
                        <option value="rented">Rented</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 d-flex gap-2">
                    <button class="btn btn-sm btn-portal-primary flex-grow-1" type="submit"><i class="fas fa-search me-1"></i>Filter</button>
                    <RouterLink v-if="page.search !== '' || page.type" :to="{ query: {} }" class="btn btn-sm portal-btn-ghost">Clear</RouterLink>
                </div>
            </form>

            <div v-if="!page.data.length" class="text-center portal-muted py-5">No sold or rented listings match.</div>
            <template v-else>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr class="small text-uppercase portal-muted">
                                <th>Property</th><th>Outcome</th><th class="text-end">Price</th><th>Date</th><th>Buyer / Tenant</th><th>Agent</th><th v-if="page.is_admin">Account</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in page.data" :key="row.id">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img :src="row.thumb || 'https://placehold.co/64x48?text=%20'" alt="" style="width: 64px; height: 48px; object-fit: cover; border-radius: 8px;">
                                        <div class="min-w-0">
                                            <RouterLink :to="{ name: row.segment === 'commercial' ? 'commercial.show' : 'properties.show', params: { id: row.id } }" class="fw-semibold text-decoration-none d-block text-truncate" style="max-width: 260px;">{{ row.title }}</RouterLink>
                                            <div class="small portal-muted">{{ row.reference_no }}<template v-if="row.type_label"> &middot; {{ row.type_label }}</template></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" :class="row.sold_type === 'rented' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success-emphasis'">{{ row.sold_type === 'rented' ? 'Rented' : 'Sold' }}</span>
                                    <div v-if="row.rented_until" class="small portal-muted">until {{ formatDate(row.rented_until) }}</div>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="fw-bold">{{ money(row.sold_price, row.currency) }}</div>
                                    <div v-if="row.price && row.price !== row.sold_price" class="small portal-muted">listed {{ money(row.price, row.currency) }}</div>
                                    <div v-if="row.commission" class="small portal-muted">commission {{ money(row.commission, row.currency) }}</div>
                                </td>
                                <td class="small text-nowrap">{{ row.sold_at ? formatDate(row.sold_at) : '' }}</td>
                                <td class="small">
                                    <template v-if="row.lead">
                                        <RouterLink :to="{ name: 'leads.show', params: { id: row.lead.id } }" class="fw-semibold text-decoration-none">{{ row.lead.name }}</RouterLink>
                                        <div class="portal-muted">{{ row.lead.contact }}</div>
                                    </template>
                                    <span v-else class="portal-muted">—</span>
                                </td>
                                <td class="small">{{ row.agent ?? '—' }}</td>
                                <td v-if="page.is_admin" class="small">{{ row.owner }}</td>
                                <td class="text-end text-nowrap">
                                    <button v-if="row.has_ownership_document" type="button" class="btn btn-sm portal-btn-ghost" title="Ownership document (title deed)" @click="openDocument(row, 'ownership')"><i class="fas fa-file-shield"></i></button>
                                    <button v-if="row.has_contract_document" type="button" class="btn btn-sm portal-btn-ghost" :title="row.sold_type === 'rented' ? 'Ejari' : 'Sale contract (Form F / MOU)'" @click="openDocument(row, 'contract')"><i class="fas fa-file-contract"></i></button>
                                    <CrmPopover v-if="row.documents_password" button-class="btn btn-sm portal-btn-ghost" title="Document password" :content="row.documents_password"><i class="fas fa-key"></i></CrmPopover>
                                    <CrmPopover v-if="row.notes" button-class="btn btn-sm portal-btn-ghost" title="Notes" :content="row.notes"><i class="fas fa-note-sticky"></i></CrmPopover>
                                    <button type="button" class="btn btn-sm portal-btn-ghost" title="Put back on the market" :disabled="reverting === row.id" @click="revert(row)"><i class="fas fa-rotate-left me-1"></i>Revert</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="portal-muted small">Showing {{ page.meta.from }}–{{ page.meta.to }} of {{ page.meta.total }}</span>
                    <div><CrmPagination :meta="page.meta" :on-each-side="1" @change="goToPage" /></div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
/**
 * Sold Listings (resources/views/portal/sold/index) — listings marked sold or rented, their proof
 * documents (private, opened through the API), and reverting one. Filters live in the URL (?q=&type=&page=).
 */
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import CrmPopover from '../../components/CrmPopover.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { formatDate, number } from '../../utils/format';

const route = useRoute();
const router = useRouter();
const { confirm } = useConfirm();
const { error: toastError } = useToast();

const page = ref(null);
const loadError = ref('');
const searchInput = ref('');
const typeInput = ref('');
const reverting = ref(null);

const money = (value, currency = 'AED') => `${currency} ${Number(value || 0).toLocaleString('en', { maximumFractionDigits: 0 })}`;
const stats = computed(() => {
    const t = page.value.totals;
    return [
        { label: 'Sold', value: number(t.sold.total), icon: 'fa-key', tone: 'indigo' },
        { label: 'Sales Value', value: money(t.sold.value), icon: 'fa-sack-dollar', tone: 'teal' },
        { label: 'Rented', value: number(t.rented.total), icon: 'fa-file-signature', tone: 'amber' },
        { label: 'Rental Value', value: money(t.rented.value), icon: 'fa-coins', tone: 'rose' },
    ];
});

function load() {
    loadError.value = '';
    return http.get('/sold-listings', { params: { q: route.query.q, type: route.query.type, page: route.query.page } })
        .then((res) => {
            page.value = res.data;
            searchInput.value = res.data.search;
            typeInput.value = res.data.type ?? '';
        })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function applyFilters() {
    router.push({ query: { q: searchInput.value.trim() || undefined, type: typeInput.value || undefined } });
}

function goToPage(p) {
    router.push({ query: { ...route.query, page: p } });
}

/** Proof documents are private — fetched with this session / token, then shown in a new tab. */
function openDocument(row, kind) {
    const tab = window.open('', '_blank');
    http.get(`/sold-listings/${row.id}/documents/${kind}`, { responseType: 'blob' })
        .then((res) => {
            const url = URL.createObjectURL(res.data);
            if (tab && !tab.closed) tab.location.href = url;
            else window.open(url, '_blank');
            setTimeout(() => URL.revokeObjectURL(url), 60000);
        })
        .catch(() => {
            tab?.close();
            toastError('Could not open the document. Please try again.');
        });
}

async function revert(row) {
    if (!(await confirm({
        title: 'Put back on the market?',
        message: 'It will show on the website again (if it was active), and the sale is removed from Sold Listings. The lead keeps its history.',
        confirmText: 'Revert',
        tone: 'warning',
    }))) return;
    reverting.value = row.id;
    http.post(`/sold-listings/${row.id}/revert`)
        .then(() => load())
        .catch(() => toastError('Could not revert. Please try again.'))
        .finally(() => { reverting.value = null; });
}

watch(() => route.query, () => {
    if (route.name === 'sold-listings.index') load();
}, { immediate: true });
</script>
