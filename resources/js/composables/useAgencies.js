import { ref } from 'vue';

const agenciesListing = ref(null);
const loading = ref(false);

function fetchAgenciesListing(langCode) {
    loading.value = true;
    return window.axios.get('/api/agencies', { params: langCode ? { lang: langCode } : {} })
        .then((res) => {
            agenciesListing.value = res.data;
        })
        .catch(() => {
            agenciesListing.value = null;
        })
        .finally(() => {
            loading.value = false;
        });
}

/** GET /api/agencies -> Api\AgencyController::index -> AgentAgencyPageService::getAgenciesListing. */
export function useAgencies() {
    return { agenciesListing, loading, fetchAgenciesListing };
}
