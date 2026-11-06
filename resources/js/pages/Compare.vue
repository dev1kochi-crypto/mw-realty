<template>
    <main>
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ t('compare.title', 'Compare Properties') }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" :aria-label="t('compare.breadcrumb_aria', 'Breadcrumb')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('compare.breadcrumb_home', 'Home') }}</router-link><span class="mw-about-crumb__sep"> / </span><router-link to="/properties">{{ t('compare.breadcrumb_properties', 'Properties') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ t('compare.title', 'Compare Properties') }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-compare">
            <div class="container-ctn">
                <div class="mw-compare__intro">
                    <h2 class="mw-compare__heading">{{ t('compare.heading', 'Side-by-side, in your currency') }}</h2>
                    <p class="mw-compare__lead">{{ t('compare.lead', 'Price, space, running costs, buying fees in each emirate and expected returns — all on one page, converted to the currency you pay in.') }}</p>
                </div>

                <div v-if="loading" class="mw-compare__summary" aria-busy="true">
                    <div v-for="n in 4" :key="n" class="mw-compare__skeleton"></div>
                </div>

                <div v-else-if="properties.length < COMPARE_MIN" class="mw-compare__empty">
                    <p>{{ properties.length ? t('compare.need_one_more', 'Add at least one more property to compare.') : t('compare.empty', 'You haven\'t picked any properties to compare yet. Tick "Compare" on any listing to add it here.') }}</p>
                    <router-link to="/properties" class="mw-compare__btn">{{ t('compare.browse', 'Browse properties') }}</router-link>
                </div>

                <template v-else>
                    <div class="mw-compare__toolbar">
                        <label class="mw-compare__field">
                            {{ t('compare.currency', 'Show prices in') }}
                            <select :value="selectedCurrency.code" @change="selectCurrency($event.target.value)">
                                <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }} — {{ c.name }}</option>
                            </select>
                        </label>
                        <label class="mw-compare__check">
                            <input v-model="differencesOnly" type="checkbox">
                            {{ t('compare.differences_only', 'Only show differences') }}
                        </label>
                        <p v-if="!isBase" class="mw-compare__rates">
                            1 AED = {{ rateText }} {{ selectedCurrency.code }}<template v-if="ratesDate"> · {{ t('compare.rates_as_of', 'rates as of') }} {{ ratesDate }}</template>
                        </p>
                    </div>

                    <!-- Money & investment figures are held back until the visitor leaves their details. -->
                    <div v-if="locked" class="mw-compare__unlock">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        <p>{{ t('compare.unlock_bar', 'Buying costs, total cash needed, rental yields and market data are hidden.') }}</p>
                        <button type="button" class="mw-compare__btn" @click="unlockOpen = true">{{ t('compare.unlock_cta', 'Unlock full comparison') }}</button>
                    </div>

                    <!-- AI summary -->
                    <section class="mw-compare__summary" aria-live="polite">
                        <div class="mw-compare__summary-head">
                            <h3 class="mw-compare__summary-title">{{ t('compare.summary_title', 'Key differences at a glance') }}</h3>
                            <span v-if="summary" class="mw-compare__badge" :class="{ 'mw-compare__badge--ai': summary.source === 'ai' }">{{ summary.source === 'ai' ? t('compare.badge_ai', 'AI summary') : t('compare.badge_rules', 'Quick summary') }}</span>
                        </div>
                        <template v-if="summaryLoading">
                            <div v-for="n in 4" :key="n" class="mw-compare__skeleton"></div>
                        </template>
                        <template v-else-if="summary">
                            <p class="mw-compare__overview">{{ summary.overview }}</p>
                            <ul v-if="summary.points.length" class="mw-compare__points">
                                <li v-for="(point, i) in summary.points" :key="i">{{ point }}</li>
                            </ul>
                            <p class="mw-compare__disclaimer">{{ t('compare.summary_disclaimer', 'Written from the listing data below. Yields, values and fees are estimates — check them with the agent before you decide.') }}</p>
                        </template>
                        <p v-else class="mw-compare__overview">{{ t('compare.summary_failed', 'The summary isn\'t available right now — the full comparison is below.') }}</p>
                    </section>

                    <div class="mw-compare__scroll">
                        <table class="mw-compare__table" :style="{ '--cmp-cols': properties.length + (canAdd ? 1 : 0) }">
                            <thead>
                                <tr>
                                    <th class="mw-compare__corner" scope="col"><span class="sr-only">{{ t('compare.property', 'Property') }}</span></th>
                                    <th v-for="(p, i) in properties" :key="p.slug" class="mw-compare__col" scope="col">
                                        <div class="mw-compare__card">
                                            <img v-if="p.image" :src="p.image" :alt="p.name" loading="lazy">
                                            <button type="button" class="mw-compare__card-remove" :aria-label="`${t('compare.remove', 'Remove')} ${p.name}`" @click="removeProperty(p.slug)">&times;</button>
                                            <router-link :to="p.url" class="mw-compare__card-name">{{ p.name }}</router-link>
                                            <span class="mw-compare__card-loc">{{ p.location }}</span>
                                            <span v-if="summary?.best_for?.[i + 1]" class="mw-compare__best-for">{{ t('compare.best_for', 'Best for') }}: {{ summary.best_for[i + 1] }}</span>
                                        </div>
                                    </th>
                                    <th v-if="canAdd" class="mw-compare__col" scope="col">
                                        <router-link to="/properties" class="mw-compare__add">+ {{ t('compare.add_property', 'Add a property') }}</router-link>
                                    </th>
                                </tr>
                            </thead>
                            <tbody v-for="section in visibleSections" :key="section.key">
                                <tr class="mw-compare__section">
                                    <th scope="colgroup">{{ section.title }}</th>
                                    <td :colspan="properties.length + (canAdd ? 1 : 0)"></td>
                                </tr>
                                <tr v-for="row in section.rows" :key="row.key">
                                    <th scope="row">
                                        {{ row.label }}
                                        <span v-if="row.hint" class="mw-compare__sub">{{ row.hint }}</span>
                                    </th>
                                    <td v-for="(cell, i) in row.cells" :key="i" :class="{ 'is-best': row.bestIndex === i }">
                                        <button v-if="cell.locked" type="button" class="mw-compare__locked" :title="t('compare.unlock_cta', 'Unlock full comparison')" @click="unlockOpen = true">
                                            <span class="mw-compare__locked-value" aria-hidden="true">{{ cell.placeholder }}</span>
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                                            <span class="sr-only">{{ t('compare.unlock_cta', 'Unlock full comparison') }}</span>
                                        </button>
                                        <span v-else-if="cell.yes !== undefined" :class="cell.yes ? 'mw-compare__yes' : 'mw-compare__no'">{{ cell.yes ? '✓' : '—' }}</span>
                                        <template v-else>{{ cell.text }}</template>
                                        <span v-if="cell.sub" class="mw-compare__sub">{{ cell.sub }}</span>
                                        <template v-if="row.key === 'costs' && cell.items">
                                            <ul v-if="showFees" class="mw-compare__fees">
                                                <li v-for="fee in cell.items" :key="fee.key">{{ fee.label }}: {{ formatPrice(fee.amount, '—') }}</li>
                                            </ul>
                                        </template>
                                    </td>
                                    <td v-if="canAdd"></td>
                                </tr>
                                <tr v-if="section.key === 'price' && !locked">
                                    <th scope="row"></th>
                                    <td :colspan="properties.length + (canAdd ? 1 : 0)" class="mw-compare__fees">
                                        <button type="button" @click="showFees = !showFees">{{ showFees ? t('compare.hide_fees', 'Hide fee breakdown') : t('compare.show_fees', 'Show fee breakdown') }}</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Cross-border notes -->
                    <div class="mw-compare__notes">
                        <div class="mw-compare__note">
                            <h3>{{ t('compare.note_tax_title', 'Taxes in the UAE') }}</h3>
                            <p>{{ notes.uae_tax }}</p>
                        </div>
                        <div v-if="homeCountryNote" class="mw-compare__note">
                            <h3>{{ t('compare.note_home_title', 'Paying in') }} {{ selectedCurrency.code }}</h3>
                            <p>{{ homeCountryNote }}</p>
                        </div>
                        <div v-if="!isBase" class="mw-compare__note">
                            <h3>{{ t('compare.note_fx_title', 'Currency conversion') }}</h3>
                            <p>{{ t('compare.note_fx', 'Banks usually add a margin when converting to AED. The "with conversion margin" row assumes') }} {{ notes.fx_spread_pct }}% — {{ t('compare.note_fx_tail', 'a specialist transfer service is often cheaper.') }}</p>
                        </div>
                        <div v-if="hasSale" class="mw-compare__note">
                            <h3>{{ t('compare.note_finance_title', 'Mortgages for non-residents') }}</h3>
                            <p>{{ t('compare.note_finance', 'UAE banks lend non-residents up to') }} {{ notes.non_resident_max_ltv_pct }}% {{ t('compare.note_finance_tail', 'of the property value, so plan for at least a 40% deposit plus the buying costs above.') }}</p>
                        </div>
                        <div v-if="hasSale" class="mw-compare__note">
                            <h3>{{ t('compare.note_visa_title', 'Golden Visa') }}</h3>
                            <p>{{ t('compare.note_visa', 'Owning property worth at least') }} {{ formatPrice(notes.golden_visa_min) }} {{ t('compare.note_visa_tail', 'can qualify you for the UAE\'s 10-year Golden Visa (other conditions apply).') }}</p>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <CompareUnlockModal v-model:open="unlockOpen" :properties="properties" :currency="selectedCurrency.code" />
    </main>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStaticText } from '../composables/useStaticText';
