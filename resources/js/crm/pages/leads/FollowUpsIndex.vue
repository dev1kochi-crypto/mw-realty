<template>
    <div class="portal-lead-page">
        <div class="mb-3">
            <div class="portal-section-title mb-0">Follow-ups</div>
            <p class="text-muted mb-0" style="font-size: 0.88rem;">
                Calls, WhatsApp messages and emails scheduled on your leads. Whoever is assigned to the lead gets a notification and an email 15 minutes before each one is due. Times are UAE time.
            </p>
        </div>

        <ul class="nav nav-pills portal-lead-page-tabs mb-3" role="tablist">
            <li v-for="t in tabs" :key="t.key" class="nav-item" role="presentation">
                <button type="button" class="nav-link" :class="{ active: tab === t.key }" role="tab" :aria-selected="tab === t.key" @click="setTab(t.key)">
                    <i class="fas me-1" :class="t.icon" aria-hidden="true"></i>{{ t.label }}
                    <span v-if="counts[t.key] !== null && counts[t.key] !== undefined" class="badge" :class="{ 'bg-danger': t.key === 'overdue' && counts.overdue }">{{ number(counts[t.key]) }}</span>
                </button>
            </li>
        </ul>

        <div class="portal-card p-3 p-md-4">
            <div v-if="loadError" class="text-center py-4 text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
            <div v-else class="table-responsive">
                <table class="table portal-table mb-0">
                    <thead>
                        <tr>
                            <th>Lead</th>
                            <th>Follow-up</th>
                            <th>{{ tab === 'done' ? 'Done' : 'Due (UAE)' }}</th>
                            <th>{{ tab === 'done' ? 'Outcome' : 'Note' }}</th>
                            <th>Assigned</th>
                            <th v-if="tab !== 'done'" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody :class="{ 'opacity-50': loading }">
                        <tr v-for="f in rows" :key="f.id">
                            <td><RouterLink :to="{ name: 'leads.show', params: { id: f.lead_id } }" class="fw-semibold text-decoration-none">{{ f.lead?.name || 'Unknown' }}</RouterLink></td>
                            <td class="text-nowrap">
                                <i class="fas me-1" :class="TYPE_ICONS[f.type]" aria-hidden="true"></i>{{ f.type_label }}
                                <span v-if="f.status === 'cancelled'" class="badge bg-secondary ms-1">Cancelled</span>
                                <span v-else-if="f.status === 'completed'" class="badge bg-success ms-1">Done</span>
                                <span v-else-if="f.is_overdue" class="badge bg-danger ms-1">Overdue</span>
                            </td>
                            <td class="text-nowrap">{{ tab === 'done' ? f.done_label : f.due_label }}</td>
                            <td class="small" style="max-width: 320px;">{{ (tab === 'done' ? f.outcome : f.note) || '—' }}</td>
                            <td class="small">{{ f.lead?.agent || f.lead?.owner || '—' }}</td>
                            <td v-if="tab !== 'done'" class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-success" @click="open('complete', f)"><i class="fas fa-check me-1"></i>Complete</button>
                                <button type="button" class="btn btn-sm portal-btn-ghost ms-1" @click="open('reschedule', f)">Reschedule</button>
                                <button type="button" class="btn btn-sm portal-btn-ghost text-danger ms-1" @click="open('cancel', f)">Cancel</button>
                            </td>
                        </tr>
                        <tr v-if="!loading && !rows.length">
                            <td :colspan="tab === 'done' ? 5 : 6" class="portal-empty">{{ empty[tab] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="meta && meta.last_page > 1" class="mt-3"><CrmPagination :meta="meta" @change="load" /></div>
        </div>

        <FollowUpModal :open="!!modal" :mode="modal || 'complete'" :follow-up="selected" @close="modal = null" @saved="load(meta?.current_page ?? 1)" />
    </div>
</template>

<script setup>
/** Leads › Follow-ups — overdue / today / upcoming / done across the leads you can see. GET /leads/follow-ups. */
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import FollowUpModal from './components/FollowUpModal.vue';
import { TYPE_ICONS } from './components/followUps';
import { useSession } from '../../composables/useSession';
import { number } from '../../utils/format';

const route = useRoute();
const router = useRouter();
const { refreshNavigation } = useSession();

const tabs = [
    { key: 'overdue', label: 'Overdue', icon: 'fa-triangle-exclamation' },
    { key: 'today', label: 'Today', icon: 'fa-calendar-day' },
    { key: 'upcoming', label: 'Upcoming', icon: 'fa-calendar-week' },
    { key: 'done', label: 'Done', icon: 'fa-check' },
];
const empty = {
    overdue: 'Nothing overdue — nice work.',
    today: 'Nothing else due today.',
    upcoming: 'Nothing scheduled yet. Open a lead and use Follow-ups → + to schedule one.',
    done: 'No completed or cancelled follow-ups yet.',
};

const tab = ref(tabs.some((t) => t.key === route.query.tab) ? route.query.tab : 'overdue');
const rows = ref([]);
const counts = ref({});
const meta = ref(null);
const loading = ref(false);
const loadError = ref('');
const modal = ref(null);
const selected = ref(null);

function load(page = 1) {
    loading.value = true;
    loadError.value = '';
    return http.get('/leads/follow-ups', { params: { tab: tab.value, page } })
        .then((res) => {
            rows.value = res.data.data;
            counts.value = res.data.counts;
            meta.value = res.data.meta;
            // The sidebar's overdue badge follows this screen.
            refreshNavigation().catch(() => {});
        })
        .catch((e) => {
            loadError.value = errorMessage(e);
        })
        .finally(() => {
            loading.value = false;
        });
}

function setTab(key) {
    tab.value = key;
    router.replace({ query: key === 'overdue' ? {} : { tab: key } });
    load();
}

function open(mode, followUp) {
    selected.value = followUp;
    modal.value = mode;
}

onMounted(() => load());
</script>
