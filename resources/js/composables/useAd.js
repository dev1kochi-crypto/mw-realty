import { onMounted, ref } from 'vue';

/**
 * Fetches the active ad for a given placement (a page name, matching CMS > Ad Management's
 * Placement dropdown) — e.g. useAd('home'). Resolves to null when the ad is off, expired,
 * or none is configured, so the calling page can hide its ad section entirely.
 */
export function useAd(placement) {
    const ad = ref(null);

    onMounted(() => {
        window.axios.get('/api/ads', { params: { placement } }).then((res) => {
            ad.value = res.data;
        }).catch(() => {
            ad.value = null;
        });
    });

    return { ad };
}
