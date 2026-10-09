<template>
    <CrmModal :open="open" size="lg" scrollable content-class="portal-modal-content portal-tf" bare @close="$emit('close')">
        <template #header="{ close }">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="portal-tf-icon" aria-hidden="true"><i class="fas fa-table-columns"></i></span>
                    <div>
                        <h5 class="modal-title">Table Fields</h5>
                        <p class="text-muted small mb-0">Pick the columns for your leads table and drag them into order.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
            </div>
        </template>
        <div class="modal-body p-0">
            <div v-if="error" class="alert alert-danger py-2 small m-3 mb-0" role="alert">{{ error }}</div>
            <div class="portal-tf-panes">
                <section class="portal-tf-pane">
                    <div class="portal-tf-pane-head">
                        <h6>Shown in table</h6>
                        <span class="portal-tf-count">{{ draft.length }} of {{ columns.available.length }}</span>
                    </div>
                    <p class="portal-tf-hint"><i class="fas fa-grip-vertical" aria-hidden="true"></i> Drag to reorder — left to right in the table.</p>
                    <ol class="portal-tf-shown">
                        <li v-for="(key, index) in draft" :key="key" class="portal-tf-item" :class="{ 'is-pinned': key === 'lead', 'is-new': key === flashKey }"
                            :draggable="key !== 'lead'" @dragstart="dragIndex = index" @dragover.prevent="dragOver(index)" @dragend="dragIndex = null">
                            <span class="portal-tf-grip" :class="{ 'is-disabled': key === 'lead' }" aria-hidden="true" :title="key === 'lead' ? '' : 'Drag to reorder'">
                                <i class="fas" :class="key === 'lead' ? 'fa-thumbtack' : 'fa-grip-vertical'"></i>
                            </span>
                            <span class="portal-tf-label">{{ meta[key].label }}</span>
                            <span v-if="key === 'lead' || meta[key].locked" class="portal-tf-meta">{{ key === 'lead' ? 'Always first' : 'Always shown' }}</span>
                            <template v-else>
                                <span class="portal-tf-meta d-none d-sm-inline">{{ groupLabel(meta[key].group) }}</span>
                                <button type="button" class="portal-tf-remove" :title="`Hide ${meta[key].label}`" :aria-label="`Hide ${meta[key].label}`" @click="toggle(key)">
                                    <i class="fas fa-xmark" aria-hidden="true"></i>
                                </button>
                            </template>
                        </li>
                    </ol>
                </section>
                <section class="portal-tf-pane portal-tf-pane-catalog">
                    <div class="portal-tf-pane-head"><h6>Add fields</h6></div>
                    <div class="portal-tf-search">
                        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                        <input v-model="search" type="search" class="form-control form-control-sm" placeholder="Search fields…" aria-label="Search fields" autocomplete="off">
                    </div>
                    <div class="portal-tf-catalog">
                        <div v-for="group in catalog" :key="group.key" class="portal-tf-group">
                            <div class="portal-tf-group-title"><i class="fas" :class="group.icon" aria-hidden="true"></i>{{ group.label }}</div>
                            <div class="portal-tf-chips">
                                <button v-for="key in group.keys" :key="key" type="button" class="portal-tf-chip" :class="{ 'is-on': draft.includes(key) }"
                                        :disabled="meta[key].locked" :aria-pressed="draft.includes(key)" @click="toggle(key)"
                                        :title="meta[key].locked ? 'Always shown' : `${draft.includes(key) ? 'Hide' : 'Show'} ${meta[key].label}`">
                                    <i class="fas" :class="meta[key].locked ? 'fa-lock' : (draft.includes(key) ? 'fa-check' : 'fa-plus')" aria-hidden="true"></i>{{ meta[key].label }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-if="!catalog.length" class="portal-tf-empty">No fields match your search.</p>
                </section>
            </div>
        </div>
        <template #footer>
            <button type="button" class="btn btn-link btn-sm text-decoration-none me-auto px-0" @click="resetDefault"><i class="fas fa-rotate-left me-1" aria-hidden="true"></i>Reset to default</button>
            <button type="button" class="btn btn-outline-secondary" @click="$emit('close')">Cancel</button>
            <button type="button" class="btn btn-portal-primary" :disabled="saving" @click="save">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                Save Fields
            </button>
        </template>
    </CrmModal>
</template>

<script setup>
/**
 * Table Fields (resources/views/portal/crm/leads/_lead_table_fields_modal) — "Shown in table"
 * (drag to order, × to hide) and "Add fields" by group. Saved per user: PUT /leads/table-columns.
 */
import { computed, ref, watch } from 'vue';
import CrmModal from '../../../components/CrmModal.vue';
import http, { errorMessage } from '../../../api/http';
import { useToast } from '../../../composables/useToast';

const props = defineProps({
    open: { type: Boolean, default: false },
    columns: { type: Object, required: true }, // meta.columns from /leads/meta
});
const emit = defineEmits(['close', 'saved']);

const { success } = useToast();
const draft = ref([]);
const search = ref('');
const saving = ref(false);
const error = ref('');
const dragIndex = ref(null);
const flashKey = ref(null);

const meta = computed(() => Object.fromEntries(props.columns.available.map((column) => [column.key, column])));
const groupLabel = (key) => props.columns.groups.find((group) => group.key === key)?.label ?? '';

const catalog = computed(() => {
    const term = search.value.trim().toLowerCase();
    return props.columns.groups.map((group) => ({
        ...group,
        keys: props.columns.default_order.filter((key) => meta.value[key]?.group === group.key && (!term || meta.value[key].label.toLowerCase().includes(term))),
    })).filter((group) => group.keys.length);
});

watch(() => props.open, (open) => {
    if (!open) return;
    draft.value = [...props.columns.shown];
    search.value = '';
    error.value = '';
});

function toggle(key) {
    if (meta.value[key].locked) return;
    if (draft.value.includes(key)) {
        draft.value = draft.value.filter((k) => k !== key);
    } else {
        draft.value = [...draft.value, key];
        flashKey.value = key;
    }
}

/** Rows move while dragging; Lead stays first. */
function dragOver(index) {
    const from = dragIndex.value;
    if (from === null || from === index || index === 0) return;
    const list = [...draft.value];
    list.splice(index, 0, list.splice(from, 1)[0]);
    draft.value = list;
    dragIndex.value = index;
}

function resetDefault() {
    draft.value = props.columns.default_order.filter((key) => meta.value[key]?.default);
}

function save() {
    saving.value = true;
    error.value = '';
    http.put('/leads/table-columns', { columns: draft.value })
        .then((res) => {
            success(res.data.message);
            emit('saved', res.data.columns);
        })
        .catch((e) => {
            error.value = errorMessage(e);
        })
        .finally(() => {
            saving.value = false;
        });
}
</script>
