import { ref } from 'vue';

const agentsListing = ref(null);
const loading = ref(false);

function fetchAgentsListing(langCode) {
    loading.value = true;
    return window.axios.get('/api/agents', { params: langCode ? { lang: langCode } : {} })
        .then((res) => {
            agentsListing.value = res.data;
        })
        .catch(() => {
            agentsListing.value = null;
        })
        .finally(() => {
            loading.value = false;
        });
}

/** GET /api/agents -> Api\AgentController::index -> AgentAgencyPageService::getAgentsListing. */
export function useAgents() {
    return { agentsListing, loading, fetchAgentsListing };
}
