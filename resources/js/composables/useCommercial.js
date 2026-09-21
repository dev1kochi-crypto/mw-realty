import { ref } from 'vue';

const commercialListing = ref(null);
const loading = ref(false);

function fetchCommercialListing(params = {}) {
    loading.value = true;
    return window.axios.get('/api/commercial', { params })
        .then((res) => {
            commercialListing.value = res.data;
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
