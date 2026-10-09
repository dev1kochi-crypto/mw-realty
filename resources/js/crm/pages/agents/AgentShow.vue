<template>
    <div v-if="!data" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="portal-section-title mb-0">{{ data.agent.name }}</div>
            <RouterLink :to="{ name: 'agents.index' }" class="portal-btn-ghost btn btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</RouterLink>
        </div>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="portal-card p-4 h-100">
                    <div class="mb-2"><span class="badge border" :class="`bg-${data.membership.status_tone}-subtle text-${data.membership.status_tone}-emphasis`">{{ data.membership.status_label }}</span></div>
                    <div class="small portal-muted">Agent ID</div><div class="mb-2">{{ data.agent.id }}</div>
                    <div class="small portal-muted">Email</div><div class="mb-2">{{ data.agent.email }}</div>
                    <div class="small portal-muted">Phone</div><div class="mb-2">{{ data.agent.phone || '—' }}</div>
                    <div class="small portal-muted">Joined</div><div class="mb-2">{{ data.joined_at ? formatDate(data.joined_at) : '—' }}</div>
                    <div class="small portal-muted">Agency listings assigned</div><div class="mb-2">{{ data.properties.meta.total }}</div>
                    <div class="small portal-muted">Agency leads assigned (all time)</div><div class="mb-3">{{ data.lead_count }}</div>
                    <RouterLink :to="{ name: 'leads.index', query: { agent_id: data.agent.id } }" class="btn btn-sm portal-btn-ghost">View their leads</RouterLink>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="portal-card p-4 mb-3">
                    <div class="fw-bold mb-2">Assigned agency listings</div>
                    <table class="table portal-table mb-0">
                        <thead><tr><th>Ref</th><th>Title</th><th>Status</th></tr></thead>
                        <tbody>
                            <tr v-for="property in data.properties.data" :key="property.id">
                                <td>{{ property.reference_no }}</td>
                                <td><RouterLink :to="{ name: property.segment === 'commercial' ? 'commercial.edit' : 'properties.edit', params: { id: property.id } }" class="text-decoration-none">{{ property.title ?? '—' }}</RouterLink></td>
                                <td>{{ property.active ? 'Active' : 'Inactive' }}</td>
                            </tr>
                            <tr v-if="!data.properties.data.length"><td colspan="3" class="portal-empty">No listings assigned.</td></tr>
                        </tbody>
                    </table>
                    <div class="mt-2"><CrmPagination :meta="data.properties.meta" @change="goToPage" /></div>
                </div>

                <div class="portal-card p-4 mb-3">
                    <div class="fw-bold mb-2">Membership history with your agency</div>
                    <ul class="list-unstyled small mb-0">
                        <li v-for="row in data.history" :key="row.id" class="mb-1">
                            <span class="badge border" :class="`bg-${row.status_tone}-subtle text-${row.status_tone}-emphasis`">{{ row.status_label }}</span>
                            {{ ucfirst(row.initiated_by) }}-initiated · {{ formatDate(row.created_at) }}
                            <template v-if="row.joined_at"> · joined {{ formatDate(row.joined_at) }}</template>
                            <template v-if="row.left_at"> · left {{ formatDate(row.left_at) }}</template>
                        </li>
                    </ul>
                </div>

                <div v-if="data.membership.is_member" id="remove" class="portal-card p-4 border border-danger-subtle">
                    <div class="fw-bold mb-1 text-danger">Remove from agency</div>
                    <div class="portal-muted small mb-3">
                        Their account, personal listings and lead history are kept. Leads already assigned to them stay on record — reassign them from Leads if needed.
                        Choose who takes over the {{ data.properties.meta.total }} agency listing(s) assigned to them, or leave them unassigned so new enquiries round-robin.
                    </div>
                    <form class="d-flex flex-wrap gap-2" @submit.prevent="remove">
                        <select v-model="reassignTo" class="form-select" style="max-width: 300px;">
                            <option value="">Leave listings unassigned (round-robin)</option>
                            <option v-for="other in data.reassign_options" :key="other.id" :value="other.id">Reassign listings to {{ other.name }}</option>
                        </select>
                        <button type="submit" class="btn btn-danger" :disabled="removing">Remove agent</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
/** One agent of this agency (resources/views/portal/agents/show) — profile, listings, history, remove. */
import { nextTick, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { formatDate, ucfirst } from '../../utils/format';

const props = defineProps({ id: { type: Number, required: true } });

const route = useRoute();
const router = useRouter();
const { confirm } = useConfirm();
const { success, error: toastError } = useToast();

const data = ref(null);
const loadError = ref('');
const reassignTo = ref('');
const removing = ref(false);

function load() {
    loadError.value = '';
    return http.get(`/agents/${props.id}`, { params: { page: route.query.page } })
        .then((res) => {
            const first = !data.value;
            data.value = res.data;
            // "Remove" on the roster links here with #remove.
            if (first && route.hash === '#remove') nextTick(() => document.getElementById('remove')?.scrollIntoView({ behavior: 'smooth' }));
        })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function goToPage(p) {
    router.push({ query: { ...route.query, page: p }, hash: '' });
}

async function remove() {
    if (!(await confirm({ title: `Remove ${data.value.agent.name} from your agency?`, confirmText: 'Remove agent', tone: 'danger' }))) return;
    removing.value = true;
    http.post(`/agents/${props.id}/remove`, { reassign_to: reassignTo.value || null })
        .then((res) => {
            success(res.data.message);
            router.push({ name: 'agents.index' });
        })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { removing.value = false; });
}

watch(() => [props.id, route.query.page], () => {
    if (route.name === 'agents.show') load();
}, { immediate: true });
</script>
