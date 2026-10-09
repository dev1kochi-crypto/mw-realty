<template>
    <CrmModal :open="open" :title="`${item?.id ? 'Edit' : 'Add'} ${capitalized}`" @close="$emit('close')">
        <form id="masterItemForm" @submit.prevent="submit">
            <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Name</label>
                <input ref="nameInput" v-model="form.name" type="text" class="form-control" maxlength="100" required>
            </div>
            <div v-if="config.hasColor" class="mb-3">
                <label class="form-label fw-semibold small">Color</label>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <button v-for="color in COLOR_PRESETS" :key="color" type="button" class="rounded-circle border-0"
                            :style="{ background: color, width: '26px', height: '26px', outline: form.color === color ? '2px solid #1c2340' : 'none', outlineOffset: '2px' }"
                            :aria-label="color" @click="form.color = color"></button>
                    <input v-model="form.color" type="color" class="form-control form-control-color" title="Custom color">
                </div>
            </div>
            <div v-if="config.hasClosed" class="form-check form-switch">
                <input id="masterItemClosed" v-model="form.is_closed" class="form-check-input" type="checkbox">
                <label class="form-check-label small" for="masterItemClosed">Counts as closed (the lead is finished — won or lost)</label>
            </div>
        </form>
        <template #footer>
            <button type="button" class="btn btn-light" @click="$emit('close')">Cancel</button>
            <button type="submit" form="masterItemForm" class="btn btn-portal-primary" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                {{ item?.id ? 'Save' : `Add ${capitalized}` }}
            </button>
        </template>
    </CrmModal>
</template>

<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import CrmModal from '../../components/CrmModal.vue';
import { errorMessage } from '../../api/http';
import { COLOR_PRESETS } from './masterTypes';

const props = defineProps({
    open: { type: Boolean, default: false },
    item: { type: Object, default: null }, // null = add
    config: { type: Object, required: true },
    save: { type: Function, required: true },
});
const emit = defineEmits(['close', 'saved']);

const form = reactive({ name: '', color: COLOR_PRESETS[0], is_closed: false });
const saving = ref(false);
const error = ref('');
const nameInput = ref(null);
const capitalized = computed(() => props.config.singular.charAt(0).toUpperCase() + props.config.singular.slice(1));

watch(() => props.open, (open) => {
    if (!open) return;
    error.value = '';
    form.name = props.item?.name ?? '';
    form.color = props.item?.color ?? COLOR_PRESETS[0];
    form.is_closed = props.item?.is_closed ?? false;
    nextTick(() => nameInput.value?.focus());
});

function submit() {
    const data = { name: form.name.trim() };
    if (props.config.hasColor) data.color = form.color;
    if (props.config.hasClosed) data.is_closed = form.is_closed;

    saving.value = true;
    error.value = '';
    props.save(props.item, data)
        .then((res) => emit('saved', res.data.message))
        .catch((e) => {
            error.value = errorMessage(e);
        })
        .finally(() => {
            saving.value = false;
        });
}
</script>
