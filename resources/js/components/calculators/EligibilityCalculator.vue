<template>
    <div class="mc">
        <div class="mc__layout">
            <div class="mc__card mc__inputs">
                <CalcField v-model="income" :label="t('calculators.eligibility.income', 'Monthly income')" :min="5000" :max="300000" :step="1000" ends
                    :hint="t('calculators.eligibility.income_hint', 'Your fixed monthly salary or business income.')" />
                <CalcField v-model="liabilities" :label="t('calculators.eligibility.liabilities', 'Total monthly liabilities')" :min="0" :max="150000" :step="500"
                    :hint="t('calculators.eligibility.liabilities_hint', 'Monthly repayments on car loans, personal loans and other finance.')" />
                <CalcField v-model="cardLimits" :label="t('calculators.eligibility.card_limits', 'Credit card limits')" :min="0" :max="1000000" :step="5000"
                    :hint="t('calculators.eligibility.card_limits_hint', 'The total limit of all your cards — banks count 5% of it as a monthly debt, even if unused.')" />
                <ResidencyToggle v-model="residency" />
                <div class="mc__field">
                    <span class="mc__label">{{ t('calculators.eligibility.first_time', 'Is this your first time buying property in the UAE?') }}</span>
                    <div class="mc__toggle" role="radiogroup">
                        <button type="button" role="radio" :aria-checked="firstHome ? 'true' : 'false'" :class="{ 'is-active': firstHome }" @click="firstHome = true">{{ t('calculators.yes', 'Yes') }}</button>
                        <button type="button" role="radio" :aria-checked="firstHome ? 'false' : 'true'" :class="{ 'is-active': !firstHome }" @click="firstHome = false">{{ t('calculators.no', 'No') }}</button>
                    </div>
                </div>
                <button v-if="!unlocked" type="button" class="mc__btn mc__btn--solid mc__unlock-btn" @click="openModal">{{ t('calculators.eligibility.cta', 'Get your eligibility certificate') }}</button>
                <template v-else>
                    <p v-if="changed" class="mc__note mc__changed">{{ t('calculators.eligibility.changed', 'You have changed your details — check again to update your eligibility.') }}</p>
                    <button type="button" class="mc__btn mc__btn--solid mc__unlock-btn" :disabled="!changed" @click="check">{{ t('calculators.eligibility.check_again', 'Check eligibility') }}</button>
                </template>
            </div>

            <div class="mc__results">
                <div class="mc__card mc__monthly mc__locked-wrap" :class="{ 'is-locked': !unlocked }">
                    <p class="mc__monthly-label">{{ t('calculators.eligibility.result_title', 'Your mortgage eligibility') }}</p>
                    <p v-if="unlocked && !result.maxInstalment" class="mc__note">{{ t('calculators.eligibility.over_limit', 'Your monthly liabilities already use 50% or more of your income, so banks are unlikely to approve a new home loan.') }}</p>

                    <dl class="mc__rows mc__eligibility">
                        <div><dt>{{ t('calculators.eligibility.purchase_price', 'Property purchase price') }}</dt><dd>{{ show(result.price) }}</dd></div>
                        <div><dt>{{ t('calculators.eligibility.loan_amount', 'Mortgage loan amount') }}</dt><dd>{{ show(result.loan) }}</dd></div>
                        <div><dt>{{ t('calculators.eligibility.monthly', 'Monthly payments') }}</dt><dd>{{ show(result.instalment) }}</dd></div>
                        <div><dt>{{ t('mortgage_page.down_payment', 'Down payment') }} ({{ result.downPct }}%)</dt><dd>{{ show(result.price - result.loan) }}</dd></div>
                    </dl>
                    <p class="mc__hint">{{ t('calculators.eligibility.adjust_hint', 'You can adjust your liabilities to increase the mortgage amount.') }}</p>
                    <p class="mc__hint">{{ t('calculators.eligibility.assumptions', 'Based on {rate}% interest over {years} years and the UAE Central Bank’s 50% debt burden limit.').replace('{rate}', result.rate).replace('{years}', TERM_MAX) }}</p>

                    <div v-if="!unlocked" class="mc__lock">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        <button type="button" class="mc__btn mc__btn--solid" @click="openModal">{{ t('calculators.eligibility.unlock', 'Unlock your results') }}</button>
                    </div>
                </div>

                <PurchaseCosts v-if="unlocked && result.price" :price="result.price" :loan="result.loan" />

                <div v-if="unlocked" class="mc__actions">
                    <router-link v-if="result.price" :to="{ path: '/properties', query: { listing_type: 'sale', max_price: Math.round(result.price) } }" class="mc__btn mc__btn--solid">{{ t('calculators.eligibility.browse_cta', 'See homes you can afford') }}</router-link>
                    <router-link :to="{ hash: '#calculator-enquiry' }" class="mc__btn mc__btn--ghost">{{ t('mortgage_page.contact_cta', 'Talk to an advisor') }}</router-link>
                </div>
            </div>
        </div>

        <p class="mc__disclaimer">{{ t('calculators.disclaimer', 'Figures are estimates for guidance only and do not constitute a loan offer. Actual rates, fees and eligibility depend on your lender and circumstances.') }}</p>

        <!-- Contact details unlock the results (and become a lead with the figures attached). -->
        <Teleport to="body">
            <div v-if="modalOpen" class="mc-modal" role="dialog" aria-modal="true" :aria-labelledby="`${uid}-title`" @click.self="modalOpen = false">
                <div class="mc-modal__box">
                    <button type="button" class="mc-modal__close" :aria-label="t('calculators.close', 'Close')" @click="modalOpen = false">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                    <h3 :id="`${uid}-title`" class="mc-modal__title">{{ t('calculators.eligibility.modal_title', 'Your eligibility certificate is ready!') }}</h3>
                    <p class="mc-modal__text">{{ t('calculators.eligibility.modal_text', 'Please share your contact details to unlock it.') }}</p>
                    <form class="mw-page-contact__form mc-modal__form" novalidate @submit.prevent="unlock">
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
                        <button type="submit" class="mw-btn mw-btn--gradient mw-page-contact__submit" :disabled="submitting">{{ submitting ? t('contact_page.form.sending', 'Sending…') : t('calculators.continue', 'Continue') }}</button>
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
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useCurrency } from '../../composables/useCurrency';
import { useStaticText } from '../../composables/useStaticText';
import CalcField from './CalcField.vue';
import ResidencyToggle from './ResidencyToggle.vue';
import PurchaseCosts from './PurchaseCosts.vue';
import PhoneInput from '../PhoneInput.vue';
import { useCalculatorLead } from './useCalculatorLead';
import { CARD_LIMIT_SHARE, DBR_LIMIT, HIGH_VALUE_PRICE, RESIDENCY, TERM_MAX, aed, loanForPayment, minDownPct, monthlyPayment } from './finance';
import './calculators.css';

