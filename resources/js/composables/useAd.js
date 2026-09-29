import { onMounted, ref, watch } from 'vue';
import { useLanguages } from './useLanguages';

/**
 * Fetches a running ad for a placement (a page name, matching Admin › Ads' Placement dropdown) —
 * e.g. useAd('home'). When several ads run on a page one is picked at random each time, so they
 * take turns. Resolves to null when none is running, so the page hides its ad block entirely.
 * Re-fetches when the site language changes (the ad's overlay text is per language).
 */
export function useAd(placement) {
    const ad = ref(null);
    const { selectedLanguage } = useLanguages();

    const load = () => {
        window.axios.get('/api/ads', { params: { placement, lang: selectedLanguage.value?.code } }).then((res) => {
            ad.value = res.data || null;
        }).catch(() => {
            ad.value = null;
        });
    };

    onMounted(load);
    watch(() => selectedLanguage.value?.code, (code, previous) => { if (previous && code !== previous) load(); });

    return { ad };
}
