<template>
    <div class="row g-3 mb-3">
        <div v-for="[label, value, icon, tone] in stats" :key="label" class="col-6 col-lg">
            <div class="portal-stat-card">
                <div class="portal-stat-icon" :class="tone"><i class="fas" :class="icon"></i></div>
                <div><div class="portal-stat-value">{{ number(value) }}</div><div class="portal-stat-label">{{ label }}</div></div>
            </div>
        </div>
    </div>

    <div v-if="(k.unassigned_properties ?? 0) > 0" class="alert alert-warning d-flex align-items-center gap-2 py-2">
        <i class="fas fa-exclamation-triangle"></i>
        <span>{{ k.unassigned_properties }} of your {{ plural('property', k.unassigned_properties) }} {{ k.unassigned_properties === 1 ? 'has' : 'have' }} no agent assigned — leads on {{ k.unassigned_properties === 1 ? 'it' : 'them' }} stay with the agency.</span>
    </div>

    <div class="row g-3">
        <div v-if="d.chart.labels.length" class="col-12">
            <div class="portal-card p-4">
                <div class="portal-section-title">Leads per Agent ({{ page.range_label }})</div>
                <div class="rp-chart rp-chart--sm"><ChartCanvas :config="rpChart('bar', d.chart, null, d.chart.labels.length > 6)" /></div>
            </div>
        </div>

        <div class="col-12">
            <div class="portal-card p-4">
                <div class="portal-section-title">Agent Performance</div>
                <div v-if="!d.rows.length" class="rp-empty">
                    No agents in your agency yet.
                    <RouterLink v-if="!page.is_admin" :to="{ name: 'agents.index' }">Add or invite agents</RouterLink><template v-if="!page.is_admin">.</template>
                </div>
                <template v-else>
                    <div class="table-responsive">
                        <table class="table rp-table mb-0">
                            <thead>
                                <tr>
                                    <th>Agent</th>
                                    <th v-if="page.is_admin">Agency</th>
                                    <th>Status</th>
                                    <th class="text-end">Properties</th>
                                    <th>Leads ({{ page.range_label }})</th>
                                    <th class="text-end">All-time Leads</th>
                                    <th class="text-end">Closed</th>
                                    <th class="text-end">Conversion</th>
                                    <th>Last Lead</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in d.rows" :key="i">
                                    <td>
                                        <div class="fw-semibold">{{ row.agent.name }}</div>
                                        <div class="text-muted small">{{ row.agent.email }}</div>
                                    </td>
                                    <td v-if="page.is_admin" class="small">{{ row.agency }}</td>
                                    <td><span class="badge" :class="`bg-${row.status_tone}`">{{ row.status_label }}</span></td>
                                    <td class="text-end">
                                        <span class="fw-semibold">{{ row.active_properties }}</span>
                                        <span class="text-muted small"> / {{ row.properties }}</span>
                                    </td>
                                    <td style="min-width: 140px;">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-bold" style="min-width: 1.5rem;">{{ row.leads_in_range }}</span>
                                            <div class="rp-bar flex-grow-1"><span :style="{ width: `${(row.leads_in_range / maxLeads) * 100}%` }"></span></div>
                                        </div>
                                    </td>
                                    <td class="text-end">{{ row.leads }}</td>
                                    <td class="text-end">{{ row.closed }}</td>
                                    <td class="text-end fw-semibold">{{ row.conversion }}%</td>
                                    <td class="small text-muted text-nowrap">{{ row.last_lead_at ? timeAgo(row.last_lead_at) : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-muted small mt-2">Properties shows active / total listings assigned to the agent. Leads and conversion count only your agency's leads assigned to them.</div>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
/** Agents report (resources/views/portal/crm/reports/agents) — agencies and Super Admin. */
import { computed } from 'vue';
import ChartCanvas from '../../../components/ChartCanvas.vue';
import { number, plural, timeAgo } from '../../../utils/format';
import { rpChart } from '../rpChart';

const props = defineProps({ page: { type: Object, required: true } });

const d = computed(() => props.page.data);
const k = computed(() => d.value.kpis);
const stats = computed(() => [
    ['Active Agents', k.value.active, 'fa-user-check', 'indigo'],
    ['Suspended', k.value.suspended, 'fa-user-clock', 'rose'],
    ['Pending Approval', k.value.pending, 'fa-hourglass-half', 'amber'],
    ['Assigned Properties', k.value.properties, 'fa-building', 'teal'],
    [`Leads to Agents (${props.page.range_label})`, k.value.leads_in_range, 'fa-address-book', 'indigo'],
]);
const maxLeads = computed(() => Math.max(1, ...d.value.rows.map((r) => r.leads_in_range)));
</script>
