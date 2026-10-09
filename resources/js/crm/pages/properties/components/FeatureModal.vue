<template>
    <CrmModal :open="!!item" bare :size="picking ? 'lg' : ''" content-class="fm__content" @close="close">
        <template #header>
            <div class="fm__head">
                <span class="fm__head-icon"><i class="fas fa-star"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <h5 class="fm__title">{{ mode === 'edit' ? 'Edit premium dates' : 'Make a listing premium' }}</h5>
                    <p class="fm__subtitle">Premium listings get a badge and appear in the Premium section on the website.</p>
                </div>
                <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
            </div>
        </template>

        <form v-if="item" novalidate @submit.prevent="submit">
            <div class="fm__body">
                <div class="fm__chips">
                    <template v-if="isAdmin">
                        <span class="fm__chip"><i class="fas fa-shield-alt"></i> Super Admin &middot; no plan limits</span>
                        <span class="fm__chip"><i class="far fa-clock"></i> Up to {{ maxDays }} days, or no end date</span>
                    </template>
                    <template v-else>
                        <span class="fm__chip" :class="{ 'is-warn': (quota?.remaining ?? 0) === 0 }">
                            <i class="fas fa-layer-group"></i>
                            {{ quota.used }} of {{ quota.limit }} {{ quota.per_month ? 'used this month' : 'in use' }}
                        </span>
                        <span class="fm__chip"><i class="far fa-clock"></i> {{ quota.max_days ? `Max ${quota.max_days} days each` : 'No limit on length' }}</span>
                        <span v-if="quota.per_month" class="fm__chip"><i class="fas fa-redo"></i> Resets {{ quota.resets }}</span>
                    </template>
                </div>

                <div class="fm__section">
                    <div class="fm__label d-flex justify-content-between align-items-center">
                        <span>{{ picking && pickLimit > 1 ? 'Listings' : 'Listing' }}</span>
                        <span v-if="picking" class="fm__pick-count" :class="{ 'is-full': pickLimit > 0 && full }" aria-live="polite">{{ pickCount }}</span>
                    </div>
                    <!-- Picker (Premium menu): searched and paged on the server, 20 at a time, more on scroll.
                         Tick several to book them together, up to the plan's free premium slots. -->
                    <template v-if="picking">
                        <div class="fm__picker">
                            <div class="fm__picker-search">
                                <i class="fas fa-search"></i>
                                <input v-model="pickSearch" type="search" class="form-control" :placeholder="pickPlaceholder" autocomplete="off" @input="onPickSearch" @keydown.enter.prevent="restartPicker">
                            </div>
                            <div class="fm__picker-list" role="group" aria-label="Choose listings" :aria-busy="pickLoading ? 'true' : 'false'" @scroll="onPickScroll">
                                <label v-for="row in pickItems" :key="row.id" class="fm__option" :class="{ 'is-disabled': !picked.has(row.id) && full }">
                                    <input type="checkbox" name="feature_property[]" :value="row.id" :checked="picked.has(row.id)" :disabled="!picked.has(row.id) && full" @change="togglePick(row, $event.target.checked)">
                                    <img :src="row.thumb || 'https://placehold.co/96x72?text=No+Image'" alt="" loading="lazy">
                                    <span class="fm__option-text">
                                        <span class="fm__option-title">{{ row.title || '—' }}</span>
                                        <span class="fm__option-meta">{{ [row.ref, row.place, isAdmin ? row.owner : null].filter(Boolean).join(' · ') }}</span>
                                    </span>
                                    <span class="fm__tag" :class="{ 'fm__tag--commercial': row.segment === 'commercial' }">{{ row.segment === 'commercial' ? 'Commercial' : 'Property' }}</span>
                                    <span v-if="!row.active" class="fm__tag fm__tag--muted">Inactive</span>
                                </label>
                            </div>
                            <div v-show="pickStatus" class="fm__picker-status" aria-live="polite">{{ pickStatus }}</div>
                        </div>
                        <div v-if="picked.size" class="fm__picked" aria-label="Selected listings">
                            <span v-for="row in [...picked.values()]" :key="row.id" class="fm__chip-pick">
                                <img :src="row.thumb || 'https://placehold.co/48x36?text=%20'" alt="">
                                <span class="fm__chip-pick-text" :title="[row.title, row.ref].filter(Boolean).join(' · ')">{{ row.title || row.ref || '—' }}</span>
                                <button type="button" class="fm__chip-pick-remove" :aria-label="`Remove ${row.title || 'listing'}`" @click="togglePick(row, false)">&times;</button>
                            </span>
                        </div>
                    </template>
                    <div v-else class="fm__selected">
                        <img :src="item.thumb || 'https://placehold.co/96x72?text=No+Image'" alt="">
                        <div class="min-w-0">
                            <div class="fm__option-title">{{ item.title }}</div>
                            <div class="fm__option-meta">{{ item.ref ? `Ref: ${item.ref}` : '' }}</div>
                        </div>
                    </div>
                </div>

                <div class="fm__section">
                    <div class="fm__label d-flex justify-content-between align-items-center">
                        <span>Premium period</span>
                        <span class="fm__presets">
                            <button v-for="d in presets" :key="d" type="button" class="fm__preset" :class="{ 'is-active': activePreset === d }" @click="setPreset(d)">{{ d }} days</button>
                        </span>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fm__field-label" for="featureStart">Start date</label>
                            <input id="featureStart" v-model="start" type="date" class="form-control" :min="liveStart ? null : todayIso" :disabled="!!liveStart" required @change="render">
                            <div v-if="liveStart" class="form-text">Already live — the start date can't change.</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fm__field-label" for="featureEnd">End date</label>
                            <input id="featureEnd" v-model="end" type="date" class="form-control" :min="endMin" :max="endMax" :disabled="noEnd" :required="!isAdmin" @change="render">
                        </div>
                    </div>
                    <div v-if="isAdmin" class="form-check mt-2">
                        <input id="featureNoEnd" v-model="noEnd" class="form-check-input" type="checkbox" @change="render">
                        <label class="form-check-label small" for="featureNoEnd">No end date — keep it premium until I stop it</label>
                    </div>

                    <div class="fm__summary" :class="{ 'is-scheduled': future, 'is-invalid': tooLong }">
                        <i class="far fa-calendar-check"></i>
                        <span>{{ summary }}</span>
                    </div>
                </div>

                <p v-if="!isAdmin" class="fm__note">
                    <i class="fas fa-info-circle"></i>
                    <template v-if="quota.per_month">Uses 1 premium from the start date's month, even if you stop it early. Cancelling before it starts gives it back.</template>
                    <template v-else>Uses 1 premium slot for the whole period. The slot frees up when it ends or you stop it.</template>
                </p>

                <div v-if="error" class="alert alert-danger small mb-0" role="alert">{{ error }}</div>
            </div>

            <div class="fm__foot">
                <button type="button" class="btn btn-portal-light btn-sm" @click="close">Cancel</button>
                <button type="submit" class="btn fm__submit btn-sm" :disabled="saving"><i class="fas fa-star me-1"></i><span>{{ submitText }}</span></button>
            </div>
        </form>
    </CrmModal>
