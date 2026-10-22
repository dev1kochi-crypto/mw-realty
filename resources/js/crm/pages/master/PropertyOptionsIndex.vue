<template>
    <div>
        <div class="mb-3">
            <div class="portal-section-title mb-0">Property Options</div>
            <p class="text-muted mb-0" style="font-size: 0.85rem;">The choices on the property form, for every agent and agency. Only Super Admin can change them — agents and agencies ask for new ones with a ticket.</p>
        </div>

        <div v-if="!data" class="text-center py-5">
            <span v-if="!loadError" class="spinner-border text-primary"></span>
            <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
        </div>
        <div v-else class="po-layout">
            <nav class="portal-card po-nav" aria-label="Option lists">
                <div v-for="group in data.groups" :key="group.label" class="po-nav-group">
                    <div class="po-nav-label">{{ group.label }}</div>
                    <RouterLink v-for="item in group.lists" :key="item.key" :to="{ query: { list: item.key } }" class="po-nav-link" :class="{ 'is-active': item.key === list.key }" :aria-current="item.key === list.key ? 'page' : null">
                        <i class="fas" :class="item.icon"></i>{{ item.label }}<span class="po-nav-count">{{ item.count }}</span>
                    </RouterLink>
                </div>
            </nav>

            <div class="portal-card p-4">
                <div class="po-head">
                    <span class="po-head-icon"><i class="fas" :class="list.icon"></i></span>
                    <div>
                        <div class="po-head-title">{{ list.label }} <span class="text-muted fw-semibold" style="font-size: 0.85rem;">· {{ data.options.length }}</span></div>
                        <div class="po-head-hint">{{ list.hint }}</div>
                    </div>
                    <div class="po-head-actions">
                        <div v-if="data.options.length > 8" class="po-search">
                            <i class="fas fa-search"></i>
                            <input v-model="search" type="search" class="form-control form-control-sm" :placeholder="`Search ${list.label.toLowerCase()}`" aria-label="Search options">
                        </div>
                        <button type="button" class="btn btn-portal-primary btn-sm text-nowrap" @click="openForm(null)"><i class="fas fa-plus me-1"></i>Add option</button>
                    </div>
                </div>

                <div class="text-muted small mb-2"><i class="fas fa-arrows-alt me-1"></i> Drag rows to set the order shown on the form. Switching an option off hides it from new listings; listings that already use it keep it.</div>
                <div class="table-responsive">
                    <table class="table portal-table po-table mb-0">
                        <thead>
                            <tr>
                                <th class="po-grip"></th>
                                <th v-if="list.has_icons" class="po-icon-cell">Icon</th>
                                <th>Name</th>
                                <th>Code</th>
                                <th class="text-center">{{ ucfirst(list.usage_label) }}</th>
                                <th class="text-center">Shown</th>
                                <th class="text-end po-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(option, index) in data.options" v-show="matches(option)" :key="option.id" class="po-row" :class="{ 'is-off': !option.status, 'is-dragging': dragIndex === index }" draggable="true"
                                @dragstart="dragIndex = index" @dragover.prevent="dragOver($event, index)" @dragend="dragEnd">
                                <td class="po-grip"><i class="fas fa-grip-lines"></i></td>
                                <td v-if="list.has_icons" class="po-icon-cell po-fade">
                                    <span class="po-icon-box"><img v-if="option.icon" :src="option.icon" alt=""><i v-else class="fas fa-image text-muted"></i></span>
                                </td>
                                <td class="po-fade">
                                    <span class="po-name">{{ option.labels[mainLang.code] || option.value }}</span>
                                    <span v-for="lang in otherLangs" :key="lang.code" class="po-name-alt" :dir="lang.code === 'ar' ? 'rtl' : null" :style="lang.code === 'ar' ? 'text-align: left;' : null">{{ option.labels[lang.code] || '—' }}</span>
                                </td>
                                <td class="po-fade"><span class="po-code">{{ option.value }}</span></td>
                                <td class="text-center po-fade"><span class="po-used" :class="{ 'is-used': option.used }">{{ number(option.used) }}</span></td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" :checked="option.status" :disabled="toggling === option.id" aria-label="Shown on the form" @change="toggle(option, $event.target)">
                                    </div>
                                </td>
                                <td class="text-end po-actions">
                                    <button type="button" class="btn btn-sm portal-btn-ghost" title="Edit" aria-label="Edit" @click="openForm(option)"><i class="fas fa-pen"></i></button>
                                    <span class="d-inline-block" :title="option.used ? `Used by ${option.used} ${list.usage_label} — switch it off instead` : 'Delete'">
                                        <button type="button" class="btn btn-sm portal-btn-ghost text-danger" aria-label="Delete" :disabled="option.used > 0" :style="option.used ? 'pointer-events: none;' : null" @click="destroy(option)"><i class="fas fa-trash"></i></button>
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!data.options.length"><td :colspan="6 + (list.has_icons ? 1 : 0)" class="portal-empty">No options yet — add the first one.</td></tr>
                            <tr v-else-if="!data.options.some(matches)"><td :colspan="6 + (list.has_icons ? 1 : 0)" class="portal-empty">No option matches your search.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <CrmModal :open="!!form" :title="form ? `${form.id ? 'Edit' : 'Add'} option · ${list.label}` : ''" footer-class="" @close="form = null">
            <template v-if="form">
                <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
                <div v-for="(lang, i) in data.languages" :key="lang.code" class="mb-3">
                    <label class="form-label" :for="`poLabel${lang.code}`">Name — {{ lang.name }} <span v-if="i === 0" class="text-danger">*</span></label>
                    <input :id="`poLabel${lang.code}`" v-model="form.labels[lang.code]" type="text" class="form-control" maxlength="255" :dir="lang.code === 'ar' ? 'rtl' : null" :required="i === 0">
                </div>
                <div class="mb-1">
                    <label class="form-label" for="poValue">Code <span class="text-muted small">(saved on listings — made from the name if empty)</span></label>
                    <input id="poValue" v-model="form.value" type="text" class="form-control" maxlength="100" placeholder="e.g. duplex" :readonly="form.used > 0">
                    <div v-if="form.used > 0" class="form-text"><i class="fas fa-lock me-1"></i>Used by listings, so the code can't change. You can still rename it.</div>
                </div>
                <div v-if="list.has_icons" class="mt-3">
                    <label class="form-label" for="poIcon">Icon <span class="text-muted small">(SVG or PNG, square, max 1 MB — shown on the listing page)</span></label>
                    <div class="d-flex align-items-center gap-3">
                        <span class="po-icon-preview"><img v-if="form.preview" :src="form.preview" alt=""><i v-else class="fas fa-image text-muted"></i></span>
                        <input id="poIcon" type="file" class="form-control" accept=".svg,image/png,image/jpeg,image/webp" @change="pickIcon">
                    </div>
                </div>
            </template>
            <template #footer>
                <button type="button" class="btn btn-portal-light btn-sm" @click="form = null">Cancel</button>
                <button type="button" class="btn btn-portal-primary btn-sm" :disabled="saving" @click="save">Save</button>
            </template>
        </CrmModal>
    </div>
