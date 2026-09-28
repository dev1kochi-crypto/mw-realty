<script setup>
import { computed, reactive, ref } from 'vue';
import { useRecaptcha } from '../composables/useRecaptcha';
import { useStaticText } from '../composables/useStaticText';

// The job application form — on a vacancy page (`career` = its slug) and, with no `career`,
// as the "General Application" form at the bottom of /careers. POST /api/careers/apply
// (Api\CareerController::apply) saves it to the admin's Careers > Candidates.
const props = defineProps({
    career: { type: String, default: null },
    idPrefix: { type: String, default: 'career-apply' },
});

const { getRecaptchaToken } = useRecaptcha();
const { t } = useStaticText();

const MAX_FILE_BYTES = 5 * 1024 * 1024;
const ACCEPTED = ['pdf', 'doc', 'docx'];

const form = reactive({ name: '', email: '', phone: '', country: '', experience: '', designation: '', additional_information: '', privacy: false });
const attachment = ref(null);
const fileInput = ref(null);
const dragging = ref(false);
const submitting = ref(false);
const feedback = ref(null);
const errors = ref({});

const experienceOptions = computed(() => [
    { value: '0-1', label: t('careers.form.experience_0_1', 'Less than 1 year') },
    { value: '1-3', label: t('careers.form.experience_1_3', '1 – 3 years') },
    { value: '3-5', label: t('careers.form.experience_3_5', '3 – 5 years') },
    { value: '5-10', label: t('careers.form.experience_5_10', '5 – 10 years') },
    { value: '10+', label: t('careers.form.experience_10_plus', '10+ years') },
]);

