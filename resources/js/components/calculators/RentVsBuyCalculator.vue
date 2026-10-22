<template>
    <div class="mc">
        <div class="mc__layout">
            <div class="mc__card mc__inputs" :class="{ 'is-open': advanced }">
                <CalcField v-model="rent" :label="t('calculators.rent_vs_buy.annual_rent', 'Annual rent')" :min="20000" :max="2000000" :step="5000" ends />
                <CalcField v-model="price" :label="t('calculators.purchase_price', 'Purchase price')" :min="300000" :max="200000000" :step="10000" ends />
                <ResidencyToggle v-model="residency" />
                <p class="mc__hint mc__hint--tight">{{ t('calculators.rent_vs_buy.residency_hint', 'It affects the minimum down payment.') }}</p>
                <CalcField v-model="downAed" :label="t('mortgage_page.down_payment', 'Down payment')" :badge="`${Math.round(downPct)}%`"
                    :min="price * minDown / 100" :max="price * MAX_DOWN / 100" :step="1000" ends />
                <CalcField v-model="term" type="years" :label="t('calculators.rent_vs_buy.loan_period', 'Mortgage loan period')" :min="1" :max="TERM_MAX" ends />

                <div class="mc__advanced">
                    <button type="button" class="mc__expand" :aria-expanded="advanced ? 'true' : 'false'" @click="advanced = !advanced">
                        {{ t('calculators.rent_vs_buy.assumptions', 'Assumptions') }}
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div v-show="advanced" class="mc__advanced-body">
                        <div>
                            <p class="mc__label">{{ t('calculators.rent_vs_buy.mortgage_type', 'Mortgage type') }}</p>
                            <div class="mc__toggle" role="radiogroup">
                                <button type="button" role="radio" :aria-checked="mortgageType === 'fixed' ? 'true' : 'false'" :class="{ 'is-active': mortgageType === 'fixed' }" @click="mortgageType = 'fixed'">{{ t('calculators.rent_vs_buy.fixed', 'Fixed') }}</button>
                                <button type="button" role="radio" :aria-checked="mortgageType === 'variable' ? 'true' : 'false'" :class="{ 'is-active': mortgageType === 'variable' }" @click="mortgageType = 'variable'">{{ t('calculators.rent_vs_buy.variable', 'Variable') }}</button>
                            </div>
                        </div>
                        <template v-if="mortgageType === 'fixed'">
                            <CalcField v-model="rate" type="percent" :label="t('calculators.rent_vs_buy.fixed_rate', 'Fixed interest rate')" :min="RATE_MIN" :max="RATE_MAX" :step="0.01" :input-step="0.01" />
                            <CalcField v-model="fixedPeriod" type="years" :label="t('calculators.rent_vs_buy.fixed_period', 'Fixed period')" :min="1" :max="TERM_MAX" />
                            <CalcField v-model="reversionRate" type="percent" :label="t('calculators.rent_vs_buy.reversion_rate', 'Rate after the fixed period')" :min="RATE_MIN" :max="RATE_MAX" :step="0.01" :input-step="0.01" />
                        </template>
                        <CalcField v-else v-model="rate" type="percent" :label="t('calculators.rent_vs_buy.margin', 'Margin over EIBOR')" :min="RATE_MIN" :max="RATE_MAX" :step="0.01" :input-step="0.01"
                            :hint="t('calculators.rent_vs_buy.margin_hint', 'Your rate = 3-month EIBOR (4.9%) + this margin.')" />
                        <CalcField v-model="rentIncrease" type="percent" :label="t('calculators.rent_vs_buy.rent_increase', 'Yearly rent increase')" :min="-50" :max="50" :step="1" :input-step="0.1" />
                        <CalcField v-model="appreciation" type="percent" :label="t('calculators.rent_vs_buy.appreciation', 'Yearly property value growth')" :min="-50" :max="50" :step="1" :input-step="0.1" />
                    </div>
                </div>
            </div>

            <div class="mc__results">
                <!-- 1. Verdict -->
                <div class="mc__card mc__monthly">
                    <p class="mc__monthly-label">{{ t('calculators.rent_vs_buy.verdict_label', 'Over {n} years').replace('{n}', term) }}</p>
                    <p class="mc__monthly-value" :class="buyWins ? 'is-good' : 'is-bad'">
                        {{ buyWins ? t('calculators.rent_vs_buy.buy_wins', 'Buying is cheaper') : t('calculators.rent_vs_buy.rent_wins', 'Renting is cheaper') }}
                        <small>{{ t('calculators.rent_vs_buy.by', 'by') }} {{ formatPrice(Math.abs(res.saved), '—') }}</small>
                    </p>
                    <p class="rvb__why">{{ buyWins
                        ? t('calculators.rent_vs_buy.why_buy', 'Rent is money gone for good. Buying costs more up front, but at the end you own a home worth {value}.').replace('{value}', formatPrice(res.buy.salePrice, '—'))
                        : t('calculators.rent_vs_buy.why_rent', 'With these numbers, the cost of owning (fees, interest, insurance and service charges) is more than the home you end up owning is worth, compared with renting.') }}</p>

                    <dl class="mc__rows">
                        <div>
                            <dt>{{ t('calculators.rent_vs_buy.breakeven', 'Buying pays off after') }}</dt>
                            <dd>{{ buyWins && breakeven ? `${breakeven} ${breakeven === 1 ? t('calculators.year', 'year') : t('calculators.years_long', 'years')}` : t('calculators.rent_vs_buy.never', 'Not within the loan period') }}</dd>
                        </div>
                    </dl>

                    <!-- Net cost of each option over the period — folded away to keep the results compact -->
                    <button type="button" class="mc__expand rvb__how" :aria-expanded="breakdownOpen ? 'true' : 'false'" @click="breakdownOpen = !breakdownOpen">
                        {{ t('calculators.rent_vs_buy.how', 'How this is calculated') }}
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div v-show="breakdownOpen">
                        <p class="mc__basis-title">{{ t('calculators.rent_vs_buy.renting', 'Renting') }}</p>
                        <dl class="mc__rows">
                            <div><dt>{{ t('calculators.rent_vs_buy.rent_agent', 'Agent fees (5% + VAT each time you move, every 3 years)') }}</dt><dd>{{ formatSigned(-res.rent.agent) }}</dd></div>
                            <div><dt>{{ t('calculators.rent_vs_buy.rent_government', 'Ejari registration & cancellation') }}</dt><dd>{{ formatSigned(-res.rent.government) }}</dd></div>
                            <div><dt>{{ t('calculators.rent_vs_buy.rent_payments', 'Rent payments') }}</dt><dd>{{ formatSigned(-res.rent.payments) }}</dd></div>
                            <div class="mc__rows-total"><dt>{{ t('calculators.rent_vs_buy.net_cost_n', 'Net cost over {n} years').replace('{n}', term) }}</dt><dd>{{ formatSigned(-res.rent.total) }}</dd></div>
                        </dl>
                        <p class="mc__basis-title">{{ t('calculators.rent_vs_buy.buying', 'Buying') }}</p>
                        <dl class="mc__rows">
                            <div><dt>{{ t('mortgage_page.down_payment', 'Down payment') }}</dt><dd>{{ formatSigned(-res.buy.down) }}</dd></div>
                            <div><dt>{{ t('calculators.rent_vs_buy.buy_agent', 'Agent fees (2% + VAT)') }}</dt><dd>{{ formatSigned(-res.buy.agent) }}</dd></div>
                            <div><dt>{{ t('calculators.rent_vs_buy.buy_government', 'Land Department & trustee fees') }}</dt><dd>{{ formatSigned(-res.buy.government) }}</dd></div>
                            <div><dt>{{ t('calculators.rent_vs_buy.buy_bank', 'Bank fees (processing, valuation, mortgage registration)') }}</dt><dd>{{ formatSigned(-res.buy.bank) }}</dd></div>
                            <div><dt>{{ t('mortgage_page.principal', 'Principal') }}</dt><dd>{{ formatSigned(-res.buy.principal) }}</dd></div>
                            <div><dt>{{ t('calculators.rent_vs_buy.interest', 'Interest') }}</dt><dd>{{ formatSigned(-res.buy.interest) }}</dd></div>
                            <div><dt>{{ t('calculators.rent_vs_buy.insurance', 'Life & property insurance') }}</dt><dd>{{ formatSigned(-res.buy.insurance) }}</dd></div>
                            <div><dt>{{ t('calculators.rent_vs_buy.community', 'Community service charges (AED 15,000 / yr)') }}</dt><dd>{{ formatSigned(-res.buy.community) }}</dd></div>
                            <div><dt>{{ t('calculators.rent_vs_buy.sale_price', 'Estimated selling price') }}</dt><dd>{{ formatSigned(res.buy.salePrice) }}</dd></div>
                            <div><dt>{{ t('calculators.rent_vs_buy.selling_fees', 'Selling fees (2% agent + NOC)') }}</dt><dd>{{ formatSigned(-res.buy.sellingFees) }}</dd></div>
                            <div class="mc__rows-total"><dt>{{ t('calculators.rent_vs_buy.net_cost_n', 'Net cost over {n} years').replace('{n}', term) }}</dt><dd>{{ formatSigned(-res.buy.total) }}</dd></div>
                        </dl>
                    </div>
                </div>

                <!-- 2. Charts, one at a time: comparison | monthly cost in the first year -->
                <div class="mc__card">
                    <div class="mc__toggle rvb__switch" role="tablist">
                        <button type="button" role="tab" :aria-selected="chart === 'compare' ? 'true' : 'false'" :class="{ 'is-active': chart === 'compare' }" @click="chart = 'compare'">{{ t('calculators.rent_vs_buy.comparison', 'Comparison') }}</button>
                        <button type="button" role="tab" :aria-selected="chart === 'monthly' ? 'true' : 'false'" :class="{ 'is-active': chart === 'monthly' }" @click="chart = 'monthly'">{{ t('calculators.rent_vs_buy.monthly_title', 'Monthly cost') }}</button>
                    </div>

                    <div v-show="chart === 'compare'">
                    <p class="mc__hint rvb__sub">{{ buyWins
                        ? t('calculators.rent_vs_buy.compare_hint', 'Year by year: what you save by buying instead of renting (above the line), and what renting costs you for good (below it).')
                        : t('calculators.rent_vs_buy.compare_hint_rent', 'Year by year: what you save by renting instead of buying (above the line), and what buying costs you (below it).') }}</p>
                    <ul class="rvb__legend">
                        <li><i class="rvb__key rvb__fill--buy"></i>{{ buyLabel }}</li>
                        <li><i class="rvb__key rvb__fill--rent"></i>{{ rentLabel }}</li>
                    </ul>
                    <div class="rvb__chart" @pointermove="onHover" @pointerleave="hoverYear = null">
                        <svg :viewBox="`0 0 ${W} ${H}`" class="rvb__svg" role="img" :aria-label="t('calculators.rent_vs_buy.compare_aria', 'Money saved by buying and rent lost by renting, year by year')">
                            <g class="rvb__grid">
                                <g v-for="tick in ticks" :key="tick">
                                    <line :x1="PAD_L" :x2="W - PAD_R" :y1="y(tick)" :y2="y(tick)" :class="{ 'is-zero': tick === 0 }" />
                                    <text :x="PAD_L - 8" :y="y(tick) + 4" text-anchor="end">{{ tick === 0 ? '0' : `${tick < 0 ? '-' : ''}${formatCompact(Math.abs(tick), false)}` }}</text>
                                </g>
                                <text v-for="year in yearTicks" :key="`y${year}`" :x="x(year)" :y="H - 6" text-anchor="middle">{{ year }}</text>
                            </g>
                            <defs>
                                <linearGradient id="rvbFadeBuy" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0" style="stop-color: var(--rvb-buy)" stop-opacity="0.35" />
                                    <stop offset="1" style="stop-color: var(--rvb-buy)" stop-opacity="0.02" />
                                </linearGradient>
                                <linearGradient id="rvbFadeRent" x1="0" y1="1" x2="0" y2="0">
                                    <stop offset="0" style="stop-color: var(--rvb-rent)" stop-opacity="0.3" />
                                    <stop offset="1" style="stop-color: var(--rvb-rent)" stop-opacity="0.02" />
                                </linearGradient>
                            </defs>
                            <path :d="area('saved')" fill="url(#rvbFadeBuy)" />
                            <path :d="area('lost')" fill="url(#rvbFadeRent)" />
                            <path :d="curve('saved')" class="rvb__line rvb__line--buy" />
                            <path :d="curve('lost')" class="rvb__line rvb__line--rent" />
                            <g v-if="breakeven" class="rvb__cross">
                                <line :x1="x(breakeven)" :x2="x(breakeven)" :y1="PAD_T + 18" :y2="H - PAD_B" />
                                <rect :x="x(breakeven) - 30" :y="PAD_T" width="60" height="20" rx="10" />
                                <text :x="x(breakeven)" :y="PAD_T + 14" text-anchor="middle">{{ t('calculators.rent_vs_buy.year_n', 'Year {n}').replace('{n}', breakeven) }}</text>
                            </g>
                            <g class="rvb__ends">
                                <circle :cx="x(term)" :cy="y(compare[term].saved)" r="5" class="rvb__dot--buy" />
                                <circle :cx="x(term)" :cy="y(compare[term].lost)" r="5" class="rvb__dot--rent" />
                            </g>
                            <g v-if="hoverYear !== null" class="rvb__hover">
                                <line :x1="x(hoverYear)" :x2="x(hoverYear)" :y1="PAD_T" :y2="H - PAD_B" />
                                <circle :cx="x(hoverYear)" :cy="y(compare[hoverYear].saved)" r="5" class="rvb__dot--buy" />
                                <circle :cx="x(hoverYear)" :cy="y(compare[hoverYear].lost)" r="5" class="rvb__dot--rent" />
                            </g>
                        </svg>
                        <div v-if="hoverYear !== null" class="rvb__tip" :style="tipStyle">
                            <strong>{{ t('calculators.rent_vs_buy.year_n', 'Year {n}').replace('{n}', hoverYear) }}</strong>
                            <span><i class="rvb__key rvb__fill--buy"></i>{{ buyLabel }}: {{ formatSigned(compare[hoverYear].saved) }}</span>
                            <span><i class="rvb__key rvb__fill--rent"></i>{{ rentLabel }}: {{ formatSigned(compare[hoverYear].lost) }}</span>
                        </div>
                    </div>

                    <div class="rvb__stats">
                        <div class="rvb__stat">
                            <span><i class="rvb__key rvb__fill--buy"></i>{{ buyLabel }}</span>
                            <strong :class="res.saved >= 0 ? 'is-good' : 'is-bad'">{{ formatSigned(res.saved) }}</strong>
                        </div>
                        <div class="rvb__stat">
                            <span><i class="rvb__key rvb__fill--rent"></i>{{ rentLabel }}</span>
                            <strong :class="res.lost < 0 ? 'is-bad' : 'is-good'">{{ formatSigned(res.lost) }}</strong>
                        </div>
                    </div>
                    <p class="rvb__callout" :class="{ 'is-rent': !buyWins }">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/></svg>
                        <span v-if="buyWins && breakeven"><strong>{{ t('calculators.rent_vs_buy.buying', 'Buying') }}</strong> {{ t('calculators.rent_vs_buy.better_after', 'becomes better than renting after {n}.').replace('{n}', `${breakeven} ${breakeven === 1 ? t('calculators.year', 'year') : t('calculators.years_long', 'years')}`) }}</span>
                        <span v-else><strong>{{ t('calculators.rent_vs_buy.renting', 'Renting') }}</strong> {{ t('calculators.rent_vs_buy.rent_better', 'is a better option than buying.') }}</span>
                    </p>

                    <details class="rvb__table">
                        <summary>{{ t('calculators.rent_vs_buy.view_table', 'View as table') }}</summary>
                        <table>
                            <thead><tr><th>{{ t('calculators.rent_vs_buy.year', 'Year') }}</th><th>{{ buyLabel }}</th><th>{{ rentLabel }}</th></tr></thead>
                            <tbody><tr v-for="p in compare.slice(1)" :key="p.year"><td>{{ p.year }}</td><td>{{ formatSigned(p.saved) }}</td><td>{{ formatSigned(p.lost) }}</td></tr></tbody>
                        </table>
                    </details>
                    </div>

                    <div v-show="chart === 'monthly'">
                    <p class="mc__hint rvb__sub">{{ t('calculators.rent_vs_buy.monthly_hint', 'What you pay in the first month: rent, or the mortgage plus life and property insurance.') }}</p>
                    <div class="rvb__hbars">
                        <div class="rvb__hbar">
                            <div class="rvb__hbar-head"><span>{{ t('calculators.rent_vs_buy.renting', 'Renting') }}</span><strong>{{ formatPrice(monthly.rent, '—') }}</strong></div>
                            <div class="rvb__hbar-track">
                                <span class="rvb__seg rvb__fill--rent" :style="{ width: monthlyShare(monthly.rent) }" :title="`${t('calculators.rent_vs_buy.rent', 'Rent')}: ${formatPrice(monthly.rent, '—')}`"></span>
                            </div>
                        </div>
                        <div class="rvb__hbar">
                            <div class="rvb__hbar-head"><span>{{ t('calculators.rent_vs_buy.buying', 'Buying') }}</span><strong>{{ formatPrice(monthly.buy, '—') }}</strong></div>
                            <div class="rvb__hbar-track">
                                <span v-for="part in monthlyParts" :key="part.key" class="rvb__seg" :class="part.cls" :style="{ width: monthlyShare(part.value) }" :title="`${part.label}: ${formatPrice(part.value, '—')}`"></span>
                            </div>
                        </div>
                    </div>
                    <ul class="rvb__parts">
                        <li v-for="part in monthlyParts" :key="part.key"><i class="rvb__key" :class="part.cls"></i>{{ part.label }} <strong>{{ formatPrice(part.value, '—') }}</strong></li>
                    </ul>
                    </div>
                </div>


                <div class="mc__actions">
                    <router-link :to="{ path: '/properties', query: { listing_type: 'sale', max_price: Math.round(price) } }" class="mc__btn mc__btn--solid">{{ t('calculators.rent_vs_buy.buy_cta', 'Properties for sale') }}</router-link>
                    <router-link :to="{ path: '/properties', query: { listing_type: 'rent' } }" class="mc__btn mc__btn--ghost">{{ t('calculators.rent_vs_buy.rent_cta', 'Properties for rent') }}</router-link>
                </div>
            </div>
        </div>

        <p class="mc__disclaimer">{{ t('calculators.disclaimer', 'Figures are estimates for guidance only and do not constitute a loan offer. Actual rates, fees and eligibility depend on your lender and circumstances.') }}</p>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useCurrency } from '../../composables/useCurrency';
