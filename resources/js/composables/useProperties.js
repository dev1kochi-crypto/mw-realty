import { ref } from 'vue';

const propertiesListing = ref(null);
const loading = ref(false);

function fetchPropertiesListing(params = {}) {
    loading.value = true;
    return window.axios.get('/api/properties', { params })
        .then((res) => {
            propertiesListing.value = res.data;
        })
        .catch(() => {
            propertiesListing.value = null;
        })
        .finally(() => {
            loading.value = false;
        });
}

/** GET /api/properties -> Api\PropertiesController::index -> PropertiesPageService::getListingData. */
export function useProperties() {
    return { propertiesListing, loading, fetchPropertiesListing };
}
