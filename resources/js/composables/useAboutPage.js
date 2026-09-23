import { ref, watch } from 'vue';
import { useLanguages } from './useLanguages';
import { useDocumentHead } from './useDocumentHead';

const aboutPage = ref(null);
let initialized = false;

function fetchAboutPage(langCode) {
    window.axios.get('/api/about', { params: langCode ? { lang: langCode } : {} }).then((res) => {
        aboutPage.value = res.data;
        useDocumentHead().setSeo(res.data.seo);
    }).catch(() => {
        aboutPage.value = null;
    });
}

/** One fetch for the entire standalone About page (see routes/api.php -> Api\AboutController -> HomePageService::getAboutPageData). */
export function useAboutPage() {
    if (!initialized) {
        initialized = true;
        const { selectedLanguage } = useLanguages();
        fetchAboutPage(selectedLanguage.value?.code);
        watch(selectedLanguage, (lang) => {
            if (lang) fetchAboutPage(lang.code);
        });
    }

    return { aboutPage };
}
