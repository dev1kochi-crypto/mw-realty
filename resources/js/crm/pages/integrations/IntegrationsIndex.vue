<template>
    <div>
        <div class="mb-3">
            <div class="portal-section-title mb-0">Integrations</div>
            <p class="text-muted mb-0" style="font-size: .85rem;">Connect the tools you already use — leads and listings flow into MW Realty automatically.</p>
        </div>

        <div v-if="!data" class="text-center py-5">
            <span v-if="!loadError" class="spinner-border text-primary"></span>
            <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
        </div>
        <div v-else class="intg-grid">
            <RouterLink v-for="integration in data.integrations" :key="integration.key" :to="{ name: routes[integration.key] }" class="intg-tile"
                        :style="{ '--tile-color': integration.color }" :aria-label="`${integration.name} — ${integration.status.label}`">
                <div class="intg-tile__top">
                    <span class="intg-tile__logo" aria-hidden="true"><i :class="integration.icon"></i></span>
                    <span class="intg-status" :class="`intg-status--${integration.status.tone}`">
                        <i v-if="integration.status.tone === 'ok'" class="fas fa-circle-check"></i>
                        <i v-else-if="integration.status.tone === 'warn'" class="fas fa-hourglass-half"></i>{{ integration.status.label }}
                    </span>
                </div>
                <div>
                    <div class="intg-tile__cat">{{ integration.category }}</div>
                    <h6 class="intg-tile__name">{{ integration.name }}</h6>
                </div>
                <p class="intg-tile__desc">{{ integration.description }}</p>
                <div class="intg-tile__foot">
                    <span>{{ integration.status.tone === 'off' && !data.is_admin ? 'Connect' : 'Open' }}</span>
                    <i class="fas fa-arrow-right"></i>
                </div>
            </RouterLink>
            <div class="intg-tile intg-tile--soon">
                <i class="fas fa-puzzle-piece"></i>
                <div class="fw-semibold">More integrations coming</div>
                <div class="small">New integrations will appear here as they're added.</div>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * CRM › Integrations hub (resources/views/portal/crm/integrations/index): one card per integration
 * with its status (GET /integrations); each card opens that integration's own screen.
 */
import { onMounted, ref } from 'vue';
import http, { errorMessage } from '../../api/http';

// Integration key (from the API) → its screen.
const routes = { facebook: 'integrations.facebook', 'property-finder': 'integrations.property-finder' };

const data = ref(null);
const loadError = ref('');

function load() {
    loadError.value = '';
    return http.get('/integrations')
        .then((res) => {
            data.value = res.data;
        })
        .catch((e) => {
            loadError.value = errorMessage(e);
        });
}

onMounted(load);
</script>