import { useStaticText } from '../../composables/useStaticText';
import CalcField from './CalcField.vue';
import ResidencyToggle from './ResidencyToggle.vue';
import { RATE_MAX, RATE_MIN, TERM_MAX, aed, clamp, minDownPct } from './finance';
import { RVB_DEFAULTS, calculateRentVsBuy } from './rentVsBuy';
import './calculators.css';

/**
 * Rent vs Buy over the mortgage loan period — the maths lives in rentVsBuy.js (same model as the
 * Property Finder calculator: Dubai fees, insurance, community charges, moving every 3 years, sale at the end).
 *  - "Saved if buying" = net cost of renting − net cost of buying
 *  - "Lost if renting" = net cost of renting
 */
const { t } = useStaticText();
const { selectedCurrency, formatPrice, formatCompact } = useCurrency();

const MAX_DOWN = 80;
const rent = ref(140000);
const price = ref(1200000);
const residency = ref('resident');
const downPct = ref(20);
const term = ref(TERM_MAX);
const advanced = ref(false);
const chart = ref('compare'); // chart card: 'compare' | 'monthly'
const breakdownOpen = ref(false);
const mortgageType = ref(RVB_DEFAULTS.mortgageType);
const rate = ref(RVB_DEFAULTS.interestRate);
const fixedPeriod = ref(RVB_DEFAULTS.fixedPeriod);
const reversionRate = ref(RVB_DEFAULTS.reversionRate);
const rentIncrease = ref(RVB_DEFAULTS.rentGrowth);
const appreciation = ref(RVB_DEFAULTS.priceGrowth);

