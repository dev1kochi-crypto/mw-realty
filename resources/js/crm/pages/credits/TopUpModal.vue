<template>
    <CrmModal :open="open" bare content-class="crx__content" @close="close">
        <template #header>
            <div class="crx__head">
                <span class="crx__head-icon"><i class="fas fa-coins"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <h5 class="crx__title">{{ step === 'pay' ? 'Pay by card' : 'Buy credits' }}</h5>
                    <p class="crx__subtitle">Valid for {{ settings.validity_days }} days — until {{ formatDate(settings.valid_until) }}.</p>
                </div>
                <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
            </div>
        </template>

        <div class="crx__body">
            <div v-if="done" class="crx__done">
                <span class="crx__done-icon"><i class="fas fa-check"></i></span>
                <div class="fw-bold mb-1">Payment successful</div>
                <div class="small text-muted">{{ done }}</div>
            </div>

            <template v-else>
                <template v-if="step === 'quantity'">
                    <div>
                        <div class="crx__label">How many credits?</div>
                        <div class="crx__presets">
                            <button v-for="p in presets" :key="p" type="button" class="crx__preset" :class="{ 'is-active': Number(quantity) === p }" @click="quantity = p">{{ num(p) }}</button>
                        </div>
                        <div class="crx__stepper">
                            <button type="button" class="crx__stepper-btn" aria-label="Less" :disabled="Number(quantity) <= settings.min" @click="nudge(-step100)"><i class="fas fa-minus"></i></button>
                            <input v-model.number="quantity" type="number" class="form-control text-center" :min="settings.min" :max="settings.max" step="1" aria-label="Credits">
                            <button type="button" class="crx__stepper-btn" aria-label="More" :disabled="Number(quantity) >= settings.max" @click="nudge(step100)"><i class="fas fa-plus"></i></button>
                        </div>
                        <div v-if="quantityError" class="small text-danger mt-1">{{ quantityError }}</div>
                        <div v-else class="small text-muted mt-1">Between {{ num(settings.min) }} and {{ num(settings.max) }} credits per purchase.</div>
                    </div>


                    <div v-if="!settings.online" class="alert alert-warning small mb-0">Online payment isn't available right now — contact MW Realty to add credits to your account.</div>
                </template>

                <!-- The order, on both steps -->
                <div class="crx__receipt">
                    <div class="crx__receipt-line"><span>{{ num(quantity || 0) }} credits × {{ money(settings.price_per_credit) }} {{ settings.currency }}</span><span>{{ money(price.subtotal) }}</span></div>
                    <div class="crx__receipt-line"><span>VAT {{ settings.vat_percent }}%</span><span>{{ money(price.vat) }}</span></div>
                    <div class="crx__receipt-total"><span>Total</span><strong><small>{{ settings.currency }}</small> {{ money(price.total) }}</strong></div>
                </div>

                <!-- Card field is a Stripe Element iframe: the card goes straight from the browser to Stripe. -->
                <div v-show="step === 'pay'">
                    <div class="crx__label">Card details</div>
                    <div ref="cardEl" class="crx__card-field"></div>
                    <div class="crx__secure"><i class="fas fa-lock"></i> Processed securely by Stripe — MW Realty never sees your card.</div>
                </div>

                <div v-if="error" class="alert alert-danger small mb-0" role="alert">{{ error }}</div>
            </template>
        </div>

        <div class="crx__foot">
            <button v-if="done" type="button" class="btn crx__submit btn-sm" @click="close">Done</button>
            <template v-else>
                <button type="button" class="btn btn-portal-light btn-sm" :disabled="busy" @click="step === 'pay' ? back() : close()">{{ step === 'pay' ? 'Back' : 'Cancel' }}</button>
                <button v-if="step === 'quantity'" type="button" class="btn crx__submit btn-sm" :disabled="!settings.online || !!quantityError || busy" @click="proceed">
                    <span v-if="busy" class="spinner-border spinner-border-sm me-1"></span>Continue to payment<i class="fas fa-arrow-right ms-1"></i>
                </button>
                <button v-else type="button" class="btn crx__submit btn-sm" :disabled="busy" @click="pay">
                    <span v-if="busy" class="spinner-border spinner-border-sm me-1"></span><i v-else class="fas fa-lock me-1"></i>Pay {{ settings.currency }} {{ money(price.total) }}
                </button>
            </template>
        </div>
    </CrmModal>
