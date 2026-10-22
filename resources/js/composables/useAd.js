import { computed, onMounted, ref, watch } from 'vue';
import { useLanguages } from './useLanguages';

/**
 * Fetches the ads running on a page — e.g. useAd('home') — for its slider: MW Realty's own ads
 * (Admin › Ads) plus clients' booked ads in their hours. `slides` is empty when none runs, so the
 * page hides its ad block entirely; `ad` is the first one; `interval` = seconds per slide.
 * Re-fetches when the site language changes (the ads' overlay text is per language).
 */
export function useAd(placement) {
    const slides = ref([]);
    const interval = ref(6);
    const { selectedLanguage } = useLanguages();

    const load = () => {
        window.axios.get('/api/ads/slides', { params: { placement, lang: selectedLanguage.value?.code } }).then((res) => {
            slides.value = (Array.isArray(res.data?.slides) ? res.data.slides : []).filter((s) => s && s.image_url);
            interval.value = Number(res.data?.interval) || 6;
        }).catch(() => {
            slides.value = [];
        });
    };

    onMounted(load);
    watch(() => selectedLanguage.value?.code, (code, previous) => { if (previous && code !== previous) load(); });

    return { ad: computed(() => slides.value[0] ?? null), slides, interval };
}
