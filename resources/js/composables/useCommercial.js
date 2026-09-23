import { ref } from 'vue';
import { useDocumentHead } from './useDocumentHead';

const commercialListing = ref(null);
const loading = ref(false);

function fetchCommercialListing(params = {}) {
    loading.value = true;
    return window.axios.get('/api/commercial', { params })
        .then((res) => {
            commercialListing.value = res.data;
            useDocumentHead().setSeo(res.data.seo);
        })
        .catch(() => {
            commercialListing.value = null;
        })
        .finally(() => {
            loading.value = false;
        });
}

/** GET /api/commercial -> Api\CommercialController::index -> CommercialPageService::getListingData. */
export function useCommercial() {
    return { commercialListing, loading, fetchCommercialListing };
}