import { useCurrency } from '../composables/useCurrency';
import { useLanguages } from '../composables/useLanguages';
import { COMPARE_MAX, COMPARE_MIN, useCompare } from '../composables/useCompare';
import { useCompareUnlock } from '../composables/useCompareUnlock';
import CompareUnlockModal from '../components/compare/CompareUnlockModal.vue';
import '../components/compare/compare.css';

/**
 * /compare?slugs=a,b,c — up to five listings side by side (GET /api/compare), with a written summary
 * of the differences (GET /api/compare/summary, AI when available) in the visitor's currency. The
 * slugs in the URL are the list, so a comparison can be shared; opening /compare without them uses
 * the visitor's compare tray.
 */
const route = useRoute();
const router = useRouter();
const { t } = useStaticText();
const { currencies, ratesUpdatedAt, selectedCurrency, selectCurrency, formatPrice, formatAmount } = useCurrency();
const { selectedLanguage } = useLanguages();
const compare = useCompare();

const properties = ref([]);
const notes = ref({});
const loading = ref(true);
const summary = ref(null);
const summaryLoading = ref(false);
const differencesOnly = ref(false);
const showFees = ref(false);
// Costs, total cash and investment figures stay hidden (server-side) until the visitor unlocks them.
const { token: unlockToken, headers: unlockHeaders } = useCompareUnlock();
const locked = ref(false);
const unlockOpen = ref(false);

