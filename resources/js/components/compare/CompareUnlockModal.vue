<template>
    <!-- Contact details unlock the comparison's hidden figures (and become a lead with the listings attached). -->
    <Teleport to="body">
        <div v-if="open" class="mc-modal" role="dialog" aria-modal="true" :aria-labelledby="`${uid}-title`" @click.self="close">
            <div class="mc-modal__box">
                <button type="button" class="mc-modal__close" :aria-label="t('calculators.close', 'Close')" @click="close">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
                <h3 :id="`${uid}-title`" class="mc-modal__title">{{ t('compare.unlock_title', 'See the full comparison') }}</h3>
                <p class="mc-modal__text">{{ t('compare.unlock_text', 'Share your contact details to unlock buying costs, total cash needed, rental yields and market data for these properties.') }}</p>
                <form class="mw-page-contact__form mc-modal__form" novalidate @submit.prevent="submit">
                    <div class="mw-page-contact__field">
                        <label :for="`${uid}-name`">{{ t('contact_page.form.name_label', 'Name') }}</label>
                        <input :id="`${uid}-name`" ref="nameInput" v-model="form.name" type="text" :placeholder="t('contact_page.form.name_placeholder', 'Your name')" required>
                    </div>
                    <div class="mw-page-contact__field">
                        <label :for="`${uid}-email`">{{ t('contact_page.form.email_label', 'Email') }}</label>
                        <input :id="`${uid}-email`" v-model="form.email" type="email" :placeholder="t('contact_page.form.email_placeholder', 'you@example.com')" required>
                    </div>
                    <div class="mw-page-contact__field">
                        <label :for="`${uid}-phone`">{{ t('contact_page.form.phone_label', 'Phone') }}</label>
                        <PhoneInput :id="`${uid}-phone`" v-model="form.phone" v-model:country-code="form.phone_country_code" />
                    </div>
                    <p v-if="feedback" class="mw-form-feedback" :class="`mw-form-feedback--${feedback.type}`">{{ feedback.text }}</p>
                    <button type="submit" class="mw-btn mw-btn--gradient mw-page-contact__submit" :disabled="submitting">{{ submitting ? t('contact_page.form.sending', 'Sending…') : t('compare.unlock_submit', 'Unlock comparison') }}</button>
                    <p class="mc-modal__legal">
                        {{ t('calculators.eligibility.legal_prefix', 'By continuing you accept our') }}
                        <router-link to="/terms-and-conditions" target="_blank">{{ t('footer.legal.terms_of_use', 'Terms of Use') }}</router-link>
                        {{ t('calculators.and', 'and') }}
                        <router-link to="/privacy-policy" target="_blank">{{ t('footer.legal.privacy_policy', 'Privacy Policy') }}</router-link>.
                    </p>
                </form>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useStaticText } from '../../composables/useStaticText';
import { useCompareUnlock } from '../../composables/useCompareUnlock';
import { useCalculatorLead } from '../calculators/useCalculatorLead';
import PhoneInput from '../PhoneInput.vue';
import '../calculators/calculators.css';

/**
 * The /compare page's "unlock" popup — same look as the eligibility calculator's. Sends a lead
 * (POST /api/contact, source=compare) with the compared listings attached; the reply's token is
 * stored by useCompareUnlock and the page reloads with the full figures.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    // The listings on screen: [{ name, slug }]
    properties: { type: Array, default: () => [] },
    currency: { type: String, default: 'AED' },
});
const emit = defineEmits(['update:open', 'unlocked']);

const { t } = useStaticText();
const { setToken } = useCompareUnlock();
const { form, submitting, feedback, send } = useCalculatorLead();
const uid = `cmp-unlock-${Math.random().toString(36).slice(2, 8)}`;
const nameInput = ref(null);

const close = () => emit('update:open', false);

watch(() => props.open, async (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) {
        feedback.value = null;
        document.addEventListener('keydown', onKeydown);
        await nextTick();
        nameInput.value?.focus();
    } else {
        document.removeEventListener('keydown', onKeydown);
    }
});
function onKeydown(e) {
    if (e.key === 'Escape') close();
}
onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
});

async function submit() {
    // The lead shows which listings the visitor was weighing up.
    const listings = Object.fromEntries(props.properties.map((p, i) => [`Property ${i + 1}`, String(p.name || p.slug).slice(0, 150)]));
    const res = await send({ ...listings, Currency: props.currency }, 'compare');
    if (!res) return;
    setToken(res.compare_unlock);
    emit('unlocked');
    close();
}
</script>
