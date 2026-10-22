<template>
    <div>
        <div class="st-head">
            <div>
                <h1 class="st-head__title">Raise a Ticket</h1>
                <p class="st-head__sub">Tell us what's going on — pick the issue type so it reaches the right person faster.</p>
            </div>
            <RouterLink :to="{ name: 'contact.index' }" class="btn btn-portal-light btn-sm"><i class="fas fa-arrow-left me-1"></i>My Tickets</RouterLink>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="portal-card p-4">
                    <form @submit.prevent="save">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label fw-semibold" for="st-category">Issue Type <span class="text-danger">*</span></label>
                                <select id="st-category" v-model="form.category" class="form-select" :class="{ 'is-invalid': errors.category }" required>
                                    <option value="" disabled>Select the type of issue</option>
                                    <option v-for="c in meta?.categories ?? []" :key="c.key" :value="c.key">{{ c.label }}</option>
                                </select>
                                <div v-if="errors.category" class="invalid-feedback">{{ errors.category[0] }}</div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold" for="st-priority">Priority</label>
                                <select id="st-priority" v-model="form.priority" class="form-select" :class="{ 'is-invalid': errors.priority }">
                                    <option v-for="p in meta?.priorities ?? []" :key="p.key" :value="p.key">{{ p.label }}</option>
                                </select>
                                <div v-if="errors.priority" class="invalid-feedback">{{ errors.priority[0] }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="st-subject">Subject <span class="text-danger">*</span></label>
                                <input id="st-subject" v-model="form.subject" type="text" class="form-control" :class="{ 'is-invalid': errors.subject }" maxlength="150" required placeholder="A short summary of the issue">
                                <div v-if="errors.subject" class="invalid-feedback">{{ errors.subject[0] }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="st-message">Describe the issue <span class="text-danger">*</span></label>
                                <textarea id="st-message" v-model="form.message" class="form-control" :class="{ 'is-invalid': errors.message }" rows="7" required maxlength="5000" placeholder="What happened, what you expected, and any listing / invoice reference that helps us find it."></textarea>
                                <div v-if="errors.message" class="invalid-feedback">{{ errors.message[0] }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="st-attachment">Attachment <span class="text-muted fw-normal">(optional)</span></label>
                                <input id="st-attachment" ref="fileInput" type="file" class="form-control" :class="{ 'is-invalid': errors.attachment_document }" accept=".jpg,.jpeg,.png,.pdf">
                                <div class="form-text">A screenshot or PDF — JPG, PNG or PDF, up to 4 MB.</div>
                                <div v-if="errors.attachment_document" class="invalid-feedback">{{ errors.attachment_document[0] }}</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-portal-primary" :disabled="saving"><i class="fas fa-paper-plane me-1"></i> Submit Ticket</button>
                            <RouterLink :to="{ name: 'contact.index' }" class="btn btn-portal-light">Cancel</RouterLink>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <ContactCard :contact="meta?.contact" />
            </div>
        </div>
    </div>
</template>

<script setup>
/** Raise a ticket (resources/views/portal/contact/create) — multipart POST /support/tickets. */
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import { useToast } from '../../composables/useToast';
import ContactCard from './ContactCard.vue';

const router = useRouter();
const { success, error: toastError } = useToast();

const meta = ref(null);
const form = reactive({ category: '', priority: 'normal', subject: '', message: '' });
const fileInput = ref(null);
const errors = ref({});
const saving = ref(false);

http.get('/support/meta').then((res) => { meta.value = res.data; }).catch((e) => toastError(errorMessage(e)));

function save() {
    saving.value = true;
    errors.value = {};
    const body = new FormData();
    Object.entries(form).forEach(([key, value]) => body.append(key, value));
    if (fileInput.value.files[0]) body.append('attachment_document', fileInput.value.files[0]);
    http.post('/support/tickets', body)
        .then((res) => {
            success(res.data.message);
            router.push({ name: 'contact.show', params: { id: res.data.id } });
        })
        .catch((e) => {
            if (e.response?.status === 422 && e.response.data.errors) errors.value = e.response.data.errors;
            else toastError(errorMessage(e));
        })
        .finally(() => { saving.value = false; });
}
</script>
