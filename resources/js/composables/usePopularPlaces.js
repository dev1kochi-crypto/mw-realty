import { ref, watch } from 'vue';
import { useLanguages } from './useLanguages';

const popularPlacesSection = ref(null);
let initialized = false;

function fetchPopularPlaces(langCode) {
    window.axios.get('/api/popular-places', { params: langCode ? { lang: langCode } : {} }).then((res) => {
        popularPlacesSection.value = res.data;
    }).catch(() => {
        popularPlacesSection.value = null;
    });
}

export function usePopularPlaces() {
    if (!initialized) {
        initialized = true;
        const { selectedLanguage } = useLanguages();
        fetchPopularPlaces(selectedLanguage.value?.code);
        watch(selectedLanguage, (lang) => {
            if (lang) fetchPopularPlaces(lang.code);
        });
    }

    return { popularPlacesSection };
}
