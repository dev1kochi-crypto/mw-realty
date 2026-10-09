<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="portal-section-title mb-0">Add Agent</div>
            <RouterLink :to="{ name: 'agents.index' }" class="portal-btn-ghost btn btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</RouterLink>
        </div>

        <div v-if="duplicate" class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><i class="fas fa-user-check me-2"></i><strong>{{ duplicate.name }}</strong> — {{ duplicate.message }}</div>
            <button v-if="duplicate.can_invite" type="button" class="btn btn-sm btn-portal-primary" :disabled="inviting" @click="invite(duplicate.identifier)"><i class="fas fa-paper-plane me-1"></i> Send agency invitation</button>
        </div>

        <!-- Existing agents (e.g. independent agents already on MW Realty) are invited, never re-created. -->
        <div class="portal-card p-4 mb-3" style="border:1px solid #d9e3f1;background:linear-gradient(135deg,#f4f7fc,#fff 72%);">
            <div class="d-flex align-items-start gap-3 mb-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white flex-shrink-0" style="width:44px;height:44px;background:#203f68"><i class="fas fa-paper-plane"></i></span>
                <div><div class="fw-bold mb-1">Invite an existing agent</div>
                <div class="portal-muted small">Find them by email, mobile number or agent ID. Their account, listings and history stay with them. After they accept and Super Admin approves, they join your agency.</div></div>
            </div>
            <form class="d-flex gap-2 flex-wrap align-items-start" @submit.prevent="invite(identifier)">
                <input v-model="identifier" type="text" class="form-control" :class="{ 'is-invalid': inviteError }" style="max-width: 400px;" placeholder="Email, mobile number, or agent ID" required>
                <button type="submit" class="btn btn-portal-primary" :disabled="inviting"><i class="fas fa-paper-plane me-1"></i> Send Invitation</button>
                <div v-if="inviteError" class="invalid-feedback d-block w-100">{{ inviteError }}</div>
            </form>
        </div>

        <div class="portal-card p-4">
            <div class="fw-bold mb-1">Add a new agent</div>
            <div class="portal-muted small mb-3">Creates an agent account that becomes active after Super Admin approval. The agent will receive a secure email link to set their password.</div>
            <div v-if="errorList.length" class="alert alert-danger">
                <ul class="mb-0">
                    <li v-for="(message, i) in errorList" :key="i">{{ message }}</li>
                </ul>
            </div>

            <form ref="formEl" @submit.prevent="save">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                        <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': errors.name }" required>
                        <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Email / Login Username <span class="text-danger">*</span></label>
                        <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': errors.email }" required>
                        <div v-if="errors.email" class="invalid-feedback">{{ errors.email[0] }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Phone <span class="text-danger">*</span></label>
                        <input v-model="form.phone" type="text" class="form-control" :class="{ 'is-invalid': errors.phone }" required>
                        <div v-if="errors.phone" class="invalid-feedback">{{ errors.phone[0] }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">WhatsApp Number</label>
                        <input v-model="form.whatsapp_number" type="text" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Years of Experience</label>
                        <input v-model="form.years_of_experience" type="number" min="0" max="80" class="form-control">
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Account Status</label>
                        <input type="text" class="form-control" value="Pending Super Admin approval" disabled>
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <div class="small text-muted pb-2">The email address is the agent's username. For security, you don't set or receive their password; after approval, the agent sets one using a single-use link sent to their email.</div>
                    </div>
                </div>

                <div class="border-top mt-4 pt-4">
                    <h6 class="fw-bold mb-3">Identity &amp; broker details <span class="text-muted fw-normal">(optional)</span></h6>
                    <div class="row g-3">
                        <div v-for="[field, label, type] in identityFields" :key="field" class="col-md-6">
                            <label class="form-label fw-bold">{{ label }}</label>
                            <input v-if="type === 'file'" :name="field" type="file" class="form-control" :class="{ 'is-invalid': errors[field] }" accept=".jpg,.jpeg,.png,.pdf">
                            <input v-else v-model="form[field]" :type="type" class="form-control" :class="{ 'is-invalid': errors[field] }">
                            <div v-if="errors[field]" class="invalid-feedback">{{ errors[field][0] }}</div>
                            <small v-if="type === 'file'" class="text-muted">JPG, PNG or PDF; max 4 MB.</small>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <button type="submit" class="btn btn-portal-primary" :disabled="saving"><i class="fas fa-save me-1"></i> Save Agent</button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
/**
 * Add / Invite Agent (resources/views/portal/agents/create). Invite an existing agent by email /
 * mobile / agent ID, or add a brand-new one (multipart, pending Super Admin approval). An email or
 * mobile that already exists comes back as `duplicate`, offering an invitation instead.
 */
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import { useToast } from '../../composables/useToast';

const router = useRouter();
const { success, error: toastError } = useToast();

const identityFields = [
    ['nationality', 'Nationality', 'text'], ['emirates_id_no', 'Emirates ID No.', 'text'],
    ['emirates_id_document', 'Emirates ID Copy', 'file'], ['passport_no', 'Passport No.', 'text'],
    ['passport_document', 'Passport Copy', 'file'], ['passport_expiry', 'Passport Expiry', 'date'],
    ['brn_number', 'BRN (Broker Registration No.)', 'text'], ['rera_card_document', 'RERA Broker Card Copy', 'file'],
    ['rera_certificate_document', 'RERA Registration Certificate Copy', 'file'], ['trade_license_no', 'Trade License No.', 'text'],
    ['trade_license_document', 'Trade License Copy', 'file'], ['trade_license_expiry', 'Trade License Expiry', 'date'],
    ['trn_number', 'TRN (VAT No.)', 'text'], ['trn_expiry', 'TRN Expiry', 'date'],
];

const formEl = ref(null);
const form = reactive(Object.fromEntries([
    'name', 'email', 'phone', 'whatsapp_number', 'years_of_experience',
    ...identityFields.filter(([, , type]) => type !== 'file').map(([field]) => field),
].map((field) => [field, ''])));
const errors = ref({});
const errorList = computed(() => Object.values(errors.value).flat());
const saving = ref(false);
const duplicate = ref(null);
const identifier = ref('');
const inviteError = ref('');
const inviting = ref(false);

// Plan slots used up → back to the roster with the reason, like the old create page.
http.get('/agents/create')
    .then((res) => {
        if (!res.data.can_add) {
            toastError(res.data.message);
            router.replace({ name: 'agents.index' });
        }
    })
    .catch((e) => {
        toastError(errorMessage(e));
        router.replace({ name: 'agents.index' });
    });

function invite(value) {
    inviting.value = true;
    inviteError.value = '';
    http.post('/agents/invite', { identifier: value })
        .then((res) => {
            success(res.data.message);
            router.push({ name: 'agents.index', query: { tab: 'pending' } });
        })
        .catch((e) => {
            identifier.value = value;
            inviteError.value = e.response?.data?.errors?.identifier?.[0] || errorMessage(e);
        })
        .finally(() => { inviting.value = false; });
}

function save() {
    saving.value = true;
    errors.value = {};
    duplicate.value = null;
    const body = new FormData();
    Object.entries(form).forEach(([key, value]) => {
        if (value !== '' && value !== null) body.append(key, value);
    });
    formEl.value.querySelectorAll('input[type=file]').forEach((input) => {
        if (input.files[0]) body.append(input.name, input.files[0]);
    });
    http.post('/agents', body)
        .then((res) => {
            success(res.data.message);
            router.push({ name: 'agents.index', query: { tab: 'pending' } });
        })
        .catch((e) => {
            if (e.response?.status === 409) {
                duplicate.value = e.response.data.duplicate;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else if (e.response?.status === 422) {
                errors.value = e.response.data.errors || {};
            } else {
                toastError(errorMessage(e));
            }
        })
        .finally(() => { saving.value = false; });
}
</script>
