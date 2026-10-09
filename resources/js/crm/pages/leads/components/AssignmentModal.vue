<template>
    <CrmModal :open="open" size="lg" content-class="border-0 shadow-lg" bare @close="$emit('close')">
        <template #header>
            <span></span>
        </template>
        <form class="lassign" @submit.prevent="save">
            <div class="lassign__head">
                <span class="lassign__icon" aria-hidden="true"><i class="fas fa-shuffle"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="lassign__title">Lead assignment</div>
                    <div class="lassign__sub">Choose how new agency leads reach your agents. Listings with an assigned agent always send their enquiries to that agent.</div>
                </div>
                <button type="button" class="btn-close align-self-start" aria-label="Close" @click="$emit('close')"></button>
            </div>
            <div class="lassign__body">
                <div class="lassign__col">
                    <div class="lassign__label">Assignment mode</div>
                    <div class="lassign__modes" role="radiogroup">
                        <label v-for="option in modes" :key="option.mode" class="lassign__mode">
                            <input v-model="mode" type="radio" :value="option.mode">
                            <span class="lassign__mode-box">
                                <span class="lassign__mode-icon"><i :class="option.icon"></i></span>
                                <span class="min-w-0">
                                    <span class="lassign__mode-title">{{ option.title }}</span>
                                    <span class="lassign__mode-text">{{ option.text }}</span>
                                </span>
                                <span class="lassign__tick" aria-hidden="true"><i class="fas fa-check"></i></span>
                            </span>
                        </label>
                    </div>
                </div>
                <div class="lassign__col">
                    <div v-if="mode === 'automatic'">
                        <div class="lassign__label">Leads that rotate automatically</div>
                        <div class="lassign__sources" role="group" aria-label="Leads that rotate automatically">
                            <label v-for="source in settings.available_sources" :key="source.key" class="lassign__source">
                                <span class="lassign__source-icon" :class="`lassign__source-icon--${source.key}`" aria-hidden="true"><i :class="sourceMeta[source.key]?.icon"></i></span>
                                <span class="flex-grow-1 min-w-0">
                                    <span class="lassign__source-title">{{ source.label }}</span>
                                    <span class="lassign__source-text">{{ sourceMeta[source.key]?.hint }}</span>
                                </span>
                                <span class="form-check form-switch mb-0">
                                    <input v-model="sources" type="checkbox" class="form-check-input" role="switch" :value="source.key" :aria-label="`Round robin ${source.label}`">
                                </span>
                            </label>
                        </div>
                        <div class="lassign__hint"><i class="fas fa-inbox me-1"></i>Leads switched off wait in <strong>Unassigned</strong> for you.</div>
                    </div>
                    <div v-else class="lassign__manual">
                        <span class="lassign__manual-icon" aria-hidden="true"><i class="fas fa-inbox"></i></span>
                        <div>
                            <div class="fw-semibold">Every new lead waits in Unassigned</div>
                            <div class="small text-muted">Assign each one to an agent from <strong>Leads</strong>, or share them all out at once with <strong>Distribute unassigned</strong> (<i class="fas fa-shuffle"></i>).</div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="error" class="alert alert-danger small mx-4 mb-0">{{ error }}</div>
            <div class="lassign__foot">
                <button type="button" class="btn btn-sm portal-btn-ghost" @click="$emit('close')">Cancel</button>
                <button type="submit" class="btn btn-sm btn-portal-primary" :disabled="!changed || invalid || saving"><i class="fas fa-check me-1"></i>Save changes</button>
            </div>
        </form>
    </CrmModal>
</template>

<script setup>
/** Agency lead assignment (resources/views/portal/agents/_lead_assignment) — PUT /leads/assignment-settings. */
import { computed, ref, watch } from 'vue';
import CrmModal from '../../../components/CrmModal.vue';
import http, { errorMessage } from '../../../api/http';
import { useToast } from '../../../composables/useToast';

const props = defineProps({
    open: { type: Boolean, default: false },
    settings: { type: Object, required: true }, // { mode, sources, available_sources, agent_count }
});
const emit = defineEmits(['close', 'saved']);

const { success } = useToast();
const mode = ref('automatic');
const sources = ref([]);
const saving = ref(false);
const error = ref('');

const sourceMeta = {
    property: { icon: 'fas fa-building', hint: 'Enquiries on listings with no assigned agent' },
    generic: { icon: 'fas fa-envelope-open-text', hint: 'Profile, contact & custom requests' },
    facebook: { icon: 'fab fa-facebook-f', hint: 'Leads from your connected Facebook Pages' },
};

const modes = computed(() => [
    { mode: 'automatic', icon: 'fas fa-rotate', title: 'Automatic', text: `Round robin across ${props.settings.agent_count} active agent${props.settings.agent_count === 1 ? '' : 's'}` },
    { mode: 'manual', icon: 'fas fa-hand-pointer', title: 'Manual', text: 'You assign every new lead yourself' },
]);

const changed = computed(() => mode.value !== props.settings.mode
    || [...sources.value].sort().join() !== [...props.settings.sources].sort().join());
const invalid = computed(() => mode.value === 'automatic' && !sources.value.length);

watch(() => props.open, (open) => {
    if (!open) return;
    mode.value = props.settings.mode;
    sources.value = [...props.settings.sources];
    error.value = '';
});

function save() {
    saving.value = true;
    error.value = '';
    http.put('/leads/assignment-settings', { mode: mode.value, sources: mode.value === 'automatic' ? sources.value : [] })
        .then((res) => {
            success(res.data.message);
            emit('saved', res.data.settings);
        })
        .catch((e) => {
            error.value = errorMessage(e);
        })
        .finally(() => {
            saving.value = false;
        });
}
</script>
