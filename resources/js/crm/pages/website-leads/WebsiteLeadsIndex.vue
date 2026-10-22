<template>
    <div v-if="!page" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <div class="d-flex align-items-center gap-3">
                    <div class="portal-section-title mb-0">Website Leads</div>
                    <span v-if="selectedCount" class="portal-selected-badge">Selected Leads <span>{{ selectedCount }}</span></span>
                </div>
                <p class="text-muted mb-0" style="font-size: 0.88rem; max-width: 760px;">{{ tabHints[filters.status] }}</p>
            </div>
            <div class="portal-xbtn-bar">
                <button type="button" class="portal-xbtn portal-xbtn-primary" aria-label="Transfer selected leads" :title="selectedCount ? `Transfer ${selectedCount} lead${selectedCount === 1 ? '' : 's'}` : 'Select leads first'"
                        :disabled="!selectedCount" @click="openTransfer"><i class="fas fa-share"></i><span>Transfer</span></button>
            </div>
        </div>

        <nav class="wl-tabs mb-3" aria-label="Website lead status">
            <RouterLink v-for="(label, key) in page.statuses" :key="key" :to="{ query: { status: key } }" class="wl-tab" :class="{ 'is-active': filters.status === key }" :aria-current="filters.status === key ? 'page' : null">
                <i class="fas" :class="tabIcons[key]"></i>{{ label }}<span class="wl-tab-count">{{ number(page.counts[key]) }}</span>
            </RouterLink>
        </nav>

        <div class="portal-card portal-filter-bar mb-3">
            <form class="portal-filter-bar__form" @submit.prevent="apply">
                <div class="portal-filter-field portal-filter-field--search" :class="{ 'is-set': filters.q !== '' }">
                    <label class="visually-hidden" for="wlFilterSearch">Search</label>
                    <div class="portal-filter-control">
                        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                        <input id="wlFilterSearch" v-model="searchInput" type="search" class="form-control" placeholder="Name, email or phone" autocomplete="off" maxlength="100">
                    </div>
                </div>
                <div class="portal-filter-field" :class="{ 'is-set': filters.source }">
                    <label class="visually-hidden" for="wlFilterSource">Came in via</label>
                    <div class="portal-filter-control">
                        <i class="fas fa-arrow-right-to-bracket" aria-hidden="true"></i>
                        <select id="wlFilterSource" v-model="sourceInput" class="form-select" @change="apply">
                            <option value="">All sources</option>
                            <option v-for="(label, key) in page.sources" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                </div>
                <div class="portal-filter-actions">
                    <button type="submit" class="portal-filter-submit" title="Apply filters" aria-label="Apply filters"><i class="fas fa-filter" aria-hidden="true"></i></button>
                    <RouterLink v-if="activeFilterCount" :to="{ query: { status: filters.status } }" class="portal-filter-bar__clear" title="Clear all filters">
                        <i class="fas fa-xmark" aria-hidden="true"></i> Clear <span class="portal-filter-bar__badge">{{ activeFilterCount }}</span>
                    </RouterLink>
                </div>
            </form>
        </div>

        <div class="portal-card p-3 p-md-4">
            <!-- "Select all matching" — offered once the whole page is ticked and there's more beyond it. -->
            <div v-if="allMode || (pageChecked && page.meta.total > leads.length)" class="wl-select-all mb-3">
                <span>{{ allMode
                    ? `All ${selectedCount} matching leads are selected${page.meta.total > page.bulk_limit ? ` (up to ${page.bulk_limit} per transfer).` : '.'}`
                    : `All ${leads.length} leads on this page are selected.` }}</span>
                <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline" @click="toggleAllMode">{{ allMode ? 'Clear selection' : `Select all ${page.meta.total} matching leads` }}</button>
            </div>

            <div class="table-responsive">
                <table class="table portal-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 2.5rem;"><input type="checkbox" class="form-check-input" aria-label="Select all on this page" :disabled="!leads.length" :checked="pageChecked" :indeterminate="pageIndeterminate" @change="togglePage($event.target.checked)"></th>
                            <th>Lead</th>
                            <th>Phone</th>
                            <th>Came in via</th>
                            <th>Insights</th>
                            <th>Routed to</th>
                            <th>Last active</th>
                        </tr>
                    </thead>
                    <tbody :class="{ 'opacity-50': loading }">
                        <tr v-for="lead in leads" :key="lead.id" class="portal-lead-row wl-row" tabindex="0" :aria-label="`View ${lead.name}`" @click="openRow($event, lead)" @keydown.enter="openRow($event, lead)">
                            <td data-lead-selection><input type="checkbox" class="form-check-input" :checked="isTicked(lead)" :aria-label="`Select ${lead.name}`" @change="toggle(lead, $event.target.checked)"></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="portal-lead-avatar">{{ lead.name.charAt(0).toUpperCase() }}</span>
                                    <div class="min-w-0">
                                        <div class="d-flex align-items-center gap-1 flex-nowrap" style="max-width: 220px;">
                                            <RouterLink :to="leadRoute(lead)" class="portal-lead-name-button text-decoration-none text-truncate" :title="lead.name">{{ lead.name }}</RouterLink>
                                            <span v-if="lead.has_account" class="wl-badge flex-shrink-0" title="Has a customer account">Customer</span>
                                        </div>
                                        <div class="text-muted text-truncate" style="font-size: 0.78rem; max-width: 220px;">{{ lead.email || '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ lead.formatted_phone || '-' }}</td>
                            <td>{{ lead.source_label }}</td>
                            <td>
                                <RouterLink :to="{ ...leadRoute(lead), hash: '#insights' }" class="portal-lead-insights" title="Property views · time on site · AI chats">
                                    <span><i class="fas fa-house" aria-hidden="true"></i>{{ lead.property_views }}</span>
                                    <span><i class="far fa-clock" aria-hidden="true"></i>{{ duration(lead.site_seconds) }}</span>
                                    <span v-if="lead.chats"><i class="fas fa-robot" aria-hidden="true"></i>{{ lead.chats }}</span>
                                </RouterLink>
                            </td>
                            <td>
                                <span v-for="(owner, i) in lead.routed_to" :key="i" class="wl-pill">{{ owner }}</span>
                                <span v-if="!lead.routed_to.length" class="wl-pill is-pool">In pool</span>
                            </td>
                            <td class="text-muted text-nowrap">{{ lead.last_seen_at ? timeAgo(lead.last_seen_at) : '—' }}</td>
                        </tr>
                        <tr v-if="!leads.length">
                            <td colspan="7" class="portal-empty text-center text-muted py-5">
                                <i class="fas fa-user-clock fa-2x mb-2 d-block opacity-50"></i>
                                {{ activeFilterCount ? 'No website leads match these filters.' : 'No website leads here yet.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="page.meta.last_page > 1" class="mt-3"><CrmPagination :meta="page.meta" @change="goToPage" /></div>
        </div>

        <TransferModal :selection="transferSelection" :filters="filters" @close="transferSelection = null" @done="afterTransfer" />
    </div>
</template>

<script setup>
/**
 * Website Leads (Super Admin) — resources/views/portal/crm/website-leads/index: same layout as All
 * Leads (toolbar, filter bar, selectable table). Tick leads (or "select all matching") and Transfer
 * them to an agency / agent. Filters live in the URL (?status=&source=&q=&page=).
 */
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import TransferModal from './TransferModal.vue';
import { useToast } from '../../composables/useToast';
import { useSession } from '../../composables/useSession';
import { duration, number, timeAgo } from '../../utils/format';

const route = useRoute();
const router = useRouter();
const { success } = useToast();
const { refreshNavigation } = useSession();

const tabIcons = { pool: 'fa-inbox', routed: 'fa-share', all: 'fa-users' };
const tabHints = {
    pool: 'Visitors who shared their details (AI chat, contact forms, customer accounts) but haven\'t opened a listing that belongs to an agency or agent yet — transfer them to one.',
    routed: 'Handed to an agency / agent — automatically when they viewed one of its listings, or transferred by you.',
    all: 'Everyone who identified themselves on the website, with their tracked activity.',
};

const page = ref(null);
const loading = ref(false);
const loadError = ref('');
const filters = reactive({ status: 'pool', source: '', q: '' });
const searchInput = ref('');
const sourceInput = ref('');

// Selection: ticked rows on this page, or "all matching" (every page, minus unticked ones).
const selected = ref(new Set());
const excluded = ref(new Set());
const allMode = ref(false);
const transferSelection = ref(null);

const leads = computed(() => page.value?.data ?? []);
const activeFilterCount = computed(() => [filters.q !== '', !!filters.source].filter(Boolean).length);
const isTicked = (lead) => (allMode.value ? !excluded.value.has(lead.id) : selected.value.has(lead.id));
const tickedOnPage = computed(() => leads.value.filter(isTicked).length);
const pageChecked = computed(() => tickedOnPage.value > 0 && tickedOnPage.value === leads.value.length);
const pageIndeterminate = computed(() => tickedOnPage.value > 0 && tickedOnPage.value < leads.value.length);
const selectedCount = computed(() => (allMode.value
    ? Math.min((page.value?.meta.total ?? 0) - excluded.value.size, page.value?.bulk_limit ?? 0)
    : selected.value.size));

const leadRoute = (lead) => ({ name: 'website-leads.show', params: { id: lead.id } });

function load() {
    loading.value = true;
    loadError.value = '';
    const query = route.query;
    return http.get('/website-leads', { params: { status: query.status, source: query.source, q: query.q, page: query.page } })
        .then((res) => {
            page.value = res.data;
            Object.assign(filters, { status: res.data.filters.status, source: res.data.filters.source ?? '', q: res.data.filters.search });
            searchInput.value = filters.q;
            sourceInput.value = filters.source;
        })
        .catch((e) => {
            loadError.value = errorMessage(e);
        })
        .finally(() => {
            loading.value = false;
        });
}

function clearSelection() {
    selected.value = new Set();
    excluded.value = new Set();
    allMode.value = false;
}

function toggle(lead, checked) {
    const set = new Set(allMode.value ? excluded.value : selected.value);
    (allMode.value ? !checked : checked) ? set.add(lead.id) : set.delete(lead.id);
    if (allMode.value) excluded.value = set;
    else selected.value = set;
}

function togglePage(checked) {
    leads.value.forEach((lead) => toggle(lead, checked));
}

function toggleAllMode() {
    const on = !allMode.value;
    clearSelection();
    allMode.value = on;
}

function apply() {
    router.push({ query: { status: filters.status, source: sourceInput.value || undefined, q: searchInput.value.trim() || undefined } });
}

function goToPage(p) {
    router.push({ query: { ...route.query, page: p } });
}

// Whole row opens the lead (like All Leads) — except the checkbox and links.
function openRow(event, lead) {
    if (event.target.closest('a, button, input, label, [data-lead-selection]')) return;
    router.push(leadRoute(lead));
}

function openTransfer() {
    transferSelection.value = allMode.value
        ? { all: true, exclude: [...excluded.value], count: selectedCount.value }
        : { ids: [...selected.value], count: selected.value.size };
}

function afterTransfer(message) {
    transferSelection.value = null;
    success(message);
    clearSelection();
    load();
    refreshNavigation();
}

// Filters / page changed in the URL (tabs, filter bar, pagination, back button) → reload with a
// fresh selection, like a new page load did.
watch(() => route.query, () => {
    if (route.name !== 'website-leads.index') return;
    clearSelection();
    load();
}, { immediate: true });
</script>
