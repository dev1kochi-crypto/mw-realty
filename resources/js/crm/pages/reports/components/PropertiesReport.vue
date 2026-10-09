<template>
    <div class="row g-3 mb-3">
        <div v-for="[label, value, icon, tone] in stats" :key="label" class="col-6 col-lg">
            <div class="portal-stat-card">
                <div class="portal-stat-icon" :class="tone"><i class="fas" :class="icon"></i></div>
                <div><div class="portal-stat-value">{{ number(value) }}</div><div class="portal-stat-label">{{ label }}</div></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="portal-card p-4 h-100">
                <div class="portal-section-title">Listings Added Over Time</div>
                <div class="rp-chart"><ChartCanvas :config="rpChart('bar', d.over_time)" /></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="portal-card p-4 h-100">
                <div class="portal-section-title">Sale vs Rent</div>
                <div v-if="!d.by_listing_type.labels.length" class="rp-empty">No properties yet.</div>
                <div v-else class="rp-chart"><ChartCanvas :config="rpChart('doughnut', d.by_listing_type)" /></div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="portal-card p-4 h-100">
                <div class="portal-section-title">By Property Type</div>
                <div v-if="!d.by_type.labels.length" class="rp-empty">No properties yet.</div>
                <div v-else class="rp-chart rp-chart--sm"><ChartCanvas :config="rpChart('bar', d.by_type, null, true)" /></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="portal-card p-4 h-100">
                <div class="portal-section-title">Residential vs Commercial</div>
                <div v-if="!d.by_segment.labels.length" class="rp-empty">No properties yet.</div>
                <div v-else class="rp-chart rp-chart--sm"><ChartCanvas :config="rpChart('doughnut', d.by_segment, ['#264373', '#04a1cc'])" /></div>
            </div>
        </div>
        <div class="col-12">
            <div class="portal-card p-4">
                <div class="portal-section-title">Top Properties by Leads</div>
                <div v-if="!d.top_by_leads.length" class="rp-empty">None of these properties have received leads yet.</div>
                <div v-else class="table-responsive">
                    <table class="table rp-table mb-0">
                        <thead><tr><th>Property</th><th>Type</th><th>Price</th><th>Status</th><th class="text-end">Leads ({{ page.range_label }})</th><th class="text-end">All-time Leads</th></tr></thead>
                        <tbody>
                            <tr v-for="property in d.top_by_leads" :key="property.id">
                                <td>
                                    <RouterLink :to="{ name: 'properties.show', params: { id: property.id } }" class="fw-semibold text-decoration-none">{{ limit(property.title, 55) }}</RouterLink>
                                    <div class="text-muted small">{{ property.reference_no }}</div>
                                </td>
                                <td>{{ property.listing_type }}</td>
                                <td class="text-nowrap">{{ property.currency }} {{ number(Math.round(property.price)) }}</td>
                                <td><span class="badge" :class="property.active ? 'bg-success' : 'bg-secondary'">{{ property.active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end fw-bold">{{ property.leads_in_range }}</td>
                                <td class="text-end">{{ property.leads_total }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
/** Properties report (resources/views/portal/crm/reports/properties). */
import { computed } from 'vue';
import ChartCanvas from '../../../components/ChartCanvas.vue';
import { number } from '../../../utils/format';
import { rpChart } from '../rpChart';

const props = defineProps({ page: { type: Object, required: true } });

const d = computed(() => props.page.data);
const stats = computed(() => {
    const k = d.value.kpis;
    return [
        ['Total Properties', k.total, 'fa-building', 'indigo'],
        ['Active', k.active, 'fa-eye', 'teal'],
        ['Inactive', k.inactive, 'fa-eye-slash', 'rose'],
        ['Featured Now', k.featured, 'fa-star', 'amber'],
        [`Added (${props.page.range_label})`, k.new, 'fa-plus-circle', 'indigo'],
    ];
});
const limit = (text, max) => (text.length > max ? `${text.slice(0, max)}...` : text);
</script>