// Residency sets the minimum down payment.
const minDown = computed(() => minDownPct(residency.value, price.value));
watch(residency, () => { downPct.value = minDown.value; });
watch(minDown, (min) => { if (downPct.value < min) downPct.value = min; }, { immediate: true });
const downAed = computed({
    get: () => price.value * downPct.value / 100,
    set: (amount) => { downPct.value = clamp(amount / price.value * 100, minDown.value, MAX_DOWN); },
});

const res = computed(() => calculateRentVsBuy({
    rent: rent.value,
    price: price.value,
    down: downAed.value,
    years: term.value,
    mortgageType: mortgageType.value,
    interestRate: rate.value,
    fixedPeriod: fixedPeriod.value,
    reversionRate: reversionRate.value,
    rentGrowth: rentIncrease.value,
    priceGrowth: appreciation.value,
}));
const buyWins = computed(() => res.value.saved > 0);
const breakeven = computed(() => res.value.breakevenYears);
// Labels follow the winner, as on Property Finder: "Saved if buying / Lost if renting" or "Lost if buying / Saved if renting".
const buyLabel = computed(() => (buyWins.value ? t('calculators.rent_vs_buy.saved_buying', 'Saved if buying') : t('calculators.rent_vs_buy.lost_buying', 'Lost if buying')));
const rentLabel = computed(() => (buyWins.value ? t('calculators.rent_vs_buy.lost_renting', 'Lost if renting') : t('calculators.rent_vs_buy.saved_renting', 'Saved if renting')));

