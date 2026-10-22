<template>
    <div class="mc">
        <div class="mc__layout">
            <div class="mc__card mc__inputs">
                <CalcField v-model="priceAed" :label="t('calculators.purchase_price', 'Purchase price')" :min="PRICE_MIN" :max="priceMax" :step="10000" ends />
                <ResidencyToggle v-model="residency" />
                <CalcField v-model="downAed" :label="t('mortgage_page.down_payment', 'Down payment')" :badge="`${Math.round(downPct)}%`"
                    :min="priceAed * minDown / 100" :max="priceAed * MAX_DOWN / 100" :step="1000" ends>
                    <template #hint>{{ t('calculators.min_down_hint', 'Minimum {pct}% for {who}').replace('{pct}', minDown).replace('{who}', residencyLabel) }}<template v-if="priceAed > HIGH_VALUE_PRICE && residency !== 'non-resident'"> {{ t('calculators.high_value_hint', '(homes above AED 5M)') }}</template></template>
                </CalcField>
                <CalcField v-model="loanAed" :label="t('mortgage_page.loan_amount', 'Loan amount')" :badge="`${Math.round(100 - downPct)}%`"
                    :min="priceAed * (100 - MAX_DOWN) / 100" :max="priceAed * (100 - minDown) / 100" :step="1000" ends />
                <CalcField v-model="years" type="years" :label="t('calculators.loan_period', 'Loan period')" :min="1" :max="TERM_MAX" ends />
                <CalcField v-model="rate" type="percent" :label="t('mortgage_page.interest_rate', 'Interest rate')" :min="RATE_MIN" :max="RATE_MAX" :step="0.05" :input-step="0.01" ends />
            </div>

            <div class="mc__results">
                <div class="mc__card mc__monthly">
                    <p class="mc__monthly-label">{{ t('mortgage_page.monthly_payment', 'Monthly payment') }}</p>
                    <p class="mc__monthly-value">
                        {{ formatPrice(monthlyAed, `${selectedCurrency.code} 0`) }}
                        <small>{{ t('calculators.with_rate', 'at {rate}% over {years} years').replace('{rate}', rate).replace('{years}', years) }}</small>
                    </p>

                    <div class="mc__split" role="img" :aria-label="t('mortgage_page.split_aria', 'Principal versus interest')">
                        <span class="mc__split-principal" :style="{ width: principalShare + '%' }"></span>
                        <span class="mc__split-interest"></span>
                    </div>
                    <ul class="mc__legend">
                        <li><i class="mc__dot mc__dot--principal"></i>{{ t('mortgage_page.principal', 'Principal') }} <strong>{{ formatPrice(loanAed, '—') }}</strong></li>
                        <li><i class="mc__dot mc__dot--interest"></i>{{ t('mortgage_page.total_interest', 'Total interest') }} <strong>{{ formatPrice(interestAed, '—') }}</strong></li>
                    </ul>

                    <dl class="mc__rows">
                        <div><dt>{{ t('mortgage_page.loan_amount', 'Loan amount') }}</dt><dd>{{ formatPrice(loanAed, '—') }}</dd></div>
                        <div><dt>{{ t('mortgage_page.total_repayment', 'Total loan repayment') }}</dt><dd>{{ formatPrice(totalRepaidAed, '—') }}</dd></div>
                    </dl>
                </div>

                <PurchaseCosts :price="priceAed" :loan="loanAed" :basis="basis" />

                <div v-if="$slots.actions" class="mc__actions">
                    <slot name="actions" :price-aed="priceAed" />
                </div>
            </div>
        </div>

        <p class="mc__disclaimer">{{ t('calculators.disclaimer', 'Figures are estimates for guidance only and do not constitute a loan offer. Actual rates, fees and eligibility depend on your lender and circumstances.') }}</p>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useCurrency } from '../../composables/useCurrency';
