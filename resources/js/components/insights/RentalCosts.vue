<template>
    <div class="mc">
        <div class="mc__layout">
            <div class="mc__card mc__inputs">
                <CalcField v-model="annual" :label="t('property_details.rental_costs.annual_rent', 'Annual rent')" :min="0" :max="annualMax" :step="1000" uncapped
                    :hint="periodHint" />
                <CalcField v-model="agencyPct" type="percent" :label="t('property_details.rental_costs.agency_pct', 'Agency fee')" :min="0" :max="10" :step="0.5"
                    :hint="t('property_details.rental_costs.agency_hint', 'Usually 5% of the annual rent in Dubai, plus VAT.')" />
                <div class="mc__field">
                    <span class="mc__label">{{ t('property_details.rental_costs.cheques_label', 'Number of cheques') }}</span>
                    <div class="mc__toggle" role="radiogroup" :aria-label="t('property_details.rental_costs.cheques_label', 'Number of cheques')">
                        <button v-for="n in chequeOptions" :key="n" type="button" role="radio" :aria-checked="cheques === n ? 'true' : 'false'"
                            :class="{ 'is-active': cheques === n }" @click="cheques = n">{{ n }}</button>
                    </div>
                    <p class="mc__hint">{{ t('property_details.rental_costs.cheques_hint', 'Fewer cheques usually get a lower rent; the first one is paid on signing.') }}</p>
                </div>
                <CalcField v-model="depositModel" :label="t('property_details.rental_costs.deposit_pct', 'Security deposit')" :badge="`${depositShare}%`" :min="0" :max="depositMax" :step="500" uncapped
                    :hint="deposit ? t('property_details.rental_costs.deposit_listing_hint', 'As stated on this listing. Refunded at the end of the tenancy.') : t('property_details.rental_costs.deposit_hint', 'Usually 5% of the rent unfurnished, 10% furnished.')" />
                <div class="mc__field">
                    <span class="mc__label">{{ t('property_details.rental_costs.home_type', 'Utility connection') }}</span>
                    <div class="mc__toggle" role="radiogroup" :aria-label="t('property_details.rental_costs.home_type', 'Utility connection')">
                        <button type="button" role="radio" :aria-checked="!villa ? 'true' : 'false'" :class="{ 'is-active': !villa }" @click="villa = false">{{ t('property_details.rental_costs.apartment', 'Apartment') }}</button>
                        <button type="button" role="radio" :aria-checked="villa ? 'true' : 'false'" :class="{ 'is-active': villa }" @click="villa = true">{{ t('property_details.rental_costs.villa', 'Villa / townhouse') }}</button>
                    </div>
                    <p class="mc__hint">{{ t('property_details.rental_costs.utility_hint', 'Refundable DEWA / utility deposit: about AED 2,000 for an apartment, 4,000 for a villa.') }}</p>
                </div>
            </div>

            <div class="mc__results">
                <div class="mc__card mc__monthly">
                    <p class="mc__monthly-label">{{ t('property_details.rental_costs.move_in', 'Cash needed to move in') }}</p>
                    <p class="mc__monthly-value">
                        {{ formatPrice(firstPayment + costs.moveIn, '—') }}
                        <small>{{ t('property_details.rental_costs.move_in_note', 'First rent payment ({cheques}) plus the costs below').replace('{cheques}', chequeLabel) }}</small>
                    </p>
                    <dl class="mc__rows">
                        <div><dt>{{ t('property_details.rental_costs.first_payment', 'First rent payment') }}</dt><dd>{{ formatPrice(firstPayment, '—') }}</dd></div>
                        <div v-for="item in costs.items" :key="item.key">
                            <dt>{{ t(`property_details.rental_costs.items.${item.key}`, item.label).replace('{pct}', agencyPct) }}</dt><dd>{{ formatPrice(item.amount, '—') }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="mc__card">
                    <h4 class="mc__card-title">{{ t('property_details.rental_costs.year_title', 'Cost of the first year') }}</h4>
                    <dl class="mc__rows">
                        <div><dt>{{ t('property_details.rental_costs.annual_rent', 'Annual rent') }}</dt><dd>{{ formatPrice(annual, '—') }}</dd></div>
                        <div v-if="costs.housingFee"><dt>{{ t('property_details.rental_costs.housing_fee', 'Municipality housing fee ({pct}%, billed with utilities)').replace('{pct}', costs.housingPct) }}</dt><dd>{{ formatPrice(costs.housingFee, '—') }}</dd></div>
                        <div><dt>{{ t('property_details.rental_costs.one_off', 'Agency fee & registration') }}</dt><dd>{{ formatPrice(costs.moveIn - costs.refundable, '—') }}</dd></div>
                        <div class="mc__rows-total"><dt>{{ t('property_details.rental_costs.year_total', 'Total for the year') }}</dt><dd>{{ formatPrice(costs.yearOne, '—') }}</dd></div>
                        <div><dt>{{ t('property_details.rental_costs.per_month', 'Works out per month') }}</dt><dd>{{ formatPrice(costs.yearOne / 12, '—') }}</dd></div>
                        <div><dt>{{ t('property_details.rental_costs.refundable', 'Deposits you get back at the end') }}</dt><dd>{{ formatPrice(costs.refundable, '—') }}</dd></div>
                    </dl>
                </div>
            </div>
        </div>
        <p class="mc__disclaimer">{{ t('property_details.rental_costs.disclaimer', 'Typical UAE fees for guidance only. Utility deposits, registration fees and agency terms vary — confirm them with the agent.') }}</p>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useCurrency } from '../../composables/useCurrency';
