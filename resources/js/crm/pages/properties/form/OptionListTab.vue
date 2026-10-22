<template>
    <div class="property-tab-pane-head d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <div class="property-tab-pane-title">{{ title }}</div>
            <div class="property-tab-pane-hint">{{ hint }} <span class="option-picked-count">{{ picked.length }}</span> selected</div>
        </div>
        <input v-if="choices.length > 8" v-model="search" type="search" class="form-control form-control-sm option-search" :placeholder="`Search ${title.toLowerCase()}`" style="max-width: 220px;">
    </div>
    <div class="option-check-grid">
        <label v-for="option in choices" v-show="matches(option)" :key="option.value" class="option-check" :class="{ 'is-off': !option.status }">
            <input v-model="f.form[column]" type="checkbox" :value="option.value">
            <span class="option-check-icon">
                <img v-if="option.icon" :src="option.icon" alt="" width="22" height="22" loading="lazy">
                <i v-else class="fas fa-check"></i>
            </span>
            <span class="option-check-label">
                {{ option.label }}
                <small v-if="!option.status" class="d-block text-muted">No longer offered for new listings</small>
            </span>
            <i class="fas fa-circle-check option-check-tick"></i>
        </label>
        <div v-if="!choices.length" class="portal-empty">
            No {{ title.toLowerCase() }} options yet.
            <template v-if="f.state.options.is_admin"><RouterLink :to="{ name: 'master.property-options', query: { list: listKey } }" target="_blank">Add them in Master › Property Options</RouterLink>.</template>
        </div>
    </div>
    <div v-if="f.state.options.is_admin" class="small text-muted mt-3"><i class="fas fa-circle-info me-1"></i>Manage this list (names and icons) in
        <RouterLink :to="{ name: 'master.property-options', query: { list: listKey } }" target="_blank">Master › Property Options</RouterLink>.</div>
</template>

<script setup>
/** Property form › Amenities / Easy Access / Attributes: tick options from Master › Property Options. */
import { computed, inject, ref } from 'vue';

const props = defineProps({
    listKey: { type: String, required: true }, // amenity | easy_access | property_attribute
    title: { type: String, required: true },
    hint: { type: String, required: true },
});

const f = inject('listingForm');
const search = ref('');
const list = computed(() => f.state.options.option_lists[props.listKey]);
const column = computed(() => list.value.column);
const picked = computed(() => f.form[column.value]);
// Active options, plus any switched-off one this listing already has (so saving doesn't drop it).
const choices = computed(() => list.value.options.filter((o) => o.status || picked.value.includes(o.value)));
const matches = (option) => !search.value.trim() || option.search.includes(search.value.trim().toLowerCase());
</script>