/**
 * How much can I borrow? UAE Central Bank rules: all debt repayments (incl. 5% of card limits) ≤ 50%
 * of income, and a minimum down payment by residency and first / later home (see finance.js).
 * Results stay blurred — with placeholder figures, not the real ones — until the visitor shares
 * their contact details, which are sent as a lead with the calculation attached.
 */
const { t } = useStaticText();
const { selectedCurrency, formatPrice } = useCurrency();
const uid = `elig-${Math.random().toString(36).slice(2, 8)}`;

const income = ref(30000);
const liabilities = ref(0);
const cardLimits = ref(0);
const residency = ref('resident');
const firstHome = ref(true);

const inputs = computed(() => ({
    income: income.value, liabilities: liabilities.value, cardLimits: cardLimits.value, residency: residency.value, firstHome: firstHome.value,
}));

// Largest price the loan supports. On a first home above AED 5M the bigger down payment applies,
// which can pin the price at exactly 5M (where the lower one still works) rather than higher.
function evaluate({ income: pay, liabilities: debts, cardLimits: cards, residency: status, firstHome: first }) {
    const rate = RESIDENCY[status].rate;
    const maxInstalment = Math.max(0, pay * DBR_LIMIT - (debts + cards * CARD_LIMIT_SHARE));
    const maxLoan = loanForPayment(maxInstalment, rate, TERM_MAX);
    const d = minDownPct(status, 0, first) / 100;
    const hi = minDownPct(status, HIGH_VALUE_PRICE + 1, first) / 100;
    let price = maxLoan / (1 - d);
    let loan = maxLoan;
    if (price > HIGH_VALUE_PRICE && d < hi) {
        price = Math.max(HIGH_VALUE_PRICE, maxLoan / (1 - hi));
        loan = price === HIGH_VALUE_PRICE ? HIGH_VALUE_PRICE * (1 - d) : maxLoan;
    }
    const downPct = price ? Math.round((1 - loan / price) * 100) : d * 100;
    return { price, loan, instalment: monthlyPayment(loan, rate, TERM_MAX), rate, maxInstalment, downPct };
}