import { useStaticText } from '../../composables/useStaticText';
import CalcField from './CalcField.vue';
import ResidencyToggle from './ResidencyToggle.vue';
import PurchaseCosts from './PurchaseCosts.vue';
import { HIGH_VALUE_PRICE, RATE_MAX, RATE_MIN, RESIDENCY, TERM_MAX, aed, clamp, minDownPct, monthlyPayment } from './finance';
import './calculators.css';

/**
 * Home-loan estimator — the /mortgage-calculator page and the Property Details "Mortgage" section.
 * `price` (AED) seeds the purchase price and resets it when it changes. Down payment and loan amount
 * are two views of the same split (the down payment %), so moving either one moves the other.
 */
const props = defineProps({
    price: { type: Number, default: 1200000 },
});

const { t } = useStaticText();
const { selectedCurrency, formatPrice } = useCurrency();

const PRICE_MIN = 300000;
const MAX_DOWN = 80; // banks lend at least 20% of the price
// The slider tops out at 200M, or higher when the property itself costs more.
const priceMax = computed(() => Math.max(200000000, Math.ceil((props.price || 0) / 10000) * 10000));

const priceAed = ref(0);
const residency = ref('resident');
const downPct = ref(20);
const rate = ref(RESIDENCY[residency.value].rate);
const years = ref(TERM_MAX);
watch(() => props.price, (price) => { priceAed.value = clamp(price, PRICE_MIN, priceMax.value); }, { immediate: true });

const minDown = computed(() => minDownPct(residency.value, priceAed.value));
const residencyLabel = computed(() => ({
    national: t('calculators.residency_national', 'UAE National'),
    resident: t('calculators.residency_resident', 'UAE Resident'),
    'non-resident': t('calculators.residency_non_resident', 'Non-resident'),
}[residency.value]));
// Switching residency starts from that status's minimum down payment and typical rate; a price
// change only lifts the down payment when needed.
watch(residency, (key) => {
    downPct.value = minDown.value;
    rate.value = RESIDENCY[key].rate;
});
watch(minDown, (min) => { if (downPct.value < min) downPct.value = min; }, { immediate: true });

const setPct = (pct) => { downPct.value = clamp(pct, minDown.value, MAX_DOWN); };
const downAed = computed({
    get: () => priceAed.value * downPct.value / 100,
    set: (aed) => setPct(aed / priceAed.value * 100),
});
const loanAed = computed({
    get: () => Math.max(0, priceAed.value - downAed.value),
    set: (aed) => setPct(100 - aed / priceAed.value * 100),
});

const monthlyAed = computed(() => monthlyPayment(loanAed.value, rate.value, years.value));
const totalRepaidAed = computed(() => monthlyAed.value * years.value * 12);
const interestAed = computed(() => Math.max(0, totalRepaidAed.value - loanAed.value));
const principalShare = computed(() => (totalRepaidAed.value ? loanAed.value / totalRepaidAed.value * 100 : 100));

const basis = computed(() => [
    { label: t('mortgage_page.monthly_payment', 'Monthly payment'), value: formatPrice(monthlyAed.value, '—') },
    { label: t('mortgage_page.interest_rate', 'Interest rate'), value: `${rate.value}%` },
    { label: t('mortgage_page.loan_amount', 'Loan amount'), value: formatPrice(loanAed.value, '—') },
    { label: t('calculators.loan_duration', 'Loan duration'), value: `${years.value} ${t('calculators.years_long', 'years')}` },
]);

// The calculator page's enquiry form attaches these figures to the lead (always in AED).
defineExpose({
    summary: computed(() => ({
        Calculator: 'Mortgage',
        'Purchase price': aed(priceAed.value),
        Residency: residencyLabel.value,
        'Down payment': `${aed(downAed.value)} (${Math.round(downPct.value)}%)`,
        'Loan amount': aed(loanAed.value),
        'Loan period': `${years.value} years`,
        'Interest rate': `${rate.value}%`,
        'Monthly payment': aed(monthlyAed.value),
    })),
});
</script>