</template>

<script setup>
/**
 * "Make a listing premium" popup (the Blade _feature_modal). `item` = { id, title, thumb, ref } to book
 * one listing (cards), with `live` / `start` / `end` (yyyy-mm-dd) to change a booking's dates, or
 * { picker: true } for the Premium menu's listing picker (tick several, booked together with the same
 * dates — POST /premium). Plan quota is enforced server-side.
 */
import { computed, ref, watch } from 'vue';
import http, { errorMessage } from '../../../api/http';
import CrmModal from '../../../components/CrmModal.vue';

const props = defineProps({
    item: { type: Object, default: null },
    mode: { type: String, default: 'create' }, // create | edit
    isAdmin: { type: Boolean, default: false },
    quota: { type: Object, default: null },
    adminMaxDays: { type: Number, default: 365 },
    // Premium menu: the most listings one booking may tick (Super Admin; owners are capped by their plan).
    batchLimit: { type: Number, default: 50 },
});
const emit = defineEmits(['close', 'saved']);

const maxDays = computed(() => (props.isAdmin ? props.adminMaxDays : (props.quota?.max_days || props.adminMaxDays)));
const presets = computed(() => {
    const list = [7, 15, 30, 60, 90].filter((d) => d <= maxDays.value);
    if (!props.isAdmin && props.quota?.max_days && !list.includes(maxDays.value)) list.push(maxDays.value);
    return list;
});

