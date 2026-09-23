import { ref } from 'vue';
import { useDocumentHead } from './useDocumentHead';

const agency = ref(null);
const notFound = ref(false);

function fetchAgency(slug, langCode) {
    notFound.value = false;
    return window.axios.get(`/api/agencies/${slug}`, { params: langCode ? { lang: langCode } : {} })
        .then((res) => {
            agency.value = res.data;
            useDocumentHead().setSeo(res.data.seo);
        })
        .catch(() => {
            agency.value = null;
            notFound.value = true;
        });
}

/** GET /api/agencies/{slug} -> Api\AgencyController::show -> AgentAgencyPageService::getAgencyDetail. */
export function useAgencyDetail() {
    return { agency, notFound, fetchAgency };
}
