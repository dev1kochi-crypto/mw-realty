<template>
    <CrmModal :open="open" :title="titles[mode]" @close="$emit('close')">
        <form id="followUpForm" novalidate @submit.prevent="save">
            <p v-if="followUp && mode !== 'schedule' && mode !== 'log'" class="small text-muted mb-3">
                <i class="fas fa-calendar-check me-1" aria-hidden="true"></i>{{ followUp.type_label }} · due {{ followUp.due_label }}
                <template v-if="followUp.note"><br>{{ followUp.note }}</template>
            </p>

            <!-- Type (new follow-up / logged activity) -->
            <div v-if="mode === 'schedule' || mode === 'log'" class="mb-3">
                <label class="form-label fw-semibold">{{ mode === 'log' ? 'What did you do?' : 'Type' }}</label>
                <div class="d-flex flex-wrap gap-2" role="radiogroup">
                    <button v-for="(label, key) in TYPES" :key="key" type="button" class="btn btn-sm" :class="form.type === key ? 'btn-portal-primary' : 'portal-btn-ghost'"
                            role="radio" :aria-checked="form.type === key" @click="form.type = key"><i class="fas me-1" :class="TYPE_ICONS[key]" aria-hidden="true"></i>{{ label }}</button>
                </div>
            </div>

            <!-- When (schedule / reschedule) -->
            <div v-if="mode === 'schedule' || mode === 'reschedule'" class="mb-3">
                <DueField v-model="form.due_at" :error="errors.due_at" label="Due" />
            </div>

            <!-- Outcome (log / complete) -->
            <div v-if="mode === 'log' || mode === 'complete'" class="mb-3">
                <label class="form-label fw-semibold" for="followUpOutcome">What happened?</label>
                <textarea id="followUpOutcome" v-model="form.outcome" class="form-control" :class="{ 'is-invalid': errors.outcome }" rows="3" maxlength="2000"
                          placeholder="e.g. Spoke to her — wants a viewing on Saturday. / No answer, left a voicemail."></textarea>
                <div class="invalid-feedback">{{ errors.outcome }}</div>
            </div>

            <!-- Note (schedule / reschedule) or reason (cancel) -->
            <div v-if="mode !== 'log' && mode !== 'complete'" class="mb-3">
                <label class="form-label fw-semibold" for="followUpNote">{{ mode === 'cancel' ? 'Reason (optional)' : 'Note (optional)' }}</label>
                <textarea id="followUpNote" v-model="form.note" class="form-control" :class="{ 'is-invalid': errors.note || errors.reason }" rows="2" maxlength="2000"
                          :placeholder="mode === 'cancel' ? 'e.g. Customer bought elsewhere.' : 'What is this follow-up for?'"></textarea>
                <div class="invalid-feedback">{{ errors.note || errors.reason }}</div>
            </div>

            <!-- Next follow-up in the same step (log / complete) -->
            <div v-if="mode === 'log' || mode === 'complete'" class="border rounded p-3">
                <div class="form-check mb-0">
                    <input id="followUpNext" v-model="form.next" class="form-check-input" type="checkbox">
                    <label class="form-check-label fw-semibold" for="followUpNext">Schedule the next follow-up</label>
                </div>
                <div v-if="form.next" class="mt-3">
                    <div class="d-flex flex-wrap gap-2 mb-3" role="radiogroup" aria-label="Next follow-up type">
                        <button v-for="(label, key) in TYPES" :key="key" type="button" class="btn btn-sm" :class="form.next_type === key ? 'btn-portal-primary' : 'portal-btn-ghost'"
                                role="radio" :aria-checked="form.next_type === key" @click="form.next_type = key"><i class="fas me-1" :class="TYPE_ICONS[key]" aria-hidden="true"></i>{{ label }}</button>
                    </div>
                    <DueField v-model="form.next_due_at" :error="errors.next_due_at || errors.next_type" label="Next due" />
                    <textarea v-model="form.next_note" class="form-control mt-3" rows="2" maxlength="2000" placeholder="Note for the next follow-up (optional)" aria-label="Next follow-up note"></textarea>
                </div>
            </div>

            <div v-if="error" class="alert alert-danger mt-3 mb-0" role="alert">{{ error }}</div>
        </form>
        <template #footer>
            <span v-if="mode === 'schedule' || mode === 'reschedule' || form.next" class="small text-muted me-auto"><i class="far fa-bell me-1" aria-hidden="true"></i>Reminder by notification + email 15 min before.</span>
            <button type="button" class="btn btn-outline-secondary" @click="$emit('close')">Close</button>
            <button type="submit" form="followUpForm" class="btn" :class="mode === 'cancel' ? 'btn-danger' : 'btn-portal-primary'" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>{{ buttons[mode] }}
            </button>
        </template>
    </CrmModal>
</template>

<script setup>
/**
 * Every follow-up action in one dialog — mode: schedule | log (a lead's new one), complete | reschedule | cancel
 * (an existing one). Times are UAE time ("YYYY-MM-DDTHH:mm"), see LeadFollowUpController.
 */
