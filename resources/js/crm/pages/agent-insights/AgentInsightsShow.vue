<template>
    <PlanLockedCard v-if="locked" title="Agent Insights" heading="Unlock agent insights" icon="fas fa-user-check" feature="Agent insights" :plural="true" :locked="locked"
                    description="See how each of your agents handles the leads and listings you assign them." />
    <div v-else-if="!data" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else class="portal-lead-page" :class="{ 'opacity-50': loading }">
        <!-- Header -->
        <div class="portal-card portal-lp-header mb-3">
            <div class="portal-lp-header-top">
                <RouterLink :to="{ name: 'agent-insights.index' }" class="portal-lp-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> All agents</RouterLink>
                <select v-model.number="days" class="form-select form-select-sm ai-period" aria-label="Period">
                    <option v-for="option in data.periods" :key="option.key" :value="option.key">{{ option.label }}</option>
                </select>
            </div>
            <div class="portal-lp-header-main">
                <img v-if="agent.avatar" :src="agent.avatar" alt="" class="ai-avatar ai-avatar--lg">
                <span v-else class="portal-lp-initials">{{ initials(agent.name) }}</span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h1 class="portal-lp-name">{{ agent.name }}</h1>
                        <span v-if="agent.suspended" class="portal-lp-badge portal-lp-badge-empty"><span class="portal-lp-badge-dot"></span>Suspended</span>
                    </div>
                    <div class="portal-lp-header-meta">
                        <span v-if="agent.email"><i class="fas fa-envelope" aria-hidden="true"></i>{{ agent.email }}</span>
                        <span v-if="agent.phone"><i class="fas fa-phone" aria-hidden="true"></i>{{ agent.phone }}</span>
                        <span v-if="agent.agency"><i class="fas fa-building" aria-hidden="true"></i>{{ agent.agency }}</span>
                        <span v-if="agent.joined_at"><i class="far fa-calendar" aria-hidden="true"></i>Member since {{ formatDate(agent.joined_at) }}</span>
                    </div>
                    <div class="portal-lp-header-chips">
                        <RouterLink :to="{ name: 'leads.index', query: { agent_id: agent.id } }" class="portal-lp-badge portal-lp-badge-accent text-decoration-none"><i class="fas fa-address-book" aria-hidden="true"></i>Their leads</RouterLink>
                        <RouterLink :to="{ name: 'properties.index', query: { agent: agent.id } }" class="portal-lp-badge portal-lp-badge-accent text-decoration-none"><i class="fas fa-building" aria-hidden="true"></i>Their listings</RouterLink>
                        <RouterLink v-if="agent.membership_id" :to="{ name: 'agents.show', params: { id: agent.membership_id } }" class="portal-lp-badge portal-lp-badge-accent text-decoration-none"><i class="fas fa-user-tie" aria-hidden="true"></i>Agent profile</RouterLink>
                    </div>
                </div>
                <div class="ai-score">
                    <span v-if="agent.score.score !== null" class="pq-ring pq-ring--inline ai-ring ai-ring--lg m-0" :class="`is-${agent.score.tone}`" :style="{ '--pq': agent.score.score }"><span>{{ agent.score.score }}</span></span>
                    <span v-else class="ai-score__empty">—</span>
                    <div class="min-w-0">
                        <div class="ai-score__label">Agent score</div>
                        <div class="ai-score__hint">{{ scoreHint }}</div>
                    </div>
                </div>
            </div>
            <div class="portal-lp-stats ai-stats--agent">
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon is-accent"><i class="fas fa-inbox"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Leads received</div><div class="portal-lp-stat-value">{{ agent.leads.received }} <small class="text-muted fw-normal">· {{ agent.leads.open }} open</small></div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon is-success"><i class="fas fa-reply"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Response rate</div><div class="portal-lp-stat-value">{{ agent.leads.response_rate !== null ? `${agent.leads.response_rate}%` : '—' }} <small class="text-muted fw-normal">in {{ data.response_hours }} h</small></div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon"><i class="fas fa-stopwatch"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Response time</div><div class="portal-lp-stat-value">{{ responseTime(agent.leads.median_minutes) }}</div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon is-success"><i class="fas fa-handshake"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Closed</div><div class="portal-lp-stat-value">{{ agent.leads.closed }} <small class="text-muted fw-normal">· {{ agent.deals }} {{ plural('deal', agent.deals) }}</small></div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon is-success"><i class="fas fa-building"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Live listings</div><div class="portal-lp-stat-value">{{ agent.listings.live }} <small class="text-muted fw-normal">of {{ agent.listings.total }}</small></div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon is-warning"><i class="fas fa-gauge-high"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Listing quality</div><div class="portal-lp-stat-value">{{ agent.listings.avg_quality !== null ? `${agent.listings.avg_quality}/100` : '—' }}</div></div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav portal-lp-tabs mb-3" role="tablist">
            <li v-for="t in tabs" :key="t.key" class="nav-item" role="presentation">
                <button type="button" class="nav-link" :class="{ active: tab === t.key }" role="tab" @click="setTab(t.key)">
                    <i class="fas" :class="t.icon" aria-hidden="true"></i>{{ t.label }} <span v-if="t.count !== undefined" class="portal-lp-tab-count" :class="{ 'ai-count-alert': t.count > 0 }">{{ t.count }}</span>
                </button>
            </li>
        </ul>

        <!-- ============ Score ============ -->
        <div v-if="tab === 'score'" class="row g-3">
            <div v-for="(group, g) in agent.score.groups" :key="g" class="col-lg-6">
                <div class="portal-card p-4 h-100">
                    <div class="fw-bold mb-3"><i class="fas me-2 text-muted" :class="g === 0 ? 'fa-address-book' : 'fa-building'"></i>{{ g === 0 ? 'Leads' : 'Listings' }}</div>
                    <div v-for="check in group" :key="check.label" class="ai-check" :class="`is-${checkTone(check)}`">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <span class="fw-semibold">{{ check.label }}</span>
                            <span v-if="!check.applies" class="ai-check__points" title="Nothing to measure in this period">Not applicable</span>
                            <span v-else class="ai-check__points">{{ check.points }}/{{ check.max }}</span>
                        </div>
                        <div v-if="check.applies" class="ai-check__bar"><span :style="{ width: `${Math.round(100 * check.points / check.max)}%` }"></span></div>
                        <div v-if="check.tip" class="ai-check__tip">{{ check.tip }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ Untouched leads ============ -->
        <div v-else-if="tab === 'leads'" class="portal-card p-4">
            <div class="small portal-muted mb-2">Open leads with no note, stage / status change or follow-up for more than {{ data.response_hours }} hours since {{ agent.name }} got them.</div>
            <div class="table-responsive">
                <table class="table portal-table ai-table mb-0">
                    <thead><tr><th>Lead</th><th>Stage</th><th>Received</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="lead in data.untouched_leads.data" :key="lead.id">
                            <td><div class="fw-semibold">{{ lead.name || 'Unknown' }}</div><div class="small text-muted">{{ lead.contact || '—' }}</div></td>
                            <td>
                                <span v-if="lead.stage" class="portal-lp-badge" :style="{ '--badge': lead.stage.color }"><span class="portal-lp-badge-dot"></span>{{ lead.stage.name }}</span>
                                <span v-else class="text-muted">-</span>
                            </td>
                            <td class="text-nowrap" :title="formatDateTime(lead.received_at)">{{ timeAgo(lead.received_at) }}</td>
                            <td class="text-end"><RouterLink :to="{ name: 'leads.show', params: { id: lead.id } }" class="btn btn-sm portal-btn-ghost">Open</RouterLink></td>
                        </tr>
                        <tr v-if="!data.untouched_leads.data.length"><td colspan="4" class="portal-empty text-center text-muted py-3"><i class="fas fa-circle-check text-success me-1"></i>Every open lead has been worked on.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-2"><CrmPagination :meta="data.untouched_leads.meta" @change="(p) => goToPage('leads_page', p)" /></div>
        </div>

        <!-- ============ Overdue follow-ups ============ -->
        <div v-else-if="tab === 'follow-ups'" class="portal-card p-4">
            <div class="small portal-muted mb-2">Scheduled calls, WhatsApp messages and emails past their due time.</div>
            <div class="table-responsive">
                <table class="table portal-table ai-table mb-0">
                    <thead><tr><th>Lead</th><th>Follow-up</th><th>Was due</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="followUp in data.overdue_follow_ups.data" :key="followUp.id">
                            <td class="fw-semibold">{{ followUp.lead_name || 'Unknown' }}</td>
                            <td><div>{{ followUp.type }}</div><div v-if="followUp.note" class="small text-muted text-truncate" style="max-width: 320px;" :title="followUp.note">{{ followUp.note }}</div></td>
                            <td class="text-nowrap text-danger" :title="formatDateTime(followUp.due_at)">{{ timeAgo(followUp.due_at) }}</td>
                            <td class="text-end"><RouterLink :to="{ name: 'leads.show', params: { id: followUp.lead_id } }" class="btn btn-sm portal-btn-ghost">Open lead</RouterLink></td>
                        </tr>
                        <tr v-if="!data.overdue_follow_ups.data.length"><td colspan="4" class="portal-empty text-center text-muted py-3"><i class="fas fa-circle-check text-success me-1"></i>No overdue follow-ups.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-2"><CrmPagination :meta="data.overdue_follow_ups.meta" @change="(p) => goToPage('follow_ups_page', p)" /></div>
        </div>

        <!-- ============ Listings needing work ============ -->
        <div v-else class="portal-card p-4">
            <div class="small portal-muted mb-2">Agency listings assigned to {{ agent.name }} that are not live, have a permit problem or a quality score below 50.</div>
            <div class="table-responsive">
                <table class="table portal-table ai-table mb-0">
                    <thead><tr><th>Ref</th><th>Title</th><th>Quality</th><th>What to fix</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="listing in data.listings.data" :key="listing.id">
                            <td class="text-nowrap">{{ listing.reference_no }}</td>
                            <td><span class="d-block text-truncate" style="max-width: 280px;" :title="listing.title">{{ listing.title || '—' }}</span></td>
                            <td><span class="lp-pill" :class="`is-${listing.quality.tone}`">{{ listing.quality.score }}/100</span></td>
                            <td><span v-for="issue in listing.issues" :key="issue" class="badge bg-light text-dark border me-1 mb-1">{{ issue }}</span></td>
                            <td class="text-end"><RouterLink :to="{ name: listing.segment === 'commercial' ? 'commercial.edit' : 'properties.edit', params: { id: listing.id } }" class="btn btn-sm portal-btn-ghost">Edit</RouterLink></td>
                        </tr>
                        <tr v-if="!data.listings.data.length"><td colspan="5" class="portal-empty text-center text-muted py-3"><i class="fas fa-circle-check text-success me-1"></i>All listings are live and in good shape.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-2"><CrmPagination :meta="data.listings.meta" @change="(p) => goToPage('listings_page', p)" /></div>
        </div>
    </div>
</template>

<script setup>
/** One agent's insights — GET /agent-insights/{id}: header + score, then tabs for the breakdown and what needs their attention. */
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import PlanLockedCard from '../../components/PlanLockedCard.vue';
import { formatDate, formatDateTime, initials, plural, timeAgo } from '../../utils/format';
import { planLocked } from '../../utils/planLocked';
import { responseTime } from './insights';

const props = defineProps({ id: { type: Number, required: true } });

const route = useRoute();
const router = useRouter();

const TABS = ['score', 'leads', 'follow-ups', 'listings'];

const data = ref(null);
const loadError = ref('');
const loading = ref(false);
const locked = ref(null);
const days = ref(Number(route.query.days) || 30);
const tab = computed(() => (TABS.includes(route.query.tab) ? route.query.tab : 'score'));

const agent = computed(() => data.value.agent);
const scoreHint = computed(() => {
    const tone = agent.value.score.tone;
    if (tone === 'good') return 'Handling assigned work well.';
    if (tone === 'fair') return 'Some things need attention.';
    if (tone === 'poor') return 'Needs follow-up from you.';
    return 'Not enough leads or listings to score yet.';
});
const tabs = computed(() => [
    { key: 'score', label: 'Score breakdown', icon: 'fa-gauge-high' },
    { key: 'leads', label: 'Untouched leads', icon: 'fa-inbox', count: data.value.untouched_leads.meta.total },
    { key: 'follow-ups', label: 'Overdue follow-ups', icon: 'fa-calendar-xmark', count: data.value.overdue_follow_ups.meta.total },
    { key: 'listings', label: 'Listings needing work', icon: 'fa-building-circle-exclamation', count: data.value.listings.meta.total },
]);

function checkTone(check) {
    if (!check.applies) return 'none';
    if (check.points >= check.max) return 'good';
    return check.points > 0 ? 'fair' : 'poor';
}

function load() {
    loadError.value = '';
    loading.value = true;
    const q = route.query;
    return http.get(`/agent-insights/${props.id}`, { params: { days: days.value, leads_page: q.leads_page, follow_ups_page: q.follow_ups_page, listings_page: q.listings_page } })
        .then((res) => { data.value = res.data; })
        .catch((e) => {
            locked.value = planLocked(e);
            if (!locked.value) loadError.value = errorMessage(e);
        })
        .finally(() => { loading.value = false; });
}

function setTab(key) {
    router.replace({ query: { ...route.query, tab: key !== 'score' ? key : undefined } });
}

function goToPage(key, p) {
    router.push({ query: { ...route.query, [key]: p } });
}

watch(days, (value) => {
    router.replace({ query: { ...route.query, days: value !== 30 ? value : undefined } });
});

// Switching tabs needs no reload — every list is already in the response.
watch(() => JSON.stringify([props.id, { ...route.query, tab: undefined }]), () => {
    if (route.name === 'agent-insights.show') load();
}, { immediate: true });
</script>
