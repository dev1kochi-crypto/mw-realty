import { ref, watch } from 'vue';
import { useLanguages } from './useLanguages';

// Singleton, same pattern as useSiteInformation/useProperties — shared across every
// component that calls useStaticText(), so the JSON only loads once per language.
const translations = ref({});
const loadedCode = ref(null);
const reportedMissing = new Set();
let pendingCode = null;

function lookup(tree, dotPath) {
    return dotPath.split('.').reduce((node, key) => (node && typeof node === 'object' ? node[key] : undefined), tree);
}

function fetchTranslations(code) {
    if (pendingCode === code || loadedCode.value === code) return;
    pendingCode = code;

    return window.axios.get('/api/static-translations', { params: { lang: code } })
        .then((res) => {
            translations.value = res.data;
            loadedCode.value = code;
        })
        .catch(() => {})
        .finally(() => {
            pendingCode = null;
        });
}

/**
 * GET /api/static-translations -> Api\StaticTranslationController -> the same JSON files
 * the admin's Languages > Translations screen edits (CMS\SiteManager\Services\StaticTranslationService).
 * t('nav.home') looks up a dot-path key. When a key is missing from the language file (e.g. a
 * server whose cms-static JSON is older than the code), it falls back to a readable version of
 * the key's last segment ('home.badges.verified' -> 'Verified') instead of showing the raw key
 * to visitors; the missing key is logged to the console so it can still be spotted and added.
 */
function humanize(key) {
    const last = String(key).split('.').pop().replace(/[_-]+/g, ' ').trim();
    return last ? last.charAt(0).toUpperCase() + last.slice(1) : key;
}

export function useStaticText() {
    const { selectedLanguage } = useLanguages();

    watch(selectedLanguage, (lang) => {
        if (lang?.code) fetchTranslations(lang.code);
    }, { immediate: true });

    function t(key, fallback) {
        const value = lookup(translations.value, key);
        if (typeof value === 'string' && value !== '') return value;
        // Only report once the file has actually loaded — before that every key is "missing".
        if (loadedCode.value && fallback === undefined && !reportedMissing.has(key)) {
            reportedMissing.add(key);
            console.warn(`[i18n] Missing translation: ${key}`);
        }
        return fallback ?? humanize(key);
    }

    return { t };
}
