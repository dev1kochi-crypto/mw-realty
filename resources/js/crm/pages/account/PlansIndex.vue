<template>
    <div v-if="!d" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>

    <!-- An agency agent is covered by the agency's plan — nothing to buy here. -->
    <div v-else-if="d.agency_plan">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="portal-section-title mb-0">Your Plan</div>
            <RouterLink v-if="d.has_payments" :to="{ name: 'plans.payments' }" class="btn btn-portal-light btn-sm"><i class="fas fa-receipt me-1"></i>Payment history</RouterLink>
        </div>
        <div class="portal-card p-4" style="max-width: 720px;">
            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="d-flex align-items-center justify-content-center flex-shrink-0"
                      style="width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, var(--portal-primary), var(--portal-accent, #04a1cc)); color: #fff; font-size: 1.4rem;">
                    <i class="fas fa-building-user"></i>
                </span>
                <div class="min-w-0">
                    <div class="portal-muted small">Covered by your agency</div>
                    <div class="fs-5 fw-bold">{{ ap.plan ?? 'No plan' }} <span class="portal-muted fw-normal">· {{ ap.agency }}</span></div>
                </div>
            </div>
            <p class="portal-muted mb-3">
                You're an agent of <strong>{{ ap.agency }}</strong>, so you work on the agency's plan — there's nothing for you to buy or upgrade.
                Listings you add belong to the agency and count towards its plan. If you need more, ask your agency to upgrade.
            </p>
            <template v-if="ap.plan">
                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-semibold mb-1">
                        <span>Agency listings</span>
                        <span>{{ ap.used }}{{ ap.limit ? ` / ${ap.limit}` : '' }}{{ ap.limit ? '' : ' · unlimited' }}</span>
                    </div>
                    <div class="progress" style="height: 6px;"><div class="progress-bar" :style="{ width: `${ap.limit ? Math.min(100, Math.round(ap.used / Math.max(1, ap.limit) * 100)) : 100}%`, background: 'var(--portal-primary)' }"></div></div>
                </div>
                <ul class="list-unstyled mb-0 row g-2">
                    <li v-for="line in ap.entitlements" :key="line.text" class="col-sm-6 small" :class="{ 'portal-muted text-decoration-line-through': !line.included }">
                        <i class="fas me-1" :class="line.included ? 'fa-check-circle text-success' : 'fa-times-circle'"></i>{{ line.text }}
                    </li>
                </ul>
            </template>
            <div v-if="ap.own_subscription_ends" class="alert alert-info small mt-3 mb-0">
                <i class="fas fa-info-circle me-1"></i>Your own subscription won't renew — it ends on <strong>{{ formatDate(ap.own_subscription_ends) }}</strong> and you won't be charged again.
            </div>
        </div>
    </div>

    <div v-else>
        <!-- One compact summary bar (current plan, usage, subscription, billing actions) so the plans stay in view. -->
        <div class="pl-bar mb-3">
            <div class="pl-bar__main">
                <span class="pl-bar__icon"><i class="fas fa-layer-group"></i></span>
                <div class="min-w-0">
                    <div class="pl-bar__title">
                        <span>{{ c.plan ?? 'No plan' }}<template v-if="c.paid"> · {{ ucfirst(c.interval) }}</template></span>
                        <span v-if="c.status" class="pl-sub__badge" :class="c.status[0]">{{ c.status[1] }}</span>
                        <span v-if="c.pending_request" class="pl-sub__badge pl-sub__badge--warn"><i class="fas fa-clock me-1"></i>{{ c.pending_request }} pending review</span>
                    </div>
                    <div class="pl-bar__meta">
                        <span class="pl-bar__usage" title="Properties and Commercial combined">
                            <span class="pl-bar__meter"><span :class="{ 'is-full': c.limit && c.used >= c.limit }" :style="{ width: `${c.limit ? Math.min(100, Math.round(c.used / Math.max(1, c.limit) * 100)) : 100}%` }"></span></span>
                            {{ c.used }}{{ c.limit ? ` / ${c.limit}` : '' }} listings{{ c.limit ? '' : ' · unlimited' }}
                        </span>
                        <template v-if="d.subscribed">
                            <span class="pl-bar__sep" aria-hidden="true">·</span>
                            <span>
                                <template v-if="c.cancel_at_period_end">Auto-renew off — ends <strong>{{ formatDate(c.renews_at) }}</strong>, then Free</template>
                                <template v-else-if="c.scheduled"><strong>{{ c.scheduled.plan }} ({{ c.scheduled.interval }})</strong> starts <strong>{{ formatDate(c.renews_at) }}</strong> at AED {{ fmt(c.scheduled.price) }}/{{ unitWord(c.scheduled.interval) }}</template>
                                <template v-else>Renews <strong>{{ c.renews_at ? formatDate(c.renews_at) : '—' }}</strong> · AED {{ fmt(c.price) }}</template>
                                <template v-if="c.past_due"> — update your card to keep your plan.</template>
                            </span>
                        </template>
                        <template v-else-if="!d.stripe_enabled">
                            <span class="pl-bar__sep" aria-hidden="true">·</span>
                            <span>Upgrades are activated by our team after a quick review.</span>
                        </template>
                    </div>
                </div>
            </div>
            <div class="pl-bar__actions">
                <RouterLink v-if="d.has_payments" :to="{ name: 'plans.payments' }" class="btn btn-portal-light btn-sm"><i class="fas fa-receipt me-1"></i>Payment history</RouterLink>
                <template v-if="d.subscribed">
                    <button v-if="c.scheduled" type="button" class="btn btn-portal-primary btn-sm" :disabled="busy" @click="subscription('keep-plan')"><i class="fas fa-undo me-1"></i>Keep {{ c.plan }}</button>
                    <button v-else-if="c.cancel_at_period_end" type="button" class="btn btn-portal-primary btn-sm" :disabled="busy" @click="subscription('resume')"><i class="fas fa-redo me-1"></i>Resume auto-renew</button>
                    <button v-else type="button" class="btn btn-portal-light btn-sm text-danger" :disabled="busy" @click="cancelRenewal"><i class="fas fa-ban me-1"></i>Cancel auto-renew</button>
                </template>
            </div>
        </div>

        <div v-if="route.query.checkout === 'cancelled'" class="alert alert-warning py-2"><i class="fas fa-info-circle me-2"></i>Checkout was cancelled — you haven't been charged.</div>

        <!-- Billing period toggle + coupon on one line -->
        <div v-if="d.has_yearly || d.show_coupon" class="pl-controls">
            <div v-if="d.has_yearly" class="pl-toggle" role="tablist" aria-label="Billing period">
                <button type="button" :class="{ 'is-active': interval === 'monthly' }" @click="interval = 'monthly'">Monthly</button>
                <button type="button" :class="{ 'is-active': interval === 'yearly' }" @click="interval = 'yearly'">
                    Yearly <span v-if="d.max_saving > 0" class="pl-toggle__save">Save up to {{ d.max_saving }}%</span>
                </button>
            </div>
            <div v-if="d.show_coupon" class="pl-coupon" :class="{ 'is-applied': coupon }">
                <form class="pl-coupon__form" @submit.prevent="applyCoupon">
                    <span class="pl-coupon__icon" aria-hidden="true"><i class="fas fa-ticket-alt"></i></span>
                    <input ref="couponInput" v-model="couponCode" type="text" class="form-control text-uppercase" placeholder="Coupon code" aria-label="Coupon code" maxlength="40" autocomplete="off" :readonly="!!coupon">
                    <button type="submit" class="btn btn-portal-primary btn-sm" :disabled="couponBusy">Apply</button>
                    <button v-if="coupon" type="button" class="btn btn-portal-light btn-sm" @click="removeCoupon">Remove</button>
                </form>
                <div class="pl-coupon__status">
                    <span v-if="coupon" class="text-success fw-semibold"><i class="fas fa-check-circle me-1"></i>Coupon {{ coupon.code }} applied.</span><template v-if="coupon"> It's used on the plan you choose below.</template>
                    <span v-else-if="couponError" class="text-danger fw-semibold"><i class="fas fa-times-circle me-1"></i>{{ couponError }}</span>
                    <template v-else-if="couponTouched">Apply it to see your discounted price before choosing a plan.</template>
                    <template v-else>Have a coupon? Apply it to see discounted prices.</template>
                </div>
            </div>
        </div>

        <div class="pl-grid">
            <div v-for="plan in d.plans" :key="plan.id" class="pl-card" :class="{ 'pl-card--popular': plan.popular, 'pl-card--current': plan.is_current_plan && !plan.popular }">
                <span v-if="plan.is_current_plan" class="pl-ribbon pl-ribbon--current"><i class="fas fa-check-circle me-1"></i>Your Plan</span>
                <span v-else-if="plan.popular" class="pl-ribbon"><i class="fas fa-fire me-1"></i>Most Popular</span>

                <div class="pl-icon"><i class="fas" :class="plan.icon"></i></div>
                <h3 class="pl-name">{{ plan.name }}</h3>
                <p class="pl-desc">{{ plan.description }}</p>

                <div class="pl-price">
                    <template v-if="plan.is_free">
                        <span class="pl-price__amount">Free</span>
                        <span class="pl-price__cycle">forever</span>
                    </template>
                    <template v-else>
                        <span class="pl-price__currency">AED</span>
                        <span v-if="discountFor(plan)?.valid" class="pl-price__amount">{{ Number(discountFor(plan).final).toLocaleString('en-US', { maximumFractionDigits: 2 }) }}<span class="pl-price__old">{{ Number(discountFor(plan).original).toLocaleString('en-US') }}</span></span>
                        <span v-else class="pl-price__amount">{{ whole(cell(plan).price) }}</span>
                        <span class="pl-price__cycle">/ {{ cell(plan).unit }}</span>
                    </template>
                </div>
                <div v-if="cell(plan).yearly_note" class="pl-yearly-note" :class="{ 'is-muted': cell(plan).yearly_note.muted }">{{ cell(plan).yearly_note.text }}</div>
                <div v-if="discountFor(plan)" class="pl-discount" :class="{ 'is-invalid': !discountFor(plan).valid }">
                    <template v-if="discountFor(plan).valid"><i class="fas fa-tag me-1"></i>{{ coupon.code }}: {{ discountFor(plan).label }} — save AED {{ fmt(discountFor(plan).discount) }}</template>
                    <template v-else><i class="fas fa-info-circle me-1"></i>{{ discountFor(plan).message }}</template>
                </div>

                <ul class="pl-features">
                    <li v-for="(feature, i) in plan.features" :key="i" :class="{ 'is-highlight': feature.state === 'highlight', 'is-excluded': feature.state === 'excluded' }">
                        <span class="pl-check"><i class="fas" :class="feature.state === 'excluded' ? 'fa-times' : 'fa-check'"></i></span>{{ feature.text }}
                    </li>
                </ul>

                <template v-for="action in [cell(plan).action]" :key="action.kind">
                    <button v-if="action.kind === 'current'" type="button" class="pl-btn pl-btn--current" disabled><i class="fas fa-check me-1"></i>Current Plan</button>
                    <button v-else-if="action.kind === 'scheduled'" type="button" class="pl-btn pl-btn--muted" disabled><i class="fas fa-calendar-alt me-1"></i>Starts {{ action.starts }}</button>
                    <button v-else-if="action.kind === 'change'" type="button" class="pl-btn" :class="plan.popular ? 'pl-btn--light' : 'pl-btn--primary'" @click="openChange(plan)">
                        <template v-if="action.change === 'cancel'">Switch to {{ plan.name }}</template>
                        <template v-else-if="action.change === 'upgrade'"><i class="fas fa-arrow-up me-1"></i>Upgrade</template>
                        <template v-else><i class="fas fa-arrow-down me-1"></i>Downgrade</template>
                    </button>
                    <button v-else-if="action.kind === 'checkout'" type="button" class="pl-btn" :class="plan.popular ? 'pl-btn--light' : 'pl-btn--primary'" @click="checkout(plan)"><i class="fas fa-lock me-1"></i>Subscribe &amp; Pay</button>
                    <button v-else-if="action.kind === 'pending'" type="button" class="pl-btn pl-btn--muted" disabled><i class="fas fa-hourglass-half me-1"></i>Request Pending</button>
                    <button v-else-if="action.kind === 'blocked'" type="button" class="pl-btn pl-btn--muted" disabled title="You already have a pending request">Upgrade</button>
                    <button v-else type="button" class="pl-btn" :class="plan.popular ? 'pl-btn--light' : 'pl-btn--primary'" :disabled="busy" @click="requestPlan(plan)">
                        {{ action.label }} <i class="fas fa-arrow-right ms-1"></i>
                    </button>
                </template>
            </div>
        </div>

        <p class="pl-note"><i class="fas fa-shield-alt me-1"></i> {{ d.note }}</p>

        <!-- Plan change confirmation: shows exactly what will be charged (Stripe preview) before switching. -->
        <CrmModal :open="change.open" modal-class="pl-modal" bare @close="change.open = false">
            <template #header><span></span></template>
            <div class="pc-top">
                <button type="button" class="btn-close" aria-label="Close" @click="change.open = false"></button>
                <span class="pc-kind" :class="pv && kinds[pv.type] ? `pc-kind--${pv.type}` : ''"><template v-if="pv && kinds[pv.type]"><i class="fas" :class="kinds[pv.type].icon"></i> {{ kinds[pv.type].label }}</template></span>
                <h5 class="pc-title">{{ changeTitle }}</h5>
            </div>

            <div class="pc-flow">
                <div class="pc-plan">
                    <div class="pc-plan__tag">Current</div>
                    <div class="pc-plan__name">{{ pv ? (pv.current_plan || '—') : '' }}</div>
                    <div class="pc-plan__price">{{ pv ? `AED ${fmt(pv.current_price)}/${shortUnit(pv.current_interval)} · ${pv.current_interval}` : '' }}</div>
                </div>
                <span class="pc-arrow"><i class="fas fa-arrow-right"></i></span>
                <div class="pc-plan pc-plan--to">
                    <div class="pc-plan__tag">New</div>
                    <div class="pc-plan__name">{{ pv?.plan }}</div>
                    <div class="pc-plan__price">{{ pv ? (pv.new_price > 0 ? `AED ${fmt(pv.new_price)}/${shortUnit(pv.interval)} · ${pv.interval}` : 'Free') : '' }}</div>
                </div>
            </div>

            <div class="pc-body">
                <div v-if="change.loading" class="text-center py-4 portal-muted"><span class="spinner-border spinner-border-sm me-2"></span>Calculating with Stripe…</div>
                <div v-else-if="change.error" class="alert alert-danger mb-0">{{ change.error }}</div>
                <div v-else-if="pv">
                    <template v-if="pv.type === 'upgrade'">
                        <div class="pc-due"><div class="pc-due__label">Charged today</div><div class="pc-due__amount"><small>AED</small>{{ fmt(pv.due_now) }}</div><div class="pc-due__sub">Your new plan is active right after payment</div></div>
                        <ul class="pc-lines">
                            <li v-for="(line, i) in pv.lines" :key="i" :class="{ 'is-credit': line.amount < 0 }"><span><i class="fas" :class="line.amount < 0 ? 'fa-undo-alt' : 'fa-layer-group'"></i>{{ line.description }}</span><strong>{{ line.amount < 0 ? '−' : '' }}AED {{ fmt(Math.abs(line.amount)) }}</strong></li>
                            <li v-if="pv.discount > 0" class="is-credit"><span><i class="fas fa-ticket-alt"></i>Coupon {{ pv.coupon || '' }}</span><strong>−AED {{ fmt(pv.discount) }}</strong></li>
                            <li v-if="pv.credit > 0" class="is-credit"><span><i class="fas fa-wallet"></i>Account credit</span><strong>−AED {{ fmt(pv.credit) }}</strong></li>
                        </ul>
                        <div class="pc-timeline">
                            <div class="pc-step"><span class="pc-step__dot"></span><div><strong>Today</strong> — new billing period starts</div></div>
                            <div class="pc-step"><span class="pc-step__dot pc-step__dot--muted"></span><div><strong>{{ pv.next_date }}</strong> — next payment AED {{ fmt(pv.next_amount) }}, then every {{ unitWord(pv.interval) }}</div></div>
                        </div>
                    </template>
                    <template v-else-if="pv.type === 'downgrade'">
                        <div class="pc-due"><div class="pc-due__label">Charged today</div><div class="pc-due__amount"><small>AED</small>{{ fmt(0) }}</div><div class="pc-due__sub">No charge or refund — you keep what you paid for</div></div>
                        <div class="pc-timeline">
                            <div class="pc-step"><span class="pc-step__dot"></span><div><strong>Now → {{ pv.effective_date }}</strong> — you stay on {{ pv.current_plan }}</div></div>
                            <div class="pc-step"><span class="pc-step__dot pc-step__dot--muted"></span><div><strong>{{ pv.next_date }}</strong> — {{ pv.plan }} starts at AED {{ fmt(pv.next_amount) }}/{{ unitWord(pv.interval) }}</div></div>
                            <div class="pc-step"><span class="pc-step__dot pc-step__dot--muted"></span><div>You can undo this any time before then</div></div>
                        </div>
                    </template>
                    <template v-else-if="pv.type === 'cancel'">
                        <div class="pc-due"><div class="pc-due__label">Charged today</div><div class="pc-due__amount"><small>AED</small>{{ fmt(0) }}</div><div class="pc-due__sub">Auto-renew stops — no refund for the current period</div></div>
                        <div class="pc-timeline">
                            <div class="pc-step"><span class="pc-step__dot"></span><div><strong>Now → {{ pv.effective_date }}</strong> — you keep {{ pv.current_plan }}</div></div>
                            <div class="pc-step"><span class="pc-step__dot pc-step__dot--muted"></span><div><strong>{{ pv.effective_date }}</strong> — account moves to {{ pv.plan }}</div></div>
                        </div>
                    </template>
                    <div v-else class="pc-timeline">You are already on this plan.</div>
                </div>
            </div>

            <div class="pc-foot">
                <button type="button" class="pc-confirm" :class="{ 'pc-confirm--danger': pv?.type === 'cancel' }" :disabled="!pv || change.loading || pv.type === 'same' || change.saving" @click="confirmChange">
                    <template v-if="pv?.type === 'upgrade'"><i class="fas fa-lock me-1"></i> Pay AED {{ fmt(pv.due_now) }} &amp; upgrade</template>
                    <template v-else-if="pv?.type === 'downgrade'"><i class="fas fa-calendar-check me-1"></i> Schedule downgrade</template>
                    <template v-else-if="pv?.type === 'cancel'">Yes, stop auto-renew</template>
                    <template v-else>Confirm</template>
                </button>
                <button type="button" class="pc-cancel" @click="change.open = false">Not now</button>
                <div v-if="!pv || pv.type === 'upgrade'" class="pc-secure"><i class="fas fa-lock me-1"></i>Charged securely to your saved card via Stripe</div>
            </div>
        </CrmModal>
    </div>