// --- Unlock & check -------------------------------------------------------------------------
// The results show the inputs as they were when last checked — the first check needs contact
// details; after that "Check eligibility" re-runs it on the edited inputs (no form again).
const UNLOCK_KEY = 'mw-eligibility-unlocked';
const readUnlocked = () => { try { return window.sessionStorage.getItem(UNLOCK_KEY) === '1'; } catch { return false; } };
const unlocked = ref(readUnlocked());
const checkedInputs = ref({ ...inputs.value });
const result = computed(() => evaluate(checkedInputs.value));
const changed = computed(() => unlocked.value && JSON.stringify(inputs.value) !== JSON.stringify(checkedInputs.value));
function check() {
    checkedInputs.value = { ...inputs.value };
}
// While locked, the page only ever holds a placeholder — the real figures are not in the DOM.
const show = (amount) => (unlocked.value ? formatPrice(amount, '—') : `${selectedCurrency.value.code} 0,000,000`);

const { form, submitting, feedback, send } = useCalculatorLead();
const modalOpen = ref(false);
const nameInput = ref(null);

function openModal() {
    feedback.value = null;
    modalOpen.value = true;
}
watch(modalOpen, async (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) {
        await nextTick();
        nameInput.value?.focus();
    }
});
function onKeydown(e) {
    if (e.key === 'Escape') modalOpen.value = false;
}
watch(modalOpen, (open) => (open ? document.addEventListener('keydown', onKeydown) : document.removeEventListener('keydown', onKeydown)));
onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
});

// Figures for the lead — the inputs on screen right now.
const summary = computed(() => {
    const live = evaluate(inputs.value);
    return {
        Calculator: 'Eligibility',
        Residency: residency.value,
        'First home in the UAE': firstHome.value ? 'Yes' : 'No',
        'Monthly income': aed(income.value),
        'Monthly liabilities': aed(liabilities.value),
        'Credit card limits': aed(cardLimits.value),
        'Maximum property price': aed(live.price),
        'Maximum loan': aed(live.loan),
        'Monthly payment': aed(live.instalment),
    };
});

async function unlock() {
    if (!(await send(summary.value))) return;
    check();
    unlocked.value = true;
    modalOpen.value = false;
    try { window.sessionStorage.setItem(UNLOCK_KEY, '1'); } catch { /* private mode etc. */ }
}

// The calculator page's enquiry form attaches these figures to the lead (always in AED).
defineExpose({ summary });
</script>

<style scoped>
.mc__changed { margin-bottom: -10px; }

/* Locked results: blurred placeholder figures (unlock button + popup styles: calculators.css). */
.mc__eligibility dd { transition: filter 0.3s ease; }
.mc__locked-wrap.is-locked .mc__eligibility dd { filter: blur(7px); user-select: none; }
</style>
