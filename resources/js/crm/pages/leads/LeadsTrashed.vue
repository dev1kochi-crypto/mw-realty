<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="portal-section-title mb-0">Deleted Leads</div>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Restore a lead to bring it back, or permanently delete it.</p>
            </div>
            <RouterLink :to="{ name: 'leads.index' }" class="portal-btn-ghost btn btn-sm">Back to Leads</RouterLink>
        </div>
        <div class="portal-card p-3 p-md-4">
            <!-- Bulk bar — shown while leads are ticked (or "select all" is on). -->
            <div v-if="selectedCount" class="d-flex align-items-center flex-wrap gap-2 mb-3 p-2 rounded" style="background: rgba(0,0,0,.04);">
                <span class="fw-semibold ms-1">{{ number(selectedCount) }} selected</span>
                <button v-if="!selectAll && meta && meta.total > leads.length && pageAllTicked" type="button" class="btn btn-link btn-sm p-0" @click="selectAll = true; excluded = new Set()">Select all {{ number(meta.total) }}</button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-sm portal-btn-ghost" @click="clear">Clear</button>
                    <button type="button" class="btn btn-sm portal-btn-ghost" @click="bulk('restore')"><i class="fas fa-rotate-left me-1"></i>Restore</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" @click="bulk('force')"><i class="fas fa-trash me-1"></i>Delete Permanently</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table portal-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 2.5rem;"><input v-if="leads.length" type="checkbox" class="form-check-input" :checked="pageAllTicked" aria-label="Select all deleted leads on this page" @change="togglePage($event.target.checked)"></th>
                            <th>Lead</th>
                            <th>Property</th>
                            <th v-if="isAdmin">Owner</th>
                            <th>Stage</th>
                            <th>Deleted</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody :class="{ 'opacity-50': loading }">
                        <tr v-for="lead in leads" :key="lead.id">
                            <td><input type="checkbox" class="form-check-input" :checked="isTicked(lead)" :aria-label="`Select ${lead.name || 'lead'}`" @change="toggle(lead)"></td>
                            <td>
                                <div class="fw-semibold">{{ lead.name || 'Unknown' }}</div>
                                <div class="text-muted" style="font-size: 0.78rem;">{{ lead.email || lead.formatted_phone || '-' }}</div>
                            </td>
                            <td>{{ lead.property?.title ?? '-' }}</td>
                            <td v-if="isAdmin">{{ lead.owner?.name ?? '-' }}</td>
                            <td>{{ lead.stage?.name ?? '-' }}</td>
                            <td class="text-muted">{{ formatDateTime(lead.deleted_at) }}</td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm portal-btn-ghost" @click="restore(lead)">Restore</button>
                                <button type="button" class="btn btn-sm btn-outline-danger ms-1" @click="forceDelete(lead)">Delete Permanently</button>
                            </td>
                        </tr>
                        <tr v-if="!loading && !leads.length"><td :colspan="isAdmin ? 7 : 6" class="portal-empty">No deleted leads.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <nav v-if="meta && meta.last_page > 1" class="mt-3" aria-label="Pages">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item" :class="{ disabled: meta.current_page <= 1 }"><button type="button" class="page-link" @click="load(meta.current_page - 1)">Previous</button></li>
                <li class="page-item disabled"><span class="page-link">{{ meta.current_page }} / {{ meta.last_page }}</span></li>
                <li class="page-item" :class="{ disabled: meta.current_page >= meta.last_page }"><button type="button" class="page-link" @click="load(meta.current_page + 1)">Next</button></li>
            </ul>
        </nav>
    </div>
</template>

<script setup>
/** Deleted Leads (resources/views/portal/crm/leads/trashed) — GET /leads/trashed, restore / delete permanently. */
import { computed, onMounted, ref } from 'vue';
import http, { errorMessage } from '../../api/http';
import { useToast } from '../../composables/useToast';
import { useConfirm } from '../../composables/useConfirm';
import { formatDateTime, number } from '../../utils/format';

const { success, error: toastError } = useToast();
const { confirm } = useConfirm();

const leads = ref([]);
const meta = ref(null);
const isAdmin = ref(false);
const loading = ref(false);
const ticked = ref(new Set());
const selectAll = ref(false);
const excluded = ref(new Set());

const isTicked = (lead) => (selectAll.value ? !excluded.value.has(lead.id) : ticked.value.has(lead.id));
const pageAllTicked = computed(() => leads.value.length > 0 && leads.value.every(isTicked));
const selectedCount = computed(() => (selectAll.value ? (meta.value?.total ?? 0) - excluded.value.size : ticked.value.size));

function load(p = 1) {
    loading.value = true;
    return http.get('/leads/trashed', { params: { page: p } })
        .then((res) => {
            leads.value = res.data.data;
            meta.value = res.data.meta;
            isAdmin.value = res.data.is_admin;
        })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => {
            loading.value = false;
        });
}

function clear() {
    ticked.value = new Set();
    excluded.value = new Set();
    selectAll.value = false;
}

function toggle(lead) {
    const set = new Set(selectAll.value ? excluded.value : ticked.value);
    set.has(lead.id) ? set.delete(lead.id) : set.add(lead.id);
    if (selectAll.value) excluded.value = set;
    else ticked.value = set;
}

function togglePage(checked) {
    const set = new Set(selectAll.value ? excluded.value : ticked.value);
    leads.value.forEach((lead) => ((checked !== selectAll.value) ? set.add(lead.id) : set.delete(lead.id)));
    if (selectAll.value) excluded.value = set;
    else ticked.value = set;
}

function after(res) {
    success(res.data.message);
    clear();
    load(meta.value?.current_page ?? 1);
}

function restore(lead) {
    http.post(`/leads/trashed/${lead.id}/restore`).then(after).catch((e) => toastError(errorMessage(e)));
}

async function forceDelete(lead) {
    if (!(await confirm({ title: 'Delete permanently?', message: `Permanently delete ${lead.name || 'this lead'}? This cannot be undone.`, confirmText: 'Delete permanently', tone: 'danger' }))) return;
    http.delete(`/leads/trashed/${lead.id}`).then(after).catch((e) => toastError(errorMessage(e)));
}

async function bulk(action) {
    const count = selectedCount.value;
    if (action === 'force' && !(await confirm({ title: 'Delete permanently?', message: `Permanently delete ${number(count)} ${count === 1 ? 'lead' : 'leads'}? This cannot be undone.`, confirmText: 'Delete permanently', tone: 'danger' }))) return;
    const body = selectAll.value ? { action, select_all: true, exclude_ids: [...excluded.value] } : { action, ids: [...ticked.value] };
    http.post('/leads/trashed/bulk', body).then(after).catch((e) => toastError(errorMessage(e)));
}

onMounted(() => load());
</script>