const start = ref('');
const end = ref('');
const noEnd = ref(false);
const liveStart = ref(null);
const error = ref('');
const saving = ref(false);
const tick = ref(0); // re-runs the summary when render() is called

// Local-date helpers — <input type="date"> works in yyyy-mm-dd without a timezone.
const pad = (n) => String(n).padStart(2, '0');
const toIso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const fromIso = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
const addDays = (d, n) => { const c = new Date(d); c.setDate(c.getDate() + n); return c; };
const fmt = (d) => d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
const today = () => { const t = new Date(); return new Date(t.getFullYear(), t.getMonth(), t.getDate()); };
const dayCount = (a, b) => Math.round((b - a) / 86400000) + 1;
const todayIso = computed(() => toIso(today()));

const startDate = computed(() => (start.value ? fromIso(start.value) : today()));
const endMin = computed(() => (liveStart.value && liveStart.value < todayIso.value ? todayIso.value : toIso(startDate.value)));
const endMax = computed(() => toIso(addDays(startDate.value, maxDays.value - 1)));
const future = computed(() => { tick.value; return !liveStart.value && !!start.value && fromIso(start.value) > today(); });
const days = computed(() => (start.value && end.value && !noEnd.value ? dayCount(fromIso(start.value), fromIso(end.value)) : null));
const tooLong = computed(() => days.value !== null && days.value > maxDays.value);
const activePreset = computed(() => days.value);

const summary = computed(() => {
    tick.value;
    if (!start.value) return 'Pick the dates.';
    const s = fromIso(start.value);
    if (noEnd.value) return `${liveStart.value ? `Live since ${fmt(s)}` : future.value ? `Starts ${fmt(s)}` : 'Starts now'} · no end date`;
    if (!end.value) return 'Pick an end date.';
    const from = liveStart.value ? `live since ${fmt(s)}` : future.value ? fmt(s) : 'today';
    return `${days.value} day${days.value === 1 ? '' : 's'} in total · ${from} → ${fmt(fromIso(end.value))}${future.value ? ' · scheduled, goes live automatically on the start date' : ''}`;
});
const submitText = computed(() => {
    if (props.mode === 'edit') return 'Save dates';
    // Submit label says how many listings will be booked.
    if (picking.value && picked.value.size > 1) return `${future.value ? 'Schedule ' : 'Make '}${picked.value.size} listings premium`;
    return future.value ? 'Schedule premium' : 'Make premium now';
});

/* ---------- listing picker (Premium menu) ---------- */
const picking = computed(() => !!props.item?.picker && props.mode === 'create');
// How many listings the picker lets you tick at once: Super Admin up to the batch limit; an owner up to
// the plan's free slots ("at a time" plans), or the monthly allowance. The server re-checks all of this.
const pickLimit = computed(() => (props.isAdmin
    ? props.batchLimit
    : Math.min(props.batchLimit, Number(props.quota?.per_month ? (props.quota?.limit ?? 0) : (props.quota?.remaining ?? 0)))));
const pickPlaceholder = computed(() => `Search by title, reference or location${props.isAdmin ? ', agent / agency' : ''}`);
const pickSearch = ref('');
const pickItems = ref([]);
const picked = ref(new Map()); // ticked listings, id => row (kept across searches)
const pickLoading = ref(false);
const pickStatus = ref('');
const full = computed(() => picked.value.size >= pickLimit.value);
const pickCount = computed(() => (pickLimit.value > 0
    ? `${picked.value.size} of ${pickLimit.value} selected${props.isAdmin ? '' : ' · plan limit'}`
    : (props.isAdmin ? '' : 'No premium slots free on your plan')));
let pickTerm = '';
let pickPage = 1;
let pickRequest = 0;
let pickTimer = null;

