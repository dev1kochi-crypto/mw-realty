<template>
    <div class="mc__field">
        <div class="mc__field-head">
            <label :for="id">{{ label }}</label>
            <div class="mc__amount" :class="{ 'mc__amount--sm': type !== 'money' }">
                <template v-if="type === 'money'">
                    <span>{{ selectedCurrency.code }}</span>
                    <input :id="id" type="text" inputmode="numeric" v-model="text" @change="commitMoney" @keyup.enter="commitMoney">
                    <strong v-if="badge" class="mc__badge">{{ badge }}</strong>
                </template>
                <template v-else>
                    <input :id="id" type="number" :min="min" :max="max" :step="inputStep || step" :value="modelValue" @input="onNumberInput" @change="commitNumber">
                    <span>{{ unit }}</span>
                </template>
            </div>
        </div>
        <input type="range" class="mc__range" :min="min" :max="max" :step="step" :value="modelValue" :style="fillStyle" :aria-label="label" @input="emit('update:modelValue', Number($event.target.value))">
        <div v-if="ends" class="mc__range-ends"><span>{{ endLabel(min) }}</span><span>{{ endLabel(max) }}</span></div>
        <p v-if="hint || $slots.hint" class="mc__hint"><slot name="hint">{{ hint }}</slot></p>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useCurrency } from '../../composables/useCurrency';
import { useStaticText } from '../../composables/useStaticText';
import { clamp } from './finance';

/**
 * One calculator input: label + typed value + slider. `money` values are AED, shown and typed in
 * the visitor's currency; `percent` / `years` / `number` are plain numbers with a unit.
 */
const props = defineProps({
    modelValue: { type: Number, required: true },
    label: { type: String, required: true },
    type: { type: String, default: 'money' },
    min: { type: Number, default: 0 },
    max: { type: Number, required: true },
    step: { type: Number, default: 1 },
    inputStep: { type: Number, default: 0 },
    ends: { type: Boolean, default: false },
    hint: { type: String, default: '' },
    badge: { type: String, default: '' }, // e.g. "20%" next to a money amount
});
const emit = defineEmits(['update:modelValue']);

const { t } = useStaticText();
const { selectedCurrency, formatAmount, formatCompact, parseToAed } = useCurrency();
const id = `mc-${Math.random().toString(36).slice(2, 9)}`;

const unit = computed(() => ({ percent: '%', years: t('calculators.years', 'yrs') }[props.type] || ''));
const endLabel = (n) => (props.type === 'money' ? formatCompact(n) : n);
const fillStyle = computed(() => ({ '--mc-fill': `${((props.modelValue - props.min) / (props.max - props.min || 1)) * 100}%` }));

// Money box shows the grouped amount in the selected currency; typed values commit on change/enter.
const text = ref('');
watch([() => props.modelValue, selectedCurrency], () => { text.value = formatAmount(props.modelValue); }, { immediate: true });
function commitMoney() {
    const aed = parseToAed(text.value);
    if (!Number.isNaN(aed)) emit('update:modelValue', clamp(aed, props.min, props.max));
    text.value = formatAmount(props.modelValue);
}

function onNumberInput(e) {
    const n = parseFloat(e.target.value);
    if (!Number.isNaN(n) && n >= props.min && n <= props.max) emit('update:modelValue', n);
}
function commitNumber(e) {
    let n = clamp(e.target.value, props.min, props.max);
    if (props.type === 'years') n = Math.round(n);
    e.target.value = n;
    emit('update:modelValue', n);
}
</script>
