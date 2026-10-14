<script setup>
import { computed } from 'vue';
import { filterParams } from '../composables/useSearchFilters';

// One admin search filter (Admin › Filters › Search Filters) in a listing page's "More filters"
// panel, drawn with the panel's own markup for each type. Values live in the page's filter state,
// read / written by query key through `get` / `set`; a checkbox filter stores "a,b".
const props = defineProps({
    filter: { type: Object, required: true },
    get: { type: Function, required: true },
    set: { type: Function, required: true },
    anyLabel: { type: String, default: 'Any' },
    minLabel: { type: String, default: 'Min' },
    maxLabel: { type: String, default: 'Max' },
});

const f = computed(() => props.filter);
const keys = computed(() => filterParams(f.value));
const value = computed(() => String(props.get(keys.value[0]) ?? ''));
const picked = computed(() => value.value.split(',').filter(Boolean));

function toggle(option) {
    const next = picked.value.includes(option) ? picked.value.filter((v) => v !== option) : [...picked.value, option];
    props.set(keys.value[0], next.join(','));
}
const countText = (o) => (o.count ? ` (${o.count})` : '');
// The legacy script.js moves pill buttons into a phone dropdown; a new key per option list makes Vue
// build fresh buttons (instead of patching moved ones) when the list changes.
const pillsKey = computed(() => (f.value.options || []).map((o) => `${o.value}:${o.label}`).join('|'));
</script>

<template>
    <section class="mw-filter-panel__section">
        <h3 class="mw-filter-panel__section-title">{{ f.label }}</h3>

        <div v-if="f.type === 'radio'" :key="pillsKey" class="mw-pill-tabs mw-pill-tabs--compact">
            <button type="button" data-tab :class="{ 'is-active': !value }" @click="set(keys[0], '')">{{ anyLabel }}</button>
            <button v-for="opt in f.options" :key="opt.value" type="button" data-tab :class="{ 'is-active': value === opt.value }" @click="set(keys[0], opt.value)">{{ opt.label }}</button>
        </div>

        <div v-else-if="f.type === 'select'" class="mw-filter-panel__select-wrap">
            <select class="mw-filter-panel__select" :aria-label="f.label" :value="value" @change="set(keys[0], $event.target.value)">
                <option value="">{{ f.placeholder || anyLabel }}</option>
                <option v-for="opt in f.options" :key="opt.value" :value="opt.value">{{ opt.label }}{{ countText(opt) }}</option>
            </select>
        </div>

        <div v-else-if="f.type === 'checkbox'" class="mw-filter-panel__checks">
            <label v-for="opt in f.options" :key="opt.value" class="mw-filter-panel__check">
                <input type="checkbox" :name="f.key" :value="opt.value" :checked="picked.includes(opt.value)" @change="toggle(opt.value)">
                <span>{{ opt.label }}</span>
            </label>
        </div>

        <label v-else-if="f.type === 'text'" class="mw-filter-panel__field">
            <input type="text" :name="f.key" :value="value" :placeholder="f.placeholder || ''" :aria-label="f.label" autocomplete="off" maxlength="100" @input="set(keys[0], $event.target.value)">
        </label>

        <div v-else-if="f.type === 'number'" class="mw-filter-panel__range-inputs">
            <label>
                <span>{{ f.compare === 'max' ? maxLabel : (f.compare === 'exact' ? f.label : minLabel) }}</span>
                <input type="number" inputmode="numeric" :min="f.min ?? 0" :max="f.max" :step="f.step || 1" :value="value" :placeholder="f.placeholder || ''" :aria-label="f.label" @input="set(keys[0], $event.target.value)">
            </label>
        </div>

        <div v-else-if="f.type === 'range'" class="mw-filter-panel__range-inputs">
            <label>
                <span>{{ minLabel }}</span>
                <input type="number" inputmode="numeric" :min="f.min ?? 0" :max="f.max" :step="f.step || 1" :value="get(keys[0]) ?? ''" :placeholder="String(f.min ?? 0)" :aria-label="`${f.label} — ${minLabel}`" @input="set(keys[0], $event.target.value)">
            </label>
            <label>
                <span>{{ maxLabel }}</span>
                <input type="number" inputmode="numeric" :min="f.min ?? 0" :max="f.max" :step="f.step || 1" :value="get(keys[1]) ?? ''" :placeholder="f.max ? String(f.max) : ''" :aria-label="`${f.label} — ${maxLabel}`" @input="set(keys[1], $event.target.value)">
            </label>
        </div>
    </section>
</template>
