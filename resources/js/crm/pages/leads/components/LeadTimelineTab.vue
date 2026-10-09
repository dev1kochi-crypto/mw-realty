<template>
    <div class="portal-card p-3 p-md-4">
        <ul class="nav nav-pills portal-lead-page-tabs mb-3" role="tablist">
            <li v-for="tab in tabs" :key="tab.key" class="nav-item" role="presentation">
                <button type="button" class="nav-link" :class="{ active: active === tab.key }" role="tab" @click="active = tab.key">{{ tab.label }} <span class="badge">{{ tab.count }}</span></button>
            </li>
        </ul>

        <ol v-if="active === 'activity'" class="portal-lead-timeline">
            <li v-for="(entry, i) in timeline" :key="i" class="portal-lead-timeline-item" :class="`tone-${entry.tone}`">
                <span class="portal-lead-timeline-icon"><i class="fas" :class="entry.icon" aria-hidden="true"></i></span>
                <div class="portal-lead-timeline-body">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <strong>{{ entry.title }}</strong>
                        <span class="text-muted small">{{ formatDateTime(entry.at) }} · {{ timeAgo(entry.at) }}</span>
                    </div>
                    <div v-if="entry.body" class="portal-lead-timeline-text">{{ entry.body }}</div>
                    <div v-if="entry.author" class="text-muted small mt-1">by {{ entry.author }}</div>
                </div>
            </li>
        </ol>

        <div v-else-if="active === 'notes'">
            <form class="mb-3" novalidate @submit.prevent="addNote">
                <label class="form-label fw-semibold" for="crmLeadNoteBody">Add a note</label>
                <textarea id="crmLeadNoteBody" ref="noteInput" v-model="noteBody" class="form-control" :class="{ 'is-invalid': noteError }" rows="3" maxlength="5000" placeholder="Call summary, follow-up, requirements…"></textarea>
                <div class="invalid-feedback">{{ noteError }}</div>
                <div class="text-end mt-2">
                    <button type="submit" class="btn btn-portal-primary btn-sm" :disabled="savingNote">
                        <span v-if="savingNote" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span><i v-else class="fas fa-plus me-1"></i> Add note
                    </button>
                </div>
            </form>
            <div class="d-grid gap-2">
                <div v-for="note in notes" :key="note.id" class="portal-lead-page-note">
                    <div class="portal-lead-timeline-text">{{ note.body }}</div>
                    <div class="text-muted small mt-1">{{ note.author_name || 'Someone' }} · {{ formatDateTime(note.created_at) }}</div>
                </div>
                <div v-if="!notes.length" class="text-muted small">No notes yet.</div>
            </div>
        </div>

        <div v-else>
            <p class="text-muted small">Every enquiry this person has sent — the first one created the lead; later ones under the same email or phone were added to it.</p>
            <ol class="portal-lead-timeline">
                <li v-for="(enquiry, i) in [...sourceHistory].reverse()" :key="i" class="portal-lead-timeline-item" :class="enquiry.first ? 'tone-accent' : 'tone-warning'">
                    <span class="portal-lead-timeline-icon"><i class="fas" :class="enquiry.first ? 'fa-inbox' : 'fa-envelope-open-text'" aria-hidden="true"></i></span>
                    <div class="portal-lead-timeline-body">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <strong>{{ enquiry.channel }}{{ enquiry.first ? ' · first enquiry' : '' }}</strong>
                            <span class="text-muted small">{{ formatDateTime(enquiry.at) }}</span>
                        </div>
                        <div class="small mt-1 d-flex flex-wrap gap-3 text-muted">
                            <span v-if="enquiry.source"><i class="fas fa-share-nodes me-1"></i>{{ enquiry.source }}</span>
                            <span v-if="enquiry.property"><i class="fas fa-building me-1"></i>{{ enquiry.property.title }}</span>
                            <span v-if="enquiry.contact"><i class="fas fa-address-card me-1"></i>{{ enquiry.contact }}</span>
                        </div>
                        <div v-if="enquiry.message" class="portal-lead-timeline-text mt-2">{{ enquiry.message }}</div>
                        <div v-if="enquiry.page_url" class="small mt-1 text-truncate"><a :href="enquiry.page_url" target="_blank" rel="noopener">{{ enquiry.page_url }}</a></div>
                    </div>
                </li>
            </ol>
        </div>
    </div>
</template>

<script setup>
/** Lead page → Timeline tab: recent activity / notes / source history (resources/views/portal/crm/leads/_show_timeline). */
import { computed, nextTick, ref } from 'vue';
import http, { errorMessage } from '../../../api/http';
import { useToast } from '../../../composables/useToast';
import { formatDateTime, timeAgo } from '../../../utils/format';

const props = defineProps({
    leadId: { type: Number, required: true },
    timeline: { type: Array, required: true },
    notes: { type: Array, required: true },
    sourceHistory: { type: Array, required: true },
});
const emit = defineEmits(['note-added']);

const { success } = useToast();
const active = ref('activity');
const noteBody = ref('');
const noteError = ref('');
const savingNote = ref(false);
const noteInput = ref(null);

const tabs = computed(() => [
    { key: 'activity', label: 'Recent activity', count: props.timeline.length },
    { key: 'notes', label: 'Notes', count: props.notes.length },
    { key: 'sources', label: 'Source history', count: props.sourceHistory.length },
]);

/** The page's "Add Note" action jumps straight here. */
function focusNote() {
    active.value = 'notes';
    nextTick(() => noteInput.value?.focus());
}

function addNote() {
    noteError.value = '';
    if (!noteBody.value.trim()) {
        noteError.value = 'Write a note first.';
        return;
    }
    savingNote.value = true;
    http.post(`/leads/${props.leadId}/notes`, { body: noteBody.value.trim() })
        .then((res) => {
            noteBody.value = '';
            success(res.data.message);
            emit('note-added', res.data.note);
        })
        .catch((e) => {
            noteError.value = errorMessage(e);
        })
        .finally(() => {
            savingNote.value = false;
        });
}

defineExpose({ focusNote });
</script>
