import { ref, watch } from 'vue';
import { useLanguages } from './useLanguages';

const postPropertySection = ref(null);
let initialized = false;

function fetchPostPropertySteps(langCode) {
    window.axios.get('/api/post-property-steps', { params: langCode ? { lang: langCode } : {} }).then((res) => {
        postPropertySection.value = res.data;
    }).catch(() => {
        postPropertySection.value = null;
    });
}

export function usePostPropertySteps() {
    if (!initialized) {
        initialized = true;
        const { selectedLanguage } = useLanguages();
        fetchPostPropertySteps(selectedLanguage.value?.code);
        watch(selectedLanguage, (lang) => {
            if (lang) fetchPostPropertySteps(lang.code);
        });
    }

    return { postPropertySection };
}
