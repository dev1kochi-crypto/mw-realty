import { ref, watch } from 'vue';
import { useLanguages } from './useLanguages';

// Singleton, same pattern as useSiteInformation/useProperties — shared across every
// component that calls useStaticText(), so the JSON only loads once per language.
const translations = ref({});
const loadedCode = ref(null);
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
 * t('nav.home') looks up a dot-path key; the raw key is returned if the site hasn't been
 * (re)built with real copy yet, so a missing translation is visible rather than blank.
 */
export function useStaticText() {
    const { selectedLanguage } = useLanguages();

    watch(selectedLanguage, (lang) => {
        if (lang?.code) fetchTranslations(lang.code);
    }, { immediate: true });

    function t(key, fallback = key) {
        const value = lookup(translations.value, key);
        return typeof value === 'string' && value !== '' ? value : fallback;
    }

    return { t };
}
