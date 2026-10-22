import { ref } from 'vue';
import { useDocumentHead } from './useDocumentHead';

const property = ref(null);
const notFound = ref(false);
const loading = ref(false);
// Bumped on every successful load so the page can remount its markup from scratch —
// the sliders are rebuilt by jQuery/slick, so Vue must never patch new data into them.
const renderKey = ref(0);
let requestId = 0;

function fetchProperty(slug, langCode) {
    const id = ++requestId;
    notFound.value = false;
    loading.value = true;
    // A different property: drop the old one so the page shows the loading state instead of
    // the previous listing while the new one arrives.
    if (property.value && property.value.slug !== slug) property.value = null;

    return window.axios.get(`/api/properties/${slug}`, { params: langCode ? { lang: langCode } : {} })
        .then((res) => {
            if (id !== requestId) return; // a newer request (fast clicks / language switch) wins
            renderKey.value++;
            property.value = res.data;
            useDocumentHead().setSeo(res.data.seo);
        })
        .catch(() => {
            if (id !== requestId) return;
            property.value = null;
            notFound.value = true;
        })
        .finally(() => {
            if (id === requestId) loading.value = false;
        });
}

/** GET /api/properties/{slug} -> Api\PropertyController::show -> PropertyPageService::getPropertyDetail. */
export function usePropertyDetail() {
    return { property, notFound, loading, renderKey, fetchProperty };
}
