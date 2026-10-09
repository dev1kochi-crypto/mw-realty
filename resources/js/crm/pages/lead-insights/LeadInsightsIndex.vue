<template>
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <div class="portal-section-title mb-0">Lead Insights</div>
                <p class="text-muted mb-0" style="font-size: 0.88rem; max-width: 760px;">
                    How {{ isAdmin ? 'website' : 'your website' }} leads behave on the site — listings they view, time spent, searches and AI chats. Open one to see their full activity.
                </p>
            </div>
        </div>

        <div class="portal-lp-stats portal-card p-3 mb-3">
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon"><i class="fas fa-users"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Leads with website activity</div><div class="portal-lp-stat-value">{{ number(totals.leads) }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-accent"><i class="fas fa-house"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Property views</div><div class="portal-lp-stat-value">{{ number(totals.property_views) }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-warning"><i class="fas fa-clock"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Total time on site</div><div class="portal-lp-stat-value">{{ duration(totals.seconds) }}</div></div>
            </div>
            <div class="portal-lp-stat">
                <span class="portal-lp-stat-icon is-success"><i class="fas fa-bolt"></i></span>
                <div class="min-w-0"><div class="portal-lp-stat-label">Active this week</div><div class="portal-lp-stat-value">{{ number(totals.active_week) }} <small class="text-muted fw-normal">· {{ totals.chats }} AI chats</small></div></div>
            </div>
        </div>

        <div class="portal-card portal-filter-bar mb-3">
            <form class="portal-filter-bar__form" @submit.prevent="load(1)">
                <div class="portal-filter-field portal-filter-field--search" :class="{ 'is-set': search }">
                    <div class="portal-filter-control">
                        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                        <input v-model="searchInput" type="search" class="form-control" placeholder="Name, email or phone" autocomplete="off" maxlength="100" aria-label="Search">
                    </div>
                </div>
                <div class="portal-filter-field" :class="{ 'is-set': sort !== 'active' }">
                    <div class="portal-filter-control">
                        <i class="fas fa-arrow-down-wide-short" aria-hidden="true"></i>
                        <select v-model="sort" class="form-select" aria-label="Sort" @change="load(1)">
                            <option v-for="option in sorts" :key="option.key" :value="option.key">{{ option.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="portal-filter-actions">
                    <button type="submit" class="portal-filter-submit" title="Apply" aria-label="Apply"><i class="fas fa-filter" aria-hidden="true"></i></button>
                    <button v-if="search || sort !== 'active'" type="button" class="portal-filter-bar__clear" title="Clear" @click="clear"><i class="fas fa-xmark" aria-hidden="true"></i> Clear</button>
                </div>
            </form>
        </div>

        <div class="portal-card p-3 p-md-4">
            <div class="table-responsive">
                <table class="table portal-table mb-0">
                    <thead>
                        <tr>
                            <th>Lead</th>
                            <th v-if="isAdmin">Owner</th>
                            <th>Stage</th>
                            <th>Property views</th>
                            <th>Time on site</th>
                            <th>Searches</th>
                            <th>AI chats</th>
                            <th>Last active</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody :class="{ 'opacity-50': loading }">
                        <tr v-for="lead in leads" :key="lead.id" class="portal-lead-row li-row" tabindex="0" @click="open($event, lead)" @keydown.enter="open($event, lead)">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="portal-lead-avatar">{{ initials(lead.name, 1) }}</span>
                                    <div class="min-w-0">
                                        <RouterLink :to="insightsRoute(lead)" class="portal-lead-name-button text-decoration-none text-truncate d-block" style="max-width: 200px;" :title="lead.name">{{ lead.name || 'Unknown' }}</RouterLink>
                                        <div class="text-muted text-truncate" style="font-size: 0.78rem; max-width: 200px;">{{ lead.email || lead.formatted_phone || '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td v-if="isAdmin">{{ lead.owner?.name ?? 'Unassigned' }}</td>
                            <td>
                                <span v-if="lead.stage" class="portal-lp-badge" :style="{ '--badge': lead.stage.color }"><span class="portal-lp-badge-dot"></span>{{ lead.stage.name }}</span>
                                <span v-else class="text-muted">-</span>
                            </td>
                            <td><span class="li-metric">{{ lead.stats.property_views }}<small>{{ lead.stats.properties }} {{ plural('listing', lead.stats.properties) }}</small></span></td>
                            <td class="text-nowrap">
                                <span class="li-metric">{{ duration(lead.stats.seconds) }}</span>
                                <span class="li-heat" aria-hidden="true"><span :style="{ width: `${Math.round((100 * lead.stats.seconds) / maxSeconds)}%` }"></span></span>
                            </td>
                            <td><span class="li-metric">{{ lead.stats.searches }}</span></td>
                            <td><span class="li-metric">{{ lead.stats.chats }}</span></td>
                            <td class="text-muted text-nowrap">{{ lead.stats.last_seen ? timeAgo(lead.stats.last_seen) : '—' }}</td>
                            <td class="text-end"><RouterLink :to="insightsRoute(lead)" class="portal-lead-insights"><i class="fas fa-chart-line" aria-hidden="true"></i>Insights</RouterLink></td>
                        </tr>
                        <tr v-if="!loading && !leads.length">
                            <td :colspan="isAdmin ? 9 : 8" class="portal-empty text-center text-muted py-5">
                                <i class="fas fa-chart-line fa-2x mb-2 d-block opacity-50"></i>
                                {{ search ? 'No leads match your search.' : 'No leads with website activity yet — they appear here once website visitors (AI chat, enquiry forms, customer accounts) become your leads.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <nav v-if="meta && meta.last_page > 1" class="mt-3" aria-label="Pages">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item" :class="{ disabled: meta.current_page <= 1 }"><button type="button" class="page-link" @click="load(meta.current_page - 1)">Previous</button></li>
                    <li class="page-item disabled"><span class="page-link">{{ meta.current_page }} / {{ meta.last_page }}</span></li>
                    <li class="page-item" :class="{ disabled: meta.current_page >= meta.last_page }"><button type="button" class="page-link" @click="load(meta.current_page + 1)">Next</button></li>
                </ul>
            </nav>
        </div>
    </div>
</template>

<script setup>
/** Lead Insights (resources/views/portal/crm/lead-insights/index) — GET /lead-insights; each row opens the lead's Insights tab. */
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import { useToast } from '../../composables/useToast';
import { duration, initials, number, plural, timeAgo } from '../../utils/format';

const router = useRouter();
const { error: toastError } = useToast();

const leads = ref([]);
const meta = ref(null);
const totals = ref({ leads: 0, property_views: 0, seconds: 0, chats: 0, active_week: 0 });
const sorts = ref([{ key: 'active', label: 'Recently active' }]);
const isAdmin = ref(false);
const loading = ref(false);
const sort = ref('active');
const search = ref('');
const searchInput = ref('');
let timer = null;

const maxSeconds = computed(() => Math.max(1, ...leads.value.map((lead) => lead.stats.seconds)));
const insightsRoute = (lead) => ({ name: 'leads.show', params: { id: lead.id }, hash: '#insights' });

function load(p = 1) {
    loading.value = true;
    return http.get('/lead-insights', { params: { sort: sort.value, search: search.value || undefined, page: p } })
        .then((res) => {
            leads.value = res.data.data;
            meta.value = res.data.meta;
            totals.value = res.data.totals;
            sorts.value = res.data.sorts;
            isAdmin.value = res.data.is_admin;
        })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => {
            loading.value = false;
        });
}

function clear() {
    searchInput.value = '';
    search.value = '';
    sort.value = 'active';
    load(1);
}

function open(event, lead) {
    if (event.target.closest('a, button, input')) return;
    router.push(insightsRoute(lead));
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
