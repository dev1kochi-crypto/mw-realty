<template>
    <CrmModal :open="open" content-class="border-0 shadow-lg" bare @close="$emit('close')">
        <template #header="{ close }">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 46px; height: 46px; background: rgba(20, 184, 166, 0.12);">
                        <i class="fas fa-file-arrow-down" style="color: var(--portal-accent); font-size: 1.1rem;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0">Export Leads</h5>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Download the current list as an Excel file</p>
                    </div>
                </div>
                <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
            </div>
        </template>
        <div class="modal-body pt-3">
            <div class="d-flex align-items-center gap-3 p-3" style="border: 1px solid var(--portal-border); border-radius: 12px; background: var(--portal-bg);">
                <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 40px; height: 40px; background: var(--portal-surface); border: 1px solid var(--portal-border);">
                    <i class="fas fa-users text-muted"></i>
                </div>
                <div>
                    <span class="d-block fw-semibold" style="font-size: 1.05rem;">{{ number(count) }} {{ plural('lead', count) }}</span>
                    <span class="d-block text-muted" style="font-size: 0.8rem;">{{ allMatching ? 'Every lead matching your filters' : 'Selected in the table' }}</span>
                </div>
            </div>
        </div>
        <template #footer>
            <button type="button" class="btn btn-outline-secondary px-4" @click="$emit('close')">Cancel</button>
            <button type="button" class="btn btn-portal-primary px-4" :disabled="downloading" @click="run">
                <span v-if="downloading" class="spinner-border spinner-border-sm me-1"></span><i v-else class="fas fa-download me-1"></i> Download
            </button>
        </template>
    </CrmModal>
</template>

<script setup>
/** Export Leads (resources/views/portal/crm/leads/_lead_export_modal) — POST /leads/export → .xlsx. */
import { ref } from 'vue';
import CrmModal from '../../../components/CrmModal.vue';
import { errorMessage } from '../../../api/http';
import { download } from '../../../utils/download';
import { useToast } from '../../../composables/useToast';
import { number, plural } from '../../../utils/format';

const props = defineProps({
    open: { type: Boolean, default: false },
    count: { type: Number, default: 0 },
    allMatching: { type: Boolean, default: false },
    selection: { type: Function, required: true },
});
const emit = defineEmits(['close']);

const { error } = useToast();
const downloading = ref(false);

function run() {
    downloading.value = true;
    download('post', '/leads/export', props.selection())
        .then(() => emit('close'))
        .catch((e) => error(errorMessage(e, 'The export could not be created.')))
        .finally(() => {
            downloading.value = false;
        });
}
</script>