function loadPicker() {
    if (pickLoading.value || !pickPage) return;
    pickLoading.value = true;
    const id = ++pickRequest;
    pickStatus.value = pickPage === 1 ? 'Loading…' : 'Loading more…';
    http.get('/premium/eligible', { params: { page: pickPage, q: pickTerm || undefined } })
        .then((res) => {
            if (id !== pickRequest) return;
            pickItems.value.push(...res.data.items);
            pickPage = res.data.next_page;
            if (!pickItems.value.length) {
                pickStatus.value = pickTerm ? `No listings match “${pickTerm}”.` : (props.isAdmin ? 'No listings available to make premium.' : 'No listings available to make premium. Only active listings that aren\'t already premium or scheduled can be picked.');
            } else {
                pickStatus.value = pickPage ? '' : (pickItems.value.length > 20 ? `All ${pickItems.value.length} listings loaded.` : '');
            }
        })
        .catch(() => { if (id === pickRequest) pickStatus.value = 'Could not load listings. Scroll or type to try again.'; })
        .finally(() => { if (id === pickRequest) pickLoading.value = false; });
}

function restartPicker() {
    clearTimeout(pickTimer);
    pickRequest++; // cancel whatever is in flight
    pickLoading.value = false;
    pickTerm = pickSearch.value.trim();
    pickPage = 1;
    pickItems.value = [];
    loadPicker();
}

function onPickSearch() {
    clearTimeout(pickTimer);
    pickTimer = setTimeout(() => {
        if (pickSearch.value.trim() !== pickTerm) restartPicker();
    }, 300);
}

function onPickScroll(event) {
    const list = event.target;
    if (list.scrollTop + list.clientHeight >= list.scrollHeight - 80) loadPicker();
}

function togglePick(row, checked) {
    const next = new Map(picked.value);
    if (checked && !next.has(row.id) && !full.value) next.set(row.id, row);
    if (!checked) next.delete(row.id);
    picked.value = next;
}

/** Keeps the dates within bounds (as syncBounds() did). */
function render() {
    if (liveStart.value) {
        start.value = liveStart.value;
    } else if (start.value && start.value < todayIso.value) {
        start.value = todayIso.value;
    }
    if (end.value && (end.value < endMin.value || end.value > endMax.value)) {
        end.value = end.value < endMin.value ? endMin.value : endMax.value;
    }
    tick.value++;
}

function setPreset(d) {
    if (!start.value) start.value = todayIso.value;
    noEnd.value = false;
    end.value = toIso(addDays(fromIso(start.value), Math.min(d, maxDays.value) - 1));
    render();
}

watch(() => props.item, (item) => {
    if (!item) return;
    error.value = '';
    saving.value = false;
    if (props.mode === 'edit') {
        liveStart.value = item.live ? item.start : null;
        start.value = item.start || todayIso.value;
        end.value = item.end || '';
        noEnd.value = props.isAdmin && !item.end;
        render();
    } else {
        liveStart.value = null;
        noEnd.value = false;
        start.value = todayIso.value;
        end.value = '';
        setPreset(Math.min(30, maxDays.value));
        if (item.picker) {
            pickSearch.value = '';
            picked.value = new Map();
            restartPicker();
        }
    }
});

function close() {
    if (!saving.value) emit('close');
}

function submit() {
    error.value = '';
    let problem = null;
    if (picking.value ? picked.value.size === 0 : !props.item.id) problem = picking.value ? 'Tick at least one listing to make premium.' : 'Choose a listing to make premium.';
    else if (!start.value) problem = 'Choose a start date.';
    else if (!noEnd.value && !end.value) problem = props.isAdmin ? 'Choose an end date, or tick "No end date".' : 'Choose an end date.';
    else if (!noEnd.value && tooLong.value) problem = `Premium can run for at most ${maxDays.value} days.`;
    if (problem) {
        error.value = problem;
        return;
    }

    const body = { start_date: liveStart.value ? null : start.value, end_date: noEnd.value ? null : end.value };
    saving.value = true;
    // Picker: one batch request for every ticked listing, all-or-nothing. Otherwise book (POST) or re-date (PUT) the one listing.
    const request = picking.value
        ? http.post('/premium', { ...body, property_ids: [...picked.value.keys()] })
        : (props.mode === 'edit' ? http.put(`/properties/${props.item.id}/feature`, body) : http.post(`/properties/${props.item.id}/feature`, body));
    request
        .then(() => {
            saving.value = false;
            emit('saved');
        })
        .catch((e) => {
            saving.value = false;
            error.value = errorMessage(e, props.mode === 'edit' ? 'Could not change the dates.' : 'Could not make the listing(s) premium.');
        });
}
</script>
