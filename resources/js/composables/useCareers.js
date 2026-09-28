import { ref } from 'vue';
import { useDocumentHead } from './useDocumentHead';

const careersListing = ref(null);
const listingLoading = ref(false);
const careerJob = ref(null);
const jobNotFound = ref(false);

function fetchCareers(filters = {}, langCode) {
    listingLoading.value = true;
    const params = { ...(langCode ? { lang: langCode } : {}) };
    Object.entries(filters).forEach(([key, value]) => {
        if (value) params[key] = value;
    });
    return window.axios.get('/api/careers', { params })
        .then((res) => {
            careersListing.value = res.data;
            useDocumentHead().setSeo(res.data.seo);
        })
        .catch(() => {
            careersListing.value = null;
        })
        .finally(() => {
            listingLoading.value = false;
        });
}

function fetchCareerJob(slug, langCode) {
    jobNotFound.value = false;
    return window.axios.get(`/api/careers/${slug}`, { params: langCode ? { lang: langCode } : {} })
        .then((res) => {
            careerJob.value = res.data;
            useDocumentHead().setSeo(res.data.job?.seo);
        })
        .catch(() => {
            careerJob.value = null;
            jobNotFound.value = true;
        });
}

/**
 * GET /api/careers (?department=&job_type=&location=, filtered server-side) and
 * GET /api/careers/{slug} -> Api\CareerController -> CareerPageService.
 * Applications post separately, from CareerApplyForm.vue.
 */
export function useCareers() {
    return { careersListing, listingLoading, fetchCareers, careerJob, jobNotFound, fetchCareerJob };
}
