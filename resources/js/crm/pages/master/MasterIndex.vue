<template>
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <div class="portal-section-title mb-0">{{ config.title }}</div>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">{{ config.intro }}</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-portal-primary btn-sm" @click="openForm(null)">
                    <i class="fas fa-plus me-1" aria-hidden="true"></i> Add {{ capitalized }}
                </button>
            </div>
        </div>

        <div class="alert alert-light border small d-flex align-items-start gap-2 mb-3" style="border-radius: 12px;">
            <i class="fas fa-circle-info mt-1" style="color: var(--portal-primary);"></i>
            <div>
                <template v-if="isAdmin">
                    The {{ type }} you add here are shared with <strong>every agency and agent</strong> — they can use them but not edit or delete them.
                    Agencies and agents can also add their own {{ type }}, which only they see.
                </template>
                <template v-else>
                    <strong><i class="fas fa-globe me-1"></i>MW Realty</strong> {{ type }} are set for every account — you can use them but not change them.
                    {{ capitalizedPlural }} you add are yours only, and you can edit or delete them.
                </template>
                A {{ config.singular }} still used by leads can't be deleted — click its lead count to remove it from those leads first.
            </div>
        </div>

        <div v-if="flash" class="alert py-2" :class="flash.ok ? 'alert-success' : 'alert-danger'">{{ flash.text }}</div>

        <div class="portal-card p-4">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <div v-if="config.reorderable" class="text-muted small"><i class="fas fa-arrows-alt me-1"></i> Drag your own rows to reorder.</div>
                <input v-if="config.paged" v-model="searchInput" type="search" class="form-control form-control-sm ms-auto" style="max-width: 260px;" :placeholder="`Search ${type}`">
            </div>
            <div class="table-responsive">
                <table class="table portal-table mb-0">
                    <thead>
                        <tr>
                            <th v-if="config.reorderable" style="width: 32px;"></th>
                            <th>Name</th>
                            <th v-if="config.hasClosed">Closed?</th>
                            <th v-if="config.hasDefault">Default</th>
                            <th class="text-center">Leads</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody :class="{ 'opacity-50': loading }">
                        <tr v-for="(item, index) in items" :key="item.id"
                            :draggable="config.reorderable && item.editable"
                            :class="{ 'table-active': dragIndex === index }"
                            @dragstart="dragIndex = index" @dragover.prevent="dragOver(index)" @dragend="dragEnd">
                            <td v-if="config.reorderable" class="text-muted" :style="item.editable ? 'cursor: grab;' : ''">
                                <i v-if="item.editable" class="fas fa-grip-lines"></i>
                                <i v-else class="fas fa-lock" title="Set by MW Realty"></i>
                            </td>
                            <td>
                                <span v-if="config.hasColor" class="portal-color-dot" :style="{ background: item.color }"></span>
                                <span class="fw-semibold">{{ item.name }}</span>
                                <span v-if="item.is_global" class="badge rounded-pill ms-1" style="background: rgba(36,67,115,.1); color: var(--portal-primary); font-size: .66rem; font-weight: 700;"
                                      title="Set by MW Realty for every account — you can use it, but not change it.">
                                    <i class="fas fa-globe me-1"></i>MW Realty
                                </span>
                            </td>
                            <td v-if="config.hasClosed">{{ item.is_closed ? 'Yes' : 'No' }}</td>
                            <td v-if="config.hasDefault">
                                <span v-if="item.is_default" class="portal-badge-status portal-badge-active">Default</span>
                                <button v-else-if="item.editable" type="button" class="btn btn-link btn-sm p-0" @click="makeDefault(item)">Set default</button>
                            </td>
                            <td class="text-center">
                                <button v-if="item.editable && item.leads_count > 0" type="button" class="btn btn-link btn-sm p-0" title="Show these leads" @click="linkedItem = item">
                                    {{ item.leads_count.toLocaleString() }} <i class="fas fa-arrow-up-right-from-square ms-1" style="font-size: .7rem;"></i>
                                </button>
                                <span v-else :class="{ 'portal-muted': !item.leads_count }">{{ item.leads_count.toLocaleString() }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <template v-if="item.editable">
                                    <button type="button" class="btn btn-sm portal-btn-ghost" @click="openForm(item)">Edit</button>
                                    <button v-if="!item.is_default" type="button" class="btn btn-sm btn-outline-danger ms-1" @click="remove(item)">Delete</button>
                                </template>
                                <span v-else class="portal-muted small">View only</span>
                            </td>
                        </tr>
                        <tr v-if="!loading && !items.length">
                            <td colspan="6" class="portal-empty">No {{ type }} {{ searchInput ? 'match your search' : 'yet — add one above' }}.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="meta && meta.last_page > 1" class="d-flex justify-content-end mt-3">
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="meta.current_page <= 1" @click="fetchItems(meta.current_page - 1)"><i class="fas fa-chevron-left"></i></button>
                    <span class="btn btn-sm btn-outline-secondary disabled">{{ meta.current_page }} / {{ meta.last_page }}</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="meta.current_page >= meta.last_page" @click="fetchItems(meta.current_page + 1)"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <MasterItemModal :open="formOpen" :item="formItem" :config="config" :save="save" @close="formOpen = false" @saved="onSaved" />
        <LinkedLeadsModal :open="!!linkedItem" :type="type" :item="linkedItem" :config="config"
                          @close="linkedItem = null" @changed="(remaining) => { linkedItem.leads_count = remaining; }" />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import MasterItemModal from './MasterItemModal.vue';
import LinkedLeadsModal from './LinkedLeadsModal.vue';
import { MASTER_TYPES } from './masterTypes';
import { useMasterItems } from '../../composables/useMasterItems';
import { useSession } from '../../composables/useSession';
import { errorMessage } from '../../api/http';

const route = useRoute();
const type = route.meta.masterType;
const config = MASTER_TYPES[type];
const { user } = useSession();
const isAdmin = computed(() => user.value.is_super_admin);
const capitalized = config.singular.charAt(0).toUpperCase() + config.singular.slice(1);
const capitalizedPlural = type.charAt(0).toUpperCase() + type.slice(1);

const { items, meta, loading, search, fetchItems, save, destroy, reorder, setDefault } = useMasterItems(type);

const flash = ref(null);
const formOpen = ref(false);
const formItem = ref(null);
const linkedItem = ref(null);
const searchInput = ref('');
const dragIndex = ref(null);
let orderBeforeDrag = null;
let searchTimer = null;

function notify(text, ok = true) {
    flash.value = { text, ok };
}

function openForm(item) {
    formItem.value = item;
    formOpen.value = true;
}

function onSaved(message) {
    formOpen.value = false;
    notify(message);
    fetchItems(meta.value?.current_page ?? 1);
}

function remove(item) {
    if (!window.confirm(`Delete the ${config.singular} “${item.name}”?`)) return;
    destroy(item)
        .then((res) => {
            notify(res.data.message);
            fetchItems(meta.value?.current_page ?? 1);
        })
        .catch((e) => {
            // Still used by leads → straight to the leads popup, where it can be taken off them.
            if (e.response?.data?.in_use) linkedItem.value = item;
            notify(errorMessage(e), false);
        });
}

function makeDefault(item) {
    setDefault(item).then((res) => {
        notify(res.data.message);
        fetchItems();
    }).catch((e) => notify(errorMessage(e), false));
}

/** Rows move while dragging (own rows only — MW Realty's shared ones stay put); saved on drop. */
function dragOver(index) {
    const from = dragIndex.value;
    if (from === null || from === index || !items.value[index].editable) return;
    orderBeforeDrag ??= [...items.value];
    const list = [...items.value];
    list.splice(index, 0, list.splice(from, 1)[0]);
    items.value = list;
    dragIndex.value = index;
}

function dragEnd() {
    dragIndex.value = null;
    if (!orderBeforeDrag) return;
    const previous = orderBeforeDrag;
    orderBeforeDrag = null;
    reorder(items.value.filter((item) => item.editable).map((item) => item.id))
        .catch((e) => {
            items.value = previous;
            notify(errorMessage(e, 'The new order could not be saved.'), false);
        });
}

watch(searchInput, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        search.value = value.trim();
        fetchItems(1);
    }, 300);
});

onMounted(() => fetchItems());
</script>
