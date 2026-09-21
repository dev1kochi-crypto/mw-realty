import { ref } from 'vue';

const agent = ref(null);
const notFound = ref(false);

function fetchAgent(slug, langCode) {
    notFound.value = false;
    return window.axios.get(`/api/agents/${slug}`, { params: langCode ? { lang: langCode } : {} })
        .then((res) => {
            agent.value = res.data;
        })
        .catch(() => {
            agent.value = null;
            notFound.value = true;
        });
}

/** GET /api/agents/{slug} -> Api\AgentController::show -> AgentAgencyPageService::getAgentDetail. */
export function useAgentDetail() {
    return { agent, notFound, fetchAgent };
}
