import { ref } from 'vue';

const property = ref(null);
const notFound = ref(false);

function fetchProperty(slug, langCode) {
    notFound.value = false;
    return window.axios.get(`/api/properties/${slug}`, { params: langCode ? { lang: langCode } : {} })
        .then((res) => {
            property.value = res.data;
        })
        .catch(() => {
            property.value = null;
            notFound.value = true;
        });
}

/** GET /api/properties/{slug} -> Api\PropertyController::show -> PropertyPageService::getPropertyDetail. */
export function usePropertyDetail() {
    return { property, notFound, fetchProperty };
}