</template>

<script setup>
/**
 * Buy credits — choose an amount (quick picks or −/+), see the order with VAT; then the card
 * (Stripe Element): POST /credits/top-up/intent → stripe.confirmCardPayment → POST /credits/top-up/complete.
 * The server re-prices everything; the numbers here are only the preview.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import http, { errorMessage } from '../../api/http';
import CrmModal from '../../components/CrmModal.vue';
import { formatDate } from '../../utils/format';
import { loadScript } from '../../utils/loadScript';

const props = defineProps({
    open: { type: Boolean, default: false },
    settings: { type: Object, required: true },
});
const emit = defineEmits(['close', 'paid']);

const quantity = ref(1000);
const step = ref('quantity'); // quantity | pay
const busy = ref(false);
const error = ref('');
const done = ref('');
const cardEl = ref(null);
const step100 = 100;

let stripe = null;
let card = null;
let intent = null;

const num = (n) => Number(n || 0).toLocaleString('en-US');
const money = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const round2 = (n) => Math.round(n * 100) / 100;
// Quick picks that fit Super Admin's min / max.
const presets = computed(() => [500, 1000, 2500, 5000, 10000].filter((p) => p >= props.settings.min && p <= props.settings.max).slice(0, 4));
const price = computed(() => {
    const subtotal = round2((Number(quantity.value) || 0) * props.settings.price_per_credit);
    const vat = round2(subtotal * props.settings.vat_percent / 100);
    return { subtotal, vat, total: round2(subtotal + vat) };
});
const quantityError = computed(() => {
    const q = Number(quantity.value);
    if (!Number.isInteger(q) || q < props.settings.min || q > props.settings.max) {
        return `Enter between ${num(props.settings.min)} and ${num(props.settings.max)} credits.`;
    }
    return '';
});

function nudge(by) {
    const q = (Number(quantity.value) || 0) + by;
    quantity.value = Math.min(props.settings.max, Math.max(props.settings.min, q));
}

// CrmModal drops its DOM when closed, so the card field is unmounted with it.
const unmountCard = () => { try { card?.unmount(); } catch { /* not mounted */ } };

async function proceed() {
    error.value = '';
    busy.value = true;
    try {
        await loadScript('https://js.stripe.com/v3/');
        if (typeof window.Stripe === 'undefined' || !props.settings.stripe_key) throw new Error('Card payments are unavailable right now. Please try again later.');
        stripe ??= window.Stripe(props.settings.stripe_key);
        step.value = 'pay';
        await nextTick();
        if (!card) {
            card = stripe.elements().create('card', { hidePostalCode: true, style: { base: { fontSize: '15px', color: '#1f2340', '::placeholder': { color: '#aab0c8' } } } });
            card.on('change', (e) => { error.value = e.error?.message || ''; });
        }
        card.mount(cardEl.value);
    } catch (e) {
        step.value = 'quantity';
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}

function back() {
    unmountCard();
    intent = null; // the amount may change: a new payment is created for it
    step.value = 'quantity';
    error.value = '';
}

async function pay() {
    error.value = '';
    busy.value = true;
    try {
        if (!intent) {
            const res = await http.post('/credits/top-up/intent', { credits: quantity.value });
            if (res.data.success === false) throw new Error(res.data.message);
            intent = res.data;
        }
        const { error: cardError } = await stripe.confirmCardPayment(intent.client_secret, { payment_method: { card } });
        if (cardError) throw new Error(cardError.message);
        const res = await http.post('/credits/top-up/complete', { payment_intent_id: intent.payment_intent_id });
        done.value = res.data.message;
        unmountCard();
        emit('paid', res.data.balance);
    } catch (e) {
        error.value = e.response ? errorMessage(e, 'Payment could not be completed.') : e.message;
    } finally {
        busy.value = false;
    }
}

function close() {
    if (!busy.value) emit('close');
}

watch(() => props.open, (open) => {
    if (!open) { unmountCard(); return; }
    quantity.value = Math.max(props.settings.min, Math.min(1000, props.settings.max));
    step.value = 'quantity';
    error.value = '';
    done.value = '';
    intent = null;
});

onBeforeUnmount(() => card?.destroy());
</script>
