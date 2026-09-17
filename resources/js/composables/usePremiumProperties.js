import { ref, watch } from 'vue';
import { useLanguages } from './useLanguages';

const premiumProperties = ref(null);
let initialized = false;

function fetchPremiumProperties(langCode) {
    window.axios.get('/api/premium-properties', { params: langCode ? { lang: langCode } : {} }).then((res) => {
        premiumProperties.value = res.data;
    }).catch(() => {
        premiumProperties.value = null;
    });
}

export function usePremiumProperties() {
    if (!initialized) {
        initialized = true;
        const { selectedLanguage } = useLanguages();
        fetchPremiumProperties(selectedLanguage.value?.code);
        watch(selectedLanguage, (lang) => {
            if (lang) fetchPremiumProperties(lang.code);
        });
    }

    return { premiumProperties };
}
