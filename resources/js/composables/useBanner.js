import { ref, watch } from 'vue';
import { useLanguages } from './useLanguages';

const banner = ref(null);
let initialized = false;

function fetchBanner(langCode) {
    window.axios.get('/api/banner', { params: langCode ? { lang: langCode } : {} }).then((res) => {
        banner.value = res.data;
    }).catch(() => {
        banner.value = null;
    });
}

export function useBanner() {
    if (!initialized) {
        initialized = true;
        const { selectedLanguage } = useLanguages();
        fetchBanner(selectedLanguage.value?.code);
        // Re-fetch whenever the header's language dropdown changes, so switching
        // to Arabic actually swaps the banner text (already saved per-language
        // in the admin) instead of just flipping the RTL class.
        watch(selectedLanguage, (lang) => {
            if (lang) fetchBanner(lang.code);
        });
    }

    return { banner };
}
