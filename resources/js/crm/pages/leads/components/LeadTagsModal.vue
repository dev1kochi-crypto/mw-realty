<template>
    <CrmModal :open="open" size="sm" scrollable content-class="portal-lead-tags-modal" bare @close="$emit('close')">
        <template #header="{ close }">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="portal-lead-view-eyebrow">Lead tags</div>
                    <h5 class="modal-title">{{ lead?.name || 'Manage tags' }}</h5>
                </div>
                <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
            </div>
        </template>
        <div class="modal-body pt-3">
            <p class="text-muted small mb-2">Choose the labels that help your team prioritise this lead.</p>
            <TagMultiSelect v-if="open" v-model="chosen" />
            <div v-if="error" class="alert alert-danger mt-3 mb-0" role="alert">{{ error }}</div>
        </div>
        <template #footer>
            <span class="small text-muted me-auto">{{ chosen.length }} {{ plural('tag', chosen.length) }} selected</span>
            <button type="button" class="btn btn-outline-secondary" @click="$emit('close')">Cancel</button>
            <button type="button" class="btn btn-portal-primary" :disabled="saving" @click="save">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                Save tags
            </button>
        </template>
    </CrmModal>
</template>

<script setup>
/** One lead's tags — PUT /leads/{id}/tags (resources/views/portal/crm/leads/_lead_tags_modal). */
import { ref, watch } from 'vue';
import CrmModal from '../../../components/CrmModal.vue';
import TagMultiSelect from '../../../components/TagMultiSelect.vue';
import http, { errorMessage } from '../../../api/http';
import { useToast } from '../../../composables/useToast';
import { plural } from '../../../utils/format';

const props = defineProps({
    open: { type: Boolean, default: false },
    lead: { type: Object, default: null },
});
const emit = defineEmits(['close', 'saved']);

const { success } = useToast();
const chosen = ref([]);
const saving = ref(false);
const error = ref('');

watch(() => props.open, (open) => {
    if (!open) return;
    chosen.value = [...(props.lead?.tags ?? [])];
    error.value = '';
});

function save() {
    saving.value = true;
    error.value = '';
    http.put(`/leads/${props.lead.id}/tags`, { tags: chosen.value.map((tag) => tag.id) })
        .then((res) => {
            success(res.data.message);
            emit('saved', res.data.tags);
        })
        .catch((e) => {
            error.value = errorMessage(e);
        })
        .finally(() => {
            saving.value = false;
        });
}
</script>
