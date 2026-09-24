<template>
    <main>
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ t('contact_page.page_title') }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" :aria-label="t('contact_page.breadcrumb_aria')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('contact_page.breadcrumb_home') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ t('contact_page.breadcrumb_current') }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-page-contact">
            <div class="container-ctn">
                <div class="mw-page-contact__layout">
                    <div class="mw-page-contact__form-col" data-reveal="left">
                        <h2 class="mw-about-title mw-page-contact__title">{{ title }}</h2>
                        <p class="mw-page-contact__lead">{{ description }}</p>
                        <form class="mw-page-contact__form" novalidate @submit.prevent="handleSubmit">
                            <div class="mw-page-contact__field">
                                <label for="page-contact-name">{{ t('contact_page.form.name_label') }}</label>
                                <input type="text" id="page-contact-name" name="name" :placeholder="t('contact_page.form.name_placeholder')" v-model="form.name" required>
                            </div>
                            <div class="mw-page-contact__field">
                                <label for="page-contact-email">{{ t('contact_page.form.email_label') }}</label>
                                <input type="email" id="page-contact-email" name="email" :placeholder="t('contact_page.form.email_placeholder')" v-model="form.email" required>
                            </div>
                            <div class="mw-page-contact__field">
                                <label for="page-contact-phone">{{ t('contact_page.form.phone_label') }}</label>
                                <input type="tel" id="page-contact-phone" name="phone" :placeholder="t('contact_page.form.phone_placeholder')" v-model="form.phone">
                            </div>
                            <div class="mw-page-contact__field">
                                <label for="page-contact-message">{{ t('contact_page.form.message_label') }}</label>
                                <textarea id="page-contact-message" name="message" rows="4" :placeholder="t('contact_page.form.message_placeholder')" v-model="form.message" required></textarea>
                            </div>
                            <p v-if="feedback" class="mw-form-feedback" :class="`mw-form-feedback--${feedback.type}`">{{ feedback.text }}</p>
                            <button type="submit" class="mw-btn mw-btn--gradient mw-page-contact__submit" :disabled="submitting">{{ submitting ? t('contact_page.form.sending') : t('contact_page.form.submit') }}</button>
                        </form>
                    </div>

                    <div class="mw-page-contact__info-col" data-reveal="right">
                        <div class="mw-page-contact__details">
                            <div class="mw-page-contact__item" v-if="address">
                                <p class="mw-page-contact__label">{{ t('contact_page.info.office_address_label') }}</p>
                                <p class="mw-page-contact__value">{{ address }}</p>
                            </div>
                            <div class="mw-page-contact__item" v-if="phone">
                                <p class="mw-page-contact__label">{{ t('contact_page.info.call_us_label') }}</p>
                                <a class="mw-page-contact__value mw-page-contact__value--lg" :href="'tel:' + phoneHref">{{ phone }}</a>
                            </div>
                            <div class="mw-page-contact__item" v-if="email">
                                <p class="mw-page-contact__label">{{ t('contact_page.info.email_us_label') }}</p>
                                <a class="mw-page-contact__value" :href="'mailto:' + email">{{ email }}</a>
                            </div>
                            <div class="mw-page-contact__item" v-if="whatsappNumber">
                                <p class="mw-page-contact__label">{{ t('contact_page.info.whatsapp_label') }}</p>
                                <a class="mw-page-contact__value mw-page-contact__value--lg" :href="'https://wa.me/' + whatsappNumber" target="_blank" rel="noopener">{{ whatsappDisplay }}</a>
                            </div>
                            <div class="mw-page-contact__item" v-if="workingHours">
                                <p class="mw-page-contact__label">{{ t('contact_page.info.working_hours_label') }}</p>
                                <p class="mw-page-contact__value">{{ workingHours }}</p>
                            </div>
                            <div class="mw-page-contact__item">
                                <p class="mw-page-contact__label">{{ t('contact_page.info.follow_us_label') }}</p>
                                <div class="mw-page-contact__social">
                                    <a :href="social.twitter || '#'" :aria-label="t('contact_page.social.twitter_aria')">
                                        <img src="/frontend/assets/images/icons/contact-twitter.svg" alt="" width="24" height="24">
                                    </a>
                                    <a :href="social.facebook || '#'" :aria-label="t('contact_page.social.facebook_aria')">
                                        <img src="/frontend/assets/images/icons/contact-facebook.svg" alt="" width="24" height="24">
                                    </a>
                                    <a :href="social.instagram || '#'" :aria-label="t('contact_page.social.instagram_aria')">
                                        <img src="/frontend/assets/images/icons/contact-instagram.svg" alt="" width="24" height="24">
                                    </a>
                                    <a :href="social.linkedin || '#'" :aria-label="t('contact_page.social.linkedin_aria')">
                                        <img src="/frontend/assets/images/icons/contact-linkedin.svg" alt="" width="24" height="24">
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="mw-page-contact__map">
                            <iframe v-if="mapUrl" :src="mapUrl" style="border:0; width:100%; height:100%;" loading="lazy" referrerpolicy="no-referrer-when-downgrade" :title="t('contact_page.map_title')"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mw-page-contact__photo">
                <img :src="heroImage" :alt="heroImageAlt" width="392" height="875">
            </div>
        </section>
    </main>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useContactPage } from '../composables/useContactPage';
import { useRecaptcha } from '../composables/useRecaptcha';
import { useStaticText } from '../composables/useStaticText';

const { contactPage } = useContactPage();
const { getRecaptchaToken } = useRecaptcha();
const { t } = useStaticText();

const form = reactive({ name: '', email: '', phone: '', message: '' });
const submitting = ref(false);
const feedback = ref(null);

async function handleSubmit() {
    if (submitting.value) return;
    submitting.value = true;
    feedback.value = null;

    try {
        const recaptcha_token = await getRecaptchaToken('contact');
        const { data } = await window.axios.post('/api/contact', { ...form, recaptcha_token });
        feedback.value = { type: 'success', text: data.message };
        form.name = '';
        form.email = '';
        form.phone = '';
        form.message = '';
    } catch (error) {
        feedback.value = { type: 'error', text: error.response?.data?.message || t('contact_page.generic_error') };
    } finally {
        submitting.value = false;
    }
}

const title = computed(() => contactPage.value?.title || 'Get in Touch');
const description = computed(() => contactPage.value?.description || 'Have questions about our properties or services? Our team is here to help you.');
const address = computed(() => contactPage.value?.address || 'Office No: 703 - Churchill Tower Business Bay - Dubai, UAE');
const phone = computed(() => contactPage.value?.phone || '+971 58 589 9990');
const phoneHref = computed(() => phone.value.replace(/[^\d+]/g, ''));
const email = computed(() => contactPage.value?.email || 'info@mightywarnersrealty.com');
const whatsappNumber = computed(() => contactPage.value?.whatsapp_number || '971585899990');
const whatsappDisplay = computed(() => phone.value);
const workingHours = computed(() => contactPage.value?.working_hours || 'Mon - Sat: 9:00 AM - 6:00 PM');
const social = computed(() => contactPage.value?.social || {});
const mapUrl = computed(() => contactPage.value?.map_url || 'https://maps.google.com/maps?q=Churchill+Tower+Business+Bay+Dubai&output=embed');
const heroImage = computed(() => contactPage.value?.image || '/frontend/assets/images/contact/villa.png');
const heroImageAlt = computed(() => contactPage.value?.image_alt || 'Poolside villa at sunset');
</script>
