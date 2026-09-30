<script setup>
// Phone field with a flag / country-code picker (intl-tel-input). v-model = the number WITHOUT its
// code (7–13 digits); v-model:country-code = "+971" style dial code, sent as phone_country_code.
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import intlTelInput from 'intl-tel-input';
import 'intl-tel-input/build/css/intlTelInput.css';
import { PHONE_MAX_DIGITS, phoneError } from '../composables/useContactValidation';

const props = defineProps({
    modelValue: { type: String, default: '' },
    countryCode: { type: String, default: '+971' },
    id: { type: String, default: undefined },
    name: { type: String, default: 'phone' },
    placeholder: { type: String, default: '50 123 4567' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'update:countryCode']);

const input = ref(null);
let iti = null;

/** Country for a dial code — the main one when several share it (+1 → US, +7 → RU). */
function iso2For(code) {
    const dial = String(code || '').replace(/\D/g, '');
    const match = intlTelInput.getCountryData()
        .filter((c) => c.dialCode === dial)
        .sort((a, b) => (a.priority || 0) - (b.priority || 0))[0];
    return match ? match.iso2 : 'ae';
}

// The site stylesheet pins .iti inputs to a fixed left padding; size it to the actual "🇦🇪 +971 ⌄" button.
function fitPadding() {
    const selector = input.value?.parentElement?.querySelector('.iti__country-container');
    if (selector) input.value.style.setProperty('padding-left', `${selector.offsetWidth + 8}px`, 'important');
}

function emitCountry() {
    const dial = iti?.getSelectedCountryData()?.dialCode;
    if (dial) emit('update:countryCode', `+${dial}`);
    nextTick(fitPadding);
}

function onInput(event) {
    // Number only: digits with optional spaces/dashes, capped at the max digit count.
    let seen = 0;
    const value = event.target.value.replace(/[^\d\s-]/g, '').replace(/\d/g, (d) => (++seen <= PHONE_MAX_DIGITS ? d : ''));
    if (value !== event.target.value) event.target.value = value;
    emit('update:modelValue', value);
    event.target.setCustomValidity(phoneError(value, props.required) || '');
}

onMounted(() => {
    iti = intlTelInput(input.value, {
        initialCountry: iso2For(props.countryCode),
        separateDialCode: true,
        countryOrder: ['ae', 'sa', 'kw', 'bh', 'qa', 'om', 'in', 'gb', 'us'],
    });
    input.value.addEventListener('countrychange', emitCountry);
    emitCountry();
});

onBeforeUnmount(() => iti?.destroy());

// Parent resets the form (e.g. after a send) → mirror it, number and country alike.
watch(() => props.modelValue, (value) => {
    if (input.value && input.value.value !== (value || '')) input.value.value = value || '';
});
watch(() => props.countryCode, (code) => {
    if (iti && code && `+${iti.getSelectedCountryData()?.dialCode}` !== code) {
        iti.setCountry(iso2For(code));
        nextTick(fitPadding);
    }
});
</script>

<template>
    <div class="mw-phone-input">
        <input
            ref="input"
            type="tel"
            :id="id"
            :name="name"
            :value="modelValue"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled"
            autocomplete="tel-national"
            maxlength="20"
            @input="onInput"
        >
    </div>
</template>

<style>
.mw-phone-input { width: 100%; }
.mw-phone-input .iti { display: block; width: 100%; }
.mw-phone-input .iti input[type='tel'] { width: 100%; }
.mw-phone-input .iti__selected-dial-code { font-size: 15px; }
.iti--container, .mw-phone-input .iti__dropdown-content { z-index: 10050 !important; }
</style>
