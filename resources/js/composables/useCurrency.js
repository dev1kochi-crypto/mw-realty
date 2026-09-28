import { computed, ref, watch } from 'vue';

/**
 * Website currency switcher. Every price in the database/API is in AED (the base); the admin
 * sets each currency's `rate` = units per 1 AED (Admin › General Settings › Currencies), and the
 * site shows price_aed × rate. Price FILTERS still travel in AED (min_price/max_price) — amounts a
 * visitor types in another currency are converted back with toAed().
 *
 * The visitor's choice is remembered in localStorage; until then the admin's default is used.
 * Legacy script.js widgets (the Home price slider) format through window.MWCurrency and re-render
 * on the "mw:currency-change" event.
 */
const STORAGE_KEY = 'mw-currency';
const BASE = { code: 'AED', name: 'UAE Dirham', symbol: 'AED', rate: 1, is_default: true };

const currencies = ref([BASE]);
const chosenCode = ref(readStored());
let initialized = false;

function readStored() {
    try { return window.localStorage.getItem(STORAGE_KEY); } catch { return null; }
}
function store(code) {
    try { window.localStorage.setItem(STORAGE_KEY, code); } catch { /* private mode etc. */ }
}

const selectedCurrency = computed(() => currencies.value.find((c) => c.code === chosenCode.value)
    || currencies.value.find((c) => c.is_default)
    || currencies.value[0]
    || BASE);
const rate = () => Number(selectedCurrency.value.rate) || 1;

/** AED → selected currency (number). */
function convert(aed) {
    return Number(aed) * rate();
}
/** Selected currency → AED (number), for price filters. */
function toAed(amount) {
    return Number(amount) / rate();
}

const grouped = (n, decimals = 0) => Number(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: decimals });

/** "1,234,567" in the selected currency (no code) — for layouts that show the code separately. */
function formatAmount(aed) {
    if (aed === null || aed === undefined || aed === '') return '';
    return grouped(Math.round(convert(aed)));
}
/** "USD 1,234,567" — or `fallback` (e.g. "Price on request") when there is no price. */
function formatPrice(aed, fallback = '') {
    if (aed === null || aed === undefined || aed === '' || !Number(aed)) return fallback;
    return `${selectedCurrency.value.code} ${formatAmount(aed)}`;
}
/** Short form for sliders/chips: "USD 1.5M", "USD 250K", "USD 900" (withCode=false drops the code). */
function formatCompact(aed, withCode = true) {
    const n = convert(aed || 0);
    const abs = Math.abs(n);
    const text = abs >= 1e6 ? `${grouped(n / 1e6, 2)}M` : abs >= 1e3 ? `${grouped(n / 1e3, abs >= 1e5 ? 0 : 1)}K` : grouped(Math.round(n));
    return withCode ? `${selectedCurrency.value.code} ${text}` : text;
}
/** Parses "USD 1.5M" / "250k" / "1,200,000" typed in the selected currency → AED (NaN if not a number). */
function parseToAed(text) {
    const raw = String(text || '').toUpperCase().replace(/[A-Z]{3}\s*/, '').replace(/[,\s]/g, '');
    const m = raw.match(/^(\d*\.?\d+)([KM])?$/);
    if (!m) return NaN;
    const value = parseFloat(m[1]) * (m[2] === 'M' ? 1e6 : m[2] === 'K' ? 1e3 : 1);
    return toAed(value);
}

/**
 * Label for a price quick-select chip whose bounds are in AED. In AED the page's own (translated)
 * label is kept, e.g. "Under 500K"; in another currency it's built from the converted bounds,
 * e.g. "Under USD 136K", "USD 136K – 272K", "USD 2.72M+".
 */
function presetLabel(min, max, { top = false, baseLabel = '', under = 'Under' } = {}) {
    if (selectedCurrency.value.code === BASE.code && baseLabel) return baseLabel;
    if (!min) return `${under} ${formatCompact(max)}`;
    if (top) return `${formatCompact(min)}+`;
    return `${formatCompact(min)} – ${formatCompact(max, false)}`;
}

function selectCurrency(code) {
    if (!currencies.value.some((c) => c.code === code)) return;
    chosenCode.value = code;
    store(code);
}

// Legacy (non-Vue) widgets format through this and re-render when the currency changes.
function exposeToLegacyScript() {
    window.MWCurrency = {
        code: () => selectedCurrency.value.code,
        formatCompact: (aed) => formatCompact(aed),
        parseToAed,
    };
}

export function useCurrency() {
    if (!initialized) {
        initialized = true;
        exposeToLegacyScript();
        window.axios.get('/api/currencies').then((res) => {
            const list = res.data?.currencies || [];
            if (list.length) currencies.value = list;
        }).catch(() => { /* keep AED */ });
        watch(() => selectedCurrency.value.code + ':' + selectedCurrency.value.rate, () => {
            window.dispatchEvent(new CustomEvent('mw:currency-change', { detail: { code: selectedCurrency.value.code } }));
        });
    }

    return { currencies, selectedCurrency, selectCurrency, convert, toAed, formatAmount, formatPrice, formatCompact, parseToAed, presetLabel };
}
