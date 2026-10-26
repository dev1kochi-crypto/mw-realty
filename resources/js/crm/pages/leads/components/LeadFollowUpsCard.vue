<template>
    <section class="portal-lp-card">
        <header class="portal-lp-card-head">
            <h2>
                <span class="portal-lp-card-icon is-warning"><i class="fas fa-calendar-check"></i></span>Follow-ups
                <span v-if="data.open.length" class="portal-lp-count">{{ data.open.length }}</span>
            </h2>
            <div class="d-flex gap-1">
                <button type="button" class="portal-lp-card-action" title="Log an activity already done" aria-label="Log activity" @click="openModal('log')"><i class="fas fa-clipboard-check"></i></button>
                <button type="button" class="portal-lp-card-add" title="Schedule a follow-up" aria-label="Schedule follow-up" @click="openModal('schedule')"><i class="fas fa-plus"></i></button>
            </div>
        </header>
        <div class="portal-lp-card-body">
            <div v-if="loading && !loaded" class="text-center py-2"><span class="spinner-border spinner-border-sm text-primary"></span></div>
            <template v-else>
                <!-- Upcoming / overdue — still to do -->
                <div class="portal-lp-label mb-2">To do</div>
                <div class="d-grid gap-2">
                    <div v-for="f in data.open" :key="f.id" class="portal-lp-row align-items-start">
                        <span class="portal-lp-row-icon" :class="f.is_overdue ? 'is-danger' : 'is-warning'"><i class="fas" :class="TYPE_ICONS[f.type]"></i></span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="portal-lp-row-title">
                                {{ f.type_label }}
                                <span v-if="f.is_overdue" class="badge bg-danger ms-1">Overdue</span>
                            </div>
                            <div class="portal-lp-row-sub">Due {{ f.due_label }}</div>
                            <div v-if="f.note" class="small mt-1">{{ f.note }}</div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-success" @click="openModal('complete', f)"><i class="fas fa-check me-1"></i>Complete</button>
                                <button type="button" class="btn btn-sm portal-btn-ghost" @click="openModal('reschedule', f)"><i class="far fa-clock me-1"></i>Reschedule</button>
                                <button type="button" class="btn btn-sm portal-btn-ghost text-danger" @click="openModal('cancel', f)">Cancel</button>
                            </div>
                        </div>
                    </div>
                    <div v-if="!data.open.length" class="portal-lp-empty"><i class="far fa-calendar" aria-hidden="true"></i>Nothing scheduled.</div>
                </div>

                <!-- Done — completed activity and cancelled follow-ups, kept apart from what's still to do -->
                <details v-if="data.done.length" class="portal-lp-history mt-3" open>
                    <summary><i class="fas fa-clock-rotate-left me-1" aria-hidden="true"></i>Done ({{ data.done.length }})</summary>
                    <ul class="list-unstyled small mt-2 mb-0 d-grid gap-2">
                        <li v-for="f in data.done" :key="f.id">
                            <i class="fas me-1" :class="[TYPE_ICONS[f.type], f.status === 'cancelled' ? 'text-muted' : 'text-success']" aria-hidden="true"></i>
                            <strong>{{ f.type_label }}</strong>
                            <span class="badge ms-1" :class="f.status === 'cancelled' ? 'bg-secondary' : 'bg-success'">{{ f.status === 'cancelled' ? 'Cancelled' : 'Done' }}</span>
                            <span class="text-muted"> · {{ f.done_label }}<template v-if="f.completed_by_name"> · {{ f.completed_by_name }}</template></span>
                            <div v-if="f.outcome" class="mt-1">{{ f.outcome }}</div>
                        </li>
                    </ul>
                </details>

                <div v-if="data.responsible" class="small text-muted mt-3"><i class="far fa-bell me-1" aria-hidden="true"></i>Reminders go to {{ data.responsible }} (notification + email).</div>
            </template>
        </div>

        <FollowUpModal :open="!!modal" :mode="modal || 'schedule'" :lead-id="leadId" :follow-up="selected" @close="modal = null" @saved="changed" />
    </section>
</template>

<script setup>
/** Lead screen → Follow-ups card: what's still to do (complete / reschedule / cancel) and what's done. GET /leads/{id}/follow-ups. */
import { onMounted, ref, watch } from 'vue';
import http, { errorMessage } from '../../../api/http';
import { useToast } from '../../../composables/useToast';
import FollowUpModal from './FollowUpModal.vue';
import { TYPE_ICONS } from './followUps';

const props = defineProps({ leadId: { type: Number, required: true } });
const emit = defineEmits(['changed']);

const { error: toastError } = useToast();
const data = ref({ open: [], done: [], responsible: null });
const loading = ref(false);
const loaded = ref(false);
const modal = ref(null);
const selected = ref(null);

function load() {
    loading.value = true;
    return http.get(`/leads/${props.leadId}/follow-ups`)
        .then((res) => {
            data.value = res.data;
            loaded.value = true;
        })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => {
            loading.value = false;
        });
}

function openModal(mode, followUp = null) {
    selected.value = followUp;
    modal.value = mode;
}

/** After any change: refresh this card, and let the page refresh its timeline. */
function changed() {
    load();
    emit('changed');
}

onMounted(load);
watch(() => props.leadId, () => {
    loaded.value = false;
    load();
});

defineExpose({ openModal });
</script>
