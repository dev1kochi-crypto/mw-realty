import { computed, ref, watch } from 'vue';
import { useLanguages } from './useLanguages';

// A listing page's own search filters (Admin › Filters › Search Filters), from the key-value
// GET /api/search-filters/{page} -> Api\SearchFilterController. One shared cache per page + language.
// The Home banner search is fixed and keeps using usePropertyFilters('home').
const cache = ref({});
const pending = new Set();

/** { filters: {key: def}, order: [keys] } -> [{ key, ...def, options: [{value, label, count}] }] in admin order. */
function toList(body) {
    const defs = body?.filters || {};
    return (body?.order || Object.keys(defs)).filter((key) => defs[key]).map((key) => {
        const def = defs[key];
        const options = def.options
            ? (def.options_order || Object.keys(def.options)).map((value) => ({
                value: String(value),
                label: def.options[value] ?? String(value),
                count: def.counts?.[value],
            }))
            : undefined;
        return { key, ...def, options };
    });
}

function fetchFilters(page, lang) {
    const key = `${page}:${lang || ''}`;
    if (cache.value[key] || pending.has(key)) return;
    pending.add(key);
    window.axios.get(`/api/search-filters/${page}`, { params: { lang } })
        .then((res) => { cache.value = { ...cache.value, [key]: toList(res.data) }; })
        .catch(() => { cache.value = { ...cache.value, [key]: [] }; })
        .finally(() => pending.delete(key));
}

/**
 * @param {'properties'|'commercial'|'premium'|'offplan'} page
 * @returns {{ filters, loaded, byKey, options }}
 *   filters — [{ key, label, type: 'select'|'radio'|'checkbox'|'text'|'number'|'range', placeholder,
 *               options?: [{value, label, count?}], multiple?, param?, params?: {min, max}, min?, max?, step?, compare? }]
 *   byKey(key) — one filter or undefined (not on this page / switched off)
 *   options(key) — that filter's [{value, label}]
 */
export function useSearchFilters(page) {
    const { selectedLanguage } = useLanguages();
    const cacheKey = computed(() => `${page}:${selectedLanguage.value?.code || ''}`);

    watch(() => selectedLanguage.value?.code, (code) => fetchFilters(page, code), { immediate: true });

    const filters = computed(() => cache.value[cacheKey.value] || []);
    const loaded = computed(() => cacheKey.value in cache.value);
    const byKey = (key) => filters.value.find((f) => f.key === key);
    const options = (key) => byKey(key)?.options || [];

    return { filters, loaded, byKey, options };
}

/**
 * Price quick-select buttons as [min, max, label, isOpenEnded], capped to the slider's ceiling.
 * From the admin's price filter (`presets`), else `fallback` ([[min, max|Infinity, label], …]).
 */
export function quickSelect(priceFilter, ceiling, fallback) {
    const list = priceFilter?.presets?.length
        ? priceFilter.presets.map((p) => [p.min, p.max ?? Infinity, p.label])
        : fallback;
    return list
        .filter(([lo]) => lo < ceiling)
        .map(([lo, hi, label]) => [lo, Math.min(hi, ceiling), label, hi === Infinity]);
}

/** Filters that drive a page control (location box, sort, features, amenity shortcuts), not a panel field. */
export const PAGE_CONTROLS = ['location', 'sort', 'features', 'amenities'];

/** The query keys a filter writes: its `param`, or `params.min` / `params.max` for a range. */
export function filterParams(f) {
    if (f.type === 'range') return [f.params?.min || `min_${f.key}`, f.params?.max || `max_${f.key}`];
    return [f.param || f.key];
}