const slugs = computed(() => String(route.query.slugs || '').split(',').map((s) => s.trim()).filter(Boolean).slice(0, COMPARE_MAX));
const langCode = computed(() => selectedLanguage.value?.code);
const isBase = computed(() => selectedCurrency.value.code === 'AED');
const canAdd = computed(() => properties.value.length < COMPARE_MAX);
const hasSale = computed(() => properties.value.some((p) => p.listing_type === 'sale'));
const samePurpose = computed(() => new Set(properties.value.map((p) => p.listing_type)).size === 1);
const homeCountryNote = computed(() => notes.value.home_country?.[selectedCurrency.value.code] || null);
const rateText = computed(() => Number(selectedCurrency.value.rate).toLocaleString('en-US', { maximumSignificantDigits: 5 }));
const ratesDate = computed(() => (ratesUpdatedAt.value ? new Date(ratesUpdatedAt.value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : null));

// No slugs in the URL: open the tray's list instead.
if (!slugs.value.length && compare.items.value.length) {
    router.replace({ query: { slugs: compare.items.value.map((i) => i.slug).join(',') } });
}

let requestId = 0;
function load() {
    const id = ++requestId;
    if (!slugs.value.length) {
        properties.value = [];
        loading.value = false;
        return;
    }
    loading.value = !properties.value.length;
    window.axios.get('/api/compare', { params: { slugs: slugs.value.join(','), lang: langCode.value }, headers: unlockHeaders() })
        .then((res) => {
            if (id !== requestId) return;
            locked.value = !!res.data.locked;
            properties.value = res.data.properties || [];
            notes.value = res.data.notes || {};
            // Keep the tray in step with what's on screen (e.g. after opening a shared link).
            compare.setSlugs(properties.value.map((p) => ({ slug: p.slug, name: p.name, image: p.image })));
        })
        .catch(() => { if (id === requestId) properties.value = []; })
        .finally(() => { if (id === requestId) loading.value = false; });
}

let summaryId = 0;
let summaryTimer = null;
function loadSummary() {
    clearTimeout(summaryTimer);
    if (properties.value.length < COMPARE_MIN) {
        summary.value = null;
        return;
    }
    const id = ++summaryId;
    summaryLoading.value = true;
    // Short pause so flicking through currencies only asks once.
    summaryTimer = setTimeout(() => {
        window.axios.get('/api/compare/summary', { params: { slugs: properties.value.map((p) => p.slug).join(','), currency: selectedCurrency.value.code, lang: langCode.value }, headers: unlockHeaders() })
            .then((res) => { if (id === summaryId) summary.value = res.data; })
            .catch(() => { if (id === summaryId) summary.value = null; })
            .finally(() => { if (id === summaryId) summaryLoading.value = false; });
    }, 400);
}

watch([slugs, langCode, unlockToken], load, { immediate: true });
watch([() => properties.value.map((p) => p.slug).join(','), () => selectedCurrency.value.code, unlockToken], loadSummary);

function removeProperty(slug) {
    compare.remove(slug);
    properties.value = properties.value.filter((p) => p.slug !== slug);
    router.replace({ query: { slugs: properties.value.map((p) => p.slug).join(',') || undefined } });
}

/* ---------- Rows ---------- */

const money = (aed) => (aed === null || aed === undefined ? '—' : formatPrice(aed, '—'));
const pct = (v, digits = 1) => (v === null || v === undefined ? '—' : `${Number(v).toFixed(digits)}%`);
const km = (v) => `${Number(v).toLocaleString('en-US', { maximumFractionDigits: 1 })} km`;
const plain = (v) => (v === null || v === undefined || v === '' ? '—' : String(v));
const yes = (v) => ({ yes: !!v, compare: v ? 'yes' : 'no' });

/**
 * A row: label + one cell per property. `num` picks the number to rank by and `best` which end
 * wins ('min' | 'max'); `samePurpose` rows only rank when every listing is for sale, or every one
 * for rent (a price and a yearly rent aren't comparable).
 */
function row(key, label, cell, { num, best, hint, purposeBound = false, lock = null, placeholder = null } = {}) {
    // Locked rows: the server sent no figures, so each cell is a blurred dummy that opens the unlock
    // popup (`lock(p)` false = the row doesn't apply to that listing, e.g. yields on a rental).
    if (lock && locked.value) {
        const dummy = placeholder || `${selectedCurrency.value.code} 0,000,000`;
        return { key, label, hint, cells: properties.value.map((p) => (lock(p) ? { locked: true, placeholder: dummy } : { text: '—' })), bestIndex: -1 };
    }
    const cells = properties.value.map(cell);
    let bestIndex = -1;
    if (best && num && (!purposeBound || samePurpose.value)) {
        const values = properties.value.map(num).map((v) => (v === null || v === undefined || Number.isNaN(Number(v)) ? null : Number(v)));
        const present = values.filter((v) => v !== null);
        if (present.length >= 2 && new Set(present).size > 1) {
            const target = best === 'min' ? Math.min(...present) : Math.max(...present);
            bestIndex = values.indexOf(target);
        }
    }
    return { key, label, hint, cells, bestIndex };
}

const priceAmount = (p) => (p.listing_type === 'rent' ? p.annual_rent : p.price);
// Which listings a locked row would have a figure for.
const anyListing = () => true;
const isSale = (p) => p.listing_type === 'sale';
const fxSpread = computed(() => (Number(notes.value.fx_spread_pct) || 0) / 100);

const sections = computed(() => {
    if (!properties.value.length) return [];
    const list = properties.value;

    const price = [
        row('price', t('compare.row_price', 'Price'), (p) => ({
            text: money(priceAmount(p)),
            sub: p.listing_type === 'rent' ? `${t('compare.per_year', 'per year')}${p.rental_period ? ` · ${t('compare.advertised', 'advertised')} ${formatPrice(p.price)} ${p.rental_period.toLowerCase()}` : ''}` : null,
        }), { num: priceAmount, best: 'min', purposeBound: true }),
        row('psf', t('compare.row_psf', 'Price per sq.ft'), (p) => ({ text: p.price_psf ? `${selectedCurrency.value.code} ${formatAmount(p.price_psf)}` : '—' }), { num: (p) => p.price_psf, best: 'min', purposeBound: true }),
        row('vs_market', t('compare.row_vs_market', 'Price vs similar in area'), (p) => {
            const v = p.market?.vs_market_pct;
            if (v === null || v === undefined) return { text: '—' };
            return { text: v === 0 ? t('compare.in_line', 'In line') : `${Math.abs(v)}% ${v < 0 ? t('compare.below', 'below') : t('compare.above', 'above')}` };
        }, { num: (p) => p.market?.vs_market_pct, best: 'min', purposeBound: true, lock: anyListing, placeholder: '00% below' }),
        row('costs', hasSale.value ? t('compare.row_costs', 'Buying / move-in costs') : t('compare.row_move_in', 'Move-in costs'), (p) => ({
            text: money(p.costs?.total),
            sub: p.listing_type === 'rent' ? t('compare.move_in_sub', 'agency fee + deposit') : (p.emirate_label ? `${p.emirate_label} ${t('compare.fees', 'fees')}` : null),
            items: p.costs?.items,
        }), { num: (p) => p.costs?.total, best: 'min', purposeBound: true, lock: anyListing }),
        row('total', t('compare.row_total', 'Total cash needed'), (p) => ({
            text: money(p.total_outlay),
            sub: p.listing_type === 'rent' ? t('compare.total_rent_sub', 'first year\'s rent + move-in') : t('compare.total_sale_sub', 'price + buying costs, cash purchase'),
        }), { num: (p) => p.total_outlay, best: 'min', purposeBound: true, lock: anyListing }),
    ];
    if (!isBase.value) {
        price.push(row('total_fx', t('compare.row_total_fx', 'With conversion margin'), (p) => ({
            text: p.total_outlay ? money(p.total_outlay * (1 + fxSpread.value)) : '—',
            sub: p.total_outlay ? `+${money(p.total_outlay * fxSpread.value)} (${notes.value.fx_spread_pct}%)` : null,
        }), { num: (p) => p.total_outlay, best: 'min', purposeBound: true, lock: anyListing, hint: `${t('compare.paying_from', 'paying from')} ${selectedCurrency.value.code}` }));
    }

    const investment = hasSale.value ? [
        row('rent_est', t('compare.row_rent_est', 'Estimated annual rent'), (p) => ({ text: p.listing_type === 'sale' ? money(p.market?.estimated_annual_rent) : '—' }), { num: (p) => (p.listing_type === 'sale' ? p.market?.estimated_annual_rent : null), best: 'max', lock: isSale }),
        row('gross_yield', t('compare.row_gross_yield', 'Gross rental yield'), (p) => ({ text: p.listing_type === 'sale' ? pct(p.market?.gross_yield_pct, 2) : '—' }), { num: (p) => (p.listing_type === 'sale' ? p.market?.gross_yield_pct : null), best: 'max', lock: isSale, placeholder: '0.00%' }),
        row('net_yield', t('compare.row_net_yield', 'Net yield (est.)'), (p) => ({ text: p.listing_type === 'sale' ? pct(p.market?.net_yield_pct, 2) : '—' }), { num: (p) => (p.listing_type === 'sale' ? p.market?.net_yield_pct : null), best: 'max', hint: t('compare.net_yield_hint', 'after service charge, upkeep, vacancy'), lock: isSale, placeholder: '0.00%' }),
        row('service_charge', t('compare.row_service_charge', 'Service charge (est.)'), (p) => ({ text: p.market?.service_charge_annual ? `${money(p.market.service_charge_annual)} / ${t('compare.yr', 'yr')}` : '—' }), { num: (p) => p.market?.service_charge_annual, best: 'min', lock: isSale, placeholder: `${selectedCurrency.value.code} 00,000 / ${t('compare.yr', 'yr')}` }),
        row('growth', t('compare.row_growth', 'Area price growth (1 yr)'), (p) => ({ text: p.market?.growth_pct === null || p.market?.growth_pct === undefined ? '—' : `${p.market.growth_pct > 0 ? '+' : ''}${p.market.growth_pct}%` }), { num: (p) => p.market?.growth_pct, best: 'max', lock: anyListing, placeholder: '+0.0%' }),
    ] : [];

    const unit = [
        row('purpose', t('compare.row_purpose', 'Listed for'), (p) => ({ text: plain(p.listing_type_label) })),
        row('type', t('compare.row_type', 'Property type'), (p) => ({ text: plain(p.type) })),
        row('status', t('compare.row_status', 'Completion'), (p) => ({ text: plain(p.completion_status) })),
        row('beds', t('compare.row_beds', 'Bedrooms'), (p) => ({ text: plain(p.beds) }), { num: (p) => (p.beds === 'studio' ? 0 : p.beds), best: 'max' }),
        row('baths', t('compare.row_baths', 'Bathrooms'), (p) => ({ text: plain(p.baths) }), { num: (p) => p.baths, best: 'max' }),
        row('size', t('compare.row_size', 'Size'), (p) => ({ text: p.sqft ? `${Number(p.sqft).toLocaleString('en-US')} sq.ft` : '—', sub: p.sqft ? `${Math.round(p.sqft * 0.092903).toLocaleString('en-US')} m²` : null }), { num: (p) => p.sqft, best: 'max' }),
        row('furnishing', t('compare.row_furnishing', 'Furnishing'), (p) => ({ text: plain(p.furnishing) })),
        row('floor', t('compare.row_floor', 'Floor'), (p) => ({ text: plain(p.floor) })),
        row('parking', t('compare.row_parking', 'Parking'), (p) => ({ text: plain(p.parking) }), { num: (p) => p.parking, best: 'max' }),
        row('year_built', t('compare.row_year_built', 'Year built'), (p) => ({ text: plain(p.year_built) }), { num: (p) => p.year_built, best: 'max' }),
        row('developer', t('compare.row_developer', 'Developer'), (p) => ({ text: plain(p.developer) })),
        row('view', t('compare.row_view', 'View'), (p) => ({ text: plain(p.view) })),
    ];

    // One row per kind of nearby place any of the listings has (metro, school, mall …).
    const nearbyKeys = [...new Map(list.flatMap((p) => Object.entries(p.nearby || {}).map(([k, v]) => [k, v.label]))).entries()];
    const location = [
        row('emirate', t('compare.row_emirate', 'Emirate'), (p) => ({ text: plain(p.emirate_label) })),
        row('area', t('compare.row_area', 'Area'), (p) => ({ text: plain(p.location) })),
        ...nearbyKeys.map(([key, label]) => row(`near_${key}`, `${t('compare.nearest', 'Nearest')} ${label.toLowerCase()}`, (p) => {
            const n = p.nearby?.[key];
            return n ? { text: km(n.distance_km), sub: n.name } : { text: '—' };
        }, { num: (p) => p.nearby?.[key]?.distance_km, best: 'min' })),
    ];

    const amenityNames = [...new Set(list.flatMap((p) => p.amenities || []))].sort((a, b) => a.localeCompare(b));
    const amenities = [
        row('amenity_count', t('compare.row_amenity_count', 'Amenities listed'), (p) => ({ text: String((p.amenities || []).length) }), { num: (p) => (p.amenities || []).length, best: 'max' }),
        ...amenityNames.map((name) => row(`amenity_${name}`, name, (p) => yes((p.amenities || []).includes(name)))),
    ];

    const ownership = hasSale.value ? [
        row('ownership', t('compare.row_ownership', 'Foreign ownership'), (p) => ({ text: p.ownership || '—' })),
        row('golden_visa', t('compare.row_golden_visa', 'Golden Visa eligible'), (p) => (p.listing_type === 'sale' ? yes(p.golden_visa) : { text: '—' }), { hint: `${t('compare.from', 'from')} ${formatPrice(notes.value.golden_visa_min)}` }),
    ] : [];

    return [
        { key: 'price', title: t('compare.section_price', 'Price & cost to buy'), rows: price },
        { key: 'investment', title: t('compare.section_investment', 'Investment'), rows: investment },
        { key: 'unit', title: t('compare.section_unit', 'The property'), rows: unit },
        { key: 'location', title: t('compare.section_location', 'Location'), rows: location },
        { key: 'amenities', title: t('compare.section_amenities', 'Amenities'), rows: amenities },
        { key: 'ownership', title: t('compare.section_ownership', 'Ownership & residency'), rows: ownership },
    ];
});

/** "Only show differences" hides rows where every listing reads the same; empty sections drop out. */
const visibleSections = computed(() => sections.value
    .map((s) => ({
        ...s,
        rows: s.rows.filter((r) => {
            if (r.cells.some((c) => c.locked)) return true; // a hidden figure — keep it on show as a teaser
            if (r.cells.every((c) => c.text === '—')) return false;
            if (!differencesOnly.value) return true;
            const values = r.cells.map((c) => (c.yes !== undefined ? c.compare : c.text));
            return new Set(values).size > 1;
        }),
    }))
    .filter((s) => s.rows.length));
</script>