// First-month picture: rent vs mortgage (principal + interest) + life & property insurance.
const monthly = computed(() => res.value.monthly);
const monthlyParts = computed(() => [
    { key: 'principal', label: t('mortgage_page.principal', 'Principal'), value: monthly.value.principal, cls: 'rvb__fill--buy' },
    { key: 'interest', label: t('calculators.rent_vs_buy.interest', 'Interest'), value: monthly.value.interest, cls: 'rvb__fill--interest' },
    { key: 'insurance', label: t('calculators.rent_vs_buy.insurance', 'Life & property insurance'), value: monthly.value.insurance, cls: 'rvb__fill--charges' },
].filter((part) => part.value > 0));
const monthlyShare = (amount) => `${amount / Math.max(monthly.value.rent, monthly.value.buy, 1) * 100}%`;

// --- Comparison chart: mirrored areas around zero (SVG, sized by viewBox) -------------------
const W = 560;
const H = 240;
const PAD_L = 52;
const PAD_R = 16;
const PAD_T = 14;
const PAD_B = 26;

function niceStep(span, count = 4) {
    const raw = span / count;
    const mag = Math.pow(10, Math.floor(Math.log10(raw || 1)));
    return [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw) || raw;
}
const compare = computed(() => res.value.series);
// Symmetric axis so zero sits in the middle: owned above, rent lost below.
const range = computed(() => {
    const peak = Math.max(1, ...compare.value.flatMap((p) => [Math.abs(p.saved), Math.abs(p.lost)]));
    const step = niceStep(peak, 2);
    const max = Math.ceil(peak / step) * step;
    return { min: -max, max, step };
});
const ticks = computed(() => {
    const list = [];
    for (let v = range.value.min; v <= range.value.max + 1; v += range.value.step) list.push(Math.round(v));
    return list;
});
const yearTicks = computed(() => {
    const step = term.value > 15 ? 5 : term.value > 6 ? 2 : 1;
    const list = [];
    for (let yr = 0; yr <= term.value; yr += step) list.push(yr);
    if (list[list.length - 1] !== term.value) list.push(term.value);
    return list;
});
const x = (year) => PAD_L + (year / (term.value || 1)) * (W - PAD_L - PAD_R);
const y = (v) => PAD_T + ((range.value.max - v) / (range.value.max - range.value.min || 1)) * (H - PAD_T - PAD_B);
// Smooth line through the yearly points (monotone cubic, so the curve never overshoots).
function curve(key) {
    const pts = compare.value.map((p) => [x(p.year), y(p[key])]);
    if (pts.length < 2) return '';
    const n = pts.length;
    const d = pts.slice(1).map((p, i) => (p[1] - pts[i][1]) / (p[0] - pts[i][0]));
    const m = pts.map((_, i) => (i === 0 ? d[0] : i === n - 1 ? d[n - 2] : d[i - 1] * d[i] <= 0 ? 0 : (d[i - 1] + d[i]) / 2));
    let out = `M${pts[0][0].toFixed(1)},${pts[0][1].toFixed(1)}`;
    for (let i = 0; i < n - 1; i++) {
        const h = (pts[i + 1][0] - pts[i][0]) / 3;
        out += ` C${(pts[i][0] + h).toFixed(1)},${(pts[i][1] + m[i] * h).toFixed(1)} ${(pts[i + 1][0] - h).toFixed(1)},${(pts[i + 1][1] - m[i + 1] * h).toFixed(1)} ${pts[i + 1][0].toFixed(1)},${pts[i + 1][1].toFixed(1)}`;
    }
    return out;
}
const area = (key) => `${curve(key)} L${x(term.value).toFixed(1)},${y(0).toFixed(1)} L${x(0).toFixed(1)},${y(0).toFixed(1)} Z`;
const formatSigned = (v) => `${v < 0 ? '-' : ''}${formatPrice(Math.abs(v), `${selectedCurrency.value.code} 0`)}`;

