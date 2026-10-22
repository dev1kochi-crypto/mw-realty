<template>
    <CrmModal :open="open" bare size="lg" content-class="crx__content" @close="$emit('close')">
        <template #header>
            <div class="crx__head">
                <span class="crx__head-icon"><i class="fas fa-calculator"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <h5 class="crx__title">Credit calculator</h5>
                    <p class="crx__subtitle">Describe the listing — see what each add-on costs before you spend.</p>
                </div>
                <button type="button" class="btn-close" aria-label="Close" @click="$emit('close')"></button>
            </div>
        </template>

        <div class="crx__body">
            <div v-if="!options" class="text-center py-4">
                <span v-if="!loadError" class="spinner-border spinner-border-sm text-primary"></span>
                <div v-else class="text-muted small">{{ loadError }} <button type="button" class="btn btn-link btn-sm" @click="loadOptions">Try again</button></div>
            </div>
            <template v-else>
                <div class="crx__grid">
                    <div>
                        <div class="crx__label">Category</div>
                        <div class="crx__seg" role="group" aria-label="Category">
                            <button v-for="o in options.categories" :key="o.value" type="button" class="crx__seg-btn" :class="{ 'is-active': form.segment === o.value }" @click="form.segment = o.value">
                                <i class="fas me-1" :class="o.value === 'commercial' ? 'fa-store' : 'fa-house'"></i>{{ o.label }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <div class="crx__label">Offering</div>
                        <div class="crx__seg" role="group" aria-label="Offering">
                            <button v-for="o in options.listing_types" :key="o.value" type="button" class="crx__seg-btn" :class="{ 'is-active': form.listing_type === o.value }" @click="form.listing_type = o.value">{{ o.label }}</button>
                        </div>
                    </div>
                    <div>
                        <label class="crx__label" for="calcType">Property type</label>
                        <select id="calcType" v-model="form.property_type" class="form-select">
                            <option value="">Choose…</option>
                            <option v-for="o in options.property_types" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="crx__label" for="calcPrice">Price <span class="fw-normal text-muted">(optional)</span></label>
                        <div class="input-group">
                            <span class="input-group-text">AED</span>
                            <input id="calcPrice" v-model="form.price" type="number" min="0" step="1" class="form-control" placeholder="e.g. 85,000">
                        </div>
                    </div>
                </div>

                <div class="crx__results" :class="{ 'is-waiting': !result }" aria-live="polite">
                    <div class="crx__results-head">
                        <span>Cost in credits</span>
                        <span v-if="busy" class="spinner-border spinner-border-sm text-secondary"></span>
                    </div>
                    <div v-if="error" class="alert alert-danger small mb-0">{{ error }}</div>
                    <div v-else-if="!result" class="crx__empty"><i class="far fa-hand-pointer me-1"></i> Pick a category, offering and property type to see the costs.</div>
                    <div v-else class="crx__cards">
                        <div class="crx__card crx__card--listing">
                            <div class="crx__card-tag"><i class="fas fa-plus"></i> Extra listing</div>
                            <div class="crx__card-value">{{ num(result.listing) }}</div>
                            <div class="crx__card-sub">credits · ≈ AED {{ aed(result.listing) }}</div>
                        </div>
                        <div v-for="d in options.premium_days" :key="d" class="crx__card crx__card--premium">
                            <div class="crx__card-tag"><i class="fas fa-star"></i> Premium · {{ d }} days</div>
                            <div class="crx__card-value">{{ num(result.premium[d]) }}</div>
                            <div class="crx__card-sub">credits · ≈ AED {{ aed(result.premium[d]) }}</div>
                        </div>
                    </div>
                </div>
                <p class="crx__note"><i class="fas fa-info-circle"></i> An extra listing is one listing beyond your plan's allowance. Premium bought with credits doesn't use your plan's premium quota. AED amounts exclude VAT.</p>
            </template>
        </div>

        <div class="crx__foot">
            <button type="button" class="btn btn-portal-light btn-sm" @click="reset">Clear</button>
            <button v-if="canTopUp" type="button" class="btn crx__submit btn-sm" @click="$emit('top-up')"><i class="fas fa-coins me-1"></i> Buy credits</button>
        </div>
    </CrmModal>
</template>

<script setup>
/**
 * Credit calculator — what an extra listing and Premium (7 / 15 / 30 days) cost in credits for a
 * listing like this (rates set by Super Admin in CMS › Credits). Recalculates as the choices change
 * (POST /credits/calculate).
 */
import { computed, reactive, ref, watch } from 'vue';
import http, { errorMessage } from '../../api/http';
import CrmModal from '../../components/CrmModal.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    pricePerCredit: { type: Number, default: 0 },
    canTopUp: { type: Boolean, default: false },
});
defineEmits(['close', 'top-up']);

const options = ref(null);
const loadError = ref('');
const form = reactive({ segment: '', listing_type: '', property_type: '', price: '' });
const result = ref(null);
const error = ref('');
const busy = ref(false);
let timer = null;
let request = 0;

const ready = computed(() => form.segment && form.listing_type && form.property_type);
const num = (n) => Number(n || 0).toLocaleString('en-US');
const aed = (credits) => (credits * props.pricePerCredit).toLocaleString('en-US', { maximumFractionDigits: 0 });

function loadOptions() {
    loadError.value = '';
    http.get('/credits/calculator')
        .then((res) => {
            options.value = res.data;
            form.segment = res.data.categories[0]?.value ?? '';
        })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function reset() {
    Object.assign(form, { segment: options.value?.categories[0]?.value ?? '', listing_type: '', property_type: '', price: '' });
    result.value = null;
    error.value = '';
}

function calculate() {
    const id = ++request;
    busy.value = true;
    error.value = '';
    http.post('/credits/calculate', { ...form, price: form.price === '' ? null : form.price })
        .then((res) => { if (id === request) result.value = res.data; })
        .catch((e) => { if (id === request) { result.value = null; error.value = errorMessage(e); } })
        .finally(() => { if (id === request) busy.value = false; });
}

watch(form, () => {
    clearTimeout(timer);
    if (!ready.value) { result.value = null; error.value = ''; return; }
    timer = setTimeout(calculate, 300);
});

watch(() => props.open, (open) => {
    if (open && !options.value) loadOptions();
}, { immediate: true });
</script>
