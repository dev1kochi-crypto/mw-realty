<template>
    <div class="mc__field">
        <span class="mc__label">{{ t('calculators.residency', 'Residency status') }}</span>
        <div class="mc__toggle" role="radiogroup" :aria-label="t('calculators.residency', 'Residency status')">
            <button v-for="option in options" :key="option.key" type="button" role="radio" :aria-checked="modelValue === option.key ? 'true' : 'false'"
                :class="{ 'is-active': modelValue === option.key }" @click="emit('update:modelValue', option.key)">{{ option.label }}</button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useStaticText } from '../../composables/useStaticText';

defineProps({ modelValue: { type: String, required: true } });
const emit = defineEmits(['update:modelValue']);
const { t } = useStaticText();

// Keys match RESIDENCY in finance.js.
const options = computed(() => [
    { key: 'national', label: t('calculators.residency_national', 'UAE National') },
    { key: 'resident', label: t('calculators.residency_resident', 'UAE Resident') },
    { key: 'non-resident', label: t('calculators.residency_non_resident', 'Non-resident') },
]);
</script>
