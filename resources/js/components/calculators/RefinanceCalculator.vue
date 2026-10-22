<template>
    <div class="mc">
        <div class="mc__layout">
            <!-- Three steps: current loan → employment → refinance -->
            <div class="mc__card mc__inputs rf">
                <ol class="rf__steps">
                    <li v-for="(s, i) in steps" :key="s.key">
                        <button type="button" class="rf__step" :class="{ 'is-active': step === i, 'is-done': i < step }" :aria-current="step === i ? 'step' : null" :disabled="i > furthest" @click="goTo(i)">
                            <span class="rf__step-no">
                                <svg v-if="i < step" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>
                                <template v-else>{{ i + 1 }}</template>
                            </span>
                            <span class="rf__step-label">{{ s.title }}</span>
                        </button>
                    </li>
                </ol>

                <!-- 1. Current loan -->
                <div v-show="step === 0" class="rf__grid">
                    <div class="rf__field rf__field--full">
                        <label :for="`${uid}-lender`">{{ t('calculators.refinance.lender', 'Current mortgage lender') }}</label>
                        <select :id="`${uid}-lender`" v-model="f.lender" :class="{ 'is-invalid': errors.lender }">
                            <option value="" disabled>{{ t('calculators.refinance.lender_placeholder', 'Select your bank') }}</option>
                            <option v-for="bank in lenders" :key="bank" :value="bank">{{ bank }}</option>
                        </select>
                        <small v-if="errors.lender" class="rf__error">{{ errors.lender }}</small>
                    </div>
                    <div class="rf__field">
                        <label :for="`${uid}-original`">{{ t('calculators.refinance.original_loan', 'Original loan amount') }}</label>
                        <div class="rf__input" :class="{ 'is-invalid': errors.originalLoan }"><span>{{ selectedCurrency.code }}</span><input :id="`${uid}-original`" :value="f.originalLoan" type="text" inputmode="numeric" placeholder="1,000,000" @input="money('originalLoan', $event)"></div>
                        <small v-if="errors.originalLoan" class="rf__error">{{ errors.originalLoan }}</small>
                    </div>
                    <div class="rf__field">
                        <label :for="`${uid}-valuation`">{{ t('calculators.refinance.valuation', 'Current property valuation') }}</label>
                        <div class="rf__input" :class="{ 'is-invalid': errors.valuation }"><span>{{ selectedCurrency.code }}</span><input :id="`${uid}-valuation`" :value="f.valuation" type="text" inputmode="numeric" placeholder="2,500,000" @input="money('valuation', $event)"></div>
                        <small v-if="errors.valuation" class="rf__error">{{ errors.valuation }}</small>
                    </div>
                    <div class="rf__field">
                        <label :for="`${uid}-term-y`">{{ t('calculators.refinance.term_years', 'Full loan term (years)') }}</label>
                        <select :id="`${uid}-term-y`" v-model.number="f.termYears" :class="{ 'is-invalid': errors.term }">
                            <option v-for="n in 26" :key="n" :value="n - 1">{{ n - 1 }}</option>
                        </select>
                    </div>
                    <div class="rf__field">
                        <label :for="`${uid}-term-m`">{{ t('calculators.refinance.term_months', 'Full loan term (months)') }}</label>
                        <select :id="`${uid}-term-m`" v-model.number="f.termMonths" :disabled="f.termYears === 25" :class="{ 'is-invalid': errors.term }">
                            <option v-for="n in 12" :key="n" :value="n - 1">{{ n - 1 }}</option>
                        </select>
                    </div>
                    <small v-if="errors.term" class="rf__error rf__field--full">{{ errors.term }}</small>
                    <div class="rf__field">
                        <label :for="`${uid}-start`">{{ t('calculators.refinance.start_date', 'Date mortgage started') }}</label>
                        <input :id="`${uid}-start`" v-model="f.startDate" type="date" :min="MIN_DATE" :max="today" :class="{ 'is-invalid': errors.startDate }">
                        <small v-if="errors.startDate" class="rf__error">{{ errors.startDate }}</small>
                    </div>
                    <div class="rf__field">
                        <label :for="`${uid}-rate`">{{ t('calculators.refinance.current_rate', 'Current interest rate') }}</label>
                        <div class="rf__input" :class="{ 'is-invalid': errors.currentRate }"><input :id="`${uid}-rate`" v-model="f.currentRate" type="number" min="0.1" max="30" step="0.01" placeholder="5.25"><span>%</span></div>
                        <small v-if="errors.currentRate" class="rf__error">{{ errors.currentRate }}</small>
                    </div>
                    <div class="rf__field rf__field--full">
                        <label :for="`${uid}-fixed`">{{ t('calculators.refinance.fixed_period', 'Fixed interest rate period') }}</label>
                        <select :id="`${uid}-fixed`" v-model.number="f.fixedYears">
                            <option v-for="n in 5" :key="n" :value="n">{{ n }} {{ n === 1 ? t('calculators.year', 'year') : t('calculators.years_long', 'years') }}</option>
                        </select>
                    </div>
                </div>

                <!-- 2. Employment -->
                <div v-show="step === 1" class="rf__grid">
                    <div class="rf__field rf__field--full">
                        <label :for="`${uid}-employment`">{{ t('calculators.refinance.employment', 'Employment status') }}</label>
                        <select :id="`${uid}-employment`" v-model="f.employment" :class="{ 'is-invalid': errors.employment }">
                            <option value="" disabled>{{ t('calculators.refinance.employment_placeholder', 'Select client type') }}</option>
                            <option v-for="type in EMPLOYMENT_TYPES" :key="type.value" :value="type.value">{{ employmentLabel(type.value) }}</option>
                        </select>
                        <small v-if="errors.employment" class="rf__error">{{ errors.employment }}</small>
                    </div>
                    <div class="rf__field rf__field--full">
                        <label :for="`${uid}-income`">{{ t('calculators.refinance.income', 'Income per month') }}</label>
                        <div class="rf__input" :class="{ 'is-invalid': errors.income }"><span>{{ selectedCurrency.code }}</span><input :id="`${uid}-income`" :value="f.income" type="text" inputmode="numeric" :placeholder="t('calculators.refinance.income_placeholder', 'Your monthly income')" @input="money('income', $event)"></div>
                        <small v-if="errors.income" class="rf__error">{{ errors.income }}</small>
                    </div>
                    <div class="rf__field">
                        <label :for="`${uid}-ten-y`">{{ t('calculators.refinance.tenure_years', 'Employment tenure (years)') }}</label>
                        <select :id="`${uid}-ten-y`" v-model="f.tenureYears" :class="{ 'is-invalid': errors.tenure }">
                            <option value="" disabled>{{ t('calculators.refinance.in_years', 'In years') }}</option>
                            <option v-for="n in 41" :key="n" :value="n - 1">{{ n - 1 }}</option>
                        </select>
                    </div>
                    <div class="rf__field">
                        <label :for="`${uid}-ten-m`">{{ t('calculators.refinance.tenure_months', 'Employment tenure (months)') }}</label>
                        <select :id="`${uid}-ten-m`" v-model="f.tenureMonths">
                            <option value="" disabled>{{ t('calculators.refinance.additional_months', 'Additional months') }}</option>
                            <option v-for="n in 12" :key="n" :value="n - 1">{{ n - 1 }}</option>
                        </select>
                    </div>
                    <small v-if="errors.tenure" class="rf__error rf__field--full">{{ errors.tenure }}</small>
                </div>

                <!-- 3. Refinance -->
                <div v-show="step === 2" class="rf__grid">
                    <div class="rf__field rf__field--full">
                        <label :for="`${uid}-dob`">{{ t('calculators.refinance.dob', 'Borrower’s date of birth') }}</label>
                        <input :id="`${uid}-dob`" v-model="f.dob" type="date" :min="MIN_DATE" :max="today" :class="{ 'is-invalid': errors.dob }">
                        <small v-if="errors.dob" class="rf__error">{{ errors.dob }}</small>
                    </div>
                    <div class="rf__field rf__field--full">
                        <label :for="`${uid}-balance`">{{ t('calculators.refinance.balance', 'Current mortgage balance') }}</label>
                        <div class="rf__input" :class="{ 'is-invalid': errors.balance }"><span>{{ selectedCurrency.code }}</span><input :id="`${uid}-balance`" :value="f.balance" type="text" inputmode="numeric" :placeholder="t('calculators.refinance.balance_placeholder', 'What you still owe')" @input="money('balance', $event)"></div>
                        <small v-if="errors.balance" class="rf__error">{{ errors.balance }}</small>
                        <small v-else-if="balanceEstimated" class="mc__hint">{{ t('calculators.refinance.balance_estimated', 'Estimated from your loan details — change it if your statement shows a different amount.') }}</small>
                    </div>
                    <div class="rf__field rf__field--full">
                        <label :for="`${uid}-equity`">{{ t('calculators.refinance.equity', 'Desired equity release') }}</label>
                        <div class="rf__input" :class="{ 'is-invalid': errors.equityRelease }"><span>{{ selectedCurrency.code }}</span><input :id="`${uid}-equity`" :value="f.equityRelease" type="text" inputmode="numeric" :placeholder="t('calculators.refinance.equity_placeholder', 'Enter amount')" @input="money('equityRelease', $event)"></div>
                        <small v-if="errors.equityRelease" class="rf__error">{{ errors.equityRelease }}</small>
                        <small v-else class="mc__hint">{{ t('calculators.refinance.equity_hint', 'Extra cash to borrow against your home on top of what you owe.') }}</small>
                    </div>
                </div>

                <p v-if="changed" class="mc__note">{{ t('calculators.refinance.changed', 'You have changed your details — press “See my savings” to update your results.') }}</p>

                <div class="rf__nav">
                    <button type="button" class="rf__btn rf__btn--ghost" :disabled="step === 0" @click="goTo(step - 1)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                        {{ t('calculators.previous', 'Previous') }}
                    </button>
                    <button v-if="step < steps.length - 1" type="button" class="rf__btn rf__btn--solid" @click="next">
                        {{ t('calculators.next', 'Next') }}
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                    <button v-else type="button" class="rf__btn rf__btn--solid" @click="finish">{{ t('calculators.refinance.see_savings', 'See my savings') }}</button>
                </div>
            </div>

            <!-- Results: placeholders and blurred until the steps are done and contact details shared -->
            <div class="mc__results">
                <div class="mc__card mc__monthly mc__locked-wrap" :class="{ 'is-locked': locked }">
                    <p class="mc__monthly-label">{{ t('calculators.refinance.recommendations', 'Refinance recommendations') }}</p>
                    <h3 class="rf__result-title">{{ !locked && r && r.monthlySaving <= 0
                        ? t('calculators.refinance.no_saving_title', 'Refinancing would not lower your payments')
                        : t('calculators.refinance.save_title', 'By refinancing your mortgage you can save:') }}</h3>
                    <dl class="rf__savings">
                        <div><dt>{{ t('calculators.refinance.per_month', 'Per month') }}</dt><dd :class="tone(r?.monthlySaving)">{{ show(r?.monthlySaving) }}</dd></div>
                        <div><dt>{{ t('calculators.refinance.first_year', 'In the first year') }}</dt><dd :class="tone(r?.firstYearSaving)">{{ show(r?.firstYearSaving) }}</dd></div>
                        <div><dt>{{ t('calculators.refinance.over_years', 'Over {n}').replace('{n}', duration(SAVING_YEARS * 12)) }}</dt><dd :class="tone(r?.totalSaving)">{{ show(r?.totalSaving) }}</dd></div>
                    </dl>
                    <p class="rf__cost">{{ t('calculators.refinance.will_cost', 'Refinancing your mortgage will cost') }} <strong>{{ show(r?.switchCosts, false) }}</strong>
                                            </p>

                    <div v-if="locked" class="mc__lock">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        <template v-if="!checked">
                            <p class="mc__lock-text">{{ t('calculators.refinance.lock_steps', 'Complete the 3 steps to see how much you could save.') }}</p>
                        </template>
                        <button v-else type="button" class="mc__btn mc__btn--solid" @click="openModal">{{ t('calculators.eligibility.unlock', 'Unlock your results') }}</button>
                    </div>
                </div>

                <div class="mc__card mc__locked-wrap" :class="{ 'is-locked': locked }">
                    <div class="rf__table-wrap">
                        <table class="rf__table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>{{ t('calculators.refinance.col_current', 'Current loan') }}</th>
                                    <th>{{ t('calculators.refinance.col_refinance', 'Refinance') }}</th>
                                    <th>{{ t('calculators.refinance.col_difference', 'Difference') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th>{{ t('calculators.refinance.loan_amount', 'Loan amount') }}</th>
                                    <td>{{ show(checked?.balance) }}</td><td>{{ show(r?.newLoan) }}</td><td>{{ signed(r ? r.newLoan - checked.balance : null) }}</td>
                                </tr>
                                <tr>
                                    <th>{{ t('calculators.refinance.equity_release', 'Equity release') }}</th>
                                    <td>—</td><td>{{ show(r?.equity) }}</td><td>{{ signed(r?.equity) }}</td>
                                </tr>
                                <tr>
                                    <th>{{ t('calculators.refinance.interest_rate', 'Interest rate') }}</th>
                                    <td>{{ pct(r?.currentRate) }}</td><td>{{ pct(r?.rate) }}</td><td>{{ r ? `${r.rate - r.currentRate > 0 ? '+' : ''}${pct(r.rate - r.currentRate)}` : pct(null) }}</td>
                                </tr>
                                <tr>
                                    <th>{{ t('calculators.refinance.monthly_payment', 'Monthly payment') }}</th>
                                    <td>{{ show(r?.currentMonthly) }}</td><td>{{ show(r?.newMonthly) }}</td><td>{{ signed(r ? r.newMonthly - r.currentMonthly : null) }}</td>
                                </tr>
                                <tr>
                                    <th>{{ t('calculators.refinance.term_left', 'Term left') }}</th>
                                    <td>{{ locked ? '— —' : duration(r.remaining) }}</td><td>{{ locked ? '— —' : duration(r.newTerm) }}</td><td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <dl class="mc__rows">
                        <div><dt>{{ t('calculators.refinance.settlement_fee_vat', 'Early settlement fee (1%, max AED 10,000, + VAT)') }}</dt><dd>{{ show(r?.settlementFee, false) }}</dd></div>
                        <div><dt>{{ t('calculators.refinance.registration_transfer', 'Mortgage registration, release and transfer fees') }}</dt><dd>{{ show(r?.registrationFee, false) }}</dd></div>
                        <div><dt>{{ t('calculators.refinance.valuation_fee', 'Property valuation fee') }}</dt><dd>{{ show(r?.valuationFee, false) }}</dd></div>
                        <div class="mc__rows-total"><dt>{{ t('calculators.refinance.switch_costs', 'Total switching costs') }}</dt><dd>{{ show(r?.switchCosts, false) }}</dd></div>
                    </dl>
                </div>

                <ul v-if="!locked && notes.length" class="rf__notes">
                    <li v-for="note in notes" :key="note">{{ note }}</li>
                </ul>

                <div v-if="!locked" class="mc__actions">
                    <router-link :to="{ hash: '#calculator-enquiry' }" class="mc__btn mc__btn--solid">{{ t('mortgage_page.contact_cta', 'Talk to an advisor') }}</router-link>
                </div>
            </div>
        </div>

        <p class="mc__disclaimer">{{ t('calculators.refinance.disclaimer', 'Figures are estimates for guidance only and do not constitute a loan offer. The refinance rate is a typical market rate; your actual rate, loan amount and fees depend on the lender, the property valuation and your profile.') }}</p>

        <!-- Contact details unlock the results (and become a lead with the figures attached). -->
        <Teleport to="body">
            <div v-if="modalOpen" class="mc-modal" role="dialog" aria-modal="true" :aria-labelledby="`${uid}-title`" @click.self="modalOpen = false">
                <div class="mc-modal__box">
                    <button type="button" class="mc-modal__close" :aria-label="t('calculators.close', 'Close')" @click="modalOpen = false">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                    <h3 :id="`${uid}-title`" class="mc-modal__title">{{ t('calculators.refinance.modal_title', 'Your custom refinance solutions are ready!') }}</h3>
                    <p class="mc-modal__text">{{ t('calculators.refinance.modal_text', 'Please verify your contact details to unlock them.') }}</p>
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
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useCurrency } from '../../composables/useCurrency';
import { useStaticText } from '../../composables/useStaticText';
import PhoneInput from '../PhoneInput.vue';
import { useCalculatorLead } from './useCalculatorLead';
import { aed } from './finance';
import { DEFAULT_LENDERS, EMPLOYMENT_TYPES, MAX_AGE, MIN_AGE, MIN_DATE, MIN_INCOME, MIN_LOAN, MIN_PROPERTY_VALUE, SAVING_YEARS, ageYears, calculateRefinance, estimatedBalance, monthsBetween } from './refinance';
import './calculators.css';

/**
 * Refinance in three steps (current loan → employment → refinance). "See my savings" asks for
 * contact details once — sent as a lead with every answer and figure attached — then shows the
 * savings and the current-vs-refinance comparison. The maths and rates are Mortgage Finder's (refinance.js);
 * the lender list comes from Admin › Calculators.
 */
const props = defineProps({
    settings: { type: Object, default: null }, // { lenders[] }
});

const { t } = useStaticText();
const { selectedCurrency, formatPrice, convert, toAed } = useCurrency();
const uid = `refi-${Math.random().toString(36).slice(2, 8)}`;
const today = new Date().toISOString().slice(0, 10);

const steps = computed(() => [
    { key: 'loan', title: t('calculators.refinance.step_loan', 'Current loan') },
    { key: 'employment', title: t('calculators.refinance.step_employment', 'Employment details') },
    { key: 'refinance', title: t('calculators.refinance.step_refinance', 'Refinance') },
]);
const step = ref(0);
const furthest = ref(0);
const lenders = computed(() => (props.settings?.lenders?.length ? props.settings.lenders : DEFAULT_LENDERS));

const f = reactive({
    lender: '', originalLoan: '', valuation: '', termYears: 25, termMonths: 0, startDate: '', currentRate: '', fixedYears: 3,
    employment: '', income: '', tenureYears: '', tenureMonths: '',
    dob: '', balance: '', equityRelease: '',
});
const errors = reactive({});

const num = (v) => Number(String(v ?? '').replace(/[^\d.]/g, '')) || 0;
// Amounts are typed in the visitor's currency; the maths and the lead use AED.
const MONEY_KEYS = ['originalLoan', 'valuation', 'income', 'balance', 'equityRelease'];
const money2aed = (key) => toAed(num(f[key]));
watch(() => selectedCurrency.value.rate, (rate, old) => {
    MONEY_KEYS.forEach((key) => {
        if (f[key] === '') return;
        f[key] = Math.round(num(f[key]) / (Number(old) || 1) * (Number(rate) || 1)).toLocaleString('en-US');
    });
});
function money(key, e) {
    const digits = e.target.value.replace(/[^\d]/g, '').slice(0, 12);
    f[key] = digits ? Number(digits).toLocaleString('en-US') : '';
    e.target.value = f[key];
    if (key === 'balance') balanceEstimated.value = false;
}

// The answers as numbers — what the maths and the lead use.
const inputs = computed(() => ({
    lender: f.lender,
    originalLoan: money2aed('originalLoan'),
    valuation: money2aed('valuation'),
    termMonths: f.termYears * 12 + f.termMonths,
    startDate: f.startDate,
    currentRate: Number(f.currentRate) || 0,
    fixedYears: f.fixedYears,
    employment: f.employment,
    income: money2aed('income'),
    tenureMonths: (Number(f.tenureYears) || 0) * 12 + (Number(f.tenureMonths) || 0),
    dob: f.dob,
    balance: money2aed('balance'),
    equityRelease: money2aed('equityRelease'),
}));

const employmentLabel = (code) => t(`calculators.refinance.employment_${code.toLowerCase()}`, EMPLOYMENT_TYPES.find((e) => e.value === code)?.label || code);
// A 25-year term has no extra months (as on Mortgage Finder).
watch(() => f.termYears, (y) => { if (y === 25) f.termMonths = 0; });

// --- Steps & validation -----------------------------------------------------------------------
function validate(i) {
    Object.keys(errors).forEach((k) => delete errors[k]);
    const v = inputs.value;
    const required = t('calculators.refinance.err_required', 'This field cannot be left empty.');
    if (i === 0) {
        if (!v.lender) errors.lender = t('calculators.refinance.err_lender', 'Please select your current lender.');
        if (!v.originalLoan) errors.originalLoan = required;
        else if (v.originalLoan < MIN_LOAN) errors.originalLoan = inCurrency(t('calculators.refinance.err_loan_min', 'Minimum mortgage amount should be AED 100,000.'), MIN_LOAN);
        if (!v.valuation) errors.valuation = required;
        else if (v.valuation < MIN_PROPERTY_VALUE) errors.valuation = minValuationText();
        if (!v.termMonths) errors.term = t('calculators.refinance.err_term', 'Please enter the full loan term.');
        else if (v.termMonths > 300) errors.term = t('calculators.refinance.err_term_max', 'UAE home loans run for at most 25 years.');
        if (!v.startDate) errors.startDate = required;
        else if (v.startDate > today) errors.startDate = t('calculators.refinance.err_start_future', 'Mortgage start date cannot be in the future.');
        else if (v.startDate < MIN_DATE) errors.startDate = t('calculators.refinance.err_start_min', 'Mortgage start date cannot be before 1950.');
        else if (v.termMonths && monthsBetween(v.startDate) >= v.termMonths) errors.startDate = t('calculators.refinance.err_paid_off', 'With this start date and term, the loan is already paid off.');
        if (f.currentRate === '') errors.currentRate = required;
        else if (!(v.currentRate > 0 && v.currentRate <= 30)) errors.currentRate = t('calculators.refinance.err_rate', 'Enter your interest rate (e.g. 5.25).');
    }
    if (i === 1) {
        if (!v.employment) errors.employment = t('calculators.refinance.err_employment', 'Please choose your employment status.');
        if (!v.income) errors.income = required;
        else if (v.income < MIN_INCOME) errors.income = minIncomeText();
        if (f.tenureYears === '') errors.tenure = t('calculators.refinance.err_tenure', 'Please enter how long you have been in your job.');
    }
    if (i === 2) {
        const age = ageYears(v.dob);
        if (!v.dob) errors.dob = required;
        else if (v.dob > today) errors.dob = t('calculators.refinance.err_dob_future', 'Date of birth cannot be in the future.');
        else if (v.dob < MIN_DATE) errors.dob = t('calculators.refinance.err_dob_min', 'Date of birth cannot be before 1950.');
        else if (age < MIN_AGE) errors.dob = t('calculators.refinance.err_age_min', 'Minimum age should be {n}.').replace('{n}', MIN_AGE);
        else if (age >= MAX_AGE) errors.dob = t('calculators.refinance.err_age_max', 'Maximum age should be {n}.').replace('{n}', MAX_AGE - 1);
        if (!v.balance) errors.balance = required;
        else if (v.balance > v.originalLoan) errors.balance = t('calculators.refinance.err_balance', 'The balance cannot be more than the original loan.');
        // Property-value and income limits don't block: the results cap the new loan and say why.
        if (f.equityRelease === '') errors.equityRelease = required;
    }
    return !Object.keys(errors).length;
}

// The minimums are AED rules, shown in the visitor's currency.
const inCurrency = (text, amountAed) => text.replace(/AED\s?[\d,]+|[\d,]+\s?درهم/, formatPrice(amountAed));
const minValuationText = () => inCurrency(t('calculators.refinance.err_valuation_min', 'Property value must be at least AED 300,000.'), MIN_PROPERTY_VALUE);
const minIncomeText = () => inCurrency(t('calculators.refinance.err_income_min', 'Total monthly income must be more than AED 10,000.'), MIN_INCOME);

// Minimums show while typing (not only on Next), and clear as soon as the value is fixed.
watch(() => f.valuation, () => {
    const v = money2aed('valuation');
    if (v && v < MIN_PROPERTY_VALUE) errors.valuation = minValuationText();
    else delete errors.valuation;
});
watch(() => f.income, () => {
    const v = money2aed('income');
    if (v && v < MIN_INCOME) errors.income = minIncomeText();
    else delete errors.income;
});

const balanceEstimated = ref(false);
function goTo(i) {
    if (i < 0 || i > furthest.value) return;
    Object.keys(errors).forEach((k) => delete errors[k]);
    step.value = i;
}
function next() {
    if (!validate(step.value)) return;
    step.value += 1;
    furthest.value = Math.max(furthest.value, step.value);
    // Arriving at step 3: suggest the balance the original loan would have today.
    if (step.value === 2 && !f.balance) {
        const estimate = Math.round(convert(estimatedBalance(inputs.value)));
        if (estimate > 0) {
            f.balance = estimate.toLocaleString('en-US');
            balanceEstimated.value = true;
        }
    }
}

// --- Results (from the answers as they were when last checked) --------------------------------
const UNLOCK_KEY = 'mw-refinance-unlocked';
const readUnlocked = () => { try { return window.sessionStorage.getItem(UNLOCK_KEY) === '1'; } catch { return false; } };
const unlocked = ref(readUnlocked());
const checked = ref(null);
const r = computed(() => (checked.value ? calculateRefinance(checked.value) : null));
const locked = computed(() => !checked.value || !unlocked.value);
const changed = computed(() => !locked.value && JSON.stringify(inputs.value) !== JSON.stringify(checked.value));

function finish() {
    for (let i = 0; i < steps.value.length; i += 1) {
        if (!validate(i)) {
            step.value = i;
            return;
        }
    }
    if (unlocked.value) checked.value = { ...inputs.value };
    else openModal();
}

// While locked, the page only ever holds placeholders — the real figures are not in the DOM.
const placeholder = computed(() => `${selectedCurrency.value.code} 0,000`);
const show = (amount, signedTone = true) => (locked.value || amount == null ? placeholder.value : formatPrice(signedTone ? Math.abs(amount) : amount, '—'));
const signed = (amount) => (locked.value || amount == null ? placeholder.value : `${amount > 0 ? '+' : amount < 0 ? '−' : ''}${formatPrice(Math.abs(amount), `${selectedCurrency.value.code} 0`)}`);
const pct = (v) => (locked.value || v == null ? '0.00%' : `${Number(v).toFixed(2)}%`);
const tone = (v) => (locked.value || v == null ? '' : v > 0 ? 'is-good' : v < 0 ? 'is-bad' : '');
function duration(months) {
    const y = Math.floor(months / 12);
    const m = Math.round(months % 12);
    const yrs = y ? `${y} ${y === 1 ? t('calculators.year', 'year') : t('calculators.years_long', 'years')}` : '';
    const mos = m ? `${m} ${m === 1 ? t('calculators.month', 'month') : t('calculators.months', 'months')}` : '';
    return [yrs, mos].filter(Boolean).join(' ') || `0 ${t('calculators.months', 'months')}`;
}

// Things the visitor should know about their result.
const notes = computed(() => {
    if (!r.value) return [];
    const list = [];
    list.push(t('calculators.refinance.note_current_rate', 'The current loan is shown at the rate it moves to when its fixed period ends: your lender’s margin over EIBOR.'));
    if (r.value.termLimitedByAge) list.push(t('calculators.refinance.note_age', 'The new loan has to end by age {n}, so its term is shorter than 25 years.').replace('{n}', MAX_AGE));
    return list;
});

// --- Contact details → lead → unlock --------------------------------------------------------
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

// Figures for the lead (always in AED) — every answer, plus the result when the answers are complete.
const summary = computed(() => {
    const v = inputs.value;
    const out = {
        Calculator: 'Refinance',
        'Current lender': v.lender || '—',
        'Original loan': aed(v.originalLoan),
        'Property valuation': aed(v.valuation),
        'Full loan term': `${f.termYears} years ${f.termMonths} months`,
        'Mortgage started': v.startDate || '—',
        'Current rate': `${v.currentRate}% (fixed for ${v.fixedYears} years)`,
        Employment: `${(EMPLOYMENT_TYPES.find((e) => e.value === v.employment) || {}).label || '—'}, ${f.tenureYears || 0} years ${f.tenureMonths || 0} months`,
        'Monthly income': aed(v.income),
        'Date of birth': v.dob || '—',
        'Current balance': aed(v.balance),
        'Equity release requested': aed(v.equityRelease),
    };
    if (v.balance && v.termMonths && v.dob && v.startDate) {
        const res = calculateRefinance(v);
        Object.assign(out, {
            'Interest rate': `${res.currentRate.toFixed(2)}% → ${res.rate.toFixed(2)}%`,
            'New loan': `${aed(res.newLoan)} (equity ${aed(res.equity)})`,
            'Monthly payment': `${aed(res.currentMonthly)} → ${aed(res.newMonthly)}`,
            'Monthly saving': aed(res.monthlySaving),
            [`Saving over ${SAVING_YEARS} years`]: aed(res.totalSaving),
            'Cost of refinancing': aed(res.switchCosts),
        });
    }
    return out;
});

async function unlock() {
    if (!(await send(summary.value))) return;
    checked.value = { ...inputs.value };
    unlocked.value = true;
    modalOpen.value = false;
    try { window.sessionStorage.setItem(UNLOCK_KEY, '1'); } catch { /* private mode etc. */ }
}

// The calculator page's enquiry form attaches these figures to the lead too.
defineExpose({ summary });
</script>

<style scoped>
.rf { gap: 20px; }

/* Step tabs */
.rf__steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin: 0 0 6px; padding: 0; list-style: none; }
.rf__step { display: flex; align-items: center; gap: 10px; width: 100%; padding: 0 0 12px; border: 0; border-bottom: 3px solid #e3e7f0; background: none; color: #8a8f9c; font: inherit; font-size: 14px; font-weight: 700; line-height: 1.3; text-align: start; cursor: pointer; transition: color 0.2s ease, border-color 0.2s ease; }
.rf__step:disabled { cursor: default; }
.rf__step.is-active { border-color: var(--mc-red); color: var(--mc-navy); }
.rf__step.is-done { border-color: var(--mc-navy); color: var(--mc-navy); }
.rf__step-no { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: #eef1f6; font-size: 13px; }
.rf__step.is-active .rf__step-no { background: linear-gradient(135deg, var(--mc-red), var(--mc-navy)); color: #fff; }
.rf__step.is-done .rf__step-no { background: var(--mc-navy); color: #fff; }

/* Fields */
.rf__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 16px; }
.rf__field { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.rf__field--full { grid-column: 1 / -1; }
.rf__field label, .rf__label { color: var(--mc-navy); font-size: 15px; font-weight: 700; }
.rf__field label em { color: #8a8f9c; font-size: 13px; font-style: normal; font-weight: 500; }
.rf__field select, .rf__field > input, .rf__input { width: 100%; min-height: 48px; padding: 0 14px; border: 1px solid #d6dce8; border-radius: 8px; background: #f7f9fc; color: var(--mc-navy); font: inherit; font-size: 15px; font-weight: 600; transition: border-color 0.2s ease; }
.rf__field select:focus, .rf__field > input:focus, .rf__input:focus-within { border-color: var(--mc-navy); outline: none; }
.rf__input { display: flex; align-items: center; gap: 8px; }
.rf__input span { color: #6b7080; font-size: 14px; font-weight: 600; }
.rf__input input { flex: 1; min-width: 0; height: 46px; padding: 0; border: 0; background: transparent; color: var(--mc-navy); font: inherit; font-weight: 700; outline: none; }
.rf__field .is-invalid, .rf__field.is-invalid, .mc__toggle.is-invalid { border-color: var(--mc-red) !important; box-shadow: 0 0 0 1px var(--mc-red); }
.rf__field .mc__toggle { margin-top: 0; }
.rf__error { color: var(--mc-red); font-size: 13px; font-weight: 600; }
.rf__field .mc__hint { margin: 0; }

/* Previous / Next */
.rf__nav { display: flex; justify-content: space-between; gap: 12px; padding-top: 6px; }
.rf__btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-width: 130px; padding: 13px 22px; border: 1.5px solid var(--mc-navy); border-radius: 6px; font: inherit; font-size: 15px; font-weight: 700; cursor: pointer; transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease, opacity 0.2s ease; }
.rf__btn--ghost { background: #fff; color: var(--mc-navy); }
.rf__btn--ghost:disabled { border-color: #e3e7f0; background: #eef1f6; color: #8a8f9c; cursor: default; }
.rf__btn--solid { border-color: transparent; background: linear-gradient(90deg, var(--mc-red) 0%, var(--mc-navy) 100%); color: #fff; box-shadow: 0 12px 26px rgba(201, 40, 68, 0.2); }
.rf__btn:not(:disabled):hover { transform: translateY(-2px); }

/* Savings */
.rf__result-title { margin: 6px 0 16px; color: var(--mc-navy); font-size: clamp(20px, 1.6vw, 26px); font-weight: 800; line-height: 1.3; }
.rf__savings { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 0; }
.rf__savings > div { padding: 14px; border: 1px solid #e3e7f0; border-radius: 12px; background: #fff; }
.rf__savings dt { color: #6b7080; font-size: 13px; font-weight: 600; }
.rf__savings dd { margin: 6px 0 0; color: var(--mc-navy); font-size: clamp(17px, 1.4vw, 22px); font-weight: 800; word-break: break-word; transition: filter 0.3s ease; }
.rf__savings dd.is-good { color: var(--mc-green); }
.rf__savings dd.is-bad { color: var(--mc-red); }
.rf__cost { margin: 14px 0 0; color: #35373c; font-size: 14px; }
.rf__cost strong { color: var(--mc-navy); transition: filter 0.3s ease; }

/* Current vs refinance */
.rf__table-wrap { overflow-x: auto; }
.rf__table { width: 100%; min-width: 420px; border-collapse: collapse; font-size: 14px; }
.rf__table th, .rf__table td { padding: 11px 8px; border-bottom: 1px solid #eef1f6; text-align: end; white-space: nowrap; }
.rf__table tr > :first-child { padding-inline-start: 0; text-align: start; }
.rf__table thead th { color: var(--mc-navy); font-size: 13px; font-weight: 800; }
.rf__table tbody th { color: #6b7080; font-weight: 600; }
.rf__table td { color: var(--mc-navy); font-weight: 700; transition: filter 0.3s ease; }

.mc__locked-wrap.is-locked .rf__savings dd,
.mc__locked-wrap.is-locked .rf__cost strong,
.mc__locked-wrap.is-locked .rf__table td,
.mc__locked-wrap.is-locked .mc__rows dd { filter: blur(7px); user-select: none; }

.rf__notes { display: flex; flex-direction: column; gap: 8px; margin: 0; padding: 14px 16px 14px 34px; border-radius: 12px; background: rgba(36, 67, 115, 0.06); color: #35373c; font-size: 14px; line-height: 1.55; }

@container (max-width: 520px) {
    .rf__grid { grid-template-columns: minmax(0, 1fr); }
    .rf__savings { grid-template-columns: minmax(0, 1fr); }
    .rf__step-label { font-size: 12px; }
    .rf__btn { min-width: 0; flex: 1; }
}
</style>
