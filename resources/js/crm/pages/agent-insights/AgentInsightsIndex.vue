<template>
    <PlanLockedCard v-if="locked" title="Agent Insights" heading="Unlock agent insights" icon="fas fa-user-check" feature="Agent insights" :plural="true" :locked="locked"
                    description="See how each of your agents handles the leads and listings you assign them — response rate and time, untouched leads, overdue follow-ups and listing quality." />
    <div v-else>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <div class="portal-section-title mb-0">Agent Insights</div>
                <p class="text-muted mb-0" style="font-size: 0.88rem; max-width: 760px;">
                    How {{ isAdmin ? 'each agency\'s' : 'your' }} agents handle the leads and listings assigned to them. Open an agent to see exactly what needs their attention.
                </p>
            </div>
        </div>

        <div class="portal-lp-stats ai-stats portal-card p-3 mb-3">
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon"><i class="fas fa-user-tie"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Agents</div><div class="portal-lp-stat-value">{{ number(totals.agents) }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-accent"><i class="fas fa-inbox"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Leads received</div><div class="portal-lp-stat-value">{{ number(totals.received) }} <small class="text-muted fw-normal">· {{ periodLabel }}</small></div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-warning"><i class="fas fa-hourglass-half"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Untouched leads</div><div class="portal-lp-stat-value" :class="{ 'text-danger': totals.untouched }">{{ number(totals.untouched) }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-warning"><i class="fas fa-calendar-xmark"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Overdue follow-ups</div><div class="portal-lp-stat-value" :class="{ 'text-danger': totals.overdue_follow_ups }">{{ number(totals.overdue_follow_ups) }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-success"><i class="fas fa-building"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Live listings</div><div class="portal-lp-stat-value">{{ number(totals.live_listings) }}</div></div>
            </div>
        </div>

        <div class="portal-card portal-filter-bar mb-3">
            <form class="portal-filter-bar__form" @submit.prevent="load(1)">
                <div class="portal-filter-field portal-filter-field--search" :class="{ 'is-set': search }">
                    <div class="portal-filter-control">
                        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                        <input v-model="searchInput" type="search" class="form-control" placeholder="Agent name or email" autocomplete="off" maxlength="100" aria-label="Search">
                    </div>
                </div>
                <div class="portal-filter-field" :class="{ 'is-set': days !== 30 }">
                    <div class="portal-filter-control">
                        <i class="fas fa-calendar" aria-hidden="true"></i>
                        <select v-model.number="days" class="form-select" aria-label="Period" @change="load(1)">
                            <option v-for="option in periods" :key="option.key" :value="option.key">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="portal-filter-field" :class="{ 'is-set': sort !== 'attention' }">
                    <div class="portal-filter-control">
                        <i class="fas fa-arrow-down-wide-short" aria-hidden="true"></i>
                        <select v-model="sort" class="form-select" aria-label="Sort" @change="load(1)">
                            <option v-for="option in sorts" :key="option.key" :value="option.key">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="portal-filter-actions">
                    <button type="submit" class="portal-filter-submit" title="Apply" aria-label="Apply"><i class="fas fa-filter" aria-hidden="true"></i></button>
                    <button v-if="search || days !== 30 || sort !== 'attention'" type="button" class="portal-filter-bar__clear" title="Clear" @click="clear"><i class="fas fa-xmark" aria-hidden="true"></i> Clear</button>
                </div>
            </form>
        </div>

        <div class="portal-card p-3 p-md-4">
            <div class="table-responsive">
                <table class="table portal-table mb-0 ai-table">
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th class="text-center">Agent score</th>
                            <th>Leads received</th>
                            <th :title="`Leads acted on within ${responseHours} hours`">Response rate</th>
                            <th title="Median time to the first action on a new lead">Response time</th>
                            <th title="Open leads with no activity since they were assigned">Untouched</th>
                            <th>Overdue follow-ups</th>
                            <th>Live listings</th>
                            <th>Listing quality</th>
                            <th :title="`Leads closed and listings sold / rented · ${periodLabel}`">Closed</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody :class="{ 'opacity-50': loading }">
                        <tr v-for="agent in agents" :key="agent.id" class="li-row" tabindex="0" @click="open($event, agent)" @keydown.enter="open($event, agent)">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img v-if="agent.avatar" :src="agent.avatar" alt="" class="ai-avatar">
                                    <span v-else class="portal-lead-avatar">{{ initials(agent.name, 1) }}</span>
                                    <div class="min-w-0">
                                        <RouterLink :to="showRoute(agent)" class="portal-lead-name-button text-decoration-none text-truncate d-block" style="max-width: 200px;" :title="agent.name">{{ agent.name }}</RouterLink>
                                        <div class="text-muted text-truncate" style="font-size: 0.78rem; max-width: 200px;">{{ agent.agency || agent.email }}</div>
                                        <span v-if="agent.suspended" class="badge bg-secondary-subtle text-secondary-emphasis border">Suspended</span>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span v-if="agent.score.score !== null" class="pq-ring pq-ring--inline ai-ring" :class="`is-${agent.score.tone}`" :style="{ '--pq': agent.score.score }"
                                      :title="`Agent score ${agent.score.score} of 100`"><span>{{ agent.score.score }}</span></span>
                                <span v-else class="text-muted small" title="Nothing to measure yet — no leads or listings">—</span>
                            </td>
                            <td><span class="li-metric">{{ agent.leads.received }}<small>{{ agent.leads.open }} open</small></span></td>
                            <td>
                                <template v-if="agent.leads.response_rate !== null">
                                    <span class="li-metric">{{ agent.leads.response_rate }}%</span>
                                    <span class="ai-bar" :class="tone(agent.leads.response_rate)" aria-hidden="true"><span :style="{ width: `${agent.leads.response_rate}%` }"></span></span>
                                </template>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td class="text-nowrap"><span class="li-metric">{{ responseTime(agent.leads.median_minutes) }}</span></td>
                            <td><span class="li-metric" :class="{ 'text-danger': agent.leads.untouched }">{{ agent.leads.untouched }}</span></td>
                            <td><span class="li-metric" :class="{ 'text-danger': agent.follow_ups.overdue }">{{ agent.follow_ups.overdue }}<small>{{ agent.follow_ups.scheduled }} scheduled</small></span></td>
                            <td><span class="li-metric">{{ agent.listings.live }}<small>of {{ agent.listings.total }}</small></span></td>
                            <td>
                                <span v-if="agent.listings.avg_quality !== null" class="lp-pill" :class="`is-${qualityTone(agent.listings.avg_quality)}`">{{ agent.listings.avg_quality }}/100</span>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td><span class="li-metric">{{ agent.leads.closed }}<small>{{ agent.deals }} {{ plural('deal', agent.deals) }}</small></span></td>
                            <td class="text-end"><RouterLink :to="showRoute(agent)" class="portal-lead-insights"><i class="fas fa-chart-line" aria-hidden="true"></i>Details</RouterLink></td>
                        </tr>
                        <tr v-if="!loading && !agents.length">
                            <td colspan="11" class="portal-empty text-center text-muted py-5">
                                <i class="fas fa-user-check fa-2x mb-2 d-block opacity-50"></i>
                                {{ search ? 'No agents match your search.' : 'No agents yet — add agents from the Agents page and assign them leads and listings.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-3"><CrmPagination :meta="meta" @change="load" /></div>
        </div>
    </div>
</template>

<script setup>
/** Agent Insights — GET /agent-insights; each row opens the agent's insights (agent-insights.show). */
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import PlanLockedCard from '../../components/PlanLockedCard.vue';
import { useToast } from '../../composables/useToast';
import { initials, number, plural } from '../../utils/format';
import { planLocked } from '../../utils/planLocked';
import { qualityTone, responseTime, tone } from './insights';

const router = useRouter();
const { error: toastError } = useToast();

const agents = ref([]);
const meta = ref(null);
const totals = ref({ agents: 0, received: 0, untouched: 0, overdue_follow_ups: 0, live_listings: 0 });
const periods = ref([{ key: 30, label: 'Last 30 days' }]);
const sorts = ref([{ key: 'attention', label: 'Needs attention first' }]);
const responseHours = ref(24);
const isAdmin = ref(false);
const loading = ref(false);
const locked = ref(null); // plan without agent insights → "Unlock agent insights" card
const days = ref(30);
const sort = ref('attention');
const search = ref('');
const searchInput = ref('');
let timer = null;

const periodLabel = computed(() => (periods.value.find((p) => p.key === days.value)?.label ?? '').toLowerCase());
const showRoute = (agent) => ({ name: 'agent-insights.show', params: { id: agent.id }, query: days.value !== 30 ? { days: days.value } : {} });

function load(p = 1) {
    loading.value = true;
    return http.get('/agent-insights', { params: { days: days.value, sort: sort.value, search: search.value || undefined, page: p } })
        .then((res) => {
            agents.value = res.data.data;
            meta.value = res.data.meta;
            totals.value = res.data.totals;
            periods.value = res.data.periods;
            sorts.value = res.data.sorts;
            responseHours.value = res.data.response_hours;
            isAdmin.value = res.data.is_admin;
        })
        .catch((e) => {
            locked.value = planLocked(e);
            if (!locked.value) toastError(errorMessage(e));
        })
        .finally(() => {
            loading.value = false;
        });
}

function clear() {
    searchInput.value = '';
    search.value = '';
    days.value = 30;
    sort.value = 'attention';
    load(1);
}

function open(event, agent) {
    if (event.target.closest('a, button, input')) return;
    router.push(showRoute(agent));
}

watch(searchInput, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        search.value = value.trim();
        load(1);
    }, 350);
});

onMounted(() => load());
</script>
