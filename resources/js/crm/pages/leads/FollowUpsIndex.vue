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
                <table class="table portal-table fu-table mb-0">
                    <thead>
                        <tr>
                            <th class="fu-id">ID</th>
                            <th>Lead</th>
                            <th>Follow-up</th>
                            <th>{{ tab === 'done' ? 'Done' : 'Due (UAE)' }}</th>
                            <th>{{ tab === 'done' ? 'Outcome' : 'Note' }}</th>
                            <th>Assigned</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody :class="{ 'opacity-50': loading }">
                        <tr v-for="(f, index) in rows" :key="f.id" :class="{ 'is-overdue': f.is_overdue && f.status === 'scheduled' }">
                            <td class="fu-id">{{ (meta?.from ?? 1) + index }}</td>
                            <td>
                                <RouterLink :to="{ name: 'leads.show', params: { id: f.lead_id } }" class="fu-lead text-decoration-none" :title="f.lead?.name">{{ f.lead?.name || 'Unknown' }}</RouterLink>
                                <div v-if="f.created_by_name" class="fu-sub">Added by {{ f.created_by_name }}</div>
                            </td>
                            <td class="text-nowrap">
                                <span class="fu-type" :class="`is-${f.type}`"><i class="fas" :class="TYPE_ICONS[f.type]" aria-hidden="true"></i>{{ f.type_label }}</span>
                                <span v-if="f.status === 'cancelled'" class="fu-status is-cancelled">Cancelled</span>
                                <span v-else-if="f.status === 'completed'" class="fu-status is-done">Done</span>
                                <span v-else-if="f.is_overdue" class="fu-status is-overdue">Overdue</span>
                            </td>
                            <td class="text-nowrap">
                                <template v-if="tab === 'done'"><div class="fu-when">{{ when(f.done_label) }}</div></template>
                                <template v-else>
                                    <div class="fu-when">{{ when(f.due_label) }}</div>
                                    <div class="fu-sub" :class="{ 'text-danger': f.is_overdue }">{{ timeAgo(f.due_at) }}</div>
                                </template>
                            </td>
                            <td><div class="fu-note" :title="(tab === 'done' ? f.outcome : f.note) || ''">{{ (tab === 'done' ? f.outcome : f.note) || '—' }}</div></td>
                            <td>
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <span class="fu-avatar" aria-hidden="true">{{ initials(assignee(f), 1) }}</span>
                                    <span class="fu-assignee text-truncate" :title="assignee(f)">{{ assignee(f) }}</span>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="fu-actions">
                                    <template v-if="f.status === 'scheduled'">
                                        <button type="button" class="fu-act is-complete" title="Complete" aria-label="Complete" @click="open('complete', f)"><i class="fas fa-check"></i></button>
                                        <button type="button" class="fu-act" title="Reschedule" aria-label="Reschedule" @click="open('reschedule', f)"><i class="fas fa-calendar-days"></i></button>
                                        <button type="button" class="fu-act is-cancel" title="Cancel" aria-label="Cancel" @click="open('cancel', f)"><i class="fas fa-xmark"></i></button>
                                    </template>
                                    <RouterLink :to="{ name: 'leads.show', params: { id: f.lead_id } }" class="fu-act" title="Open lead" aria-label="Open lead"><i class="fas fa-arrow-up-right-from-square"></i></RouterLink>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!loading && !rows.length">
                            <td colspan="7" class="portal-empty">{{ empty[tab] }}</td>
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
import { initials, number, timeAgo } from '../../utils/format';

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

// The column header already says UAE, so the label drops its "(UAE)" suffix.
const when = (label) => (label ? label.replace(/\s*\(UAE\)$/, '') : '—');
const assignee = (f) => f.lead?.agent || f.lead?.owner || '—';

function open(mode, followUp) {
    selected.value = followUp;
    modal.value = mode;
}

onMounted(() => load());
</script>
