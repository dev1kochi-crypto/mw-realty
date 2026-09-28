import { computed, ref, watch } from 'vue';
import { useLanguages } from './useLanguages';

// Admin-managed search filters (Admin > Filters), from GET /api/property-filters -> PropertyFilterController.
// One shared cache per page ("home" | "listing") + scope + language, so the home search and each listing
// page fetch their filter set once. scope "commercial" takes option counts / slider bounds from
// Commercial-menu listings (the /commercial page) instead of residential ones.
const cache = ref({});
const pending = new Set();

function fetchFilters(page, lang, scope) {
    const key = `${page}:${scope}:${lang || ''}`;
    if (cache.value[key] || pending.has(key)) return;
    pending.add(key);
    window.axios.get('/api/property-filters', { params: { page, lang, scope } })
        .then((res) => { cache.value = { ...cache.value, [key]: res.data.filters || [] }; })
        .catch(() => { cache.value = { ...cache.value, [key]: [] }; })
        .finally(() => pending.delete(key));
}

/**
 * @param {'home'|'listing'} page which filters to load (each filter's "Show on" setting in the admin)
 * @param {'residential'|'commercial'} [scope] which listings the options/bounds come from
 * @returns {{ filters, loaded, byKey, options }}
 *   filters — [{ key, label, type: 'select'|'range', options?: [{value,label}], min?, max?, step? }]
 *   byKey(key) — one filter definition or undefined (disabled / not shown on this page)
 *   options(key) — that filter's [{value,label}] (empty when the admin has it switched off)
 */
export function usePropertyFilters(page, scope = 'residential') {
    const { selectedLanguage } = useLanguages();
    const cacheKey = computed(() => `${page}:${scope}:${selectedLanguage.value?.code || ''}`);

    watch(() => selectedLanguage.value?.code, (code) => fetchFilters(page, code, scope), { immediate: true });

    const filters = computed(() => cache.value[cacheKey.value] || []);
    const loaded = computed(() => cacheKey.value in cache.value);
    const byKey = (key) => filters.value.find((f) => f.key === key);
    const options = (key) => byKey(key)?.options || [];

    return { filters, loaded, byKey, options };
}
