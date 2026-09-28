<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useRecaptcha } from '../composables/useRecaptcha';
import { useStaticText } from '../composables/useStaticText';

const props = defineProps({
    profileType: { type: String, required: true },
    profileSlug: { type: String, required: true },
});

const { getRecaptchaToken } = useRecaptcha();
const { t } = useStaticText();
const router = useRouter();
const drawerId = 'profile-request-' + props.profileType + '-drawer';
const drawerTitleId = drawerId + '-title';
const submitting = ref(false);
const feedback = ref(null);
const form = reactive({
    first_name: '', last_name: '', email: '', phone: '', property_category: '', specification: '',
    price_range: '', area: '', preferred_location: '', additional_details: '', move_in_timeline: '',
    furnishing_status: '', whatsapp_consent: false,
});

async function submit() {
    if (submitting.value) return;
    submitting.value = true;
    feedback.value = null;
    try {
        const recaptcha_token = await getRecaptchaToken('profile_property_request');
        await window.axios.post('/leads/profile-request', {
            ...form,
            profile_type: props.profileType,
            profile_slug: props.profileSlug,
            recaptcha_token,
        });
        closeDrawer();
        router.push({ path: '/thank-you', state: { thankYou: { type: 'request', to: props.profileType, name: form.first_name.trim() } } });
    } catch (error) {
        feedback.value = { type: 'error', text: error.response?.data?.message || t('custom_request.generic_error') };
        submitting.value = false;
    }
}