// Hovered-year highlight + tooltip.
const hoverYear = ref(null);
const tipLeft = ref(0);
function onHover(e) {
    const box = e.currentTarget.getBoundingClientRect();
    const px = ((e.clientX - box.left) / box.width) * W;
    hoverYear.value = clamp(Math.round(((px - PAD_L) / (W - PAD_L - PAD_R)) * term.value), 1, term.value);
    tipLeft.value = (x(hoverYear.value) / W) * 100;
}
// Tooltip sits in the corner away from the hovered year, so it never runs off a narrow chart.
const tipStyle = computed(() => (tipLeft.value > 50 ? { left: '8px' } : { right: '8px' }));

// The calculator page's enquiry form attaches these figures to the lead (always in AED).
defineExpose({
    summary: computed(() => ({
        Calculator: 'Rent vs Buy',
        'Annual rent': aed(rent.value),
        'Purchase price': aed(price.value),
        Residency: residency.value,
        'Down payment': `${aed(downAed.value)} (${Math.round(downPct.value)}%)`,
        'Loan period': `${term.value} years`,
        'Interest rate': mortgageType.value === 'fixed' ? `${rate.value}% fixed for ${fixedPeriod.value} years, then ${reversionRate.value}%` : `EIBOR + ${rate.value}%`,
        'Lost if renting': aed(res.value.rent.total),
        'Net cost of buying': aed(res.value.buy.total),
        'Saved if buying': aed(res.value.saved),
        'Buying pays off after': buyWins.value && breakeven.value ? `${breakeven.value} years` : 'Not within the loan period',
    })),
});
</script>

