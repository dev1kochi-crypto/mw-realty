<template>
    <div v-if="!loaded" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else>
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
            <div>
                <div class="portal-section-title mb-1">Marketing Properties</div>
                <p class="portal-muted small mb-0">Pick listings from any agency or agent for the home page <strong>Realty Property</strong> section. The first {{ homeLimit }} show on the home page; all of them on the “View more” page.</p>
            </div>
            <a :href="websiteUrl" target="_blank" rel="noopener" class="portal-xbtn" aria-label="View on website"><i class="fas fa-arrow-up-right-from-square"></i><span>View on website</span></a>
        </div>

        <div class="row g-3">
            <!-- ========== Add ========== -->
            <div class="col-xl-6">
                <section class="portal-lp-card mb-3">
                    <header class="portal-lp-card-head">
                        <h2><span class="portal-mk-step">1</span> Agencies &amp; agents</h2>
                        <button v-if="chosenAccounts.size" type="button" class="btn btn-link btn-sm p-0" @click="clearAccounts">Clear</button>
                    </header>
                    <div class="portal-lp-card-body">
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span v-if="!chosenAccounts.size" class="portal-muted small">Choose one or more below.</span>
                            <template v-else>
                                <span v-for="[id, name] in chosenAccounts" :key="id" class="portal-mk-chip">{{ name }}<button type="button" :aria-label="`Remove ${name}`" @click="toggleAccount(id, name, false)"><i class="fas fa-xmark"></i></button></span>
                            </template>
                        </div>
                        <div class="portal-picker-search m-0 mb-2">
                            <input v-model="accounts.search" type="search" class="form-control" placeholder="Search agencies or agents" autocomplete="off" aria-label="Search agencies or agents" @input="accounts.debounced()">
                            <span class="portal-picker-search-btn" aria-hidden="true"><i class="fas fa-magnifying-glass"></i></span>
                        </div>
                        <div class="portal-mk-scroll" role="listbox" aria-multiselectable="true" @scroll="accounts.onScroll">
                            <label v-for="row in accounts.rows" :key="row.id" class="portal-picker-item">
                                <span class="portal-picker-initials" :style="{ '--item': row.type === 'Agency' ? '#244373' : '#ca2844' }">{{ initialsOf(row.name) }}</span>
                                <span class="portal-picker-text">
                                    <span class="portal-picker-name">{{ row.name }}</span>
                                    <span class="portal-picker-count">{{ row.type }} · {{ row.listings }} {{ row.listings === 1 ? 'listing' : 'listings' }}</span>
                                </span>
                                <input class="form-check-input" type="checkbox" :value="row.id" :checked="chosenAccounts.has(String(row.id))" @change="toggleAccount(row.id, row.name, $event.target.checked)">
                            </label>
                        </div>
                        <div class="portal-picker-status">{{ accounts.status }}</div>
                    </div>
                </section>

                <section class="portal-lp-card">
                    <header class="portal-lp-card-head">
                        <h2><span class="portal-mk-step">2</span> Their properties <span v-if="propertyTotal !== null" class="portal-lp-count">{{ propertyTotal }}</span></h2>
                        <button type="button" class="btn btn-portal-primary btn-sm" :class="{ 'is-busy': adding }" :disabled="!chosenProperties.size || adding" @click="addSelected"><i class="fas fa-plus me-1"></i> Add selected ({{ chosenProperties.size }})</button>
                    </header>
                    <div class="portal-lp-card-body">
                        <div class="portal-picker-search m-0 mb-2">
                            <input v-model="properties.search" type="search" class="form-control" placeholder="Search by title or reference" autocomplete="off" aria-label="Search properties" :disabled="!chosenAccounts.size" @input="properties.debounced()">
                            <span class="portal-picker-search-btn" aria-hidden="true"><i class="fas fa-magnifying-glass"></i></span>
                        </div>
                        <div class="portal-mk-scroll" @scroll="properties.onScroll">
                            <label v-for="row in properties.rows" :key="row.id" class="portal-picker-item portal-mk-prop" :class="{ 'is-on-list': isOnList(row) }">
                                <span class="portal-mk-thumb"><img v-if="row.image" :src="row.image" alt="" loading="lazy"><i v-else class="fas fa-image"></i></span>
                                <span class="portal-picker-text">
                                    <span class="portal-picker-name">{{ row.title }}</span>
                                    <span class="portal-picker-count">{{ details(row) }}</span>
                                </span>
                                <span v-if="isOnList(row)" class="portal-mk-onlist">On list</span>
                                <input v-else class="form-check-input" type="checkbox" :value="row.id" :checked="chosenProperties.has(row.id)" @change="toggleProperty(row.id, $event.target.checked)">
                            </label>
                        </div>
                        <div class="portal-picker-status">{{ properties.status }}</div>
                    </div>
                </section>
            </div>

            <!-- ========== The list ========== -->
            <div class="col-xl-6">
                <section class="portal-lp-card">
                    <header class="portal-lp-card-head">
                        <h2>Marketing list <span class="portal-lp-count">{{ items.length }}</span></h2>
                        <span class="portal-muted small"><i class="fas fa-grip-vertical me-1"></i>Drag to reorder</span>
                    </header>
                    <div class="portal-lp-card-body p-0">
                        <ol class="portal-mk-list" aria-label="Marketing properties, in order" @dragstart="onDragStart" @dragover="onDragOver" @drop="onDrop" @dragend="onDragEnd">
                            <template v-for="(item, index) in items" :key="item.id">
                                <li v-if="index === homeLimit" class="portal-mk-divider" aria-hidden="true">Above this line: shown on the home page · below: only on the “View more” page</li>
                                <li class="portal-mk-row" :class="{ 'is-home': index < homeLimit, 'portal-lp-just-changed': highlight.includes(item.id), 'is-dragging': dragId === item.id, 'is-drop-before': dropTarget?.id === item.id && dropTarget.before, 'is-drop-after': dropTarget?.id === item.id && !dropTarget.before }" draggable="true" :data-id="item.id">
                                    <span class="portal-mk-handle"><i class="fas fa-grip-vertical"></i></span>
                                    <span class="portal-mk-pos">{{ index + 1 }}</span>
                                    <span class="portal-mk-thumb"><img v-if="item.image" :src="item.image" alt="" loading="lazy"><i v-else class="fas fa-image"></i></span>
                                    <span class="portal-picker-text">
                                        <span class="portal-picker-name">{{ item.title }}</span>
                                        <span class="portal-picker-count">{{ details(item) }}</span>
                                    </span>
                                    <span class="portal-mk-actions">
                                        <button type="button" title="Move up" :aria-label="`Move up: ${item.title}`" :disabled="index === 0" @click="move(index, index - 1)"><i class="fas fa-arrow-up"></i></button>
                                        <button type="button" title="Move down" :aria-label="`Move down: ${item.title}`" :disabled="index === items.length - 1" @click="move(index, index + 1)"><i class="fas fa-arrow-down"></i></button>
                                        <button type="button" class="text-danger" title="Remove" :aria-label="`Remove ${item.title}`" @click="removeItem(item)"><i class="fas fa-trash-can"></i></button>
                                    </span>
                                </li>
                            </template>
                        </ol>
                        <div v-if="!items.length" class="portal-lp-empty py-4">
                            Nothing picked yet — the home page shows the latest listings automatically until you add some.
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * Marketing Properties (resources/views/portal/marketing-properties/index) — Super Admin only.
 * Left: choose agencies / agents (multi-select), then add their listings. Right: the list itself —
 * drag (or ↑ / ↓) to reorder; the first `home_limit` show on the home "Realty Property" section.
 * Both pickers are searched and paged on the server, 20 at a time, more on scroll.
 */
