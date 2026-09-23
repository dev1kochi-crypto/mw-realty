import { ref, watch } from 'vue';
import { useLanguages } from './useLanguages';
import { useDocumentHead } from './useDocumentHead';

const contactPage = ref(null);
let initialized = false;

function fetchContactPage(langCode) {
    window.axios.get('/api/contact', { params: langCode ? { lang: langCode } : {} }).then((res) => {
        contactPage.value = res.data;
        useDocumentHead().setSeo(res.data.seo);
    }).catch(() => {
        contactPage.value = null;
    });
}

/** One fetch for the entire standalone Contact page (see routes/api.php -> Api\ContactController -> HomePageService::getContactPageData). */
export function useContactPage() {
    if (!initialized) {
        initialized = true;
        const { selectedLanguage } = useLanguages();
        fetchContactPage(selectedLanguage.value?.code);
        watch(selectedLanguage, (lang) => {
            if (lang) fetchContactPage(lang.code);
        });
    }

    return { contactPage };
}
