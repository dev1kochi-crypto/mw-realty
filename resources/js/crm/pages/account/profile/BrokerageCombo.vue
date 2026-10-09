<template>
    <!-- Just the brokerage named for RERA/KYC. Joining an agency (and its plan) needs the agency's
         acceptance and admin approval — see My Agency. -->
    <div ref="root" class="brokerage-combo">
        <input :value="modelValue" type="text" name="affiliated_brokerage" class="form-control form-control-sm" maxlength="255" autocomplete="off"
               placeholder="Search agencies on MW Realty, or type your brokerage name" role="combobox" :aria-expanded="open ? 'true' : 'false'"
               @focus="onFocus" @input="onInput" @keydown="onKeydown">
        <div v-if="open" class="brokerage-combo__list" role="listbox" @mousedown.prevent @scroll="onScroll">
            <button v-if="showTyped" type="button" class="brokerage-combo__item brokerage-combo__item--typed" @click="pick(term)"><i class="fas fa-pen"></i>Use “{{ term }}”</button>
            <button v-for="agency in results" :key="agency.id" type="button" class="brokerage-combo__item" @click="pick(agency.text)"><i class="fas fa-building"></i>{{ agency.text }}</button>
            <div v-if="status" class="brokerage-combo__status">{{ status }}</div>
        </div>
    </div>
</template>

<script setup>
/**
 * Affiliated Brokerage (portal/profile): suggests registered agencies (GET /profile/brokerages,
 * 20 at a time, more on scroll); any typed name is kept as-is. Picking one only fills the text —
 * it never links the account.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import http from '../../../api/http';

const props = defineProps({ modelValue: { type: String, default: '' } });
const emit = defineEmits(['update:modelValue']);

const root = ref(null);
const open = ref(false);
const results = ref([]);
const status = ref('');
const term = ref('');
let page = 1;
let more = false;
let loading = false;
let timer = null;
let requestNo = 0;

const showTyped = computed(() => term.value && !status.value.startsWith('Searching')
    && !results.value.some((a) => a.text.toLowerCase() === term.value.toLowerCase()));

function load(reset) {
    if (loading && !reset) return;
    if (reset) { page = 1; results.value = []; }
    loading = true;
    const mine = ++requestNo;
    status.value = 'Searching…';
    http.get('/profile/brokerages', { params: { q: term.value, page } })
        .then((res) => {
            if (mine !== requestNo) return;
            results.value.push(...res.data.results);
            status.value = page === 1 && !res.data.results.length && !term.value ? 'No agencies yet — type your brokerage name.' : '';
            more = res.data.more;
            page += 1;
        })
        .catch(() => { if (mine === requestNo) status.value = 'Could not load agencies — you can still type the name.'; })
        .finally(() => { if (mine === requestNo) loading = false; });
}

function onFocus() {
    term.value = (props.modelValue || '').trim();
    open.value = true;
    load(true);
}

function onInput(event) {
    emit('update:modelValue', event.target.value);
    open.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => { term.value = event.target.value.trim(); load(true); }, 250);
}

function onKeydown(event) {
    if (event.key === 'Escape' || event.key === 'Tab') open.value = false;
}

function onScroll(event) {
    const list = event.target;
    if (more && !loading && list.scrollTop + list.clientHeight >= list.scrollHeight - 30) load(false);
}

function pick(value) {
    emit('update:modelValue', value);
    open.value = false;
}

// Close on a click outside — not on blur, so switching windows (e.g. a screenshot) keeps it open.
function onPointerDown(event) {
    if (root.value && !root.value.contains(event.target)) open.value = false;
}
onMounted(() => document.addEventListener('pointerdown', onPointerDown));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onPointerDown));
</script>
