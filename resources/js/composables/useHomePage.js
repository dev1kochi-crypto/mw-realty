import { ref, watch } from 'vue';
import { useLanguages } from './useLanguages';

const homePage = ref(null);
let initialized = false;

function fetchHomePage(langCode) {
    window.axios.get('/api/home', { params: langCode ? { lang: langCode } : {} }).then((res) => {
        homePage.value = res.data;
    }).catch(() => {
        homePage.value = null;
    });
}

/**
 * One fetch for the entire home page (see routes/api.php -> Api\HomeController -> HomePageService)
 * instead of one request per section — every section's data lives under its own key on
 * `homePage.value` (banner, brands, ad, developments, premiumProperties, luxury, whyChooseUs,
 * realty, communities, findProperties, postProperty, popularPlaces, testimonials, contact).
 */
export function useHomePage() {
    if (!initialized) {
        initialized = true;
        const { selectedLanguage } = useLanguages();
        fetchHomePage(selectedLanguage.value?.code);
        watch(selectedLanguage, (lang) => {
            if (lang) fetchHomePage(lang.code);
        });
    }

    return { homePage };
}
