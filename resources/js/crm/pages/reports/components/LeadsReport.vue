<template>
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg">
            <div class="portal-stat-card">
                <div class="portal-stat-icon indigo"><i class="fas fa-address-book"></i></div>
                <div><div class="portal-stat-value">{{ number(k.total) }}</div><div class="portal-stat-label">Total Leads</div></div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="portal-stat-card">
                <div class="portal-stat-icon teal"><i class="fas fa-user-plus"></i></div>
                <div>
                    <div class="portal-stat-value">{{ number(k.in_range) }} <RpChange :pct="k.change" :days="page.days" /></div>
                    <div class="portal-stat-label">New ({{ page.range_label }})</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="portal-stat-card">
                <div class="portal-stat-icon amber"><i class="fas fa-check-circle"></i></div>
                <div><div class="portal-stat-value">{{ number(k.closed) }}</div><div class="portal-stat-label">Closed Leads</div></div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="portal-stat-card">
                <div class="portal-stat-icon rose"><i class="fas fa-percentage"></i></div>
                <div><div class="portal-stat-value">{{ k.conversion }}%</div><div class="portal-stat-label">Conversion Rate</div></div>
            </div>
        </div>
        <div v-if="k.unassigned !== null" class="col-6 col-lg">
            <RouterLink :to="{ name: 'leads.index' }" class="portal-stat-card text-decoration-none">
                <div class="portal-stat-icon indigo"><i class="fas fa-user-slash"></i></div>
                <div><div class="portal-stat-value">{{ number(k.unassigned) }}</div><div class="portal-stat-label">Not Assigned to an Agent</div></div>
            </RouterLink>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="portal-card p-4 h-100">
                <div class="portal-section-title">Leads Over Time</div>
                <div class="rp-chart"><ChartCanvas :config="rpChart('line', d.over_time)" /></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="portal-card p-4 h-100">
                <div class="portal-section-title">Leads by Stage</div>
                <div v-if="!d.by_stage.length" class="rp-empty">No leads yet.</div>
                <div v-else class="rp-chart"><ChartCanvas :config="rpChart('doughnut', stageSeries, d.by_stage.map((s) => s.color || '#264373'))" /></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="portal-card p-4 h-100">
                <div class="portal-section-title">Leads by Source</div>
                <div v-if="!d.by_source.labels.length" class="rp-empty">No leads yet.</div>
                <div v-else class="rp-chart rp-chart--sm"><ChartCanvas :config="rpChart('bar', d.by_source, null, true)" /></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="portal-card p-4 h-100">
                <div class="portal-section-title">Most Enquired Properties <span class="text-muted fw-normal small">({{ page.range_label }})</span></div>
                <div v-for="(row, i) in d.top_properties" :key="i" class="d-flex align-items-center gap-3 py-2" :class="{ 'border-bottom': i < d.top_properties.length - 1 }">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ row.title }}</div>
                        <div class="text-muted small">{{ row.reference_no }}<template v-if="row.listing_type"> &middot; {{ row.listing_type }}</template></div>
                        <div class="rp-bar mt-1"><span :style="{ width: `${(row.total / maxEnquiries) * 100}%` }"></span></div>
                    </div>
                    <div class="fw-bold">{{ row.total }}</div>
                </div>
                <div v-if="!d.top_properties.length" class="rp-empty">No property enquiries in this period.</div>
            </div>
        </div>
    </div>
</template>

<script setup>
/** Leads report (resources/views/portal/crm/reports/leads). */
import { computed } from 'vue';
import ChartCanvas from '../../../components/ChartCanvas.vue';
import { number } from '../../../utils/format';
import { rpChart } from '../rpChart';
import RpChange from './RpChange.vue';

const props = defineProps({ page: { type: Object, required: true } });

const d = computed(() => props.page.data);
const k = computed(() => d.value.kpis);
const stageSeries = computed(() => ({ labels: d.value.by_stage.map((s) => s.name), values: d.value.by_stage.map((s) => s.total) }));
const maxEnquiries = computed(() => Math.max(1, ...d.value.top_properties.map((r) => r.total)));
</script>