</template>

<script setup>
/**
 * Plans (resources/views/portal/plans/index + agency) — current plan / subscription, the plans with
 * a monthly / yearly toggle and coupon preview, and the change-plan modal (Stripe preview). Stripe's
 * hosted checkout comes back here with ?session_id=, which is finished first.
 */
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmModal from '../../components/CrmModal.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { formatDate, ucfirst } from '../../utils/format';

const route = useRoute();
const router = useRouter();
const { confirm } = useConfirm();
const { success, error: toastError, info } = useToast();

const d = ref(null);
const loadError = ref('');
const interval = ref('monthly');
const busy = ref(false);
const couponInput = ref(null);
const couponCode = ref('');
const coupon = ref(null); // { code, plans: { planId: { monthly: {...}, yearly: {...} } } }
const couponError = ref('');
const couponTouched = ref(false);
const couponBusy = ref(false);
const change = reactive({ open: false, loading: false, saving: false, error: '', preview: null, plan: null, interval: null, coupon: '' });

const kinds = {
    upgrade: { icon: 'fa-arrow-up', label: 'Upgrade' },
    downgrade: { icon: 'fa-arrow-down', label: 'Downgrade' },
    cancel: { icon: 'fa-ban', label: 'Cancel subscription' },
};

const ap = computed(() => d.value.agency_plan);
const c = computed(() => d.value.current);
const pv = computed(() => change.preview);
const changeTitle = computed(() => {
    if (!pv.value) return 'Change plan';
    return { upgrade: `Upgrade to ${pv.value.plan}`, downgrade: `Switch to ${pv.value.plan}`, cancel: `Move to ${pv.value.plan}` }[pv.value.type] ?? 'Change plan';
});

