<template>
    <div class="mc__card">
        <h4 class="mc__card-title">{{ t('calculators.upfront_title', 'Estimated upfront costs') }}</h4>
        <dl class="mc__rows">
            <div><dt>{{ t('mortgage_page.down_payment', 'Down payment') }}</dt><dd>{{ formatPrice(down, '—') }}</dd></div>
            <div>
                <dt>
                    <button type="button" class="mc__expand" :aria-expanded="open ? 'true' : 'false'" @click="open = !open">
                        {{ t('calculators.purchase_costs', 'Total purchase costs') }}
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                </dt>
                <dd>{{ formatPrice(fees.total, '—') }}</dd>
            </div>
            <div v-for="item in fees.items" v-show="open" :key="item.key" class="mc__rows-sub">
                <dt>{{ t(`calculators.fees.${item.key}`, item.label) }}</dt><dd>{{ formatPrice(item.amount, '—') }}</dd>
            </div>
            <div class="mc__rows-total"><dt>{{ t('calculators.upfront_total', 'Total amount required upfront') }}</dt><dd>{{ formatPrice(down + fees.total, '—') }}</dd></div>
        </dl>

        <template v-if="basis.length">
            <p class="mc__basis-title">{{ t('calculators.based_on', 'Calculations based on') }}</p>
            <dl class="mc__basis">
                <div v-for="item in basis" :key="item.label"><dt>{{ item.label }}</dt><dd>{{ item.value }}</dd></div>
            </dl>
        </template>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useCurrency } from '../../composables/useCurrency';
import { useStaticText } from '../../composables/useStaticText';
import { purchaseFees } from './finance';

/**
 * Down payment + purchase costs (each fee listed) = cash needed on day one, optionally followed by
 * the figures it was worked out from (`basis`: [{ label, value }]).
 */
const props = defineProps({
    price: { type: Number, required: true },
    loan: { type: Number, required: true },
    basis: { type: Array, default: () => [] },
});

const { t } = useStaticText();
const { formatPrice } = useCurrency();
const open = ref(false); // fees stay folded until "Total purchase costs" is clicked
const down = computed(() => Math.max(0, props.price - props.loan));
const fees = computed(() => purchaseFees(props.price, props.loan));
</script>
