<template>
    <div v-if="!meta" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="init">Try again</button></div>
    </div>
    <div v-else>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <div class="d-flex align-items-center gap-3">
                    <div class="portal-section-title mb-0">{{ meta.is_admin ? 'All Leads' : 'My Leads' }}</div>
                    <span v-if="selectedCount" class="portal-selected-badge">Selected Leads <span>{{ number(selectedCount) }}</span></span>
                </div>
                <p class="text-muted mb-0" style="font-size: 0.88rem;">
                    Every enquiry a visitor sends about {{ meta.is_admin ? 'any' : 'your' }} listing lands here.
                    <RouterLink :to="{ name: 'leads.trashed' }" class="portal-link-muted ms-1"><i class="fas fa-trash-can-arrow-up me-1"></i>View deleted leads</RouterLink>
                </p>
            </div>
            <!-- Icon toolbar: each button shows its label on hover / focus (.portal-xbtn). Selection buttons wait for ticked leads. -->
            <div class="portal-xbtn-bar">
                <button type="button" class="portal-xbtn" aria-label="Assign stage" :title="selectedCount ? '' : 'Select leads first'" :disabled="!selectedCount" @click="openPicker('stages')"><i class="fas fa-layer-group"></i><span>Assign Stage</span></button>
                <button type="button" class="portal-xbtn" aria-label="Assign tag" :title="selectedCount ? '' : 'Select leads first'" :disabled="!selectedCount" @click="openPicker('tags')"><i class="fas fa-tags"></i><span>Assign Tag</span></button>
                <button type="button" class="portal-xbtn" aria-label="Export" :title="selectedCount ? '' : 'Select leads first'" :disabled="!selectedCount" @click="modal = 'export'"><i class="fas fa-file-arrow-down"></i><span>Export</span></button>
                <button type="button" class="portal-xbtn portal-xbtn-danger" aria-label="Delete leads" :title="selectedCount ? '' : 'Select leads first'" :disabled="!selectedCount" @click="bulkDelete"><i class="fas fa-trash-can"></i><span>Delete Leads</span></button>
                <span class="portal-xbtn-sep" aria-hidden="true"></span>
                <button type="button" class="portal-xbtn" aria-label="Table fields" @click="modal = 'fields'"><i class="fas fa-table-columns"></i><span>Table Fields</span></button>
                <button type="button" class="portal-xbtn" aria-label="Import" @click="modal = 'import'"><i class="fas fa-cloud-arrow-up"></i><span>Import</span></button>
                <button v-if="meta.agency" type="button" class="portal-xbtn" :aria-label="`Lead assignment: ${meta.agency.assignment.mode}`" @click="modal = 'assignment'">
                    <i class="fas" :class="meta.agency.assignment.mode === 'manual' ? 'fa-hand-pointer' : 'fa-rotate'"></i>
                    <span>Lead Assignment<em class="lassign-xbtn-state">{{ meta.agency.assignment.mode === 'manual' ? 'Manual' : 'Auto' }}</em></span>
                </button>
                <button v-if="meta.agency && meta.agency.unassigned_count > 0 && meta.agency.agents.length" type="button" class="portal-xbtn" @click="distribute">
                    <i class="fas fa-shuffle"></i><span>Distribute {{ meta.agency.unassigned_count }} unassigned</span>
                </button>
                <button type="button" class="portal-xbtn portal-xbtn-primary" aria-label="Add lead" @click="openForm(null)"><i class="fas fa-plus"></i><span>Add Lead</span></button>
            </div>
        </div>

        <!-- A background lead import (running, or opened from its "finished" notification). -->
        <div v-if="importTracker.current.value" class="portal-card p-3 mb-3 position-relative">
            <button v-if="!importWorking" type="button" class="btn-close position-absolute top-0 end-0 m-2" aria-label="Dismiss" @click="importTracker.dismiss()"></button>
            <ImportProgress :progress="importTracker.current.value" @refresh="refresh" />
        </div>

        <div id="leadsListingWrapper" :class="{ 'leads-loading': loading }">
            <div class="portal-card portal-filter-bar mb-3">
                <form class="portal-filter-bar__form" @submit.prevent="refetch">
                    <div class="portal-filter-field portal-filter-field--search" :class="{ 'is-set': filters.search }">
                        <div class="portal-filter-control">
                            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                            <input v-model="searchInput" type="search" class="form-control" placeholder="Name, email or phone" autocomplete="off" aria-label="Search">
                        </div>
                    </div>
                    <div v-if="meta.is_admin" class="portal-filter-field" :class="{ 'is-set': filters.owner_id }">
                        <div class="portal-filter-control">
                            <i class="fas fa-building-user" aria-hidden="true"></i>
                            <RemoteSelect :key="`owner-${resetKey}`" v-model="filters.owner_id" endpoint="/leads/owner-options" placeholder="All owners" fixed @change="refetch" />
                        </div>
                    </div>
                    <div v-if="meta.agency" class="portal-filter-field" :class="{ 'is-set': filters.agent_id }">
                        <div class="portal-filter-control">
                            <i class="fas fa-user-tie" aria-hidden="true"></i>
                            <select v-model="filters.agent_id" class="form-select" aria-label="Agent" @change="refetch">
                                <option value="">All agents</option>
                                <option value="unassigned">Unassigned</option>
                                <option v-for="agent in meta.agency.agents" :key="agent.id" :value="String(agent.id)">{{ agent.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div v-for="f in masterFilters" :key="f.key" class="portal-filter-field" :class="{ 'is-set': filters[f.key] }">
                        <div class="portal-filter-control">
                            <i class="fas" :class="f.icon" aria-hidden="true"></i>
                            <RemoteSelect :key="`${f.key}-${resetKey}`" v-model="filters[f.key]" :endpoint="f.endpoint" :placeholder="f.placeholder" fixed @change="refetch" />
                        </div>
                    </div>
                    <div class="portal-filter-field portal-filter-field--range" :class="{ 'is-set': filters.date_from || filters.date_to }">
                        <div class="portal-filter-control portal-filter-control--range" title="Received between">
                            <i class="far fa-calendar" aria-hidden="true"></i>
                            <input v-model="filters.date_from" type="date" class="form-control" aria-label="Received from" @change="refetch">
                            <span class="portal-filter-range-sep">to</span>
                            <input v-model="filters.date_to" type="date" class="form-control" aria-label="Received to" @change="refetch">
                        </div>
                    </div>
                    <div class="portal-filter-actions">
                        <button type="submit" class="portal-filter-submit" title="Apply filters" aria-label="Apply filters"><i class="fas fa-filter" aria-hidden="true"></i></button>
                        <button v-if="activeFilterCount" type="button" class="portal-filter-bar__clear" title="Clear all filters" @click="clearFilters">
                            <i class="fas fa-xmark" aria-hidden="true"></i> Clear <span class="portal-filter-bar__badge">{{ activeFilterCount }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="portal-card p-3 portal-leads-table-card">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <div class="portal-lead-quick" role="group" aria-label="Quick filters">
                        <button v-for="chip in quickChips" :key="chip.key" type="button" class="portal-lead-quick__chip" :class="[`tone-${chip.tone}`, { 'is-active': filters.quick === chip.key }]"
                                :aria-pressed="filters.quick === chip.key" :title="chip.hint" @click="setQuick(chip.key)">
                            <i class="fas" :class="chip.icon" aria-hidden="true"></i>{{ chip.label }}<strong>{{ number(quickCounts[chip.count] ?? 0) }}</strong>
                        </button>
                    </div>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <select v-model.number="listing.per_page" class="form-select form-select-sm w-auto" aria-label="Leads per page" @change="refetch">
                            <option v-for="size in [10, 25, 50, 100]" :key="size" :value="size">Show {{ size }}</option>
                        </select>
                        <input v-model="quickSearch" type="search" class="form-control form-control-sm" style="max-width: 200px;" placeholder="Quick search" aria-label="Quick search">
                    </div>
                </div>


                <div class="portal-leads-loader" role="status" aria-live="polite"><span class="portal-leads-loader__box"><span class="portal-leads-loader__spin" aria-hidden="true"></span>Loading leads…</span></div>
                <div ref="scroller" class="table-responsive portal-leads-dt-scroll" :class="{ 'is-scrolled-x': scrolledX }" @scroll="scrolledX = $event.target.scrollLeft > 0">
                    <table id="leadsDataTable" ref="leadsTable" class="table portal-table mb-0">
                        <thead>
                            <tr>
                                <!-- Header checkbox = every matching lead on every page (the server resolves them from the filters); a dash when only some are. -->
                                <th style="width: 2.5rem;"><input type="checkbox" class="form-check-input" :checked="allSelected" :indeterminate="someSelected" aria-label="Select all leads" @change="$event.target.checked ? selectAllMatching() : clearSelection()"></th>
                                <th class="portal-lead-sn">#</th>
                                <th v-for="(column, index) in shownColumns" :key="column" :data-column-key="column" :class="{ 'portal-th-draggable': column !== 'lead' }"
                                    :draggable="column !== 'lead'" :role="isSortable(column) ? 'button' : null"
                                    @click="isSortable(column) && sortBy(column)" @dragstart="headerDrag = index" @dragover.prevent="headerDragOver(index)" @dragend="headerDragEnd">
                                    <span v-if="column !== 'lead'" class="portal-th-grip" title="Drag to move this column" aria-hidden="true"><i class="fas fa-grip-vertical"></i></span>{{ columnMeta[column]?.label ?? column }}
                                    <i v-if="isSortable(column)" class="fas ms-1" :class="sortIcon(column)" aria-hidden="true"></i>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(lead, index) in leads" :key="lead.id" class="portal-lead-row" tabindex="0" @click="openRow($event, lead)" @keydown.enter="openRow($event, lead)">
                                <td><input type="checkbox" class="form-check-input" :checked="isSelected(lead)" :aria-label="`Select ${lead.name || 'lead'}`" @change="toggleLead(lead)"></td>
                                <td class="portal-lead-sn">{{ (page?.from ?? 1) + index }}</td>
                                <LeadCell v-for="column in shownColumns" :key="column" :lead="lead" :column="column" @stage="changeStage" @tags="(l) => { tagsLead = l; modal = 'tags'; }" />
                            </tr>
                            <tr v-if="!loading && !leads.length">
                                <td :colspan="shownColumns.length + 2" class="portal-empty" data-empty-cell>
                                    <div class="portal-empty-icon"><i class="fas fa-address-book"></i></div>
                                    <div class="fw-semibold mb-1">{{ unfilteredTotal ? 'No matching leads' : (activeFilterCount ? 'No leads match these filters' : 'No leads yet') }}</div>
                                    <div style="font-size: 0.85rem;">They'll show up here the moment a visitor enquires about {{ meta.is_admin ? 'a listing' : 'one of your listings' }}.</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="page" class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 small">
                    <div class="text-muted">
                        {{ page.total ? `Showing ${number(page.from)} to ${number(page.to)} of ${number(page.total)} leads` : 'No leads' }}
                        <template v-if="unfilteredTotal !== page.total"> (filtered from {{ number(unfilteredTotal) }})</template>
                    </div>
                    <nav v-if="page.last_page > 1" aria-label="Leads pages">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item" :class="{ disabled: page.current_page <= 1 }"><button type="button" class="page-link" @click="goTo(page.current_page - 1)">Previous</button></li>
                            <li v-for="p in pageNumbers" :key="p" class="page-item" :class="{ active: p === page.current_page, disabled: p === '…' }">
                                <button type="button" class="page-link" @click="p !== '…' && goTo(p)">{{ p }}</button>
                            </li>
                            <li class="page-item" :class="{ disabled: page.current_page >= page.last_page }"><button type="button" class="page-link" @click="goTo(page.current_page + 1)">Next</button></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>

        <LeadFormModal :open="modal === 'form'" :lead-id="formLeadId" :is-admin="meta.is_admin" @close="modal = null" @saved="afterChange" />
        <LeadTagsModal :open="modal === 'tags'" :lead="tagsLead" @close="modal = null" @saved="(tags) => { tagsLead.tags = tags; modal = null; }" />
        <BulkPickerModal :open="modal === 'picker'" :mode="pickerMode" :count="selectedCount" :selection="selectionPayload" @close="modal = null" @applied="afterChange" />
        <TableFieldsModal :open="modal === 'fields'" :columns="meta.columns" @close="modal = null" @saved="(columns) => { meta.columns.shown = columns; modal = null; refetch(); }" />
        <ImportModal :open="modal === 'import'" @close="modal = null" @refresh="() => { modal = null; refresh(); }" />
        <ExportModal :open="modal === 'export'" :count="selectedCount" :all-matching="selection.selectAll" :selection="selectionPayload" @close="modal = null" />
        <AssignmentModal v-if="meta.agency" :open="modal === 'assignment'" :settings="meta.agency.assignment" @close="modal = null" @saved="(s) => { meta.agency.assignment = s; modal = null; }" />
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import RemoteSelect from '../../components/RemoteSelect.vue';
import LeadCell from './components/LeadCell.vue';
import LeadFormModal from './components/LeadFormModal.vue';
import LeadTagsModal from './components/LeadTagsModal.vue';
import BulkPickerModal from './components/BulkPickerModal.vue';
import TableFieldsModal from './components/TableFieldsModal.vue';
import ImportModal from './components/ImportModal.vue';
import ImportProgress from './components/ImportProgress.vue';
import ExportModal from './components/ExportModal.vue';
import AssignmentModal from './components/AssignmentModal.vue';
import { LEAD_FILTER_KEYS, useLeads } from '../../composables/useLeads';
import { isWorking, useLeadImport } from '../../composables/useLeadImport';
import { useToast } from '../../composables/useToast';
import { useConfirm } from '../../composables/useConfirm';
import { useSession } from '../../composables/useSession';
import { number } from '../../utils/format';

const route = useRoute();
const router = useRouter();
const { success, error: toastError } = useToast();
const { confirm } = useConfirm();
const { refreshNavigation } = useSession();
const importTracker = useLeadImport();
const {
    meta, leads, page, unfilteredTotal, quickCounts, loading, filters, listing, selection,
    activeFilterCount, selectedCount, selectionPayload, loadMeta, fetchLeads,
    clearSelection, isSelected, toggleLead, selectAllMatching,
} = useLeads();

const loadError = ref('');
const modal = ref(null);
const formLeadId = ref(null);
const tagsLead = ref(null);
const pickerMode = ref('stages');
const searchInput = ref('');
const quickSearch = ref('');
const resetKey = ref(0);
const scrolledX = ref(false);
const leadsTable = ref(null);
let pinObserver = null;
const headerDrag = ref(null);
let headerOrderBefore = null;
let searchTimer = null;
let quickTimer = null;

const masterFilters = [
    { key: 'stage_id', icon: 'fa-layer-group', endpoint: '/leads/options/stages', placeholder: 'All stages' },
    { key: 'source_id', icon: 'fa-share-nodes', endpoint: '/leads/options/sources', placeholder: 'All sources' },
    { key: 'tag_id', icon: 'fa-tag', endpoint: '/leads/options/tags', placeholder: 'All tags' },
];

// LeadService::QUICK_FILTERS + Total.
const quickChips = [
    { key: '', count: 'total', label: 'Total', icon: 'fa-users', tone: 'indigo', hint: 'All received leads' },
    { key: 'active_24h', count: 'active_24h', label: 'Active 24h', icon: 'fa-bolt', tone: 'teal', hint: 'Received in the last 24 hours, or on the website then' },
    { key: 'last_7d', count: 'last_7d', label: 'Last 7 days', icon: 'fa-calendar-week', tone: 'amber', hint: 'Received in the last 7 days' },
    { key: 'website', count: 'website', label: 'Website Insights', icon: 'fa-chart-line', tone: 'rose', hint: 'Came from a tracked website visitor' },
];

const columnMeta = computed(() => Object.fromEntries(meta.value.columns.available.map((c) => [c.key, c])));
const shownColumns = computed(() => meta.value.columns.shown);
const allSelected = computed(() => selectedCount.value > 0 && selectedCount.value === (page.value?.total ?? 0));
const someSelected = computed(() => selectedCount.value > 0 && !allSelected.value);
const importWorking = computed(() => isWorking(importTracker.current.value));

const isSortable = (column) => column !== 'tags' && columnMeta.value[column]?.sortable;
const sortIcon = (column) => (listing.sort !== column ? 'fa-sort text-muted opacity-50' : `fa-sort-${listing.dir === 'asc' ? 'up' : 'down'}`);

const pageNumbers = computed(() => {
    const { current_page: current, last_page: last } = page.value;
    const pages = new Set([1, last, current - 1, current, current + 1].filter((p) => p >= 1 && p <= last));
    const sorted = [...pages].sort((a, b) => a - b);
    return sorted.flatMap((p, i) => (i && p - sorted[i - 1] > 1 ? ['…', p] : [p]));
});

/** The address bar mirrors the filters (so dashboard links like ?status=active and refreshes keep them). */
function syncQuery() {
    const query = Object.fromEntries(LEAD_FILTER_KEYS.filter((key) => filters[key] !== '' && filters[key] !== null).map((key) => [key, String(filters[key])]));
    router.replace({ query });
}

function load() {
    return fetchLeads().catch((e) => toastError(errorMessage(e, 'Leads could not be loaded.')));
}

function refetch() {
    listing.page = 1;
    clearSelection();
    syncQuery();
    load();
}

function refresh() {
    clearSelection();
    load();
    loadMeta().catch(() => {});
}

function goTo(p) {
    listing.page = p;
    load();
    document.querySelector('.portal-leads-table-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function setQuick(key) {
    filters.quick = filters.quick === key ? '' : key;
    refetch();
}

function sortBy(column) {
    listing.dir = listing.sort === column && listing.dir === 'desc' ? 'asc' : 'desc';
    listing.sort = column;
    listing.page = 1;
    load();
}

function clearFilters() {
    LEAD_FILTER_KEYS.filter((key) => key !== 'status').forEach((key) => { filters[key] = ''; });
    searchInput.value = '';
    resetKey.value++;
    refetch();
}

function openForm(leadId) {
    formLeadId.value = leadId;
    modal.value = 'form';
}

function openPicker(mode) {
    pickerMode.value = mode;
    modal.value = 'picker';
}

/** Row click opens the lead (not when clicking its own buttons / links / inputs). */
function openRow(event, lead) {
    if (event.target.closest('a, button, input, select, label, .dropdown-menu')) return;
    router.push({ name: 'leads.show', params: { id: lead.id } });
}

function afterChange() {
    modal.value = null;
    clearSelection();
    load();
}

function changeStage(lead, stage) {
    const previous = lead.stage;
    lead.stage = stage ? { id: stage.id, name: stage.name, color: stage.color } : null;
    http.put(`/leads/${lead.id}/stage`, { stage_id: stage?.id ?? null })
        .then((res) => {
            lead.stage = res.data.stage;
            success(res.data.message);
        })
        .catch((e) => {
            lead.stage = previous;
            toastError(errorMessage(e, 'The stage could not be changed.'));
        });
}

async function bulkDelete() {
    const count = selectedCount.value;
    const ok = await confirm({
        title: 'Delete Leads',
        message: `Delete ${count === 1 ? 'this lead' : `these ${number(count)} leads`}? You can restore ${count === 1 ? 'it' : 'them'} later from Deleted Leads.`,
        confirmText: 'Delete',
        tone: 'danger',
    });
    if (!ok) return;
    http.post('/leads/bulk/delete', selectionPayload())
        .then((res) => {
            success(res.data.message);
            afterChange();
        })
        .catch((e) => toastError(errorMessage(e)));
}

async function distribute() {
    const count = meta.value.agency.unassigned_count;
    const ok = await confirm({ title: 'Distribute unassigned leads?', message: `Round-robin all ${count} unassigned lead(s) across your active agents?`, confirmText: 'Distribute', tone: 'primary' });
    if (!ok) return;
    http.post('/leads/distribute')
        .then((res) => {
            success(res.data.message);
            refresh();
        })
        .catch((e) => toastError(errorMessage(e)));
}

/** Drag a column header to move it — saved per user, like Table Fields. */
function headerDragOver(index) {
    const from = headerDrag.value;
    if (from === null || from === index || index === 0) return;
    headerOrderBefore ??= [...meta.value.columns.shown];
    const list = [...meta.value.columns.shown];
    list.splice(index, 0, list.splice(from, 1)[0]);
    meta.value.columns.shown = list;
    headerDrag.value = index;
}

function headerDragEnd() {
    headerDrag.value = null;
    if (!headerOrderBefore) return;
    const previous = headerOrderBefore;
    headerOrderBefore = null;
    http.put('/leads/table-columns', { columns: meta.value.columns.shown }).catch((e) => {
        meta.value.columns.shown = previous;
        toastError(errorMessage(e, 'The column order could not be saved.'));
    });
}

/**
 * Frozen checkbox / # / Lead columns: pin each at the real width of the ones before it, so the
 * pinned cells sit edge to edge and nothing scrolls through the gaps (styles/leads.css).
 */
function pinColumns() {
    const headers = leadsTable.value?.querySelectorAll('thead th');
    if (!headers || headers.length < 3) return;
    const first = headers[0].getBoundingClientRect().width;
    leadsTable.value.style.setProperty('--lead-pin-2', `${first}px`);
    leadsTable.value.style.setProperty('--lead-pin-3', `${first + headers[1].getBoundingClientRect().width}px`);
}

watch([leads, () => meta.value?.columns.shown], () => nextTick(pinColumns), { deep: true });
watch(leadsTable, (table) => {
    pinObserver?.disconnect();
    if (!table || !('ResizeObserver' in window)) return;
    pinObserver = new ResizeObserver(pinColumns);
    table.querySelectorAll('thead th').forEach((th, i) => i < 2 && pinObserver.observe(th));
    pinColumns();
});

watch(searchInput, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        if (filters.search === value.trim()) return;
        filters.search = value.trim();
        refetch();
    }, 350);
});

watch(quickSearch, (value) => {
    clearTimeout(quickTimer);
    quickTimer = setTimeout(() => {
        listing.q = value.trim();
        listing.page = 1;
        clearSelection();
        load();
    }, 350);
});

function init() {
    loadError.value = '';
    LEAD_FILTER_KEYS.forEach((key) => { filters[key] = route.query[key] ?? ''; });
    searchInput.value = filters.search;
    // ?import=open (dashboard shortcut) opens the Import popup; ?import={id} (notification) shows that import.
    const importParam = route.query.import;
    if (importParam === 'open') modal.value = 'import';

    Promise.all([loadMeta(importParam && importParam !== 'open' ? { import: importParam } : {}), load()])
        .then(() => {
            if (meta.value.import) importTracker.track(meta.value.import);
            refreshNavigation().catch(() => {});
        })
        .catch((e) => {
            loadError.value = errorMessage(e, 'Leads could not be loaded.');
        });
}

onMounted(init);
onBeforeUnmount(() => {
    pinObserver?.disconnect();
    clearTimeout(searchTimer);
    clearTimeout(quickTimer);
    importTracker.stop();
});
</script>