const fmt = (n) => Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const whole = (n) => Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 });
const unitWord = (i) => (i === 'yearly' ? 'year' : 'month');
const shortUnit = (i) => (i === 'yearly' ? 'yr' : 'mo');
const cell = (plan) => plan.intervals[interval.value];

/** The coupon result for this plan on the visible tab (a monthly-only plan's yearly tab shows the monthly one). */
function discountFor(plan) {
    const byInterval = coupon.value?.plans?.[plan.id];
    if (!byInterval) return null;
    return byInterval[interval.value] || (interval.value === 'yearly' ? byInterval.monthly : null);
}

/** The applied coupon, if it fits this plan + interval. */
function couponFor(plan, effective) {
    const p = coupon.value?.plans?.[plan.id]?.[effective];
    return p && p.valid ? coupon.value.code : '';
}

function load() {
    loadError.value = '';
    return http.get('/plans')
        .then((res) => {
            d.value = res.data;
            if (res.data.default_interval) interval.value = res.data.default_interval;
        })
        .catch((e) => { loadError.value = errorMessage(e); });
}

/* ---------- Coupon preview ---------- */
function applyCoupon() {
    const code = couponCode.value.trim();
    if (!code) return;
    couponBusy.value = true;
    http.post('/plans/coupon-check', { coupon_code: code })
        .then((res) => {
            coupon.value = { code: res.data.code, plans: res.data.plans };
            couponCode.value = res.data.code;
            couponError.value = '';
        })
        .catch((e) => {
            coupon.value = null;
            couponError.value = errorMessage(e, 'This coupon code is not valid.');
        })
        .finally(() => {
            couponBusy.value = false;
            couponTouched.value = true;
        });
}

