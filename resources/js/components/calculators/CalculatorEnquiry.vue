<template>
    <section id="calculator-enquiry" class="mc-enquiry">
        <div class="mc-enquiry__intro">
            <p class="mc-enquiry__eyebrow">{{ eyebrow || t('calculators.enquiry.eyebrow', 'Free mortgage advice') }}</p>
            <h2 class="mc-enquiry__title">{{ title }}</h2>
            <p class="mc-enquiry__text">{{ text || t('calculators.enquiry.text', 'Share your details and one of our mortgage advisors will compare offers from UAE banks for you — your calculation is sent along, so there is nothing to re-enter.') }}</p>
            <ul class="mc-enquiry__points">
                <li v-for="point in shownPoints" :key="point">{{ point }}</li>
            </ul>
        </div>

        <form class="mw-page-contact__form mc-enquiry__form" novalidate @submit.prevent="submit">
            <div class="mw-page-contact__field">
                <label :for="`${uid}-name`">{{ t('contact_page.form.name_label', 'Name') }}</label>
                <input :id="`${uid}-name`" v-model="form.name" type="text" name="name" :placeholder="t('contact_page.form.name_placeholder', 'Your name')" required>
            </div>
            <div class="mw-page-contact__field">
                <label :for="`${uid}-email`">{{ t('contact_page.form.email_label', 'Email') }}</label>
                <input :id="`${uid}-email`" v-model="form.email" type="email" name="email" :placeholder="t('contact_page.form.email_placeholder', 'you@example.com')" required>
            </div>
            <div class="mw-page-contact__field">
                <label :for="`${uid}-phone`">{{ t('contact_page.form.phone_label', 'Phone') }}</label>
                <PhoneInput :id="`${uid}-phone`" v-model="form.phone" v-model:country-code="form.phone_country_code" />
            </div>
            <div class="mw-page-contact__field">
                <label :for="`${uid}-message`">{{ t('calculators.enquiry.message_label', 'Message (optional)') }}</label>
                <textarea :id="`${uid}-message`" v-model="form.message" name="message" rows="3" :placeholder="t('calculators.enquiry.message_placeholder', 'e.g. I am looking at a 2-bedroom in Dubai Marina')"></textarea>
            </div>
            <p v-if="feedback" class="mw-form-feedback" :class="`mw-form-feedback--${feedback.type}`">{{ feedback.text }}</p>
            <button type="submit" class="mw-btn mw-btn--gradient mw-page-contact__submit" :disabled="submitting">{{ submitting ? t('contact_page.form.sending', 'Sending…') : t('calculators.enquiry.submit', 'Get a mortgage quote') }}</button>
        </form>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useStaticText } from '../../composables/useStaticText';
import { useCalculatorLead } from './useCalculatorLead';
import PhoneInput from '../PhoneInput.vue';

/**
 * "Get a mortgage quote" form under each calculator. Sends the visitor's figures (`summary`, from
 * the calculator's defineExpose) with the enquiry — POST /api/contact, source=calculator.
 */
const props = defineProps({
    title: { type: String, required: true },
    summary: { type: Function, required: true }, // () => { label: value } at the moment of sending
    // Admin › Calculators copy; blank = the built-in text.
    eyebrow: { type: String, default: '' },
    text: { type: String, default: '' },
    points: { type: Array, default: () => [] },
});

const { t } = useStaticText();
const shownPoints = computed(() => (props.points.length ? props.points : [
    t('calculators.enquiry.point_banks', 'Rates compared across leading UAE banks'),
    t('calculators.enquiry.point_pre_approval', 'Help with pre-approval and paperwork'),
    t('calculators.enquiry.point_free', 'No cost and no obligation'),
]));
const router = useRouter();
const uid = `calc-enq-${Math.random().toString(36).slice(2, 8)}`;
const { form, submitting, feedback, send } = useCalculatorLead();

async function submit() {
    if (await send(props.summary())) {
        router.push({ path: '/thank-you', state: { thankYou: { type: 'contact', name: form.name.trim().split(/\s+/)[0] || '' } } });
    }
}
</script>

<style scoped>
.mc-enquiry { scroll-margin-top: 120px; display: grid; grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr); gap: clamp(24px, 3vw, 64px); margin-top: clamp(36px, 4vw, 72px); padding: clamp(24px, 3vw, 48px); border: 1px solid #e3e7f0; border-radius: 16px; background: linear-gradient(135deg, rgba(201, 40, 68, 0.04), rgba(36, 67, 115, 0.06)), #fff; box-shadow: 0 10px 26px rgba(36, 67, 115, 0.08); font-family: "Plus Jakarta Sans", sans-serif; }
.mc-enquiry__eyebrow { margin: 0; color: #c92844; font-size: 12px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; }
.mc-enquiry__title { margin: 10px 0 0; color: #244373; font-family: "Playfair Display", Georgia, serif; font-size: clamp(26px, 2.2vw, 40px); font-weight: 600; line-height: 1.2; }
.mc-enquiry__text { margin: 14px 0 0; color: #35373c; font-size: 15px; line-height: 1.65; }
.mc-enquiry__points { display: flex; flex-direction: column; gap: 10px; margin: 20px 0 0; padding: 0; list-style: none; }
.mc-enquiry__points li { position: relative; padding-inline-start: 28px; color: #244373; font-size: 15px; font-weight: 600; }
.mc-enquiry__points li::before { content: ''; position: absolute; top: 2px; inset-inline-start: 0; width: 18px; height: 18px; border-radius: 50%; background: linear-gradient(135deg, #c92844, #244373); }
.mc-enquiry__points li::after { content: ''; position: absolute; top: 7px; inset-inline-start: 6px; width: 6px; height: 4px; border-inline-start: 2px solid #fff; border-bottom: 2px solid #fff; transform: rotate(-45deg); }
.mc-enquiry__form { margin: 0; }
@media (max-width: 991px) {
    .mc-enquiry { grid-template-columns: minmax(0, 1fr); }
}
</style>
