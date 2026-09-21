import { ref } from 'vue';

const blogListing = ref(null);
const loading = ref(false);

function fetchBlogListing(page = 1, langCode, category) {
    loading.value = true;
    const params = { page, ...(langCode ? { lang: langCode } : {}) };
    if (category && category !== 'all') params.category = category;
    return window.axios.get('/api/blogs', { params })
        .then((res) => {
            blogListing.value = res.data;
        })
        .catch(() => {
            blogListing.value = null;
        })
        .finally(() => {
            loading.value = false;
        });
}

/** GET /api/blogs (paginated) -> Api\BlogController::index -> BlogPageService::getListingData. */
export function useBlogListing() {
    return { blogListing, loading, fetchBlogListing };
}