import { h, reactive, ref, watch } from 'vue';
import http, { errorMessage } from '../../../api/http';
import CrmModal from '../../../components/CrmModal.vue';
import { useToast } from '../../../composables/useToast';
import { TYPE_ICONS, TYPES, uaeLocal } from './followUps';

const props = defineProps({
    open: { type: Boolean, default: false },
    mode: { type: String, default: 'schedule' },
    leadId: { type: Number, default: null },
    followUp: { type: Object, default: null },
});
const emit = defineEmits(['close', 'saved']);

const { success } = useToast();
const saving = ref(false);
const error = ref('');
const errors = reactive({});
const form = reactive({});

const titles = { schedule: 'Schedule follow-up', log: 'Log activity', complete: 'Complete follow-up', reschedule: 'Reschedule follow-up', cancel: 'Cancel follow-up' };
const buttons = { schedule: 'Schedule', log: 'Save activity', complete: 'Mark complete', reschedule: 'Reschedule', cancel: 'Cancel follow-up' };

/** Due date/time input (UAE) with quick picks. */
const DueField = (p, { emit: fieldEmit }) => {
    const pick = (hoursAhead, atHour = null) => fieldEmit('update:modelValue', uaeLocal(hoursAhead, atHour));
    return h('div', [
        h('label', { class: 'form-label fw-semibold' }, [p.label, h('span', { class: 'text-muted fw-normal ms-1' }, '(UAE time)')]),
        h('input', {
            type: 'datetime-local', class: ['form-control', { 'is-invalid': p.error }], value: p.modelValue, min: uaeLocal(0),
            'aria-label': `${p.label} (UAE time)`, onInput: (e) => fieldEmit('update:modelValue', e.target.value),
        }),
        h('div', { class: 'invalid-feedback' }, p.error),
        h('div', { class: 'd-flex flex-wrap gap-2 mt-2' }, [
            h('button', { type: 'button', class: 'btn btn-sm btn-light', onClick: () => pick(1) }, 'In 1 hour'),
            h('button', { type: 'button', class: 'btn btn-sm btn-light', onClick: () => pick(24, 10) }, 'Tomorrow 10:00'),
            h('button', { type: 'button', class: 'btn btn-sm btn-light', onClick: () => pick(72, 10) }, 'In 3 days'),
            h('button', { type: 'button', class: 'btn btn-sm btn-light', onClick: () => pick(168, 10) }, 'Next week'),
        ]),
    ]);
};
DueField.props = ['modelValue', 'error', 'label'];
DueField.emits = ['update:modelValue'];

watch(() => props.open, (open) => {
    if (!open) return;
    error.value = '';
    Object.keys(errors).forEach((k) => delete errors[k]);
    Object.keys(form).forEach((k) => delete form[k]);
    Object.assign(form, {
        type: 'call',
        due_at: props.mode === 'reschedule' ? (props.followUp?.due_local ?? uaeLocal(24, 10)) : uaeLocal(24, 10),
        note: props.mode === 'reschedule' ? (props.followUp?.note ?? '') : '',
        outcome: '',
        next: false,
        next_type: props.followUp?.type ?? 'call',
        next_due_at: uaeLocal(72, 10),
        next_note: '',
    });
});

function body() {
    const next = form.next ? { next_type: form.next_type, next_due_at: form.next_due_at, next_note: form.next_note } : {};
    switch (props.mode) {
        case 'schedule': return { mode: 'schedule', type: form.type, due_at: form.due_at, note: form.note };
        case 'log': return { mode: 'log', type: form.type, outcome: form.outcome, ...next };
        case 'complete': return { outcome: form.outcome, ...next };
        case 'reschedule': return { due_at: form.due_at, note: form.note };
        default: return { reason: form.note };
    }
}

function request() {
    const id = props.followUp?.id;
    switch (props.mode) {
        case 'schedule':
        case 'log': return http.post(`/leads/${props.leadId}/follow-ups`, body());
        case 'complete': return http.post(`/leads/follow-ups/${id}/complete`, body());
        case 'reschedule': return http.put(`/leads/follow-ups/${id}`, body());
        default: return http.post(`/leads/follow-ups/${id}/cancel`, body());
    }
}

function save() {
    saving.value = true;
    error.value = '';
    Object.keys(errors).forEach((k) => delete errors[k]);
    request()
        .then((res) => {
            success(res.data.message);
            emit('saved');
            emit('close');
        })
        .catch((e) => {
            const fieldErrors = e.response?.data?.errors ?? e.response?.data?.data?.errors;
            if (fieldErrors) Object.entries(fieldErrors).forEach(([k, v]) => { errors[k] = Array.isArray(v) ? v[0] : v; });
            if (!fieldErrors || !Object.keys(fieldErrors).some((k) => ['due_at', 'outcome', 'note', 'reason', 'next_due_at', 'next_type'].includes(k))) error.value = errorMessage(e);
        })
        .finally(() => {
            saving.value = false;
        });
}
</script>
