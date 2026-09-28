<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useLanguages } from '../composables/useLanguages';
import { useStaticText } from '../composables/useStaticText';

/**
 * Location search box with live suggestions (cities, communities, full addresses) taken from the
 * properties table via GET /api/location-suggestions — used by the Home search, the Properties
 * listing and the Commercial page.
 *
 * v-model is the text in the box. `select` fires with the picked suggestion
 * ({ type: 'city'|'community'|'address', label, sub, count?, slug? }), or null once the visitor
 * types again (the text is then a free-text search). The parent decides what Search does with it.
 * Other attributes (class, id, placeholder, name…) go on the <input> so existing styling still applies.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: String, default: '' },
    scope: { type: String, default: 'all' }, // 'all' | 'commercial'
});
const emit = defineEmits(['update:modelValue', 'select', 'enter']);

const { selectedLanguage } = useLanguages();
const { t } = useStaticText();

const suggestions = ref([]);
const open = ref(false);
const active = ref(-1);
const loading = ref(false);
let timer = null;
let requestId = 0;
const listId = `loc-suggest-${Math.random().toString(36).slice(2, 8)}`;

const typeLabel = (type) => ({
    city: t('location_search.type_city', 'City'),
    community: t('location_search.type_community', 'Community'),
    address: t('location_search.type_address', 'Address'),
    property: t('location_search.type_property', 'Property'),
}[type] || type);

function fetchSuggestions(term) {
    const id = ++requestId;
    loading.value = true;
    window.axios.get('/api/location-suggestions', { params: { q: term, lang: selectedLanguage.value?.code, scope: props.scope } })
        .then((res) => {
            if (id !== requestId) return; // a newer keystroke's request supersedes this one
            suggestions.value = res.data.suggestions || [];
            active.value = -1;
            open.value = true;
        })
        .catch(() => { if (id === requestId) suggestions.value = []; })
        .finally(() => { if (id === requestId) loading.value = false; });
}

function onInput(e) {
    const value = e.target.value;
    emit('update:modelValue', value);
    emit('select', null);
    clearTimeout(timer);
    if (value.trim().length < 2) {
        suggestions.value = [];
        open.value = false;
        return;
    }
    timer = setTimeout(() => fetchSuggestions(value.trim()), 250);
}

function pick(s) {
    emit('update:modelValue', s.label);
    emit('select', s);
    open.value = false;
}

function onKeydown(e) {
    if (!open.value || !suggestions.value.length) {
        if (e.key === 'Enter') { e.preventDefault(); emit('enter'); }
        return;
    }
    if (e.key === 'ArrowDown') { e.preventDefault(); active.value = (active.value + 1) % suggestions.value.length; }
    else if (e.key === 'ArrowUp') { e.preventDefault(); active.value = (active.value - 1 + suggestions.value.length) % suggestions.value.length; }
    else if (e.key === 'Enter') {
        e.preventDefault();
        if (active.value >= 0) pick(suggestions.value[active.value]);
        else { open.value = false; emit('enter'); }
    } else if (e.key === 'Escape') { open.value = false; }
}

// Close shortly after blur so a click on a suggestion still registers.
function onBlur() { setTimeout(() => { open.value = false; }, 150); }
function onFocus() { if (suggestions.value.length && props.modelValue.trim().length >= 2) open.value = true; }

watch(() => props.modelValue, (v) => { if (!v) { suggestions.value = []; open.value = false; } });
onBeforeUnmount(() => clearTimeout(timer));

const showEmpty = computed(() => open.value && !loading.value && !suggestions.value.length && props.modelValue.trim().length >= 2);
</script>

<template>
    <div class="mw-loc">
        <input
            v-bind="$attrs"
            type="text"
            autocomplete="off"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="open"
            :aria-controls="listId"
            :aria-activedescendant="active >= 0 ? `${listId}-${active}` : undefined"
            :value="modelValue"
            @input="onInput"
            @keydown="onKeydown"
            @blur="onBlur"
            @focus="onFocus"
        >
        <ul v-if="open && (suggestions.length || showEmpty)" :id="listId" class="mw-loc__menu" role="listbox">
            <li
                v-for="(s, i) in suggestions"
                :id="`${listId}-${i}`"
                :key="`${s.type}:${s.label}:${s.slug || ''}`"
                class="mw-loc__item"
                :class="{ 'is-active': i === active }"
                role="option"
                :aria-selected="i === active"
                @mousedown.prevent="pick(s)"
                @mouseenter="active = i"
            >
                <span class="mw-loc__icon" :class="`mw-loc__icon--${s.type}`" aria-hidden="true">
                    <svg v-if="s.type === 'address'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5V21H3z"/><path d="M9 21v-6h6v6"/></svg>
                    <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                </span>
                <span class="mw-loc__text">
                    <span class="mw-loc__label">{{ s.label }}</span>
                    <span v-if="s.sub || s.count" class="mw-loc__sub">{{ s.sub }}{{ s.sub && s.count ? ' · ' : '' }}{{ s.count ? `${s.count} ${s.count === 1 ? t('location_search.listing', 'listing') : t('location_search.listings', 'listings')}` : '' }}</span>
                </span>
                <span class="mw-loc__type" :class="`mw-loc__type--${s.type}`">{{ typeLabel(s.type) }}</span>
            </li>
            <li v-if="showEmpty" class="mw-loc__empty">{{ t('location_search.no_match', 'No matching location, search will use your text') }}</li>
        </ul>
    </div>
</template>
