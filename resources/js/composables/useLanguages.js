import { ref } from 'vue';

const languages = ref([]);
const selectedLanguage = ref(null);
let loaded = false;

function selectLanguage(lang) {
    selectedLanguage.value = lang;
    document.documentElement.classList.toggle('is-rtl', !lang.is_default);
    document.documentElement.setAttribute('lang', lang.code);
}

export function useLanguages() {
    if (!loaded) {
        loaded = true;
        window.axios.get('/api/languages').then((res) => {
            languages.value = res.data;
            selectedLanguage.value = res.data.find((lang) => lang.is_default) ?? res.data[0] ?? null;
        }).catch(() => {
            languages.value = [{ id: 0, name: 'English', code: 'en', is_default: true, flag_url: null }];
            selectedLanguage.value = languages.value[0];
        });
    }

    return { languages, selectedLanguage, selectLanguage };
}
