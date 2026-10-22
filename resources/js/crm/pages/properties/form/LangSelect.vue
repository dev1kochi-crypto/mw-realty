<template>
    <span ref="root" class="select2 select2-container select2-container--default select2-container--below"
          :class="{ 'select2-container--open': open, 'select2-container--focus': focused, 'select2-container--disabled': disabled }" dir="ltr" style="width: 100%;">
        <span class="selection">
            <span ref="selection" class="select2-selection select2-selection--single" :class="{ 'is-invalid': invalid }" role="combobox" :tabindex="disabled ? -1 : 0"
                  aria-haspopup="true" :aria-expanded="open" :aria-disabled="disabled" :id="id"
                  @click="toggle" @keydown="onSelectionKey" @focus="focused = true" @blur="focused = false">
                <span class="select2-selection__rendered" role="textbox" aria-readonly="true" :title="current ? labelOf(current) : null">
                    <button v-if="current && !disabled" type="button" class="select2-selection__clear" tabindex="-1" title="Remove item" @click.stop="choose(null)"><span aria-hidden="true">×</span></button>
                    <span v-if="!current" class="select2-selection__placeholder">{{ placeholder }}</span>
                    <template v-else>{{ labelOf(current) }}</template>
                </span>
                <span class="select2-selection__arrow" role="presentation"><b role="presentation"></b></span>
            </span>
        </span>
        <span class="dropdown-wrapper" aria-hidden="true"></span>
    </span>
    <Teleport to="body">
        <span v-if="open" ref="dropdown" class="select2-container select2-container--default select2-container--open" :style="dropdownStyle">
            <span class="select2-dropdown select2-dropdown--below" dir="ltr" :style="{ width: `${width}px` }">
                <span class="select2-search select2-search--dropdown">
                    <input ref="searchInput" v-model="term" class="select2-search__field" type="search" tabindex="0" autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" role="searchbox" aria-autocomplete="list" @keydown="onSearchKey">
                </span>
                <span class="select2-results">
                    <ul ref="list" class="select2-results__options" role="listbox">
                        <li v-for="(option, i) in filtered" :key="option.value" class="select2-results__option select2-results__option--selectable"
                            :class="{ 'select2-results__option--highlighted': i === highlighted, 'select2-results__option--selected': option.value === modelValue }"
                            role="option" :aria-selected="option.value === modelValue ? 'true' : 'false'" @mouseenter="highlighted = i" @mouseup="choose(option)">
                            <!-- Every configured language at once: the active one first, the rest muted on the right. -->
                            <div class="select2-bilingual-row">
                                <span class="select2-bilingual-primary">{{ labelOf(option) }}</span>
                                <span v-if="secondary(option)" class="select2-bilingual-secondary">{{ secondary(option) }}</span>
                            </div>
                        </li>
                        <li v-if="!filtered.length" class="select2-results__option select2-results__message" role="alert" aria-live="assertive">No results found</li>
                    </ul>
                </span>
            </span>
        </span>
    </Teleport>
</template>

<script setup>
/**
 * The property form's searchable dropdown — the select2 picker the Blade form used (same markup and
 * classes, so its styling carries over). Option text follows the form's content language; each row
 * also shows the other languages' labels. options: [{ value, labels: { en: …, ar: … } }].
 */
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    options: { type: Array, default: () => [] },
    lang: { type: String, default: 'en' },
    langs: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Search' },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    id: { type: String, default: null },
});
const emit = defineEmits(['update:modelValue', 'change']);

const root = ref(null);
const selection = ref(null);
const dropdown = ref(null);
const searchInput = ref(null);
const list = ref(null);
const open = ref(false);
const focused = ref(false);
const term = ref('');
const highlighted = ref(0);
const width = ref(0);
const dropdownStyle = ref({});

const labelOf = (option) => option.labels?.[props.lang] || Object.values(option.labels || {}).find(Boolean) || option.value;
const secondary = (option) => props.langs.filter((code) => code !== props.lang).map((code) => option.labels?.[code]).filter(Boolean).join(' / ');
const current = computed(() => props.options.find((o) => String(o.value) === String(props.modelValue ?? '')) || null);
const filtered = computed(() => {
    const q = term.value.trim().toLowerCase();
    return q ? props.options.filter((o) => Object.values(o.labels || {}).some((l) => String(l).toLowerCase().includes(q))) : props.options;
});

function place() {
    const rect = selection.value.getBoundingClientRect();
    width.value = rect.width;
    dropdownStyle.value = { position: 'fixed', top: `${rect.bottom}px`, left: `${rect.left}px`, zIndex: 1060 };
}

function onOutside(event) {
    if (root.value?.contains(event.target) || dropdown.value?.contains(event.target)) return;
    close();
}
function listen(on) {
    const method = on ? 'addEventListener' : 'removeEventListener';
    document[method]('mousedown', onOutside);
    window[method]('resize', close);
    window[method]('scroll', reposition, true);
}
function reposition(event) {
    if (dropdown.value?.contains(event.target)) return;
    place();
}

function show() {
    if (props.disabled || open.value) return;
    open.value = true;
    term.value = '';
    const index = filtered.value.findIndex((o) => String(o.value) === String(props.modelValue ?? ''));
    highlighted.value = Math.max(0, index);
    place();
    listen(true);
    nextTick(() => {
        searchInput.value?.focus();
        list.value?.children[highlighted.value]?.scrollIntoView({ block: 'nearest' });
    });
}

function close() {
    if (!open.value) return;
    open.value = false;
    listen(false);
}

function toggle() {
    open.value ? close() : show();
}

function choose(option) {
    const value = option ? option.value : '';
    emit('update:modelValue', value);
    emit('change', value);
    close();
    selection.value?.focus();
}

function move(delta) {
    if (!filtered.value.length) return;
    highlighted.value = (highlighted.value + delta + filtered.value.length) % filtered.value.length;
    nextTick(() => list.value?.children[highlighted.value]?.scrollIntoView({ block: 'nearest' }));
}

function onSearchKey(event) {
    if (event.key === 'ArrowDown') { event.preventDefault(); move(1); }
    else if (event.key === 'ArrowUp') { event.preventDefault(); move(-1); }
    else if (event.key === 'Enter') { event.preventDefault(); if (filtered.value[highlighted.value]) choose(filtered.value[highlighted.value]); }
    else if (event.key === 'Escape' || event.key === 'Tab') { close(); selection.value?.focus(); }
}

function onSelectionKey(event) {
    if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) {
        event.preventDefault();
        show();
    }
}

onBeforeUnmount(() => listen(false));

/** Used by the form's required-field check to open the picker that's still empty. */
defineExpose({ open: show, el: root });
</script>
