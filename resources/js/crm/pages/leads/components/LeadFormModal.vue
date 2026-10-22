<template>
    <CrmModal :open="open" size="lg" scrollable content-class="lead-form-modal" bare @close="$emit('close')">
        <template #header="{ close }">
            <div class="modal-header lead-form-modal__header">
                <span class="lead-form-modal__icon" aria-hidden="true"><i class="fas" :class="isEdit ? 'fa-user-pen' : 'fa-user-plus'"></i></span>
                <div class="min-w-0">
                    <h5 class="modal-title">{{ isEdit ? 'Edit Lead' : 'Add Lead' }}</h5>
                    <p class="lead-form-modal__subtitle mb-0">{{ isEdit ? 'Update this lead\'s details.' : 'Add an enquiry you received by phone, email or in person.' }}</p>
                </div>
                <button type="button" class="btn-close ms-auto" aria-label="Close" @click="close"></button>
            </div>
        </template>

        <form id="crmLeadForm" class="lead-form-modal__form d-contents" novalidate @submit.prevent="submit">
            <div class="modal-body lead-form-modal__body">
                <div v-if="loadingLead" class="text-center py-5"><span class="spinner-border text-primary"></span></div>
                <template v-else>
                    <div v-if="generalError" class="alert alert-danger" role="alert">{{ generalError }}</div>

                    <section class="lead-form-modal__section">
                        <div class="lead-form-modal__section-title"><i class="fas fa-address-card"></i>Contact details</div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="crmLeadName">Name <span class="text-danger">*</span></label>
                                <div class="lead-form-modal__field">
                                    <i class="fas fa-user" aria-hidden="true"></i>
                                    <input id="crmLeadName" ref="nameInput" v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': errors.name }" maxlength="255" placeholder="Full name" required>
                                    <div class="invalid-feedback">{{ errors.name }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="crmLeadEmail">Email</label>
                                <div class="lead-form-modal__field">
                                    <i class="fas fa-envelope" aria-hidden="true"></i>
                                    <input id="crmLeadEmail" v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': errors.email }" maxlength="255" placeholder="name@example.com">
                                    <div class="invalid-feedback">{{ errors.email }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="crmLeadPhone">Phone</label>
                                <PhoneInput id="crmLeadPhone" v-model="form.phone" v-model:country-code="form.phone_country_code" />
                                <div v-if="errors.phone" class="invalid-feedback d-block">{{ errors.phone }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="crmLeadMessage">Message</label>
                                <textarea id="crmLeadMessage" v-model="form.message" class="form-control" rows="3" placeholder="What is the lead looking for? Budget, area, timeline…"></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="lead-form-modal__section">
                        <div class="lead-form-modal__section-title"><i class="fas fa-sliders"></i>Lead details</div>
                        <div class="row g-3">
                            <div v-if="isAdmin" class="col-sm-6">
                                <label class="form-label">Owner</label>
                                <RemoteSelect v-model="form.owner_id" endpoint="/leads/owner-options" :initial="ownerInitial" placeholder="My own leads (Admin)" />
                            </div>
                            <!-- Source is chosen when a lead is added by hand; fixed once it exists. -->
                            <div v-if="!isEdit" class="col-sm-6">
                                <label class="form-label">Source</label>
                                <RemoteSelect v-model="form.source_id" endpoint="/leads/options/sources" placeholder="Auto — set from where the lead came in" />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Stage</label>
                                <div class="d-flex gap-2">
                                    <div class="flex-grow-1 min-w-0">
                                        <RemoteSelect :key="stageKey" v-model="form.stage_id" endpoint="/leads/options/stages" :initial="stageInitial" placeholder="Default stage (e.g. New)" />
                                    </div>
                                    <button type="button" class="btn portal-btn-ghost lead-form-modal__add-stage" title="Add a stage" @click="addingStage = !addingStage">
                                        <i class="fas fa-plus"></i><span class="d-none d-md-inline ms-1">Stage</span>
                                    </button>
                                </div>
                                <div v-if="addingStage" class="d-flex gap-2 mt-2">
                                    <input v-model="newStage" type="text" class="form-control form-control-sm" maxlength="100" placeholder="New stage name" @keydown.enter.prevent="createStage">
                                    <button type="button" class="btn btn-sm btn-portal-primary text-nowrap" :disabled="!newStage.trim()" @click="createStage">Add</button>
                                </div>
                            </div>
                            <div v-if="isEdit" class="col-sm-6">
                                <label class="form-label" for="crmLeadStatus">Status</label>
                                <div class="lead-form-modal__field">
                                    <i class="fas fa-toggle-on" aria-hidden="true"></i>
                                    <select id="crmLeadStatus" v-model="form.status" class="form-select">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Tags</label>
                            <TagMultiSelect v-model="form.tags" />
                        </div>
                    </section>
                </template>
            </div>
        </form>

        <template #footer>
            <span class="lead-form-modal__hint me-auto"><span class="text-danger">*</span> Required</span>
            <button type="button" class="btn portal-btn-ghost" @click="$emit('close')">Cancel</button>
            <button type="submit" form="crmLeadForm" class="btn btn-portal-primary px-4" :disabled="saving || loadingLead">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                {{ isEdit ? 'Save Changes' : 'Create Lead' }}
            </button>
        </template>
    </CrmModal>
</template>

<script setup>
/** Add Lead / Edit Lead — POST /leads, PUT /leads/{id} (resources/views/portal/crm/leads/_lead_form_modal). */
import { computed, nextTick, reactive, ref, watch } from 'vue';
import CrmModal from '../../../components/CrmModal.vue';
import RemoteSelect from '../../../components/RemoteSelect.vue';
import TagMultiSelect from '../../../components/TagMultiSelect.vue';
import PhoneInput from '../../../../components/PhoneInput.vue';
import http, { errorMessage } from '../../../api/http';
import { useToast } from '../../../composables/useToast';

const props = defineProps({
    open: { type: Boolean, default: false },
    leadId: { type: Number, default: null }, // null = Add
    isAdmin: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'saved']);

const { success, error: toastError } = useToast();
const isEdit = computed(() => !!props.leadId);
const blank = () => ({ name: '', email: '', phone: '', phone_country_code: '+971', message: '', status: 'active', stage_id: null, source_id: null, owner_id: null, tags: [] });
const form = reactive(blank());
const errors = reactive({});
const generalError = ref('');
const saving = ref(false);
const loadingLead = ref(false);
const stageInitial = ref(null);
const ownerInitial = ref(null);
const stageKey = ref(0);
const addingStage = ref(false);
const newStage = ref('');
const nameInput = ref(null);

function reset() {
    Object.assign(form, blank());
    Object.keys(errors).forEach((key) => delete errors[key]);
    generalError.value = '';
    stageInitial.value = null;
    ownerInitial.value = null;
    addingStage.value = false;
    newStage.value = '';
}

watch(() => props.open, (open) => {
    if (!open) return;
    reset();
    if (!isEdit.value) {
        nextTick(() => nameInput.value?.focus());
        return;
    }
    loadingLead.value = true;
    http.get(`/leads/${props.leadId}`)
        .then((res) => {
            const lead = res.data.data.lead;
            Object.assign(form, {
                name: lead.name ?? '',
                email: lead.email ?? '',
                phone: lead.phone ?? '',
                phone_country_code: lead.phone_country_code || '+971',
                message: lead.message ?? '',
                status: lead.status ?? 'active',
                stage_id: lead.stage?.id ?? null,
                owner_id: lead.owner?.id ?? null,
                tags: lead.tags,
            });
            stageInitial.value = lead.stage;
            ownerInitial.value = lead.owner ? { id: lead.owner.id, name: lead.owner.name } : null;
            stageKey.value++;
        })
        .catch((e) => {
            generalError.value = errorMessage(e, 'This lead could not be loaded.');
        })
        .finally(() => {
            loadingLead.value = false;
        });
});

/** "+ Stage": a new stage for whichever owner this lead belongs to, selected straight away. */
function createStage() {
    const name = newStage.value.trim();
    if (!name) return;
    http.post('/master/stages', { name, owner_id: props.isAdmin ? form.owner_id || undefined : undefined })
        .then((res) => {
            form.stage_id = res.data.stage.id;
            stageInitial.value = res.data.stage;
            stageKey.value++;
            addingStage.value = false;
            newStage.value = '';
            success('Stage added.');
        })
        .catch((e) => toastError(errorMessage(e, 'Could not add this stage.')));
}

function submit() {
    Object.keys(errors).forEach((key) => delete errors[key]);
    generalError.value = '';
    if (!form.name.trim()) {
        errors.name = 'Enter the lead\'s name.';
        return;
    }

    const body = {
        name: form.name.trim(),
        email: form.email || null,
        phone: form.phone || null,
        phone_country_code: form.phone ? form.phone_country_code : null,
        message: form.message || null,
        stage_id: form.stage_id || null,
        tags: form.tags.map((tag) => tag.id),
    };
    if (isEdit.value) body.status = form.status;
    else body.source_id = form.source_id || null;
    if (props.isAdmin && form.owner_id) body.owner_id = form.owner_id;

    saving.value = true;
    (isEdit.value ? http.put(`/leads/${props.leadId}`, body) : http.post('/leads', body))
        .then((res) => {
            success(res.data.message);
            emit('saved', res.data);
        })
        .catch((e) => {
            const fieldErrors = e.response?.data?.errors;
            if (fieldErrors) {
                Object.entries(fieldErrors).forEach(([field, messages]) => { errors[field] = messages[0]; });
                const shown = ['name', 'email', 'phone'];
                const other = Object.keys(fieldErrors).filter((field) => !shown.includes(field));
                if (other.length) generalError.value = fieldErrors[other[0]][0];
            } else {
                generalError.value = errorMessage(e);
            }
        })
        .finally(() => {
            saving.value = false;
        });
}
</script>

<style>
/* The form wraps body + footer content without adding a box of its own. */
.d-contents { display: contents; }
</style>
