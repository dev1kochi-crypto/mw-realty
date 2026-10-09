<template>
    <CrmModal :open="open" content-class="portal-picker" bare @close="$emit('close')">
        <template #header="{ close }">
            <div class="portal-picker-head">
                <h5 class="modal-title">{{ isStages ? 'Assign Stage' : 'Assign Tag' }}</h5>
                <button type="button" class="portal-picker-new" @click="creating = !creating"><i class="fas fa-plus"></i> <span>Add New {{ isStages ? 'Stage' : 'Tag' }}</span></button>
                <button type="button" class="btn-close ms-auto" aria-label="Close" @click="close"></button>
            </div>
        </template>
        <div class="portal-picker-sub">
            {{ isStages ? 'One stage' : 'Every ticked tag' }} will be {{ isStages ? 'set on' : 'added to' }} {{ count }} selected {{ plural('lead', count) }}.
        </div>
        <form v-if="creating" class="portal-picker-create" novalidate @submit.prevent="create">
            <input v-model="newColor" type="color" class="form-control form-control-color" title="Colour" aria-label="Colour">
            <input v-model="newName" type="text" class="form-control form-control-sm" maxlength="100" :placeholder="`New ${isStages ? 'stage' : 'tag'} name`" required aria-label="Name">
            <button type="submit" class="btn btn-portal-primary btn-sm text-nowrap">Add</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" @click="creating = false">Cancel</button>
        </form>
        <div class="portal-picker-search">
            <input v-model="search" type="search" class="form-control" :placeholder="`Search ${isStages ? 'Stages' : 'Tags'}`" autocomplete="off" aria-label="Search">
            <span class="portal-picker-search-btn" aria-hidden="true"><i class="fas fa-magnifying-glass"></i></span>
        </div>
        <div class="portal-picker-list" role="listbox" @scroll="onScroll">
            <label v-for="item in items" :key="item.id" class="portal-picker-item" role="option" :aria-selected="chosen.has(item.id)">
                <span class="portal-picker-initials" :style="{ '--item': item.color || '#ca2844' }">{{ initials(item.name) }}</span>
                <span class="portal-picker-text"><span class="portal-picker-name">{{ item.name }}</span><span class="portal-picker-count">{{ item.leads || 0 }} {{ plural('lead', item.leads || 0) }}</span></span>
                <input :type="isStages ? 'radio' : 'checkbox'" class="form-check-input" :checked="chosen.has(item.id)" @change="pick(item)">
            </label>
        </div>
        <div class="portal-picker-status">
            <span v-if="loading" class="spinner-border spinner-border-sm text-muted"></span>
            <template v-else-if="!items.length">{{ search ? 'No matches.' : `No ${isStages ? 'stages' : 'tags'} yet.` }}</template>
        </div>
        <div class="portal-picker-foot">
            <span class="portal-picker-chosen">{{ chosenLabel }}</span>
            <button type="button" class="btn btn-outline-secondary btn-sm px-3" @click="$emit('close')">Cancel</button>
            <button type="button" class="btn btn-portal-primary btn-sm px-3" :disabled="!chosen.size || applying" @click="apply">
                <span v-if="applying" class="spinner-border spinner-border-sm me-1"></span>Apply ({{ count }})
            </button>
        </div>
    </CrmModal>
</template>

<script setup>
/**
 * Toolbar Assign Stage / Assign Tag for the selected leads (resources/views/portal/crm/leads/_lead_master_picker_modal):
 * stages → exactly one stage for every selected lead (POST /leads/bulk/stage);
 * tags → any number of tags added to every lead (POST /leads/bulk/tags). Lists page on the server.
 */
import { computed, ref, watch } from 'vue';
import CrmModal from '../../../components/CrmModal.vue';
import http, { errorMessage } from '../../../api/http';
import { useToast } from '../../../composables/useToast';
import { initials, plural } from '../../../utils/format';

const props = defineProps({
    open: { type: Boolean, default: false },
    mode: { type: String, default: 'stages' }, // stages | tags
    count: { type: Number, default: 0 },
    selection: { type: Function, required: true }, // → the selection body (ids / select_all + filters)
});
const emit = defineEmits(['close', 'applied']);

const { success, error: toastError } = useToast();
const isStages = computed(() => props.mode === 'stages');
const items = ref([]);
const nextPage = ref(1);
const loading = ref(false);
const search = ref('');
const chosen = ref(new Set());
const chosenNames = ref({});
const creating = ref(false);
const newName = ref('');
const newColor = ref('#14b8a6');
const applying = ref(false);
let timer = null;
let requestId = 0;

const chosenLabel = computed(() => Object.entries(chosenNames.value).filter(([id]) => chosen.value.has(Number(id))).map(([, name]) => name).join(', '));

function fetchPage(reset) {
    if (reset) {
        items.value = [];
        nextPage.value = 1;
    }
    if (!nextPage.value) return;
    const id = ++requestId;
    loading.value = true;
    http.get(`/leads/options/${props.mode}`, { params: { search: search.value || undefined, page: nextPage.value } })
        .then((res) => {
            if (id !== requestId) return;
            items.value.push(...res.data.data);
            nextPage.value = res.data.next_page;
        })
        .finally(() => {
            if (id === requestId) loading.value = false;
        });
}

function onScroll(event) {
    const el = event.target;
    if (!loading.value && nextPage.value && el.scrollTop + el.clientHeight >= el.scrollHeight - 24) fetchPage(false);
}

function pick(item) {
    const next = isStages.value ? new Set() : new Set(chosen.value);
    next.has(item.id) ? next.delete(item.id) : next.add(item.id);
    chosen.value = next;
    chosenNames.value = { ...chosenNames.value, [item.id]: item.name };
}

function create() {
    const name = newName.value.trim();
    if (!name) return;
    http.post(`/master/${props.mode}`, { name, color: newColor.value })
        .then((res) => {
            const item = res.data.stage ?? res.data.tag;
            items.value.unshift({ ...item, leads: 0 });
            pick(item);
            creating.value = false;
            newName.value = '';
        })
        .catch((e) => toastError(errorMessage(e)));
}

function apply() {
    const body = isStages.value
        ? { ...props.selection(), stage_id: [...chosen.value][0] }
        : { ...props.selection(), tags: [...chosen.value] };
    applying.value = true;
    http.post(isStages.value ? '/leads/bulk/stage' : '/leads/bulk/tags', body)
        .then((res) => {
            success(res.data.message);
            emit('applied');
        })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => {
            applying.value = false;
        });
}

watch(() => props.open, (open) => {
    if (!open) return;
    search.value = '';
    chosen.value = new Set();
    chosenNames.value = {};
    creating.value = false;
    fetchPage(true);
});

watch(search, () => {
    if (!props.open) return;
    clearTimeout(timer);
    timer = setTimeout(() => fetchPage(true), 250);
});
</script>