function removeCoupon() {
    coupon.value = null;
    couponCode.value = '';
    couponError.value = '';
    couponInput.value?.focus();
}

/* ---------- Choosing a plan ---------- */
function checkout(plan) {
    const effective = cell(plan).effective;
    router.push({ name: 'plans.checkout', query: { plan_id: plan.id, interval: effective, coupon_code: couponFor(plan, effective) || undefined } });
}

function requestPlan(plan) {
    const effective = cell(plan).effective;
    busy.value = true;
    http.post('/plans', { plan_id: plan.id, interval: effective, coupon_code: couponFor(plan, effective) || null })
        .then((res) => {
            if (res.data.checkout) {
                router.push({ name: 'plans.checkout', query: res.data.checkout });
                return null;
            }
            success(res.data.message);
            return load();
        })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { busy.value = false; });
}

function openChange(plan) {
    const effective = cell(plan).effective;
    Object.assign(change, { open: true, loading: true, saving: false, error: '', preview: null, plan, interval: effective, coupon: couponFor(plan, effective) });
    http.post('/plans/change-preview', { plan_id: plan.id, interval: effective, coupon_code: change.coupon || null })
        .then((res) => {
            if (!res.data.success) throw new Error(res.data.message);
            change.preview = res.data;
        })
        .catch((e) => { change.error = e.response ? errorMessage(e, 'Could not calculate this change.') : e.message; })
        .finally(() => { change.loading = false; });
}