// The drawer is a Bootstrap offcanvas living outside Vue's page swap — close it (and drop its
// backdrop / body scroll-lock) before navigating, or they'd linger over the thank-you page.
function closeDrawer() {
    const el = document.getElementById(drawerId);
    window.bootstrap?.Offcanvas.getInstance(el)?.hide();
    document.querySelectorAll('.offcanvas-backdrop').forEach((node) => node.remove());
    document.body.classList.remove('offcanvas-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
}
</script>

<template>
    <button type="button" class="mw-profile-request__trigger" data-bs-toggle="offcanvas" :data-bs-target="'#' + drawerId" :aria-controls="drawerId">
        {{ t('custom_request.title') }}
    </button>

    <div class="offcanvas offcanvas-end mw-profile-request__drawer" tabindex="-1" :id="drawerId" :aria-labelledby="drawerTitleId">
        <div class="offcanvas-header mw-profile-request__drawer-head">
            <h2 class="offcanvas-title" :id="drawerTitleId">{{ t('custom_request.title') }}</h2>
            <button type="button" class="mw-profile-request__close" data-bs-dismiss="offcanvas" :aria-label="t('custom_request.close_aria')">&times;</button>
        </div>
        <div class="offcanvas-body mw-profile-request__drawer-body">
            <p class="mw-profile-request__drawer-subtitle">{{ t('custom_request.subtitle') }}</p>
        <form class="quote-modal__form crm-modal__form mw-profile-request__form" @submit.prevent="submit">
            <div class="quote-modal__fields">
                <p v-if="feedback" class="crm-modal__feedback" :class="feedback.type === 'success' ? 'is-success' : 'is-error'">{{ feedback.text }}</p>
                <h3 class="crm-modal__section-title">{{ t('custom_request.section_personal_information') }}</h3>
                <div class="crm-modal__row">
                    <label class="crm-modal__field"><span class="crm-modal__label">{{ t('custom_request.first_name_label') }}</span><input v-model="form.first_name" :placeholder="t('custom_request.first_name_placeholder')" required></label>
                    <label class="crm-modal__field"><span class="crm-modal__label">{{ t('custom_request.last_name_label') }}</span><input v-model="form.last_name" :placeholder="t('custom_request.last_name_placeholder')"></label>
                </div>
                <div class="crm-modal__row">
                    <label class="crm-modal__field"><span class="crm-modal__label">{{ t('custom_request.email_label') }}</span><input v-model="form.email" type="email" :placeholder="t('custom_request.email_placeholder')" required></label>
                    <label class="crm-modal__field"><span class="crm-modal__label">{{ t('custom_request.phone_label') }}</span><input v-model="form.phone" type="tel" :placeholder="t('custom_request.phone_placeholder')"></label>
                </div>

                <h3 class="crm-modal__section-title">{{ t('custom_request.section_property_requirements') }}</h3>
                <div class="crm-modal__row">
                    <label class="crm-modal__field"><span class="crm-modal__label">{{ t('custom_request.property_category_label') }}</span><select v-model="form.property_category"><option value="">{{ t('custom_request.select_category') }}</option><option value="apartment">{{ t('custom_request.category_apartment') }}</option><option value="villa">{{ t('custom_request.category_villa') }}</option><option value="townhouse">{{ t('custom_request.category_townhouse') }}</option><option value="penthouse">{{ t('custom_request.category_penthouse') }}</option><option value="commercial">{{ t('custom_request.category_commercial') }}</option></select></label>
                    <label class="crm-modal__field"><span class="crm-modal__label">{{ t('custom_request.specification_label') }}</span><input v-model="form.specification" :placeholder="t('custom_request.specification_placeholder')"></label>
                </div>
                <div class="crm-modal__row">
                    <label class="crm-modal__field"><span class="crm-modal__label">{{ t('custom_request.price_range_label') }}</span><input v-model="form.price_range" :placeholder="t('custom_request.price_range_placeholder')"></label>
                    <label class="crm-modal__field"><span class="crm-modal__label">{{ t('custom_request.area_label') }}</span><input v-model="form.area" :placeholder="t('custom_request.area_placeholder')"></label>
                </div>
                <label class="crm-modal__field crm-modal__field--full"><span class="crm-modal__label">{{ t('custom_request.preferred_location_label') }}</span><input v-model="form.preferred_location" :placeholder="t('custom_request.preferred_location_placeholder')"></label>
                <label class="crm-modal__field crm-modal__field--full"><span class="crm-modal__label">{{ t('custom_request.additional_details_label') }}</span><textarea v-model="form.additional_details" :placeholder="t('custom_request.additional_details_placeholder')"></textarea></label>

                <h3 class="crm-modal__section-title">{{ t('custom_request.section_preferences') }}</h3>
                <div class="crm-modal__row">
                    <label class="crm-modal__field"><span class="crm-modal__label">{{ t('custom_request.move_in_timeline_label') }}</span><select v-model="form.move_in_timeline"><option value="">{{ t('custom_request.select_timeline') }}</option><option value="immediate">{{ t('custom_request.timeline_immediate') }}</option><option value="1-3-months">{{ t('custom_request.timeline_1_3_months') }}</option><option value="3-6-months">{{ t('custom_request.timeline_3_6_months') }}</option><option value="6-12-months">{{ t('custom_request.timeline_6_12_months') }}</option><option value="flexible">{{ t('custom_request.timeline_flexible') }}</option></select></label>
                    <label class="crm-modal__field"><span class="crm-modal__label">{{ t('custom_request.furnishing_status_label') }}</span><select v-model="form.furnishing_status"><option value="">{{ t('custom_request.select_status') }}</option><option value="furnished">{{ t('custom_request.furnishing_furnished') }}</option><option value="unfurnished">{{ t('custom_request.furnishing_unfurnished') }}</option><option value="semi-furnished">{{ t('custom_request.furnishing_semi_furnished') }}</option><option value="no-preference">{{ t('custom_request.furnishing_no_preference') }}</option></select></label>
                </div>
                <label class="crm-modal__checkbox"><input v-model="form.whatsapp_consent" type="checkbox"><span class="crm-modal__checkbox-box"></span><span class="crm-modal__checkbox-text"><strong>{{ t('custom_request.whatsapp_consent_label') }}</strong><small>{{ t('custom_request.whatsapp_consent_text') }}</small></span></label>
            </div>
            <div class="crm-modal__footer">
                <button type="submit" class="quote-modal__btn crm-modal__submit" :disabled="submitting">{{ submitting ? t('custom_request.submitting') : t('custom_request.submit') }}</button>
                <p class="crm-modal__terms">{{ t('custom_request.terms_prefix') }} <router-link to="/terms-and-conditions">{{ t('custom_request.terms_of_service') }}</router-link> {{ t('custom_request.terms_and') }} <router-link to="/privacy-policy">{{ t('custom_request.privacy_policy') }}</router-link>.</p>
            </div>
        </form>
        </div>
    </div>
</template>
