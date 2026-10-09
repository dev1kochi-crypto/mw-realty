import { computed, reactive, ref } from 'vue';
import http from '../api/http';

/** Filters kept in the address bar (so a link like ?status=active or a refresh keeps them). */
export const LEAD_FILTER_KEYS = ['search', 'owner_id', 'agent_id', 'stage_id', 'source_id', 'tag_id', 'status', 'date_from', 'date_to', 'quick'];

/**
 * The Leads screen's state — GET /api/crm/leads (+ /leads/meta for the table set-up).
 * Selection is either ticked `ids`, or "all matching" (`selectAll`, minus `excludeIds`) — the
 * same shape every bulk action / export takes.
 */
export function useLeads() {
    const meta = ref(null);
    const leads = ref([]);
    const page = ref(null); // { current_page, last_page, per_page, total, from, to }
    const unfilteredTotal = ref(0);
    const quickCounts = ref({});
    const loading = ref(false);

    const filters = reactive(Object.fromEntries(LEAD_FILTER_KEYS.map((key) => [key, ''])));
    const listing = reactive({ sort: 'received', dir: 'desc', per_page: 25, page: 1, q: '' });

    const selection = reactive({ ids: new Set(), selectAll: false, excludeIds: new Set() });
    let requestId = 0;

    const activeFilterCount = computed(() => LEAD_FILTER_KEYS.filter((key) => key !== 'status' && filters[key] !== '' && filters[key] !== null).length);

    const selectedCount = computed(() => (selection.selectAll
        ? Math.max(0, (page.value?.total ?? 0) - selection.excludeIds.size)
        : selection.ids.size));

    function params() {
        const all = { ...filters, ...listing };
        return Object.fromEntries(Object.entries(all).filter(([, value]) => value !== '' && value !== null && value !== undefined));
    }

    /** Body for the selection-based endpoints (bulk stage / tags / delete, export). */
    function selectionPayload() {
        return selection.selectAll
            ? { ...params(), select_all: true, exclude_ids: [...selection.excludeIds] }
            : { ids: [...selection.ids] };
    }

    function loadMeta(query = {}) {
        return http.get('/leads/meta', { params: query }).then((res) => {
            meta.value = res.data;
        });
    }

    function fetchLeads() {
        const id = ++requestId;
        loading.value = true;
        return http.get('/leads', { params: params() })
            .then((res) => {
                if (id !== requestId) return;
                leads.value = res.data.data;
                page.value = res.data.meta;
                unfilteredTotal.value = res.data.unfiltered_total;
                quickCounts.value = res.data.quick_counts;
            })
            .finally(() => {
                if (id === requestId) loading.value = false;
            });
    }

    function clearSelection() {
        selection.ids = new Set();
        selection.excludeIds = new Set();
        selection.selectAll = false;
    }

    function isSelected(lead) {
        return selection.selectAll ? !selection.excludeIds.has(lead.id) : selection.ids.has(lead.id);
    }

    function toggleLead(lead) {
        const set = selection.selectAll ? selection.excludeIds : selection.ids;
        const next = new Set(set);
        next.has(lead.id) ? next.delete(lead.id) : next.add(lead.id);
        if (selection.selectAll) selection.excludeIds = next;
        else selection.ids = next;
    }

    /** Header checkbox: the rows on this page. */
    function togglePage(checked) {
        if (selection.selectAll) {
            const next = new Set(selection.excludeIds);
            leads.value.forEach((lead) => (checked ? next.delete(lead.id) : next.add(lead.id)));
            selection.excludeIds = next;
            return;
        }
        const next = new Set(selection.ids);
        leads.value.forEach((lead) => (checked ? next.add(lead.id) : next.delete(lead.id)));
        selection.ids = next;
    }

    function selectAllMatching() {
        selection.selectAll = true;
        selection.excludeIds = new Set();
        selection.ids = new Set();
    }

    return {
        meta, leads, page, unfilteredTotal, quickCounts, loading, filters, listing, selection,
        activeFilterCount, selectedCount, params, selectionPayload, loadMeta, fetchLeads,
        clearSelection, isSelected, toggleLead, togglePage, selectAllMatching,
    };
}