function confirmChange() {
    change.saving = true;
    http.post('/plans', {
        plan_id: change.plan.id,
        interval: change.interval,
        coupon_code: change.coupon || null,
        proration_date: pv.value.type === 'upgrade' ? (pv.value.proration_date || null) : null,
    })
        .then((res) => {
            change.open = false;
            success(res.data.message);
            return load();
        })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { change.saving = false; });
}

/* ---------- Subscription ---------- */
function subscription(action) {
    busy.value = true;
    http.post(`/plans/subscription/${action}`)
        .then((res) => {
            success(res.data.message);
            return load();
        })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { busy.value = false; });
}

async function cancelRenewal() {
    if (!(await confirm({
        title: 'Turn off auto-renew?',
        message: `You keep your plan until ${c.value.renews_at ? formatDate(c.value.renews_at) : 'the end of the period you already paid for'}, then move to Free.`,
        confirmText: 'Turn off',
        tone: 'warning',
    }))) return;
    subscription('cancel');
}

/** Back from Stripe's hosted checkout: activate the plan right away (the webhook does the same). */
function finishHostedCheckout(sessionId) {
    return http.post('/plans/checkout/session', { session_id: sessionId })
        .then((res) => success(res.data.message))
        .catch(() => info("Payment received — we're confirming it with Stripe. Your plan will update in a moment."))
        .finally(() => router.replace({ query: {} }));
}

if (typeof route.query.session_id === 'string' && route.query.session_id.startsWith('cs_')) {
    finishHostedCheckout(route.query.session_id).then(load);
} else {
    load();
}
</script>
