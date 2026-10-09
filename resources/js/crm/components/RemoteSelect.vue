<template>
    <div ref="root" class="dropdown crm-remote-select">
        <!-- Custom trigger (e.g. the Leads table's stage pill): slot "trigger" with { selected, open }. -->
        <button v-if="$slots.trigger" type="button" :class="buttonClass" :aria-expanded="open" @click="toggle">
            <slot name="trigger" :selected="selected" :open="open" />
        </button>
        <button v-else type="button" class="form-select text-start text-truncate" :class="buttonClass" @click="toggle">
            <span v-if="selected" class="d-inline-flex align-items-center gap-2">
                <span v-if="selected.color" class="rounded-circle d-inline-block" :style="{ background: selected.color, width: '8px', height: '8px' }"></span>
                {{ selected.name }}
            </span>
            <span v-else class="text-muted">{{ placeholder }}</span>
        </button>
        <div class="dropdown-menu p-2" :class="[{ show: open }, $slots.trigger || fixed ? '' : 'w-100']" :style="menuStyle">
            <input ref="searchInput" v-model="search" type="search" class="form-control form-control-sm mb-2" placeholder="Search…">
            <div class="overflow-auto" style="max-height: 240px;" @scroll="onScroll">
                <button v-if="clearable && selected" type="button" class="dropdown-item text-muted" @click="choose(null)">{{ placeholder }}</button>
                <button v-for="option in options" :key="option.id" type="button" class="dropdown-item d-flex align-items-center gap-2"
                        :class="{ active: option.id === modelValue }" @click="choose(option)">
                    <span v-if="option.color" class="rounded-circle d-inline-block flex-shrink-0" :style="{ background: option.color, width: '8px', height: '8px' }"></span>
                    <span class="text-truncate">{{ option.name }}</span>
                </button>
                <div v-if="loading" class="text-center py-2"><span class="spinner-border spinner-border-sm text-muted"></span></div>
                <div v-else-if="!options.length" class="text-muted small px-2 py-1">No matches.</div>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * A dropdown over a server-side list: searched and paged on the server, 20 at a time, the next
 * page loading on scroll — never the whole list. `endpoint` answers {data: [{id, name, color?}], next_page}
 * (e.g. /leads/options/stages).
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import http from '../api/http';

const props = defineProps({
    endpoint: { type: String, required: true },
    modelValue: { type: [Number, String, null], default: null },
    // The current value's {id, name, color} — shown before the list has ever been opened.
    initial: { type: Object, default: null },
    placeholder: { type: String, default: 'Select…' },
    clearable: { type: Boolean, default: true },
    buttonClass: { type: [String, Object, Array], default: '' },
    // Inside a scrolling / overflow-hidden container (the Leads table, the filter bar): position the
    // menu against the window, at the trigger's width, so it isn't clipped.
    fixed: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'change']);

const root = ref(null);
const searchInput = ref(null);
const open = ref(false);
const search = ref('');
const options = ref([]);
const nextPage = ref(1);
const loading = ref(false);
const selected = ref(props.initial);
const position = ref(null);
const menuStyle = computed(() => ({
    minWidth: '220px',
    zIndex: 1060,
    ...(props.fixed && position.value ? { position: 'fixed', top: `${position.value.top}px`, left: `${position.value.left}px`, width: `${position.value.width}px` } : {}),
}));
let searchTimer = null;
let requestId = 0;

watch(() => props.initial, (value) => {
    selected.value = value;
});

function fetchPage(reset = false) {
    if (reset) {
        options.value = [];
        nextPage.value = 1;
    }
    if (!nextPage.value || (loading.value && !reset)) {
        return;
    }
    const id = ++requestId;
    loading.value = true;
    http.get(props.endpoint, { params: { search: search.value || undefined, page: nextPage.value } })
        .then((res) => {
            if (id !== requestId) return;
            options.value.push(...res.data.data);
            nextPage.value = res.data.next_page;
        })
        .finally(() => {
            if (id === requestId) loading.value = false;
        });
}

function toggle() {
    open.value = !open.value;
    if (open.value && props.fixed && root.value) {
        const rect = root.value.getBoundingClientRect();
        const width = Math.max(rect.width, 220);
        position.value = { top: rect.bottom + 4, left: Math.min(rect.left, window.innerWidth - width - 8), width };
    }
    if (open.value) {
        if (!options.value.length) fetchPage(true);
        nextTick(() => searchInput.value?.focus());
    }
}

function onScroll(event) {
    const el = event.target;
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 24) fetchPage();
}

function choose(option) {
    selected.value = option;
    open.value = false;
    emit('update:modelValue', option?.id ?? null);
    emit('change', option);
}

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => fetchPage(true), 250);
});

function onDocumentClick(event) {
    if (open.value && root.value && !root.value.contains(event.target)) open.value = false;
}
function onAnyScroll(event) {
    if (open.value && props.fixed && !root.value?.contains(event.target)) open.value = false;
}
onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    window.addEventListener('scroll', onAnyScroll, true);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    window.removeEventListener('scroll', onAnyScroll, true);
    clearTimeout(searchTimer);
});
</script>
