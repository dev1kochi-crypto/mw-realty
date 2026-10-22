<template>
    <CrmLayout v-if="loaded">
        <!-- Keyed by route so screens shared between routes (Master) start fresh for each. -->
        <RouterView :key="$route.name" />
    </CrmLayout>
    <div v-else-if="failed" class="d-flex flex-column align-items-center justify-content-center vh-100 text-muted">
        <p class="mb-3">The CRM could not be loaded.</p>
        <button type="button" class="btn btn-primary" @click="start">Try again</button>
    </div>
    <div v-else class="d-flex align-items-center justify-content-center vh-100">
        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import CrmLayout from './layouts/CrmLayout.vue';
import { useSession } from './composables/useSession';

const { loaded, load } = useSession();
const failed = ref(false);

function start() {
    failed.value = false;
    load().catch(() => {
        failed.value = true;
    });
}

onMounted(start);
</script>