import { useStaticText } from '../../composables/useStaticText';
import CalcField from '../calculators/CalcField.vue';
import { annualRent, rentalCosts } from './investment';
import '../calculators/calculators.css';
import './insights.css';

/** Property Details › Rental costs: what a tenant pays to move in and over the first year. */
const props = defineProps({
    price: { type: Number, required: true },        // per rental period
    rentalPeriod: { type: String, default: null },  // yearly | monthly (weekly / daily stays aren't shown this)
    deposit: { type: Number, default: 0 },          // the listing's own security deposit, when stated
    cheques: { type: [Number, String], default: null },
    emirate: { type: String, default: null },
    propertyType: { type: String, default: null },
    furnished: { type: Boolean, default: false },
});

const { t } = useStaticText();
const { formatPrice } = useCurrency();

const annual = ref(0);
const agencyPct = ref(5);
watch(() => [props.price, props.rentalPeriod], () => { annual.value = Math.round(annualRent(props.price, props.rentalPeriod)); }, { immediate: true });
// Slider ranges come from the listing's own rent, never the value being edited — otherwise the range
// grows with every typed amount and the handle never moves (and nothing caps the input).
const listingAnnual = computed(() => annualRent(props.price, props.rentalPeriod));
const annualMax = computed(() => Math.max(500000, Math.ceil(listingAnnual.value * 3 / 10000) * 10000));

// Cheques: the listing's own count (monthly rents default to 12), changeable.
const CHEQUES = [1, 2, 4, 6, 12];
const listingCheques = computed(() => parseInt(props.cheques, 10) || (props.rentalPeriod === 'monthly' ? 12 : 1));
const chequeOptions = computed(() => [...new Set([...CHEQUES, listingCheques.value])].sort((a, b) => a - b));
const cheques = ref(1);
watch(listingCheques, (n) => { cheques.value = n; }, { immediate: true });
const firstPayment = computed(() => annual.value / cheques.value);
const chequeLabel = computed(() => (cheques.value === 1
    ? t('property_details.rental_costs.one_cheque', '1 cheque')
    : t('property_details.rental_costs.cheques', '1 of {n} cheques').replace('{n}', cheques.value)));

// Security deposit (AED): the listing's own amount, else 5% of the rent (10% furnished). It follows
// the rent until the visitor sets it themselves.
const depositAed = ref(0);
const depositTouched = ref(false);
watch(() => [annual.value, props.deposit, props.furnished], () => {
    if (depositTouched.value) return;
    depositAed.value = props.deposit > 0 ? props.deposit : Math.round(annual.value * (props.furnished ? 10 : 5) / 100);
}, { immediate: true });
const depositModel = computed({
    get: () => depositAed.value,
    set: (value) => { depositAed.value = value; depositTouched.value = true; },
});
const depositMax = computed(() => Math.max(10000, Math.ceil(annualMax.value * 0.2 / 500) * 500, props.deposit || 0));
const depositShare = computed(() => (annual.value ? Math.round(depositAed.value / annual.value * 100) : 0));

const villa = ref(false);
watch(() => props.propertyType, (type) => { villa.value = ['villa', 'townhouse'].includes(type); }, { immediate: true });

const costs = computed(() => rentalCosts({
    annual: annual.value,
    agencyPct: agencyPct.value,
    depositPct: 0,
    deposit: depositAed.value,
    emirate: props.emirate,
    villa: villa.value,
}));

const periodHint = computed(() => (props.rentalPeriod === 'monthly'
    ? t('property_details.rental_costs.monthly_hint', 'Listed per month — shown here for 12 months.')
    : ''));
</script>
