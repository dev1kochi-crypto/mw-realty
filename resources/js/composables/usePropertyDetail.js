import { ref } from 'vue';
import { useDocumentHead } from './useDocumentHead';

const property = ref(null);
const notFound = ref(false);

function fetchProperty(slug, langCode) {
    notFound.value = false;
    return window.axios.get(`/api/properties/${slug}`, { params: langCode ? { lang: langCode } : {} })
        .then((res) => {
            property.value = res.data;
            useDocumentHead().setSeo(res.data.seo);
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
