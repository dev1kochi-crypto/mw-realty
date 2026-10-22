<template>
    <div v-if="!page" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>

    <!-- Plan without reports. -->
    <div v-else-if="page.locked">
        <div class="portal-section-title mb-3">Reports</div>
        <div class="portal-card p-5 text-center" style="max-width: 640px; margin: 2rem auto;">
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center"
                 style="width: 72px; height: 72px; border-radius: 20px; background: linear-gradient(135deg, var(--portal-primary), var(--portal-accent)); color: #fff; font-size: 1.8rem;">
                <i class="fas fa-chart-line"></i>
            </div>
            <h4 class="fw-bold mb-2">Unlock reports &amp; analytics</h4>
            <p class="portal-muted mb-4">
                Leads, Properties{{ isCompany ? ' and Agents' : '' }} reports — lead trends, sources and conversion,
                your best-performing listings{{ isCompany ? ', and how each of your agents is doing' : '' }}.
                <template v-if="page.locked.agency">Reports aren't included in {{ page.locked.agency }}'s {{ page.locked.plan ?? 'current' }} plan. Ask your agency to upgrade.</template>
                <template v-else>Reports aren't included in your {{ page.locked.plan ?? 'current' }} plan.</template>
            </p>
            <a v-if="!page.locked.agency" href="/portal/plans" class="btn btn-portal-primary"><i class="fas fa-rocket me-2"></i>View Plans</a>
        </div>
    </div>

    <div v-else>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <div class="portal-section-title mb-0">Reports</div>
                <div v-if="page.is_admin" class="text-muted small">Showing every account's data (Super Admin).</div>
            </div>
            <div class="btn-group btn-group-sm rp-range" role="group" aria-label="Date range">
                <RouterLink v-for="range in page.ranges" :key="range.value" :to="{ params: { report: page.report }, query: { range: range.value } }" class="btn portal-btn-ghost" :class="{ active: page.days === range.value }">{{ range.label }}</RouterLink>
            </div>
        </div>

        <nav class="rp-tabs mb-4">
            <RouterLink v-for="tab in page.reports" :key="tab.key" :to="{ params: { report: tab.key }, query: { range: page.days } }" class="rp-tab" :class="{ 'is-active': page.report === tab.key }">
                <i class="fas" :class="icons[tab.key]"></i>{{ tab.label }}
            </RouterLink>
        </nav>

        <LeadsReport v-if="page.report === 'leads'" :page="page" />
        <PropertiesReport v-else-if="page.report === 'properties'" :page="page" />
        <SalesReport v-else-if="page.report === 'sales'" :page="page" />
        <AgentsReport v-else-if="page.report === 'agents'" :page="page" />
    </div>
</template>

<script setup>
/**
 * Reports (resources/views/portal/crm/reports/*) — Leads, Properties, Sales and (agencies / Super
 * Admin) Agents, over 7 / 30 / 90 days or 12 months (?range=). A plan without reports gets the
 * "Unlock reports" card instead.
 */
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import AgentsReport from './components/AgentsReport.vue';
import LeadsReport from './components/LeadsReport.vue';
import PropertiesReport from './components/PropertiesReport.vue';
import SalesReport from './components/SalesReport.vue';

const route = useRoute();

const icons = { leads: 'fa-address-book', properties: 'fa-building', sales: 'fa-handshake', agents: 'fa-user-tie' };
const page = ref(null);
const loadError = ref('');
const isCompany = computed(() => page.value?.owner_type === 'company');

function load() {
    loadError.value = '';
    const report = route.params.report || 'leads';
    return http.get(`/reports/${report}`, { params: route.query })
        .then((res) => { page.value = res.data; })
        .catch((e) => { loadError.value = errorMessage(e); });
}

watch(() => [route.params.report, route.query], () => {
    if (route.name === 'reports.show') load();
}, { immediate: true, deep: true });
</script>