import { reactive, ref } from 'vue';
import http, { errorMessage } from '../../api/http';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';

const { confirm } = useConfirm();
const { success, error: toastError } = useToast();

const loaded = ref(false);
const loadError = ref('');
const items = ref([]);
const homeLimit = ref(0);
const websiteUrl = ref('/marketing-properties');
const highlight = ref([]);
const chosenAccounts = reactive(new Map()); // id (string) → name
const chosenProperties = reactive(new Set());
const propertyTotal = ref(null);
const adding = ref(false);

const details = (row) => [row.reference, row.owner, row.price].filter(Boolean).join(' · ');
const initialsOf = (name) => name.split(/\s+/).filter(Boolean).map((w) => w[0]).join('').slice(0, 2).toUpperCase();
const isOnList = (row) => row.in_list || items.value.some((i) => i.id === row.id);

/* Server-paged list with search + load-on-scroll, reused for accounts and properties. */
function pagedList(opts) {
    let next = 1;
    let loading = false;
    let seq = 0;
    let timer;
    const list = reactive({
        rows: [],
        search: '',
        status: '',
        load(reset) {
            if (reset) { next = 1; list.rows = []; }
            if (loading || !next || !opts.ready()) return;
            loading = true;
            const mine = ++seq;
            list.status = 'Loading…';
            http.get(opts.url, { params: { ...opts.params(), page: next, search: list.search.trim() } })
                .then((res) => {
                    if (mine !== seq) return;
                    list.rows.push(...res.data.data);
                    next = res.data.next_page;
                    opts.onPage?.(res.data);
                    list.status = list.rows.length ? '' : (list.search.trim() ? 'Nothing matches your search.' : opts.empty);
                })
                .catch(() => { if (mine === seq) list.status = 'Could not load. Try again.'; })
                .finally(() => { if (mine === seq) loading = false; });
        },
        reload() {
            loading = false;
            list.load(true);
        },
        debounced() {
            clearTimeout(timer);
            timer = setTimeout(() => list.reload(), 250);
        },
        onScroll(event) {
            const el = event.target;
            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 60) list.load(false);
        },
    });
    return list;
}

