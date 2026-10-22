<template>
    <CrmModal :open="!!selection" content-class="border-0 shadow-lg" footer-class="border-0 pt-0" @close="close">
        <template #header="{ close: closeModal }">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="fas fa-share me-2"></i>Transfer {{ count === 1 ? 'lead' : `${count} leads` }}</h5>
                <button type="button" class="btn-close" aria-label="Close" @click="closeModal"></button>
            </div>
        </template>

        <div v-if="error" class="alert alert-danger small">{{ error }}</div>
        <p class="text-muted small mb-3">Each lead becomes a CRM lead of the chosen agency or agent — they're notified, and their chat history and website insights go with it.</p>
        <label class="form-label small fw-semibold" for="wlTargetSearch">Agency or agent</label>
        <div ref="picker" class="wl-picker mb-3">
            <input id="wlTargetSearch" v-model="search" type="search" class="form-control" placeholder="Search agencies and agents…" autocomplete="off" @focus="onFocus" @input="onInput">
            <div v-show="listOpen" class="wl-picker-list" @scroll="onScroll">
                <button v-for="item in options" :key="item.id" type="button" class="wl-picker-item" @click="choose(item)">
                    <span class="fw-semibold">{{ item.name }}</span><small>{{ item.meta }}</small>
                </button>
                <div v-if="loaded && !options.length" class="wl-picker-item text-muted">No matches.</div>
            </div>
        </div>
        <label class="form-label small fw-semibold" for="wlNote">Note <span class="text-muted fw-normal">(optional)</span></label>
        <textarea id="wlNote" v-model="note" class="form-control" rows="3" maxlength="1000" placeholder="Anything the agency should know"></textarea>

        <template #footer>
            <button type="button" class="btn btn-outline-secondary px-4" @click="close">Cancel</button>
            <button type="button" class="btn btn-portal-primary px-4" :disabled="!target || saving" @click="submit">
                <template v-if="saving"><span class="spinner-border spinner-border-sm me-1"></span>Transferring…</template>
                <template v-else><i class="fas fa-share me-1"></i>Transfer</template>
            </button>
        </template>
    </CrmModal>
</template>

<script setup>
/**
 * Transfer dialog — shared by the Website Leads listing (ticked leads / every lead matching the
 * filters) and a website lead's profile (that one lead). `selection` = { ids: [...] } or
 * { all: true, exclude: [...] }, plus `count`; null closes it. The agency / agent picker searches on
 * the server, 20 at a time, loading more on scroll.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import http, { errorMessage } from '../../api/http';
import CrmModal from '../../components/CrmModal.vue';

const props = defineProps({
    selection: { type: Object, default: null },
    // The listing's filters — "all matching" is resolved from them on the server.
    filters: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['close', 'done']);

const picker = ref(null);
const search = ref('');
const note = ref('');
const target = ref(null);
const options = ref([]);
const listOpen = ref(false);
const loaded = ref(false);
const saving = ref(false);
const error = ref('');
let nextPage = 1;
let loading = false;
let request = 0;
let timer = null;

const count = computed(() => props.selection?.count || props.selection?.ids?.length || 0);

watch(() => props.selection, (selection) => {
    if (!selection) return;
    search.value = '';
    note.value = '';
    target.value = null;
    options.value = [];
    loaded.value = false;
    listOpen.value = false;
    error.value = '';
});

function load(reset) {
    if (loading && !reset) return;
    if (reset) {
        nextPage = 1;
        options.value = [];
        loaded.value = false;
    }
    if (!nextPage) return;
    loading = true;
    const id = ++request;
    http.get('/website-leads/transfer-targets', { params: { q: search.value.trim(), page: nextPage } })
        .then((res) => {
            if (id !== request) return;
            options.value.push(...res.data.data);
            nextPage = res.data.next_page;
            loaded.value = true;
            listOpen.value = true;
        })
        .finally(() => {
            if (id === request) loading = false;
        });
}

function onFocus() {
    if (!loaded.value) load(true);
    else listOpen.value = true;
}

function onInput() {
    target.value = null;
    clearTimeout(timer);
    timer = setTimeout(() => load(true), 250);
}

function onScroll(event) {
    const list = event.target;
    if (nextPage && list.scrollTop + list.clientHeight >= list.scrollHeight - 30) load(false);
}

function choose(item) {
    target.value = item;
    search.value = `${item.name} (${item.meta})`;
    listOpen.value = false;
}

function onDocumentClick(event) {
    if (picker.value && !picker.value.contains(event.target)) listOpen.value = false;
}

function close() {
    if (!saving.value) emit('close');
}

function submit() {
    if (!target.value) return;
    saving.value = true;
    error.value = '';
    const selection = props.selection;
    const body = { portal_user_id: target.value.id, note: note.value || null };
    if (selection.all) {
        Object.assign(body, { all: 1, exclude: selection.exclude ?? [], status: props.filters.status, source: props.filters.source || null, q: props.filters.q || null });
    } else {
        body.ids = selection.ids;
    }
    http.post('/website-leads/transfer', body)
        .then((res) => emit('done', res.data.message))
        .catch((e) => {
            error.value = errorMessage(e);
        })
        .finally(() => {
            saving.value = false;
        });
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    clearTimeout(timer);
});
</script>
