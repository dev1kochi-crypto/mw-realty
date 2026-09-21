import { ref } from 'vue';

const siteInformation = ref(null);

function fetchSiteInformation(langCode) {
    return window.axios.get('/api/site-information', { params: langCode ? { lang: langCode } : {} })
        .then((res) => {
            siteInformation.value = res.data;
        })
        .catch(() => {
            siteInformation.value = null;
        });
}

/** GET /api/site-information -> Api\SiteInformationController::index -> HomePageService::getFooterData. */
export function useSiteInformation() {
    return { siteInformation, fetchSiteInformation };
}
