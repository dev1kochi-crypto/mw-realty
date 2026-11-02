<template>
    <div class="mc">
        <div class="mc__layout">
            <div class="mc__card mc__inputs">
                <p class="mc__group-title">{{ t('property_details.investment.income_group', 'Rental income & costs') }}</p>
                <CalcField v-model="rentModel":label="t('property_details.investment.annual_rent', 'Expected annual rent')" :min="0" :max="rentMax" :step="1000" uncapped>
                    <template #hint>
                        <SourceTag v-if="rentTouched" source="assumption" />
                        <SourceTag v-else-if="rentEstimate" :source="rentEstimate.source" :count="rentEstimate.count" />
                        <SourceTag v-else source="assumption" />
                        {{ rentHint }}
                    </template>
                </CalcField>
                <CalcField v-model="serviceCharges" :label="t('property_details.investment.service_charges', 'Service charges (per year)')" :min="0" :max="serviceMax" :step="500" uncapped>
                    <template #hint><SourceTag source="assumption" /> {{ serviceHint }}</template>
                </CalcField>
                <CalcField v-model="maintenancePct" type="percent" :label="t('property_details.investment.maintenance', 'Maintenance (of price, per year)')" :min="0" :max="5" :step="0.1" />
                <CalcField v-model="vacancyPct" type="percent" :label="t('property_details.investment.vacancy', 'Vacancy (time empty per year)')" :min="0" :max="30" :step="1" />

                <p class="mc__group-title">{{ t('property_details.investment.finance_group', 'Financing') }}</p>
                <ResidencyToggle v-model="residency" />
                <CalcField v-model="downPct" type="percent" :label="t('property_details.investment.down_payment', 'Down payment')" :min="minDown" :max="100" :step="1"
                    :hint="t('property_details.investment.down_hint', '100% means buying in cash. Minimum {pct}% for this purchase.').replace('{pct}', minDown)" />
                <CalcField v-if="downPct < 100" v-model="rate" type="percent" :label="t('mortgage_page.interest_rate', 'Interest rate')" :min="RATE_MIN" :max="RATE_MAX" :step="0.05" :input-step="0.01" />
                <CalcField v-if="downPct < 100" v-model="years" type="years" :label="t('calculators.loan_period', 'Loan period')" :min="1" :max="TERM_MAX" />
                <CalcField v-model="holdYears" type="years" :label="t('property_details.investment.hold_years', 'Holding period')" :min="1" :max="30" ends />
            </div>

            <div class="mc__results">
                <div class="mc__card mc__monthly">
                    <p class="mc__monthly-label">{{ t('property_details.investment.cash_flow', 'Monthly cash flow') }}</p>
                    <p class="mc__monthly-value" :class="result.monthlyCashFlow >= 0 ? 'is-good' : 'is-bad'">
                        {{ signed(result.monthlyCashFlow) }}
                        <small>{{ t('property_details.investment.cash_flow_note', 'Rent after vacancy, minus mortgage, service charges and maintenance') }}</small>
                    </p>
                    <dl class="mc__rows">
                        <div><dt>{{ t('property_details.investment.gross_yield', 'Gross rental yield') }}</dt><dd>{{ pct(result.grossYield) }}</dd></div>
                        <div><dt>{{ t('property_details.investment.net_yield', 'Net rental yield') }}</dt><dd>{{ pct(result.netYield) }}</dd></div>
                        <div><dt>{{ t('property_details.investment.cash_on_cash', 'Cash-on-cash return') }}</dt><dd>{{ pct(result.cashOnCash) }}</dd></div>
                        <div><dt>{{ t('property_details.investment.monthly_rent', 'Monthly rent (after vacancy)') }}</dt><dd>{{ formatPrice(result.effectiveRent / 12, '—') }}</dd></div>
                        <div v-if="result.monthlyLoan"><dt>{{ t('mortgage_page.monthly_payment', 'Monthly payment') }}</dt><dd>{{ formatPrice(result.monthlyLoan, '—') }}</dd></div>
                    </dl>
                </div>

                <div class="mc__card">
                    <h4 class="mc__card-title">{{ t('property_details.investment.tco_title', 'Total cost of ownership') }}</h4>
                    <p class="mc__hint">{{ t('property_details.investment.tco_hint', 'Everything you pay over {years} years, and what the rent brings back.').replace('{years}', holdYears) }}</p>
                    <dl class="mc__rows">
                        <div><dt>{{ t('mortgage_page.down_payment', 'Down payment') }}</dt><dd>{{ formatPrice(result.down, '—') }}</dd></div>
                        <div>
                            <dt>
                                <button type="button" class="mc__expand" :aria-expanded="feesOpen ? 'true' : 'false'" @click="feesOpen = !feesOpen">
                                    {{ t('calculators.purchase_costs', 'Total purchase costs') }}
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                                </button>
                            </dt>
                            <dd>{{ formatPrice(result.fees.total, '—') }}</dd>
                        </div>
                        <div v-for="item in result.fees.items" v-show="feesOpen" :key="item.key" class="mc__rows-sub">
                            <dt>{{ t(`calculators.fees.${item.key}`, item.label) }}</dt><dd>{{ formatPrice(item.amount, '—') }}</dd>
                        </div>
                        <div v-if="result.loanPaid"><dt>{{ t('property_details.investment.mortgage_paid', 'Mortgage payments') }}</dt><dd>{{ formatPrice(result.loanPaid, '—') }}</dd></div>
                        <div v-if="result.loanPaid" class="mc__rows-sub"><dt>{{ t('property_details.investment.of_which_interest', 'of which interest') }}</dt><dd>{{ formatPrice(result.loanInterest, '—') }}</dd></div>
                        <div><dt>{{ t('property_details.investment.service_total', 'Service charges') }}</dt><dd>{{ formatPrice(serviceCharges * holdYears, '—') }}</dd></div>
                        <div><dt>{{ t('property_details.investment.maintenance_total', 'Maintenance') }}</dt><dd>{{ formatPrice(result.maintenance * holdYears, '—') }}</dd></div>
                        <div class="mc__rows-total"><dt>{{ t('property_details.investment.total_paid', 'Total paid') }}</dt><dd>{{ formatPrice(result.totalCost, '—') }}</dd></div>
                        <div><dt>{{ t('property_details.investment.rent_income', 'Rental income') }}</dt><dd>− {{ formatPrice(result.rentIncome, '—') }}</dd></div>
                        <div class="mc__rows-total"><dt>{{ t('property_details.investment.net_cost', 'Net cost of ownership') }}</dt><dd>{{ formatPrice(result.netCost, '—') }}</dd></div>
                        <div v-if="result.loanBalance"><dt>{{ t('property_details.investment.balance_left', 'Mortgage still owed at the end') }}</dt><dd>{{ formatPrice(result.loanBalance, '—') }}</dd></div>
                    </dl>
                </div>
            </div>
        </div>

        <div class="mc__card pi-card-gap">
            <h4 class="mc__card-title">{{ t('property_details.investment.scenarios_title', 'Financing scenarios') }}</h4>
            <p class="mc__hint">{{ t('property_details.investment.scenarios_hint', 'The same rent and costs, with different down payments at {rate}% over {years} years.').replace('{rate}', rate).replace('{years}', years) }}</p>
            <div class="pi-table-wrap">
                <table class="pi-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ t('property_details.investment.col_down', 'Down payment') }}</th>
                            <th scope="col" class="pi-table__num">{{ t('property_details.investment.col_upfront', 'Cash needed upfront') }}</th>
                            <th scope="col" class="pi-table__num">{{ t('property_details.investment.col_monthly', 'Monthly payment') }}</th>
                            <th scope="col" class="pi-table__num">{{ t('property_details.investment.col_cash_flow', 'Monthly cash flow') }}</th>
                            <th scope="col" class="pi-table__num">{{ t('property_details.investment.col_coc', 'Cash-on-cash') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in scenarios" :key="row.downPct" :class="{ 'is-active': row.downPct === downPct }">
                            <td>{{ row.downPct === 100 ? t('property_details.investment.cash_purchase', 'Cash (100%)') : `${row.downPct}%` }}</td>
                            <td class="pi-table__num">{{ formatPrice(row.upfront, '—') }}</td>
                            <td class="pi-table__num">{{ row.monthlyLoan ? formatPrice(row.monthlyLoan, '—') : '—' }}</td>
                            <td class="pi-table__num" :class="row.monthlyCashFlow >= 0 ? 'is-good' : 'is-bad'">{{ signed(row.monthlyCashFlow) }}</td>
                            <td class="pi-table__num">{{ pct(row.cashOnCash) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mc__disclaimer">{{ t('property_details.investment.disclaimer', 'Estimates for guidance only, based on the assumptions above — not financial advice or a loan offer. Rent, service charges and costs vary by building and over time; check them with the agent before you buy.') }}</p>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useCurrency } from '../../composables/useCurrency';
import { useStaticText } from '../../composables/useStaticText';
import CalcField from '../calculators/CalcField.vue';
import ResidencyToggle from '../calculators/ResidencyToggle.vue';
import SourceTag from './SourceTag.vue';
import { RATE_MAX, RATE_MIN, RESIDENCY, TERM_MAX, minDownPct } from '../calculators/finance';
import { investment } from './investment';
import '../calculators/calculators.css';
import './insights.css';

/**
 * Property Details › Investment: buy-to-let yield, cash flow, total cost of ownership and financing
 * scenarios for a listing for sale. `insights` (GET /api/properties/{slug}/insights) seeds the
 * expected rent and the cost assumptions; every one of them can be changed.
 */
const props = defineProps({
    price: { type: Number, required: true },
    sqft: { type: Number, default: 0 },
    insights: { type: Object, default: null },
});

const { t } = useStaticText();
const { formatPrice } = useCurrency();

const FALLBACK_YIELD = 6; // % — only when there are no rentals to estimate from
const rentEstimate = computed(() => props.insights?.estimates?.annual_rent || null);
const assumptions = computed(() => props.insights?.assumptions || { service_charge_psf: 12, maintenance_pct: 1, vacancy_pct: 5 });

const rent = ref(0);
const rentTouched = ref(false);
const serviceCharges = ref(0);
const maintenancePct = ref(1);
const vacancyPct = ref(5);
const residency = ref('resident');
const downPct = ref(20);
const rate = ref(RESIDENCY.resident.rate);
const years = ref(TERM_MAX);
const holdYears = ref(5);
const feesOpen = ref(false);

const rentMax = computed(() => Math.max(100000, Math.ceil(props.price * 0.25 / 1000) * 1000));
const serviceMax = computed(() => Math.max(50000, Math.ceil(props.price * 0.03 / 1000) * 1000));

// Starting values arrive with the insights (they load after the page); a value the visitor has
// already moved is left alone.
watch([() => props.price, rentEstimate], () => {
    if (rentTouched.value) return;
    rent.value = Math.round(rentEstimate.value?.amount || props.price * FALLBACK_YIELD / 100);
}, { immediate: true });
const rentModel = computed({
    get: () => rent.value,
    set: (value) => { rent.value = value; rentTouched.value = true; },
});
watch(assumptions, (a) => {
    serviceCharges.value = Math.round((props.sqft || 0) * a.service_charge_psf);
    maintenancePct.value = a.maintenance_pct;
    vacancyPct.value = a.vacancy_pct;
}, { immediate: true });

const minDown = computed(() => minDownPct(residency.value, props.price));
watch(residency, (key) => { downPct.value = minDown.value; rate.value = RESIDENCY[key].rate; });
watch(minDown, (min) => { if (downPct.value < min) downPct.value = min; }, { immediate: true });

const inputs = computed(() => ({
    price: props.price, rate: rate.value, years: years.value, rent: rent.value, serviceCharges: serviceCharges.value,
    maintenancePct: maintenancePct.value, vacancyPct: vacancyPct.value, holdYears: holdYears.value,
}));
const result = computed(() => investment({ ...inputs.value, downPct: downPct.value }));
const scenarios = computed(() => [...new Set([minDown.value, 40, 60, 100, downPct.value])]
    .filter((d) => d >= minDown.value && d <= 100).sort((a, b) => a - b)
    .map((d) => ({ downPct: d, ...investment({ ...inputs.value, downPct: d }) })));

const rentHint = computed(() => {
    if (rentTouched.value) return t('property_details.investment.rent_hint_own', 'Your figure.');
    if (rentEstimate.value) {
        return (rentEstimate.value.same_beds
            ? t('property_details.investment.rent_hint_beds', 'Median for the same bedrooms in this area, per sq.ft × this size.')
            : t('property_details.investment.rent_hint_area', 'Median for this property type in this area, per sq.ft × this size.'));
    }
    return t('property_details.investment.rent_hint_fallback', 'Not enough rentals nearby to estimate — a {pct}% yield is assumed. Ask the agent for the achievable rent.').replace('{pct}', FALLBACK_YIELD);
});
const serviceHint = computed(() => (props.sqft
    ? t('property_details.investment.service_hint', 'About {rate} / sq.ft a year, typical for this property type. Ask the agent for the building’s actual charges.').replace('{rate}', formatPrice(assumptions.value.service_charge_psf, '—'))
    : t('property_details.investment.service_hint_no_size', 'Size not listed — enter the building’s annual service charges.')));

const pct = (n) => `${(Number.isFinite(n) ? n : 0).toFixed(2)}%`;
const signed = (n) => `${n < 0 ? '−' : '+'}${formatPrice(Math.abs(n), '0')}`;
</script>
