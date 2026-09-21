import { ref } from 'vue';

const legalPage = ref(null);
const notFound = ref(false);

function fetchLegalPage(key, langCode) {
    notFound.value = false;
    return window.axios.get(`/api/legal/${key}`, { params: langCode ? { lang: langCode } : {} })
        .then((res) => {
            legalPage.value = res.data;
        })
        .catch(() => {
            legalPage.value = null;
            notFound.value = true;
        });
}

/** GET /api/legal/{key} -> Api\LegalController::show -> LegalPageService::getPage. Shared by all 4 legal pages. */
export function useLegalPage() {
    return { legalPage, notFound, fetchLegalPage };
}
