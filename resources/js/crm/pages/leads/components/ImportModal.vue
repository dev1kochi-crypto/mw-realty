<template>
    <CrmModal :open="open" content-class="border-0 shadow-lg" bare @close="$emit('close')">
        <template #header="{ close }">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 46px; height: 46px; background: rgba(79, 70, 229, 0.1);">
                        <i class="fas fa-cloud-arrow-up" style="color: var(--portal-primary); font-size: 1.1rem;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0">Import Leads</h5>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Bulk-add leads from a spreadsheet</p>
                    </div>
                </div>
                <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
            </div>
        </template>

        <!-- After a successful upload: the background import's live progress. -->
        <template v-if="showProgress">
            <div class="modal-body pt-3"><ImportProgress :progress="tracker.current.value" @refresh="$emit('refresh')" /></div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary px-4" @click="$emit('close')">{{ working ? 'Close — keep importing' : 'Close' }}</button>
            </div>
        </template>

        <form v-else @submit.prevent="submit">
            <div class="modal-body pt-3">
                <div v-if="error" class="alert alert-danger d-flex gap-2 align-items-start mb-3" role="alert" style="font-size: 0.85rem; border-radius: 10px;">
                    <i class="fas fa-triangle-exclamation mt-1"></i><div>{{ error }}</div>
                </div>
                <label class="form-label fw-semibold">What are you importing?</label>
                <div class="row g-2 mb-3" role="radiogroup">
                    <div class="col-6">
                        <input id="crmImportTemplate" v-model="type" type="radio" class="btn-check" value="template">
                        <label for="crmImportTemplate" class="lead-import-type d-flex gap-2 align-items-start p-3 h-100">
                            <i class="fas fa-table-list mt-1" style="color: var(--portal-primary);"></i>
                            <span>
                                <span class="d-block fw-semibold">Direct import</span>
                                <span class="d-block text-muted" style="font-size: 0.76rem;">Our Excel template, or a file exported from Leads</span>
                            </span>
                        </label>
                    </div>
                    <div class="col-6">
                        <input id="crmImportFacebook" v-model="type" type="radio" class="btn-check" value="facebook">
                        <label for="crmImportFacebook" class="lead-import-type d-flex gap-2 align-items-start p-3 h-100">
                            <i class="fab fa-facebook mt-1" style="color: #1877f2;"></i>
                            <span>
                                <span class="d-block fw-semibold">Facebook leads</span>
                                <span class="d-block text-muted" style="font-size: 0.76rem;">CSV downloaded from Meta Leads Center / Ads Manager</span>
                            </span>
                        </label>
                    </div>
                </div>
                <div v-if="type === 'template'" class="alert alert-info d-flex gap-2 align-items-start mb-3" style="font-size: 0.85rem; border-radius: 10px;">
                    <i class="fas fa-circle-info mt-1"></i>
                    <div>
                        Use the provided Excel import template — or a file exported from Leads — without renaming or removing columns.
                        <a :href="templateUrl" class="fw-semibold">Download the template</a>, fill it in, then upload it below.
                    </div>
                </div>
                <div v-else class="alert alert-info d-flex gap-2 align-items-start mb-3" style="font-size: 0.85rem; border-radius: 10px;">
                    <i class="fas fa-circle-info mt-1"></i>
                    <div>
                        Upload the leads CSV exactly as downloaded from Meta (Leads Center or Ads Manager → Download leads). Each lead's
                        source is its ad set name; campaign, ad and form are saved on the lead.
                        <a :href="facebookSampleUrl" class="fw-semibold">Download a sample file</a> to see the format.
                    </div>
                </div>
                <label class="form-label fw-semibold">File (.xlsx, .xls, .csv)</label>
                <label for="crmImportFile" class="d-flex align-items-center gap-3 p-3 mb-0"
                       :style="{ border: `1.5px dashed ${dragging ? 'var(--portal-primary)' : 'var(--portal-border)'}`, borderRadius: '12px', cursor: 'pointer', background: 'var(--portal-bg)' }"
                       @dragover.prevent="dragging = true" @dragleave="dragging = false" @drop.prevent="onDrop">
                    <span class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 40px; height: 40px; background: var(--portal-surface); border: 1px solid var(--portal-border);">
                        <i class="fas fa-cloud-arrow-up text-muted"></i>
                    </span>
                    <span class="flex-grow-1 overflow-hidden">
                        <span class="d-block fw-semibold text-truncate">{{ file?.name ?? 'Choose a file or drag it here' }}</span>
                        <span class="d-block text-muted" style="font-size: 0.78rem;">.xlsx, .xls or .csv — up to 10 MB</span>
                    </span>
                    <span class="btn btn-sm portal-btn-ghost flex-shrink-0">Browse</span>
                </label>
                <input id="crmImportFile" ref="fileInput" type="file" class="d-none" accept=".xlsx,.xls,.csv,.tsv,.txt" @change="file = $event.target.files[0] ?? null">
                <p class="text-muted mb-0 mt-3" style="font-size: 0.78rem;">
                    Every row is checked (name, email, phone, empty and duplicate rows). Large files import in the background — you'll get a
                    notification and a summary email with the file attached and an <strong>Import Status</strong> column (added, updated or skipped with the reason).
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary px-4" @click="$emit('close')">Cancel</button>
                <button type="submit" class="btn btn-portal-primary px-4" :disabled="!file || uploading">
                    <span v-if="uploading" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    <i v-else class="fas fa-upload me-1"></i> {{ uploading ? 'Checking file…' : 'Upload & Import' }}
                </button>
            </div>
        </form>
    </CrmModal>
</template>

<script setup>
/** Import Leads (resources/views/portal/crm/leads/_lead_import_modal) — POST /leads/imports, then live progress. */
import { computed, ref, watch } from 'vue';
import CrmModal from '../../../components/CrmModal.vue';
import ImportProgress from './ImportProgress.vue';
import http, { errorMessage } from '../../../api/http';
import { isWorking, useLeadImport } from '../../../composables/useLeadImport';

const props = defineProps({ open: { type: Boolean, default: false } });
const emit = defineEmits(['close', 'refresh']);

const tracker = useLeadImport();
const type = ref('template');
const file = ref(null);
const fileInput = ref(null);
const uploading = ref(false);
const error = ref('');
const dragging = ref(false);
const justUploaded = ref(false);

const apiBase = window.CrmConfig.apiBase;
const templateUrl = `${apiBase}/leads/imports/template`;
const facebookSampleUrl = `${apiBase}/leads/imports/facebook-sample`;
const working = computed(() => isWorking(tracker.current.value));
// An import still running (or just uploaded here) — show its progress instead of the form.
const showProgress = computed(() => tracker.current.value && (working.value || justUploaded.value));

watch(() => props.open, (open) => {
    if (!open) return;
    file.value = null;
    error.value = '';
    justUploaded.value = false;
    if (fileInput.value) fileInput.value.value = '';
});

function onDrop(event) {
    dragging.value = false;
    file.value = event.dataTransfer.files[0] ?? null;
}

function submit() {
    const body = new FormData();
    body.append('import_type', type.value);
    body.append('file', file.value);
    uploading.value = true;
    error.value = '';
    http.post('/leads/imports', body)
        .then((res) => {
            justUploaded.value = true;
            tracker.track(res.data.import);
        })
        .catch((e) => {
            error.value = errorMessage(e, 'The file could not be uploaded.');
        })
        .finally(() => {
            uploading.value = false;
        });
}
</script>