/* ---------- 1. Accounts (multi-select) ---------- */
const accounts = pagedList({
    url: '/marketing-properties/accounts',
    empty: 'No agencies or agents yet.',
    ready: () => true,
    params: () => ({}),
});

/* ---------- 2. Their properties ---------- */
const properties = pagedList({
    url: '/marketing-properties/properties',
    empty: 'These accounts have no active listings.',
    ready: () => chosenAccounts.size > 0,
    params: () => ({ accounts: [...chosenAccounts.keys()] }),
    onPage: (data) => { propertyTotal.value = data.total; },
});
properties.status = 'Choose an agency or agent above to see their listings.';

function toggleAccount(id, name, on) {
    if (on) chosenAccounts.set(String(id), name);
    else chosenAccounts.delete(String(id));
    chosenProperties.clear();
    properties.reload();
    if (!chosenAccounts.size) {
        properties.rows = [];
        propertyTotal.value = null;
        properties.status = 'Choose an agency or agent above to see their listings.';
    }
}

function clearAccounts() {
    chosenAccounts.clear();
    toggleAccount(0, '', false);
}

function toggleProperty(id, on) {
    if (on) chosenProperties.add(id);
    else chosenProperties.delete(id);
}

function addSelected() {
    adding.value = true;
    const addedIds = [...chosenProperties];
    http.post('/marketing-properties', { property_ids: addedIds })
        .then((res) => {
            items.value = res.data.items;
            chosenProperties.clear();
            flash(addedIds); // new rows land at the top and flash
            properties.reload();
            success(res.data.message);
        })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { adding.value = false; });
}

/* ---------- The list: drag / ↑↓ reorder, remove ---------- */
function flash(ids) {
    highlight.value = ids;
    setTimeout(() => { if (highlight.value === ids) highlight.value = []; }, 1600);
}

function saveOrder() {
    http.post('/marketing-properties/reorder', { property_ids: items.value.map((i) => i.id) })
        .then((res) => success(res.data.message || 'Order saved.'))
        .catch((e) => toastError(errorMessage(e)));
}

function move(from, to) {
    if (to < 0 || to >= items.value.length || from === to) return;
    const [moved] = items.value.splice(from, 1);
    items.value.splice(to, 0, moved);
    flash([moved.id]);
    saveOrder();
}

async function removeItem(item) {
    if (!(await confirm({ title: 'Remove from the list?', message: `“${item.title}” will no longer show in Realty Property.`, confirmText: 'Remove', tone: 'danger' }))) return;
    http.delete(`/marketing-properties/${item.id}`)
        .then((res) => {
            items.value = res.data.items;
            properties.reload();
            success(res.data.message);
        })
        .catch((e) => toastError(errorMessage(e)));
}

// Native drag & drop.
const dragId = ref(null);
const dropTarget = ref(null); // { id, before }
const rowOf = (event) => event.target.closest?.('.portal-mk-row');

function onDragStart(event) {
    const row = rowOf(event);
    if (!row) return;
    dragId.value = Number(row.dataset.id);
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', row.dataset.id);
}

function onDragOver(event) {
    const row = rowOf(event);
    if (!row || dragId.value === null) return;
    event.preventDefault();
    const box = row.getBoundingClientRect();
    dropTarget.value = { id: Number(row.dataset.id), before: event.clientY < box.top + box.height / 2 };
}

function onDrop(event) {
    const row = rowOf(event);
    if (!row || dragId.value === null || !dropTarget.value) return;
    event.preventDefault();
    const from = items.value.findIndex((i) => i.id === dragId.value);
    let to = items.value.findIndex((i) => i.id === dropTarget.value.id);
    if (!dropTarget.value.before) to += 1;
    if (from < to) to -= 1;
    onDragEnd();
    move(from, to);
}

function onDragEnd() {
    dragId.value = null;
    dropTarget.value = null;
}

function load() {
    loadError.value = '';
    http.get('/marketing-properties')
        .then((res) => {
            items.value = res.data.items;
            homeLimit.value = res.data.home_limit;
            websiteUrl.value = res.data.website_url;
            loaded.value = true;
            accounts.reload();
        })
        .catch((e) => { loadError.value = errorMessage(e); });
}

load();
</script>
