<template>
    <Teleport to="body">
        <div class="crm-selection" :class="{ 'is-open': count > 0 }" role="region" :aria-label="label" :inert="!count || null">
            <strong>{{ count.toLocaleString() }} selected</strong>
            <button v-if="canSelectAll" type="button" class="btn btn-sm btn-outline-light" @click="$emit('select-all')">Select all {{ total.toLocaleString() }}</button>
            <slot />
            <button type="button" class="btn btn-sm btn-outline-light" aria-label="Clear selection" title="Clear selection" @click="$emit('clear')"><i class="fas fa-xmark"></i></button>
        </div>
    </Teleport>
</template>

<script setup>
/**
 * Floating bar while list rows are ticked — same look as Admin › Clients. The page's bulk actions go in
 * the slot; "Select all N" shows while `canSelectAll` (the whole page is ticked and more pages match).
 */
defineProps({
    count: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    canSelectAll: { type: Boolean, default: false },
    label: { type: String, default: 'Selected rows' },
});
defineEmits(['select-all', 'clear']);
</script>
