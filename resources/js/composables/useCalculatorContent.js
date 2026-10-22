import { ref, watch } from 'vue';
import { useLanguages } from './useLanguages';
import { useDocumentHead } from './useDocumentHead';

// Admin › Calculators content (see Api\CalculatorPageController): the menu list and each page's
// copy, FAQs, settings and SEO — refetched whenever the language changes.
const menu = ref(null); // [{ key, title }] — only the calculators shown in the menu
const pages = ref({}); // { [key]: page } for the current language
let initialized = false;
let currentLang = null;

function fetchMenu(lang) {
    window.axios.get('/api/calculators', { params: lang ? { lang } : {} })
        .then((res) => { menu.value = Array.isArray(res.data?.data) ? res.data.data : null; })
        .catch(() => { menu.value = null; });
}

function fetchPage(key, lang) {
    return window.axios.get(`/api/calculators/${key}`, { params: lang ? { lang } : {} })
        .then((res) => {
            pages.value = { ...pages.value, [key]: res.data };
            return res.data;
        })
        .catch(() => null);
}

function init() {
    if (initialized) return;
    initialized = true;
    const { selectedLanguage } = useLanguages();
    currentLang = selectedLanguage.value?.code;
    fetchMenu(currentLang);
    watch(selectedLanguage, (lang) => {
        if (!lang || lang.code === currentLang) return;
        currentLang = lang.code;
        const loaded = Object.keys(pages.value);
        pages.value = {};
        fetchMenu(currentLang);
        loaded.forEach((key) => fetchPage(key, currentLang).then((page) => {
            if (page && page.key === activeKey) useDocumentHead().setSeo(page.seo);
        }));
    });
}

let activeKey = null;

/** The calculators menu (header + page tabs). */
export function useCalculatorMenu() {
    init();
    return { menu };
}

/** One calculator page's content; `load(key)` fetches it (once per language) and sets the page SEO. */
export function useCalculatorContent() {
    init();

    async function load(key) {
        activeKey = key;
        const page = pages.value[key] || await fetchPage(key, currentLang);
        if (page && activeKey === key) useDocumentHead().setSeo(page.seo);
    }

    return { pages, load };
}
