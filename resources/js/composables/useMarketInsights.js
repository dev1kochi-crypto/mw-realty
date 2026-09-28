import { ref } from 'vue';
import { useDocumentHead } from './useDocumentHead';

const insightsListing = ref(null);
const listingLoading = ref(false);
const insightPost = ref(null);
const postNotFound = ref(false);

function fetchInsights({ page = 1, topic = '', region = '' } = {}, langCode) {
    listingLoading.value = true;
    const params = { page, ...(langCode ? { lang: langCode } : {}) };
    if (topic) params.topic = topic;
    if (region) params.region = region;
    return window.axios.get('/api/market-insights', { params })
        .then((res) => {
            insightsListing.value = res.data;
            useDocumentHead().setSeo(res.data.seo);
        })
        .catch(() => {
            insightsListing.value = null;
        })
        .finally(() => {
            listingLoading.value = false;
        });
}

function fetchInsight(slug, langCode) {
    postNotFound.value = false;
    return window.axios.get(`/api/market-insights/${slug}`, { params: langCode ? { lang: langCode } : {} })
        .then((res) => {
            insightPost.value = res.data;
            useDocumentHead().setSeo(res.data.post?.seo);
        })
        .catch(() => {
            insightPost.value = null;
            postNotFound.value = true;
        });
}

/**
 * GET /api/market-insights (?page=&topic=&region=, filtered + paginated server-side) and
 * GET /api/market-insights/{slug} -> Api\MarketInsightController -> MarketInsightPageService.
 */
export function useMarketInsights() {
    return { insightsListing, listingLoading, fetchInsights, insightPost, postNotFound, fetchInsight };
}
