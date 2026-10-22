<template>
    <label class="form-label fw-semibold d-block">{{ label }} <span v-if="required" class="text-danger">*</span>
        <i v-if="locked" class="fas fa-lock text-muted ms-1 small" :title="f.lockTitle(field)"></i></label>
    <div v-if="options.length" class="option-buttons" role="radiogroup" :aria-label="label" :data-option-buttons="field">
        <template v-for="option in options" :key="option.value">
            <input :id="`opt-${field}-${option.value}`" v-model="f.form[field]" type="radio" class="btn-check" :value="option.value"
                   :disabled="locked || (disabledValues || []).includes(option.value)" @change="$emit('change', option.value)">
            <label :for="`opt-${field}-${option.value}`" class="option-button lang-aware-label">
                <i class="fas" :class="(icons || {})[option.value] || 'fa-circle-dot'"></i>
                <span>{{ option.labels[f.state.lang] || option.value }}</span>
            </label>
        </template>
    </div>
    <input v-else v-model="f.form[field]" type="text" class="form-control" placeholder="Free text (no options configured yet)" :readonly="locked">
    <div v-if="f.fieldError(field)" class="invalid-feedback d-block">{{ f.fieldError(field) }}</div>
</template>

<script setup>
/**
 * A short managed option list as big icon buttons (radio group) — Category, Offering type
 * (resources/views/portal/properties/_option-buttons). Labels follow the form's content language.
 */
import { computed, inject } from 'vue';

const props = defineProps({
    field: { type: String, required: true },
    label: { type: String, required: true },
    required: { type: Boolean, default: false },
    icons: { type: Object, default: null }, // option value => Font Awesome icon
    disabledValues: { type: Array, default: null },
});
defineEmits(['change']);

const f = inject('listingForm');
const options = computed(() => f.state.options.selects[props.field] ?? []);
const locked = computed(() => f.isLocked(props.field));
</script>