<style scoped>
.mc { --rvb-buy: #3567b5; --rvb-rent: #c92844; --rvb-interest: #8fb0e3; }
.mc__hint--tight { margin: -20px 0 0; }

.mc__advanced { padding-top: 4px; border-top: 1px solid #e3e7f0; }
.mc__advanced .mc__expand { margin-top: 14px; }
.mc__advanced-body { display: flex; flex-direction: column; gap: clamp(22px, 2vw, 30px); margin-top: 20px; }

/* Shared fills (chart lines, bars, legend keys) */
.rvb__fill--rent { background: var(--rvb-rent); }
.rvb__fill--buy { background: var(--rvb-buy); }
.rvb__fill--interest { background: var(--rvb-interest); }
.rvb__fill--charges { background: repeating-linear-gradient(45deg, var(--rvb-buy) 0 2px, #dfe8f6 2px 5px); }
.rvb__key { display: inline-block; width: 12px; height: 12px; margin-inline-end: 8px; border-radius: 3px; vertical-align: -1px; }

/* Cost over time */
.rvb__switch { margin: 0 0 16px; }
.rvb__how { margin-top: 14px; }

.rvb__why { margin: 0 0 16px; color: #35373c; font-size: 15px; line-height: 1.55; }
.rvb__sub { margin: 0 0 12px; }
.rvb__legend { display: flex; flex-wrap: wrap; gap: 6px 22px; margin: 0 0 8px; padding: 0; list-style: none; color: #35373c; font-size: 13px; font-weight: 600; }
.rvb__chart { position: relative; touch-action: pan-y; }
.rvb__svg { display: block; width: 100%; height: auto; overflow: visible; }
.rvb__grid line { stroke: #e9edf3; stroke-width: 1; stroke-dasharray: 3 4; }
.rvb__grid line.is-zero { stroke: #b9c1cf; stroke-dasharray: none; }
.rvb__grid text { fill: #8a8f9c; font-size: 13px; font-family: inherit; }
.rvb__line { fill: none; stroke-width: 2.5; stroke-linejoin: round; stroke-linecap: round; }
.rvb__line--buy { stroke: var(--rvb-buy); }
.rvb__line--rent { stroke: var(--rvb-rent); }
.rvb__cross line { stroke: var(--mc-green); stroke-width: 1.5; stroke-dasharray: 4 3; }
.rvb__cross rect { fill: var(--mc-green); }
.rvb__cross text { fill: #fff; font-size: 12px; font-weight: 800; font-family: inherit; }
.rvb__ends circle, .rvb__hover circle { stroke: #fff; stroke-width: 2; }
.rvb__hover line { stroke: #35373c; stroke-width: 1; opacity: 0.35; }
.rvb__dot--buy { fill: var(--rvb-buy); }
.rvb__dot--rent { fill: var(--rvb-rent); }

/* Comparison totals + verdict callout */
.rvb__stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 16px; }
.rvb__stat { display: flex; flex-direction: column; gap: 6px; padding: 12px 14px; border: 1px solid #e3e7f0; border-radius: 12px; background: #f7f9fc; }
.rvb__stat span { color: #6b7080; font-size: 13px; font-weight: 600; }
.rvb__stat strong { font-size: clamp(16px, 1.4vw, 20px); font-weight: 800; word-break: break-word; }
.rvb__stat strong.is-good { color: var(--mc-green); }
.rvb__stat strong.is-bad { color: var(--mc-red); }
.rvb__stat .rvb__key { width: 10px; height: 10px; margin-inline-end: 6px; }
.rvb__callout { display: flex; align-items: center; gap: 12px; margin: 12px 0 0; padding: 12px 14px; border-radius: 12px; background: rgba(31, 157, 107, 0.08); color: #35373c; font-size: 14px; line-height: 1.5; }
.rvb__callout svg { flex: none; color: var(--mc-green); }
.rvb__callout strong { color: var(--mc-navy); }
.rvb__callout.is-rent { background: rgba(201, 40, 68, 0.07); }
.rvb__callout.is-rent svg { color: var(--mc-red); }
.rvb__tip { position: absolute; top: 0; z-index: 2; display: flex; flex-direction: column; gap: 4px; max-width: calc(100% - 16px); padding: 10px 12px; border: 1px solid #e3e7f0; border-radius: 10px; background: rgba(255, 255, 255, 0.96); box-shadow: 0 10px 24px rgba(36, 67, 115, 0.14); color: #35373c; font-size: 12px; pointer-events: none; }
.rvb__tip strong { color: var(--mc-navy); font-size: 13px; }
.rvb__tip .rvb__key { width: 9px; height: 9px; margin-inline-end: 6px; }

.rvb__table { margin-top: 14px; font-size: 13px; }
.rvb__table summary { color: var(--mc-navy); font-weight: 700; cursor: pointer; }
.rvb__table table { width: 100%; margin-top: 10px; border-collapse: collapse; }
.rvb__table th, .rvb__table td { padding: 6px 4px; border-bottom: 1px solid #eef1f6; text-align: end; white-space: nowrap; }
.rvb__table th:first-child, .rvb__table td:first-child { text-align: start; }
.rvb__table th { color: #6b7080; font-weight: 600; }

/* Monthly cost: horizontal bars, buying split into its parts */
.rvb__hbars { display: flex; flex-direction: column; gap: 18px; margin-top: 4px; }
.rvb__hbar-head { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 8px; font-size: 14px; }
.rvb__hbar-head span { color: #6b7080; font-weight: 600; }
.rvb__hbar-head strong { color: var(--mc-navy); font-size: 16px; font-weight: 800; }
.rvb__hbar-track { display: flex; gap: 2px; height: 22px; border-radius: 6px; background: #f2f4f8; overflow: hidden; }
.rvb__seg { height: 100%; transition: width 0.3s ease; }
.rvb__seg:last-child { border-start-end-radius: 6px; border-end-end-radius: 6px; }
.rvb__parts { display: flex; flex-wrap: wrap; gap: 8px 22px; margin: 16px 0 0; padding: 0; list-style: none; color: #6b7080; font-size: 13px; }
.rvb__parts strong { margin-inline-start: 4px; color: var(--mc-navy); }
</style>