</template>

<script setup>
/**
 * Master › Property Options (Super Admin) — resources/views/portal/crm/master/property-options/index:
 * the option lists behind the property form's dropdowns (GET /master/property-options?list=…).
 * The chosen list lives in the URL (?list=). Drag rows to reorder; the switch shows / hides an option.
 */
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmModal from '../../components/CrmModal.vue';
import { useToast } from '../../composables/useToast';
import { useConfirm } from '../../composables/useConfirm';
import { number, ucfirst } from '../../utils/format';

const route = useRoute();
const { success, error: toastError } = useToast();
const { confirm } = useConfirm();

const data = ref(null);
const loadError = ref('');
const search = ref('');
const form = ref(null);
const formError = ref('');
const saving = ref(false);
const toggling = ref(null);
const dragIndex = ref(null);
let orderChanged = false;

const list = computed(() => data.value.list);
const mainLang = computed(() => data.value.languages[0] ?? { code: 'en' });
const otherLangs = computed(() => data.value.languages.slice(1));
const base = computed(() => `/master/property-options/${list.value.key}`);

function matches(option) {
    const q = search.value.trim().toLowerCase();
    return !q || [...Object.values(option.labels), option.value].join(' ').toLowerCase().includes(q);
}

function load() {
    loadError.value = '';
    return http.get('/master/property-options', { params: { list: route.query.list } })
        .then((res) => {
            data.value = res.data;
        })
        .catch((e) => {
            loadError.value = errorMessage(e);
        });
}

function openForm(option) {
    formError.value = '';
    form.value = option
        ? { id: option.id, labels: { ...option.labels }, value: option.value, used: option.used, preview: option.icon, file: null }
        : { id: null, labels: {}, value: '', used: 0, preview: null, file: null };
}

function pickIcon(event) {
    const file = event.target.files[0] || null;
    form.value.file = file;
    form.value.preview = file ? URL.createObjectURL(file) : null;
}

function save() {
    const body = new FormData();
    data.value.languages.forEach((lang) => body.append(`translations[${lang.code}][label]`, form.value.labels[lang.code] || ''));
    body.append('value', form.value.value || '');
    if (form.value.file) body.append('icon', form.value.file);
    if (form.value.id) body.append('_method', 'PUT');

    saving.value = true;
    formError.value = '';
    http.post(form.value.id ? `${base.value}/${form.value.id}` : base.value, body)
        .then((res) => {
            success(res.data.message);
            form.value = null;
            return load();
        })
        .catch((e) => {
            formError.value = errorMessage(e);
        })
        .finally(() => {
            saving.value = false;
        });
}

function toggle(option, input) {
    toggling.value = option.id;
    http.post(`${base.value}/${option.id}/toggle`)
        .then((res) => {
            option.status = res.data.status;
        })
        .catch(() => {
            input.checked = option.status;
            toastError('Could not update. Please try again.');
        })
        .finally(() => {
            toggling.value = null;
        });
}

async function destroy(option) {
    if (!(await confirm({ title: 'Delete option?', message: 'Delete this option?', confirmText: 'Delete', tone: 'danger' }))) return;
    http.delete(`${base.value}/${option.id}`)
        .then(load)
        .catch((e) => toastError(errorMessage(e, 'Could not delete.')));
}

// Drag to reorder — rows move as you drag; the order is saved when you let go.
function dragOver(event, index) {
    const from = dragIndex.value;
    if (from === null || from === index) return;
    const row = event.currentTarget;
    const after = event.clientY > row.getBoundingClientRect().top + row.offsetHeight / 2;
    if ((after && index < from) || (!after && index > from)) return;
    const options = data.value.options;
    options.splice(index, 0, options.splice(from, 1)[0]);
    dragIndex.value = index;
    orderChanged = true;
}

function dragEnd() {
    dragIndex.value = null;
    if (!orderChanged) return;
    orderChanged = false;
    http.post(`${base.value}/reorder`, { order: data.value.options.map((o) => o.id) })
        .catch(() => toastError('Could not save the order.'));
}

watch(() => route.query.list, () => {
    if (route.name !== 'master.property-options') return;
    search.value = '';
    load();
}, { immediate: true });
</script>
