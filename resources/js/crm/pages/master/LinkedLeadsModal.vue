<template>
    <CrmModal :open="open" :title="`Leads using “${item?.name ?? ''}”`" size="lg" @close="$emit('close')">
        <p class="small text-muted">
            Remove this {{ config.singular }} from the leads below to be able to delete it.
            {{ config.singular === 'tag' ? '' : `Each change is recorded in the lead's timeline.` }}
        </p>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <input v-model="search" type="search" class="form-control form-control-sm" style="max-width: 280px;" placeholder="Search name, email or phone">
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-danger" :disabled="!selected.length || busy" @click="remove(false)">
                    Remove from selected ({{ selected.length }})
                </button>
                <button type="button" class="btn btn-sm btn-danger" :disabled="!total || busy" @click="remove(true)">
                    Remove from all {{ total }}
                </button>
            </div>
        </div>
        <div v-if="message" class="alert alert-success py-2 small">{{ message }}</div>
        <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>
        <div class="border rounded overflow-auto" style="max-height: 360px;" @scroll="onScroll">
            <table class="table table-sm align-middle mb-0">
                <tbody>
                    <tr v-for="lead in leads" :key="lead.id">
                        <td style="width: 32px;"><input v-model="selected" class="form-check-input" type="checkbox" :value="lead.id"></td>
                        <td>
                            <div class="fw-semibold small">{{ lead.name }}</div>
                            <div class="text-muted small">{{ lead.contact }}<span v-if="lead.owner"> · {{ lead.owner }}</span></div>
                        </td>
                        <td class="text-muted small text-end">{{ formatDate(lead.created_at) }}</td>
                    </tr>
                    <tr v-if="!loading && !leads.length"><td colspan="3" class="text-center text-muted small py-4">No leads use it.</td></tr>
                </tbody>
            </table>
            <div v-if="loading" class="text-center py-2"><span class="spinner-border spinner-border-sm text-muted"></span></div>
        </div>
    </CrmModal>
</template>

<script setup>
/** Master › a stage / tag / source's lead count → its leads, searched and paged (20 per scroll), and taking the item off them. */
import { ref, watch } from 'vue';
import CrmModal from '../../components/CrmModal.vue';
import http, { errorMessage } from '../../api/http';

const props = defineProps({
    open: { type: Boolean, default: false },
    type: { type: String, required: true },
    item: { type: Object, default: null },
    config: { type: Object, required: true },
});
const emit = defineEmits(['close', 'changed']);

const leads = ref([]);
const total = ref(0);
const page = ref(1);
const more = ref(false);
const loading = ref(false);
const busy = ref(false);
const search = ref('');
const selected = ref([]);
const message = ref('');
const error = ref('');
let searchTimer = null;
let requestId = 0;

function fetchPage(reset) {
    if (reset) {
        page.value = 1;
        leads.value = [];
        selected.value = [];
    }
    const id = ++requestId;
    loading.value = true;
    http.get(`/master/${props.type}/${props.item.id}/leads`, { params: { q: search.value || undefined, page: page.value } })
        .then((res) => {
            if (id !== requestId) return;
            leads.value.push(...res.data.results);
            total.value = res.data.total;
            more.value = res.data.more;
        })
        .finally(() => {
            if (id === requestId) loading.value = false;
        });
}

function onScroll(event) {
    const el = event.target;
    if (more.value && !loading.value && el.scrollTop + el.clientHeight >= el.scrollHeight - 24) {
        page.value += 1;
        fetchPage(false);
    }
}

function remove(all) {
    const count = all ? total.value : selected.value.length;
    if (!window.confirm(`Remove “${props.item.name}” from ${count} ${count === 1 ? 'lead' : 'leads'}?`)) return;

    busy.value = true;
    error.value = '';
    http.post(`/master/${props.type}/${props.item.id}/leads/remove`, all ? { all: true } : { ids: selected.value })
        .then((res) => {
            message.value = res.data.message;
            emit('changed', res.data.remaining);
            fetchPage(true);
        })
        .catch((e) => {
            error.value = errorMessage(e);
        })
        .finally(() => {
            busy.value = false;
        });
}

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' }) : '';
}

watch(() => props.open, (open) => {
    if (!open) return;
    search.value = '';
    message.value = '';
    error.value = '';
    fetchPage(true);
});

watch(search, () => {
    if (!props.open) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => fetchPage(true), 300);
});
</script>
