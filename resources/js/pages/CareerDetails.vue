<script setup>
import { computed, nextTick, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import CareerApplyForm from '../components/CareerApplyForm.vue';
import { useCareers } from '../composables/useCareers';
import { useLanguages } from '../composables/useLanguages';
import { useStaticText } from '../composables/useStaticText';

const route = useRoute();
const { careerJob, jobNotFound, fetchCareerJob } = useCareers();
const { selectedLanguage } = useLanguages();
const { t } = useStaticText();

function load() {
    fetchCareerJob(route.params.slug, selectedLanguage.value?.code);
}

onMounted(load);
watch(() => route.params.slug, (slug) => { if (slug) load(); });
watch(selectedLanguage, load);
watch(
    () => careerJob.value?.job?.title,
    (title) => {
        if (title) document.title = `${title} | Careers | MW Realty`;
    },
);

// Gated behind v-if="job" — re-run the legacy reveal-on-scroll scan once it renders (see Blogs.vue).
watch(careerJob, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});

// Only show the vacancy the route asks for — never the previous one while the next is loading.
const job = computed(() => (careerJob.value?.job?.slug === route.params.slug ? careerJob.value.job : null));
const related = computed(() => careerJob.value?.related || []);

const sections = computed(() => {
    if (!job.value) return [];
    return [
        { key: 'about', title: t('career_details.about_title', 'About the Role'), html: job.value.about },
        { key: 'responsibilities', title: t('career_details.responsibilities_title', 'Key Responsibilities'), html: job.value.responsibilities },
        { key: 'requirements', title: t('career_details.requirements_title', 'Requirements'), html: job.value.requirements },
        { key: 'join', title: t('career_details.join_title', 'Why Join Our Team'), html: job.value.join_the_team },
    ].filter((section) => section.html && section.html.replace(/<[^>]*>/g, '').trim() !== '');
});

const summary = computed(() => {
    if (!job.value) return [];
    return [
        { label: t('career_details.summary.department', 'Department'), value: job.value.department_label },
        { label: t('career_details.summary.job_type', 'Job Type'), value: job.value.job_type_label },
        { label: t('career_details.summary.contract', 'Contract'), value: job.value.base_label },
        { label: t('career_details.summary.location', 'Location'), value: [job.value.location, job.value.country].filter(Boolean).join(', ') },
        { label: t('career_details.summary.posted', 'Posted'), value: job.value.published_at },
    ].filter((item) => item.value);
});

function scrollToApply() {
    document.getElementById('job-apply')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <main v-if="job">
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ job.title }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" :aria-label="t('career_details.breadcrumb_aria', 'Breadcrumb')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('career_details.breadcrumb_home', 'Home') }}</router-link><span class="mw-about-crumb__sep"> / </span><router-link to="/careers">{{ t('career_details.breadcrumb_careers', 'Careers') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ job.title }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-career-details">
            <div class="container-ctn">
                <div class="mw-blog-details__layout">
                    <article class="mw-blog-details__main">
                        <span v-if="job.department_label" class="mw-blog-details__tag">{{ job.department_label }}</span>
                        <p v-if="job.short_description" class="mw-career-details__lead">{{ job.short_description }}</p>
                        <ul class="mw-career-card__meta mw-career-details__chips">
                            <li v-if="job.location">{{ [job.location, job.country].filter(Boolean).join(', ') }}</li>
                            <li v-if="job.job_type_label">{{ job.job_type_label }}</li>
                            <li v-if="job.base_label">{{ job.base_label }}</li>
                        </ul>

                        <div v-for="section in sections" :key="section.key" class="mw-career-details__section">
                            <h2 class="mw-career-details__heading">{{ section.title }}</h2>
                            <div class="mw-blog-details__content" v-html="section.html"></div>
                        </div>

                        <div id="job-apply" class="mw-career-details__apply">
                            <h2 class="mw-career-details__heading">{{ t('career_details.apply_title', 'Apply for this Position') }}</h2>
                            <p class="mw-career-details__apply-text">{{ t('career_details.apply_text', 'Fill in your details and attach your CV — our HR team will get back to you shortly.') }}</p>
                            <CareerApplyForm :career="job.slug" id-prefix="job-apply" />
                        </div>
                    </article>

                    <aside class="mw-blog-details__aside">
                        <div class="mw-blog-details__aside-panel mw-career-summary">
                            <h3 class="mw-blog-details__aside-title">{{ t('career_details.summary_title', 'Job Overview') }}</h3>
                            <dl class="mw-career-summary__list">
                                <div v-for="item in summary" :key="item.label" class="mw-career-summary__row">
                                    <dt>{{ item.label }}</dt>
                                    <dd>{{ item.value }}</dd>
                                </div>
                            </dl>
                            <button type="button" class="mw-btn mw-btn--gradient mw-career-summary__cta" @click="scrollToApply">{{ t('career_details.apply_now', 'Apply Now') }}</button>
                        </div>

                        <div v-if="related.length" class="mw-blog-details__aside-panel">
                            <h3 class="mw-blog-details__aside-title">{{ t('career_details.related_title', 'Other Openings') }}</h3>
                            <div class="mw-career-related">
                                <router-link v-for="other in related" :key="other.slug" :to="`/careers/${other.slug}`" class="mw-career-related__item">
                                    <span class="mw-career-related__title">{{ other.title }}</span>
                                    <span class="mw-career-related__meta">{{ [other.department_label, other.location].filter(Boolean).join(' · ') }}</span>
                                </router-link>
                            </div>
                            <router-link to="/careers#openings" class="mw-career-related__all">{{ t('career_details.view_all', 'View all openings') }}</router-link>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </main>

    <main v-else-if="jobNotFound">
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ t('career_details.not_found.title', 'Position Not Available') }}</h1>
                </div>
            </div>
        </section>
        <section class="mw-blog-details">
            <div class="container-ctn">
                <p>{{ t('career_details.not_found.message', 'This role has been filled or is no longer open.') }} <router-link to="/careers">{{ t('career_details.not_found.back_link', 'See current openings') }}</router-link></p>
            </div>
        </section>
    </main>
</template>
