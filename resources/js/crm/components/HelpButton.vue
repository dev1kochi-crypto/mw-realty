<template>
    <button type="button" class="portal-help-btn" :class="{ 'is-new': isNew }" :title="guide ? `How ${guide.title} works` : 'How this page works'"
            aria-label="Help: how this page works" @click="show">
        <i class="fas fa-circle-exclamation"></i>
    </button>

    <HelpTour v-if="open && guide" :guide="guide" @close="open = false" />
</template>

<script setup>
/**
 * The top bar's "!" — the current screen's help guide as a short slide tour (HelpTour).
 * Content: GET /api/crm/help/{topic} (route meta `help`). The very first guide an agent / company
 * sees opens by itself ~2.5s in, once ever (`help_auto_open` on /auth/me → POST /help/seen).
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import http from '../api/http';
import { useSession } from '../composables/useSession';
import HelpTour from './HelpTour.vue';

const props = defineProps({ topic: { type: String, required: true } });

const { user } = useSession();
const open = ref(false);
const guide = ref(null);
const isNew = ref(false);
let autoTimer = null;

function load() {
    if (guide.value) return Promise.resolve();
    return http.get(`/help/${props.topic}`).then((res) => {
        guide.value = res.data;
    });
}

function show() {
    isNew.value = false;
    load().then(() => {
        open.value = true;
    });
}

watch(() => props.topic, () => {
    guide.value = null;
});

onMounted(() => {
    // The account's first time in the CRM: open the tour after a moment, once, ever.
    if (!user.value?.help_auto_open) return;
    user.value.help_auto_open = false;
    isNew.value = true;
    autoTimer = setTimeout(() => {
        if (document.querySelector('.modal.d-block')) return; // don't stack on another open dialog
        show();
        http.post('/help/seen').catch(() => {});
    }, 2500);
});

onBeforeUnmount(() => {
    clearTimeout(autoTimer);
});
</script>
