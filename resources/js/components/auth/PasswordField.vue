<script setup>
import { ref } from 'vue';

defineProps({
    id: { type: String, required: true },
    name: { type: String, required: true },
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    autocomplete: { type: String, default: 'new-password' },
    required: { type: Boolean, default: true },
});
defineEmits(['update:modelValue']);

const visible = ref(false);
</script>

<template>
    <div class="mw-password-field">
        <input
            :id="id"
            :name="name"
            :type="visible ? 'text' : 'password'"
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :required="required"
        >
        <button
            type="button"
            class="mw-password-field__eye"
            :aria-label="visible ? 'Hide password' : 'Show password'"
            @click="visible = !visible"
        >
            <svg v-if="!visible" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
            </svg>
            <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.24 4.24M9.36 5.32A11.6 11.6 0 0 1 12 5c7 0 11 7 11 7a13.2 13.2 0 0 1-3.22 3.9M6.5 6.64C3.6 8.3 1 12 1 12s4 7 11 7a10.8 10.8 0 0 0 4.06-.79" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>
</template>
