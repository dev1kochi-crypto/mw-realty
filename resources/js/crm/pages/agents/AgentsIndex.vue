<template>
    <div v-if="!page" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>

    <!-- ========== Super Admin: every agent ========== -->
    <div v-else-if="page.is_admin">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="portal-section-title mb-0">All Agents</div>
            <a :href="page.agency_approvals_url" class="portal-btn-ghost btn btn-sm"><i class="fas fa-user-clock me-1"></i> Agency approvals</a>
        </div>

        <div class="portal-card p-4">
            <div class="table-responsive">
                <table class="table portal-table mb-0">
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>Agency</th>
                            <th>Contact</th>
                            <th>Experience</th>
                            <th>Preferred Areas</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="agent in page.data" :key="agent.id">
                            <td class="fw-semibold">{{ agent.name }}</td>
                            <td>{{ agent.agency ?? 'Independent' }}</td>
                            <td>
                                <div>{{ agent.email }}</div>
                                <div v-if="agent.phone" class="text-muted small">{{ agent.phone }}</div>
                            </td>
                            <td>{{ agent.years_of_experience !== null ? `${agent.years_of_experience} yrs` : '—' }}</td>
                            <td>{{ agent.preferred_areas.length ? agent.preferred_areas.join(', ') : '—' }}</td>
                            <td>
                                <span class="portal-badge-status" :class="agent.is_active ? 'portal-badge-active' : 'portal-badge-inactive'">{{ agent.is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                        </tr>
                        <tr v-if="!page.data.length"><td colspan="6" class="portal-empty">No agents yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3"><CrmPagination :meta="page.meta" @change="goToPage" /></div>
    </div>

    <!-- ========== Agency: its own roster ========== -->
    <div v-else>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <div class="portal-section-title mb-1">My Agents</div>
                <p class="text-muted small mb-0">
                    Enquiries on a listing with an assigned agent go to that agent. Other leads are rotated across your active agents, or wait for you to assign them — see Lead assignment below.
                </p>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2">
                <span class="portal-usage-chip" :class="{ 'is-danger': slotsFull }" :title="slotTitle">
                    <i class="fas fa-users"></i>
                    <span class="portal-usage-chip__label">Agent slots</span>
                    <strong>{{ slots.used }} / {{ slots.remaining === null ? '∞' : Number(slots.limit || 0) }}</strong>
                    <span v-if="slots.remaining !== null" class="portal-usage-chip__meter"><span :style="{ width: `${slotPct}%` }"></span></span>
                </span>
                <RouterLink v-if="!slotsFull" :to="{ name: 'agents.create' }" class="btn btn-portal-primary btn-sm">
                    <i class="fas fa-plus me-1"></i> Add / Invite Agent
                </RouterLink>
            </div>
        </div>

        <div v-if="slotsFull" class="alert alert-info d-flex align-items-center gap-2 small py-2 mb-3">
            <i class="fas fa-circle-info"></i>
            <span v-if="agentsLocked">Team agent accounts are available after you upgrade your plan.</span>
            <span v-else>You've used all {{ Number(slots.limit) }} agent slots on your plan. Upgrade your plan to add more agents.</span>
        </div>

        <!-- Lead distribution: who got the last round-robin lead, whose turn is next. -->
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="portal-stat-card h-100">
                    <div class="portal-stat-icon indigo"><i class="fas fa-user-check"></i></div>
                    <div>
                        <div class="portal-stat-value">{{ rr.eligible_count }}</div>
                        <div class="portal-stat-label">Active agents in rotation</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="portal-stat-card h-100">
                    <div class="portal-stat-icon teal"><i class="fas fa-rotate"></i></div>
                    <div class="min-w-0">
                        <div class="fw-bold text-truncate">{{ rr.last ?? '—' }}</div>
                        <div class="portal-stat-label">Last round-robin lead{{ rr.last_at ? ` · ${timeAgo(rr.last_at)}` : '' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="portal-stat-card h-100">
                    <div class="portal-stat-icon amber"><i class="fas fa-forward"></i></div>
                    <div class="min-w-0">
                        <template v-if="page.assignment.mode === 'manual'">
                            <div class="fw-bold">Manual assignment</div>
                            <div class="portal-stat-label">You assign each new lead</div>
                        </template>
                        <template v-else>
                            <div class="fw-bold">{{ rr.next ?? 'No active agents' }}</div>
                            <div class="portal-stat-label">{{ rr.next ? 'Next in line' : 'Leads stay with the agency' }}</div>
                        </template>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="portal-stat-card h-100">
                    <div class="portal-stat-icon rose"><i class="fas fa-inbox"></i></div>
                    <div class="flex-grow-1">
                        <div class="portal-stat-value">{{ rr.unassigned }}</div>
                        <div class="portal-stat-label">Unassigned agency leads</div>
                    </div>
                    <button v-if="rr.unassigned > 0 && rr.eligible_count > 0" type="button" class="btn btn-sm portal-btn-ghost" title="Distribute across active agents" :disabled="distributing" @click="distribute"><i class="fas fa-shuffle"></i></button>
                </div>
            </div>
        </div>

        <!-- Lead assignment: one slim summary line; the settings open in a modal (shared with Leads). -->
        <div class="portal-card lassign-bar mb-3">
            <span class="lassign-bar__icon" aria-hidden="true"><i class="fas fa-shuffle"></i></span>
            <span class="lassign-bar__title">Lead assignment</span>
            <div class="lassign-bar__chips">
                <template v-if="page.assignment.mode === 'manual'">
                    <span class="lassign-chip lassign-chip--mode"><i class="fas fa-hand-pointer"></i>Manual</span>
                    <span class="lassign-chip">New leads wait in Unassigned</span>
                </template>
                <template v-else>
                    <span class="lassign-chip lassign-chip--mode"><i class="fas fa-rotate"></i>Automatic round robin</span>
                    <span v-for="source in page.assignment.available_sources" :key="source.key" class="lassign-chip" :class="{ 'lassign-chip--off': !page.assignment.sources.includes(source.key) }" :title="page.assignment.sources.includes(source.key) ? 'Round robin' : 'Waits in Unassigned'">{{ source.label }}</span>
                </template>
            </div>
            <button type="button" class="btn btn-sm portal-btn-ghost" @click="assignmentOpen = true"><i class="fas fa-sliders me-1"></i>Change</button>
        </div>
        <AssignmentModal :open="assignmentOpen" :settings="page.assignment" @close="assignmentOpen = false" @saved="onAssignmentSaved" />

        <ul class="nav portal-lang-tabs mb-3">
            <li v-for="(label, key) in tabs" :key="key" class="nav-item">
                <RouterLink class="nav-link" :class="{ active: page.tab === key }" :to="{ query: key === 'active' ? {} : { tab: key } }">
                    {{ label }}<span class="portal-tab-count">{{ page.tab_counts[key] }}</span>
                </RouterLink>
            </li>
        </ul>

        <div class="portal-card p-4">
            <div class="table-responsive">
                <table class="table portal-table mb-0">
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th v-if="page.tab !== 'history'">Plan</th>
                            <template v-if="page.tab === 'active'">
                                <th class="text-center">Listings</th>
                                <th class="text-center">Leads</th>
                            </template>
                            <th>{{ page.tab === 'history' ? 'Ended' : 'Since' }}</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in page.data" :key="m.id">
                            <td class="fw-semibold">
                                <RouterLink v-if="m.is_member" :to="{ name: 'agents.show', params: { id: m.id } }" class="text-decoration-none">{{ m.agent.name }}</RouterLink>
                                <template v-else>{{ m.agent.name }}</template>
                                <div class="portal-muted small">Agent ID {{ m.agent.id }}</div>
                            </td>
                            <td>
                                <div>{{ m.agent.email }}</div>
                                <div v-if="m.agent.phone" class="text-muted small">{{ m.agent.phone }}</div>
                            </td>
                            <td>
                                <span class="badge border" :class="`bg-${m.status_tone}-subtle text-${m.status_tone}-emphasis`">{{ m.status_label }}</span>
                                <div v-if="m.rejection_reason" class="text-muted small">{{ m.rejection_reason }}</div>
                            </td>
                            <td v-if="page.tab !== 'history'" class="small">
                                <template v-if="m.is_member">
                                    <template v-if="m.plan.on_agency_plan">
                                        <span class="badge bg-success-subtle text-success-emphasis border"><i class="fas fa-building me-1"></i>Your plan</span>
                                        <div v-if="m.plan.previous" class="text-muted mt-1">switched from {{ m.plan.previous }} · no refund</div>
                                    </template>
                                    <template v-else>
                                        <span class="badge bg-light text-dark border">Own plan · {{ m.plan.own }}</span>
                                        <div class="text-muted mt-1">your plan doesn't include team agents</div>
                                    </template>
                                </template>
                                <template v-else>
                                    <span class="badge border" :class="m.plan.own_paid ? 'bg-warning-subtle text-warning-emphasis' : 'bg-light text-dark'">{{ m.plan.own }}{{ m.plan.own_paid ? ' (paid)' : '' }}</span>
                                    <div class="text-muted mt-1">→ moves to your plan on approval{{ m.plan.own_paid ? ', own plan dropped (no refund)' : '' }}</div>
                                </template>
                            </td>
                            <template v-if="page.tab === 'active'">
                                <td class="text-center">{{ m.listings }}</td>
                                <td class="text-center">{{ m.leads }}</td>
                            </template>
                            <td class="small">{{ m.date ? formatDate(m.date) : '' }}</td>
                            <td class="text-end text-nowrap">
                                <template v-if="m.status === 'approved'">
                                    <button class="btn btn-sm btn-outline-secondary" :disabled="busy === m.id" @click="act(m, 'suspend')">Suspend</button>
                                    <RouterLink :to="{ name: 'agents.show', params: { id: m.id }, hash: '#remove' }" class="btn btn-sm btn-outline-danger">Remove</RouterLink>
                                </template>
                                <template v-else-if="m.status === 'suspended'">
                                    <button class="btn btn-sm btn-outline-success" :disabled="busy === m.id" @click="act(m, 'reactivate')">Reactivate</button>
                                    <RouterLink :to="{ name: 'agents.show', params: { id: m.id }, hash: '#remove' }" class="btn btn-sm btn-outline-danger">Remove</RouterLink>
                                </template>
                                <template v-else-if="m.status === 'requested'">
                                    <button class="btn btn-sm btn-success" :disabled="busy === m.id" @click="act(m, 'accept-request')">Accept</button>
                                    <button class="btn btn-sm btn-outline-danger" :disabled="busy === m.id" @click="act(m, 'decline-request')">Decline</button>
                                </template>
                                <button v-else-if="m.status === 'invited' || m.status === 'pending'" class="btn btn-sm btn-outline-secondary" :disabled="busy === m.id" @click="cancel(m)">Cancel</button>
                            </td>
                        </tr>
                        <tr v-if="!page.data.length">
                            <td colspan="8" class="portal-empty">
                                <template v-if="page.tab === 'active' && agentsLocked">No agents yet — team agent accounts are available after you upgrade your plan.</template>
                                <template v-else-if="page.tab === 'active'">
                                    No active agents yet — your listings and enquiries still work without them.
                                    <template v-if="!slotsFull"><RouterLink :to="{ name: 'agents.create' }">Add or invite an agent</RouterLink>.</template>
                                </template>
                                <template v-else>Nothing here.</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3"><CrmPagination :meta="page.meta" @change="goToPage" /></div>
    </div>
</template>

<script setup>
/**
 * Agents (resources/views/portal/agents/index + admin-index). Agency: its roster by tab (?tab=),
 * round-robin state, lead assignment (AssignmentModal, shared with Leads) and agent slots.
 * Super Admin: every agent, read-only.
 */
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import AssignmentModal from '../leads/components/AssignmentModal.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { formatDate, timeAgo } from '../../utils/format';

const route = useRoute();
const router = useRouter();
const { confirm } = useConfirm();
const { success, error: toastError } = useToast();

const tabs = { active: 'Active', pending: 'Pending & Invited', requests: 'Join Requests', history: 'History' };
const page = ref(null);
const loadError = ref('');
const busy = ref(null);
const distributing = ref(false);
const assignmentOpen = ref(false);

const rr = computed(() => page.value.round_robin);
const slots = computed(() => page.value.agent_slots);
const slotsFull = computed(() => slots.value.remaining === 0);
const agentsLocked = computed(() => slotsFull.value && !slots.value.limit);
const slotPct = computed(() => (slots.value.remaining === null || !slots.value.limit ? 0 : Math.min(100, Math.round((slots.value.used / slots.value.limit) * 100))));
const slotTitle = computed(() => (slots.value.remaining === null ? 'Unlimited agent slots' : `${slots.value.used} of ${Number(slots.value.limit || 0)} agent slots used`)
    + (slotsFull.value ? '. Upgrade to add more.' : ''));

function load() {
    loadError.value = '';
    return http.get('/agents', { params: { tab: route.query.tab, page: route.query.page } })
        .then((res) => { page.value = res.data; })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function goToPage(p) {
    router.push({ query: { ...route.query, page: p } });
}

function act(membership, action) {
    busy.value = membership.id;
    http.post(`/agents/${membership.id}/${action}`)
        .then((res) => { success(res.data.message); return load(); })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { busy.value = null; });
}

async function cancel(membership) {
    if (!(await confirm({
        title: `Cancel this ${membership.status === 'invited' ? 'invitation' : 'request'}?`,
        confirmText: 'Cancel it',
        cancelText: 'Keep',
        tone: 'warning',
    }))) return;
    act(membership, 'cancel');
}

async function distribute() {
    if (!(await confirm({ title: 'Distribute unassigned leads?', message: 'Round-robin all unassigned leads across your active agents?', confirmText: 'Distribute', tone: 'primary' }))) return;
    distributing.value = true;
    http.post('/leads/distribute')
        .then((res) => { success(res.data.message); return load(); })
        .catch(() => toastError('Could not distribute the leads — please try again.'))
        .finally(() => { distributing.value = false; });
}

function onAssignmentSaved(settings) {
    page.value.assignment = settings;
    assignmentOpen.value = false;
}

watch(() => route.query, () => {
    if (route.name === 'agents.index') load();
}, { immediate: true });
</script>
