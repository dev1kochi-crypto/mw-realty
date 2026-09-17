import { ref, watch } from 'vue';
import { useLanguages } from './useLanguages';

const developments = ref(null);
let initialized = false;

function fetchDevelopments(langCode) {
    window.axios.get('/api/developments', { params: langCode ? { lang: langCode } : {} }).then((res) => {
        developments.value = res.data;
    }).catch(() => {
        developments.value = null;
    });
}

export function useDevelopments() {
    if (!initialized) {
        initialized = true;
        const { selectedLanguage } = useLanguages();
        fetchDevelopments(selectedLanguage.value?.code);
        // Re-fetch on language switch, same as the banner/brands — cities and the
        // heading/button text are all saved per-language in the admin.
        watch(selectedLanguage, (lang) => {
            if (lang) fetchDevelopments(lang.code);
        });
    }

    return { developments };
}
