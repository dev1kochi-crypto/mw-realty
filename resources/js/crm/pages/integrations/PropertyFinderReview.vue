<template>
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <RouterLink :to="{ name: 'integrations.property-finder' }" class="portal-link-muted small"><i class="fas fa-arrow-left me-1"></i>Property Finder</RouterLink>
                <div class="portal-section-title mb-0 mt-1">Property Finder — imported listings</div>
                <p class="text-muted mb-0" style="font-size: .85rem;">Approve to let a listing go live (its permit details still apply). Reject removes it — it won't be imported again.</p>
            </div>
        </div>

        <div v-if="errorAlert" class="alert alert-danger">{{ errorAlert }}</div>

        <div v-if="!data" class="text-center py-5">
            <span v-if="!loadError" class="spinner-border text-primary"></span>
            <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
        </div>
        <template v-else>
            <div class="pfr-tabs">
                <ul class="nav portal-lang-tabs mb-0">
                    <li v-for="(label, key) in tabs" :key="key" class="nav-item">
                        <RouterLink class="nav-link" :class="{ active: data.tab === key }" :to="{ query: { tab: key, q: data.search || undefined } }">
                            {{ label }}<span class="portal-tab-count">{{ data.counts[key] ?? 0 }}</span>
                        </RouterLink>
                    </li>
                </ul>
                <form class="pfr-search" @submit.prevent="applySearch">
                    <i class="fas fa-search"></i>
                    <input v-model="searchInput" type="search" class="form-control form-control-sm" placeholder="Search title, reference or account…" aria-label="Search imported listings">
                </form>
            </div>

            <form v-if="data.tab === 'pending' && data.data.length && ticked.size" class="pfr-bulk" @submit.prevent>
                <span class="small fw-semibold me-auto">{{ ticked.size }} selected</span>
                <input v-model="note" type="text" class="form-control form-control-sm" style="max-width: 280px;" placeholder="Reason (sent with a rejection)" maxlength="500" aria-label="Rejection reason">
                <button type="button" class="btn btn-sm btn-outline-danger" :disabled="saving" @click="act('reject')"><i class="fas fa-xmark me-1"></i>Reject</button>
                <button type="button" class="btn btn-sm btn-portal-primary" :disabled="saving" @click="act('approve')"><i class="fas fa-check me-1"></i>Approve</button>
            </form>

            <div class="portal-card p-0 mb-3">
                <div v-if="!data.data.length" class="text-center text-muted py-5"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>{{ data.search !== '' ? `No listings match “${data.search}”.` : 'Nothing here.' }}</div>
                <div v-else class="table-responsive">
                    <table class="table portal-table align-middle mb-0">
                        <thead><tr>
                            <th v-if="data.tab === 'pending'" style="width: 36px;"><input type="checkbox" class="form-check-input" aria-label="Select all" :checked="allTicked" :indeterminate="ticked.size > 0 && !allTicked" @change="tickAll($event.target.checked)"></th>
                            <th>Listing</th><th>Account</th><th>Price</th><th>Permit</th><th>Imported</th><th v-if="data.tab !== 'pending'">Reviewed</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="item in data.data" :key="item.id">
                                <td v-if="data.tab === 'pending'"><input type="checkbox" class="form-check-input" :checked="ticked.has(item.id)" :aria-label="`Select listing ${item.id}`" @change="tick(item.id, $event.target.checked)"></td>
                                <td>
                                    <div class="d-flex gap-3 align-items-center">
                                        <img v-if="item.property?.cover" :src="item.property.cover" alt="" class="pfr-thumb" loading="lazy">
                                        <span v-else class="pfr-thumb pfr-thumb--empty"><i class="fas fa-image"></i></span>
                                        <div class="min-w-0">
                                            <template v-if="item.property">
                                                <a :href="item.property.url" class="pfr-title" target="_blank" rel="noopener">{{ item.property.title }}</a>
                                                <div class="pfr-meta">{{ item.property.meta }}</div>
                                                <div class="pfr-meta">{{ item.property.address }}</div>
                                            </template>
                                            <span v-else class="text-muted">Removed</span>
                                            <div class="pfr-meta">PF {{ item.pf_reference }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ item.owner?.name ?? '—' }}</div>
                                    <div v-if="item.owner" class="pfr-meta">{{ item.owner.is_agency ? 'Agency' : 'Agent' }}</div>
                                </td>
                                <td class="text-nowrap">{{ item.property?.price ? `AED ${number(Math.round(item.property.price))}` : '—' }}</td>
                                <td>
                                    <span v-if="item.property?.permit_number" class="pfr-chip">{{ item.property.permit_number }}</span>
                                    <span v-else class="pfr-chip pfr-chip--warn">No permit</span>
                                </td>
                                <td class="text-nowrap small">{{ formatDate(item.created_at) }}</td>
                                <td v-if="data.tab !== 'pending'" class="small">
                                    {{ item.reviewed_at ? formatDate(item.reviewed_at) : '' }}
                                    <div v-if="item.review_note" class="pfr-meta">{{ item.review_note }}</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <CrmPagination :meta="data.meta" @change="goToPage" />
        </template>
    </div>
</template>

<script setup>
/**
 * Super Admin: imported Property Finder listings (resources/views/portal/crm/integrations/property-finder-review).
 * Approve → the listing joins the normal permit flow; Reject → the property is removed for good.
 * Tab / search / page live in the URL (?tab=&q=&page=).
 */
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import { useToast } from '../../composables/useToast';
import { useConfirm } from '../../composables/useConfirm';
import { formatDate, number } from '../../utils/format';

const route = useRoute();
const router = useRouter();
const { success } = useToast();
const { confirm } = useConfirm();

const tabs = { pending: 'Waiting for review', approved: 'Approved', rejected: 'Rejected' };

const data = ref(null);
const loadError = ref('');
const errorAlert = ref('');
const searchInput = ref('');
const ticked = ref(new Set());
const note = ref('');
const saving = ref(false);

const allTicked = computed(() => ticked.value.size > 0 && ticked.value.size === data.value.data.length);

function load() {
    loadError.value = '';
    return http.get('/integrations/property-finder/review', { params: { tab: route.query.tab, q: route.query.q, page: route.query.page } })
        .then((res) => {
            data.value = res.data;
            searchInput.value = res.data.search;
            ticked.value = new Set();
            note.value = '';
        })
        .catch((e) => {
            loadError.value = errorMessage(e);
        });
}

function tick(id, checked) {
    const set = new Set(ticked.value);
    checked ? set.add(id) : set.delete(id);
    ticked.value = set;
}

function tickAll(checked) {
    ticked.value = checked ? new Set(data.value.data.map((item) => item.id)) : new Set();
}

async function act(action) {
    // Rejecting deletes the properties — confirm first.
    if (action === 'reject' && !(await confirm({ title: 'Reject the selected listings?', message: 'Their properties and photos are removed, and they won\'t be imported again.', confirmText: 'Reject', tone: 'danger' }))) return;
    saving.value = true;
    errorAlert.value = '';
    http.post('/integrations/property-finder/review', { action, ids: [...ticked.value], note: note.value || null })
        .then((res) => {
            success(res.data.message);
            return load();
        })
        .catch((e) => {
            errorAlert.value = errorMessage(e);
        })
        .finally(() => {
            saving.value = false;
        });
}

function applySearch() {
    router.push({ query: { tab: data.value.tab, q: searchInput.value.trim() || undefined } });
}

function goToPage(p) {
    router.push({ query: { ...route.query, page: p } });
}

watch(() => route.query, () => {
    if (route.name === 'integrations.property-finder.review') load();
}, { immediate: true });
</script>
