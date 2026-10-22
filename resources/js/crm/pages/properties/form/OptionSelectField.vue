<template>
    <label class="form-label fw-semibold" :for="`field-${name}`">
        {{ label }} <span v-if="required" class="text-danger">*</span>
        <i v-if="locked" class="fas fa-lock text-muted ms-1 small" :title="f.lockTitle(field)"></i>
    </label>
    <LangSelect v-if="options.length" :id="`field-${name}`" ref="select" v-model="f.form[name]" :options="options" :lang="f.state.lang" :langs="f.state.langs"
                :placeholder="placeholder || `Search ${label.toLowerCase()}`" :disabled="locked" :invalid="!!f.fieldError(name)" @change="$emit('change', $event)" />
    <input v-else :id="`field-${name}`" v-model="f.form[name]" type="text" class="form-control" placeholder="Free text (no options configured yet)" :readonly="locked">
    <div v-if="f.fieldError(name)" class="invalid-feedback d-block">{{ f.fieldError(name) }}</div>
</template>

<script setup>
/**
 * One managed dropdown on the property form (resources/views/portal/properties/_option-select) —
 * options from Master › Property Options or CMS filters, text in the form's content language. A
 * locked field (verified / approved permit) is shown disabled; its value is still saved.
 */
import { computed, inject, ref } from 'vue';
import LangSelect from './LangSelect.vue';

const props = defineProps({
    field: { type: String, required: true }, // option list key
    name: { type: String, default: null }, // form field, when it differs from the list key
    label: { type: String, required: true },
    required: { type: Boolean, default: false },
    placeholder: { type: String, default: null },
});
defineEmits(['change']);

const f = inject('listingForm');
const select = ref(null);
const name = computed(() => props.name || props.field);
const options = computed(() => f.state.options.selects[props.field] ?? []);
const locked = computed(() => f.isLocked(props.field) || f.isLocked(name.value));

defineExpose({ open: () => select.value?.open() });
</script>
