<template>
    <div v-if="!o" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <RouterLink :to="{ name: 'plans.index' }" class="btn btn-link">Back to Plans</RouterLink></div>
    </div>
    <div v-else class="co-wrap">
        <div class="co-head">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <RouterLink :to="{ name: 'plans.index' }" class="co-back"><i class="fas fa-arrow-left me-1"></i>Plans</RouterLink>
                <div class="co-brand"><span class="co-brand__icon"><i class="fas fa-shield-halved"></i></span>MW Realty<small>Checkout</small></div>
            </div>
            <div class="co-steps" aria-label="Checkout steps">
                <span class="co-step"><span class="co-step__dot"><i class="fas fa-check"></i></span>Choose plan</span>
                <span class="co-step__line"></span>
                <span class="co-step"><span class="co-step__dot"><i class="fas fa-check"></i></span>Review</span>
                <span class="co-step__line"></span>
                <span class="co-step" :class="{ 'is-current': !paid }"><span class="co-step__dot"><i v-if="paid" class="fas fa-check"></i><template v-else>3</template></span>Payment</span>
            </div>
        </div>

        <div class="co-shell" :class="{ 'is-paid': paid, 'co-processing': processing }">
            <!-- Left: live card preview + order -->
            <div class="co-left">
                <div class="co-stage" @pointermove="tilt" @pointerleave="tiltStyle = ''">
                    <div class="co-card" :class="cardClasses" :data-brand="brand" :style="{ transform: tiltStyle }">
                        <CheckoutCardFaces :plan="o.plan.name" :name="cardName" :brand-icon="brandIcon" :number-state="numberState" :expiry="expiryText" :cvc="cvcText" :cvc-state="cvcState" />
                    </div>
                </div>

                <div class="co-order">
                    <div class="co-order__label">Your order</div>
                    <div class="co-line">
                        <div>{{ o.plan.name }} plan<small>Billed {{ o.interval }} · renews automatically</small></div>
                        <strong>{{ o.currency }} {{ money(o.price) }}</strong>
                    </div>
                    <ul v-if="o.highlights.length" class="co-perks"><li v-for="perk in o.highlights" :key="perk">{{ perk }}</li></ul>
                    <div v-if="o.coupon && o.coupon.discount > 0" class="co-line is-good">
                        <div><i class="fas fa-ticket-alt me-1"></i>Coupon {{ o.coupon.code }}<small>{{ o.coupon.label }}</small></div>
                        <strong>−{{ o.currency }} {{ money(o.coupon.discount) }}</strong>
                    </div>
                    <div class="co-total">
                        <span>Total due today</span>
                        <strong><small>{{ o.currency }}</small>{{ money(o.total) }}</strong>
                    </div>
                    <div class="co-renew"><i class="fas fa-rotate me-1"></i>Then {{ o.currency }} {{ money(o.renew_amount) }} every {{ o.unit }} from {{ o.renews_on }}. Cancel auto-renew any time.</div>
                </div>
            </div>

            <!-- Right: payment form (card fields are Stripe Elements iframes) -->
            <div class="co-right">
                <h1 class="co-title">Payment details</h1>
                <p class="co-sub">Enter your card to activate the {{ o.plan.name }} plan.</p>

                <div class="co-alert" :class="{ 'is-shown': alert }" role="alert"><i class="fas fa-circle-exclamation mt-1"></i><span>{{ alert }}</span></div>

                <form novalidate @submit.prevent="pay">
                    <div class="co-group">
                        <label class="co-label" for="coName">Cardholder name</label>
                        <div class="co-input" :class="{ 'is-focus': focus === 'name', 'is-invalid': hints.name }">
                            <i class="far fa-user"></i>
                            <input id="coName" v-model="cardName" type="text" autocomplete="cc-name" placeholder="Name on card" maxlength="60" @input="hints.name = ''; alert = ''" @focus="focus = 'name'; flipped = false" @blur="focus = null">
                        </div>
                        <div class="co-hint">{{ hints.name }}</div>
                    </div>

                    <div class="co-group">
                        <span class="co-label">Card number</span>
                        <div class="co-input" :class="{ 'is-focus': focus === 'number', 'is-invalid': hints.number, 'is-complete': complete.number }">
                            <i class="far fa-credit-card"></i>
                            <div ref="numberEl" class="co-el"></div>
                            <i class="co-input__brand" :class="brandIcon ? ['fab', brandIcon, 'is-known'] : ['fas', 'fa-credit-card']"></i>
                        </div>
                        <div class="co-hint">{{ hints.number }}</div>
                    </div>

                    <div class="co-group">
                        <div class="co-row">
                            <div>
                                <span class="co-label">Expiry date</span>
                                <div class="co-input" :class="{ 'is-focus': focus === 'expiry', 'is-invalid': hints.expiry, 'is-complete': complete.expiry }">
                                    <i class="far fa-calendar"></i>
                                    <div ref="expiryEl" class="co-el"></div>
                                </div>
                                <div class="co-hint">{{ hints.expiry }}</div>
                            </div>
                            <div>
                                <span class="co-label">CVC <em>back of card</em></span>
                                <div class="co-input" :class="{ 'is-focus': focus === 'cvc', 'is-invalid': hints.cvc, 'is-complete': complete.cvc }">
                                    <i class="fas fa-lock"></i>
                                    <div ref="cvcEl" class="co-el"></div>
                                </div>
                                <div class="co-hint">{{ hints.cvc }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="co-save">
                        <span class="co-save__tick"><i class="fas fa-check"></i></span>
                        <div><strong>Card saved for renewals</strong><small>Stored securely by Stripe, never by MW Realty.</small></div>
                    </div>

                    <button type="submit" class="co-pay" :disabled="processing || unavailable">
                        <i class="fas fa-lock"></i>
                        <span v-if="processing"><span class="spinner-border spinner-border-sm me-2"></span>Processing payment…</span>
                        <span v-else>Pay {{ o.currency }} {{ money(o.total) }}</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    <div class="co-trust">
                        <span><i class="fas fa-shield-halved"></i>PCI-DSS Level 1</span>
                        <span><i class="fas fa-lock"></i>256-bit TLS</span>
                        <span><i class="fab fa-stripe-s"></i>Powered by Stripe</span>
                    </div>
                    <div class="co-trust-note">MW Realty never stores your card number or CVC.</div>
                </form>
            </div>

            <!-- Paid -->
            <div class="co-done" aria-live="polite">
                <div class="co-stage w-100 mb-0">
                    <div class="co-card" :data-brand="brand">
                        <CheckoutCardFaces :plan="o.plan.name" :name="cardName" :brand-icon="brandIcon" number-state="is-done" :last4="result?.card?.last4" expiry="••/••" cvc="•••" cvc-state="is-done" />
                    </div>
                </div>
                <svg class="co-check" viewBox="0 0 52 52" aria-hidden="true"><circle cx="26" cy="26" r="24" /><path d="M15 27l7 7 15-15" /></svg>
                <h2>Paid {{ o.currency }} {{ money(o.total) }}</h2>
                <p>{{ doneText }}</p>
                <RouterLink :to="{ name: 'plans.index' }" class="btn btn-portal-primary">Go to my plan <i class="fas fa-arrow-right ms-1"></i></RouterLink>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * Card checkout for a first subscription (resources/views/portal/plans/checkout). Card fields are
 * Stripe Elements, so the card goes from the browser straight to Stripe: POST /plans/checkout/intent
 * → stripe.confirmCardPayment → POST /plans/checkout/complete. The unpaid subscription is kept for
 * retries: after a decline the same payment is confirmed with the corrected card.
 */
import { computed, nextTick, onBeforeUnmount, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import { loadScript } from '../../utils/loadScript';
import CheckoutCardFaces from './plans/CheckoutCardFaces.vue';

const route = useRoute();

const brandIcons = { visa: 'fa-cc-visa', mastercard: 'fa-cc-mastercard', amex: 'fa-cc-amex', discover: 'fa-cc-discover', diners: 'fa-cc-diners-club', jcb: 'fa-cc-jcb' };

const o = ref(null);
const loadError = ref('');
const cardName = ref('');
const alert = ref('');
const hints = reactive({ name: '', number: '', expiry: '', cvc: '' });
const complete = reactive({ number: false, expiry: false, cvc: false });
const empty = reactive({ number: true, expiry: true, cvc: true });
const focus = ref(null);
const flipped = ref(false);
const brand = ref('unknown');
const shake = ref(false);
const tiltStyle = ref('');
const processing = ref(false);
const unavailable = ref(false);
const paid = ref(false);
const result = ref(null);
const numberEl = ref(null);
const expiryEl = ref(null);
const cvcEl = ref(null);

let stripe = null;
let fields = null;
let intent = null;

const money = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const brandIcon = computed(() => brandIcons[brand.value] ?? null);
const allComplete = computed(() => complete.number && complete.expiry && complete.cvc);
const cardClasses = computed(() => ({ 'is-flipped': flipped.value, 'is-complete': allComplete.value, 'is-error': shake.value }));
// Stripe keeps the digits inside its iframe, so the preview reflects progress, not the number itself.
const numberState = computed(() => (complete.number ? 'is-done' : (!empty.number ? 'is-typing' : '')));
const expiryText = computed(() => (complete.expiry ? '••/••' : (empty.expiry ? 'MM/YY' : '••/··')));
const cvcText = computed(() => (empty.cvc ? 'CVC' : '•••'));
const cvcState = computed(() => (complete.cvc ? 'is-done' : (!empty.cvc ? 'is-typing' : '')));
const doneText = computed(() => {
    const r = result.value;
    if (!r) return `Welcome to the ${o.value.plan.name} plan.`;
    let text = r.card
        ? `${r.card.brand.charAt(0).toUpperCase() + r.card.brand.slice(1)} •••• ${r.card.last4} · welcome to the ${r.plan} plan.`
        : `Welcome to the ${o.value.plan.name} plan.`;
    if (!r.activated) text += ' Your plan is being activated — this takes a few seconds.';
    return text;
});

function showError(message) {
    alert.value = message;
    shake.value = false;
    requestAnimationFrame(() => { shake.value = true; });
}

function tilt(event) {
    if (flipped.value) return;
    const r = event.currentTarget.getBoundingClientRect();
    const x = (event.clientX - r.left) / r.width - 0.5;
    const y = (event.clientY - r.top) / r.height - 0.5;
    tiltStyle.value = `rotateY(${x * 14}deg) rotateX(${-y * 12}deg)`;
}

function mountFields() {
    if (typeof window.Stripe === 'undefined' || !o.value.stripe_key) {
        showError('Card payments are unavailable right now. Please try again later.');
        unavailable.value = true;
        return;
    }
    stripe = window.Stripe(o.value.stripe_key);
    const elements = stripe.elements({ fonts: [{ cssSrc: 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700&display=swap' }] });
    const style = {
        base: { fontFamily: "'Plus Jakarta Sans', 'Segoe UI', sans-serif", fontSize: '15px', fontWeight: '600', color: '#1f2340', letterSpacing: '0.02em', '::placeholder': { color: '#aab0c8', fontWeight: '500' } },
        invalid: { color: '#dc2626', iconColor: '#dc2626' },
    };
    fields = {
        number: elements.create('cardNumber', { style, showIcon: false, placeholder: '1234 1234 1234 1234' }),
        expiry: elements.create('cardExpiry', { style }),
        cvc: elements.create('cardCvc', { style, placeholder: '•••' }),
    };
    fields.number.mount(numberEl.value);
    fields.expiry.mount(expiryEl.value);
    fields.cvc.mount(cvcEl.value);

    Object.entries(fields).forEach(([key, el]) => {
        el.on('focus', () => { focus.value = key; flipped.value = key === 'cvc'; });
        el.on('blur', () => { focus.value = null; if (key === 'cvc') flipped.value = false; });
        el.on('change', (e) => {
            complete[key] = e.complete;
            empty[key] = e.empty;
            hints[key] = e.error?.message || '';
            alert.value = '';
            if (key === 'number') brand.value = brandIcons[e.brand] ? e.brand : 'unknown';
        });
    });
}

async function pay() {
    alert.value = '';
    const name = cardName.value.trim();
    let ok = true;
    if (!name) { hints.name = 'Enter the name shown on the card.'; ok = false; }
    if (!complete.number) { hints.number ||= 'Enter your card number.'; ok = false; }
    if (!complete.expiry) { hints.expiry ||= 'Enter the expiry date.'; ok = false; }
    if (!complete.cvc) { hints.cvc ||= 'Enter the CVC.'; ok = false; }
    if (!ok) { showError('Please check the highlighted fields.'); return; }

    processing.value = true;
    const order = { plan_id: o.value.plan.id, interval: o.value.interval, coupon_code: o.value.coupon?.code ?? null };
    try {
        if (!intent) {
            const res = await http.post('/plans/checkout/intent', order);
            if (res.data.success === false) throw new Error(res.data.message);
            intent = res.data;
        }
        if (intent.client_secret) {
            const { error } = await stripe.confirmCardPayment(intent.client_secret, {
                payment_method: { card: fields.number, billing_details: { name, email: o.value.owner.email } },
            });
            if (error) throw new Error(error.message);
        }
        const res = await http.post('/plans/checkout/complete', { subscription_id: intent.subscription_id });
        result.value = res.data;
        paid.value = true;
        Object.values(fields).forEach((f) => f.unmount());
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (err) {
        processing.value = false;
        showError(err.response ? errorMessage(err, 'Something went wrong. Please try again.') : err.message);
    }
}

http.get('/plans/checkout', { params: route.query })
    .then(async (res) => {
        o.value = res.data;
        cardName.value = res.data.owner.name;
        await loadScript('https://js.stripe.com/v3/').catch(() => {});
        await nextTick();
        mountFields();
    })
    .catch((e) => { loadError.value = errorMessage(e); });

onBeforeUnmount(() => {
    if (fields && !paid.value) Object.values(fields).forEach((f) => f.destroy());
});
</script>