const fileLabel = computed(() => {
    if (!attachment.value) return null;
    const kb = attachment.value.size / 1024;
    return `${attachment.value.name} · ${kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(kb))} KB`}`;
});

function fieldId(name) {
    return `${props.idPrefix}-${name}`;
}

function setFile(file) {
    const { attachment: _drop, ...rest } = errors.value;
    errors.value = rest;
    if (!file) {
        attachment.value = null;
        return;
    }
    const extension = file.name.split('.').pop().toLowerCase();
    if (!ACCEPTED.includes(extension)) {
        attachment.value = null;
        errors.value = { ...errors.value, attachment: t('careers.form.cv_type_error', 'Your CV must be a PDF or Word document.') };
        return;
    }
    if (file.size > MAX_FILE_BYTES) {
        attachment.value = null;
        errors.value = { ...errors.value, attachment: t('careers.form.cv_size_error', 'Your CV must be 5 MB or smaller.') };
        return;
    }
    attachment.value = file;
}

function onFileChange(event) {
    setFile(event.target.files?.[0] || null);
}

function onDrop(event) {
    dragging.value = false;
    setFile(event.dataTransfer?.files?.[0] || null);
}

function clearFile() {
    attachment.value = null;
    if (fileInput.value) fileInput.value.value = '';
}

function reset() {
    Object.assign(form, { name: '', email: '', phone: '', country: '', experience: '', designation: '', additional_information: '', privacy: false });
    clearFile();
}

async function handleSubmit() {
    if (submitting.value) return;
    feedback.value = null;
    errors.value = {};

    if (!attachment.value) {
        errors.value = { attachment: t('careers.form.cv_required', 'Please attach your CV.') };
        return;
    }

    submitting.value = true;
    try {
        const payload = new FormData();
        Object.entries(form).forEach(([key, value]) => {
            if (key === 'privacy') payload.append(key, value ? '1' : '0');
            else if (value) payload.append(key, value);
        });
        if (props.career) payload.append('career', props.career);
        payload.append('attachment', attachment.value);
        const token = await getRecaptchaToken('career_apply');
        if (token) payload.append('recaptcha_token', token);

        const { data } = await window.axios.post('/api/careers/apply', payload);
        feedback.value = { type: 'success', text: data.message };
        reset();
    } catch (error) {
        const fieldErrors = error.response?.data?.errors || {};
        errors.value = Object.fromEntries(Object.entries(fieldErrors).map(([key, messages]) => [key, messages[0]]));
        feedback.value = { type: 'error', text: error.response?.data?.message || t('careers.form.generic_error', 'Something went wrong. Please try again.') };
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <form class="mw-career-form" novalidate @submit.prevent="handleSubmit">
        <div class="mw-career-form__grid">
            <div class="mw-page-contact__field" :class="{ 'has-error': errors.name }">
                <label :for="fieldId('name')">{{ t('careers.form.name_label', 'Full Name') }} <span aria-hidden="true">*</span></label>
                <input :id="fieldId('name')" v-model="form.name" type="text" autocomplete="name" :placeholder="t('careers.form.name_placeholder', 'Your full name')" required>
                <small v-if="errors.name" class="mw-career-form__error">{{ errors.name }}</small>
            </div>
            <div class="mw-page-contact__field" :class="{ 'has-error': errors.email }">
                <label :for="fieldId('email')">{{ t('careers.form.email_label', 'Email Address') }} <span aria-hidden="true">*</span></label>
                <input :id="fieldId('email')" v-model="form.email" type="email" autocomplete="email" :placeholder="t('careers.form.email_placeholder', 'you@example.com')" required>
                <small v-if="errors.email" class="mw-career-form__error">{{ errors.email }}</small>
            </div>
            <div class="mw-page-contact__field" :class="{ 'has-error': errors.phone }">
                <label :for="fieldId('phone')">{{ t('careers.form.phone_label', 'Phone Number') }} <span aria-hidden="true">*</span></label>
                <input :id="fieldId('phone')" v-model="form.phone" type="tel" autocomplete="tel" :placeholder="t('careers.form.phone_placeholder', '+971 50 000 0000')" required>
                <small v-if="errors.phone" class="mw-career-form__error">{{ errors.phone }}</small>
            </div>
            <div class="mw-page-contact__field">
                <label :for="fieldId('country')">{{ t('careers.form.country_label', 'Country of Residence') }}</label>
                <input :id="fieldId('country')" v-model="form.country" type="text" autocomplete="country-name" :placeholder="t('careers.form.country_placeholder', 'e.g. United Arab Emirates')">
            </div>
            <div class="mw-page-contact__field">
                <label :for="fieldId('designation')">{{ t('careers.form.designation_label', 'Current Job Title') }}</label>
                <input :id="fieldId('designation')" v-model="form.designation" type="text" autocomplete="organization-title" :placeholder="t('careers.form.designation_placeholder', 'e.g. Property Consultant')">
            </div>
            <div class="mw-page-contact__field">
                <label :for="fieldId('experience')">{{ t('careers.form.experience_label', 'Years of Experience') }}</label>
                <select :id="fieldId('experience')" v-model="form.experience" class="mw-career-form__select">
                    <option value="">{{ t('careers.form.experience_placeholder', 'Select') }}</option>
                    <option v-for="opt in experienceOptions" :key="opt.value" :value="opt.label">{{ opt.label }}</option>
                </select>
            </div>
        </div>

        <div class="mw-page-contact__field">
            <label :for="fieldId('message')">{{ t('careers.form.message_label', 'Why would you be a great fit?') }}</label>
            <textarea :id="fieldId('message')" v-model="form.additional_information" rows="4" :placeholder="t('careers.form.message_placeholder', 'Tell us a little about yourself (optional)')"></textarea>
        </div>

        <div class="mw-page-contact__field" :class="{ 'has-error': errors.attachment }">
            <span class="mw-career-form__label">{{ t('careers.form.cv_label', 'Upload CV') }} <span aria-hidden="true">*</span></span>
            <label
                class="mw-career-form__drop"
                :class="{ 'is-dragging': dragging, 'has-file': attachment }"
                :for="fieldId('cv')"
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop"
            >
                <input :id="fieldId('cv')" ref="fileInput" type="file" accept=".pdf,.doc,.docx" class="mw-career-form__input" @change="onFileChange">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 17v-6M9.5 13.5 12 11l2.5 2.5"/></svg>
                <span v-if="fileLabel" class="mw-career-form__file">{{ fileLabel }}</span>
                <span v-else class="mw-career-form__drop-text">
                    <strong>{{ t('careers.form.cv_cta', 'Click to upload') }}</strong> {{ t('careers.form.cv_or_drag', 'or drag and drop') }}
                    <small>{{ t('careers.form.cv_hint', 'PDF, DOC or DOCX — max 5 MB') }}</small>
                </span>
            </label>
            <button v-if="attachment" type="button" class="mw-career-form__remove" @click="clearFile">{{ t('careers.form.cv_remove', 'Remove file') }}</button>
            <small v-if="errors.attachment" class="mw-career-form__error">{{ errors.attachment }}</small>
        </div>

        <label class="mw-career-form__consent" :class="{ 'has-error': errors.privacy }">
            <input v-model="form.privacy" type="checkbox">
            <span>{{ t('careers.form.privacy_prefix', 'I agree to the') }} <router-link to="/privacy-policy">{{ t('careers.form.privacy_link', 'Privacy Policy') }}</router-link> {{ t('careers.form.privacy_suffix', 'and consent to MW Realty storing my details for recruitment.') }}</span>
        </label>
        <small v-if="errors.privacy" class="mw-career-form__error">{{ errors.privacy }}</small>

        <p v-if="feedback" class="mw-form-feedback" :class="`mw-form-feedback--${feedback.type}`" role="status">{{ feedback.text }}</p>
        <button type="submit" class="mw-btn mw-btn--gradient mw-career-form__submit" :disabled="submitting">
            {{ submitting ? t('careers.form.submitting', 'Submitting…') : t('careers.form.submit', 'Submit Application') }}
        </button>
    </form>
</template>
