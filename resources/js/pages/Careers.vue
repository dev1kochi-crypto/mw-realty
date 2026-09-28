<script setup>
import { computed, nextTick, onMounted, reactive, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import CareerApplyForm from '../components/CareerApplyForm.vue';
import { useCareers } from '../composables/useCareers';
import { useLanguages } from '../composables/useLanguages';
import { useStaticText } from '../composables/useStaticText';

const route = useRoute();
const router = useRouter();
const { careersListing, listingLoading, fetchCareers } = useCareers();
const { selectedLanguage } = useLanguages();
const { t } = useStaticText();

const FILTER_KEYS = ['department', 'job_type', 'location'];
const filters = reactive(Object.fromEntries(FILTER_KEYS.map((key) => [key, route.query[key] ? String(route.query[key]) : ''])));

function load() {
    fetchCareers({ ...filters }, selectedLanguage.value?.code);
}

// Filters live in the URL too, so a filtered list can be shared or bookmarked — filtering itself
// runs server-side (GET /api/careers?department=…), not over an already-fetched list.
function applyFilters() {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value));
    router.replace({ query, hash: route.hash });
    load();
}

function clearFilters() {
    FILTER_KEYS.forEach((key) => { filters[key] = ''; });
    applyFilters();
}

onMounted(load);
watch(selectedLanguage, load);

// Cards/perks use the legacy reveal-on-scroll (assets/js/script.js), which only scans the DOM
// once — re-run it whenever fetched content is rendered (see the same note in Blogs.vue).
watch(careersListing, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});

const title = computed(() => careersListing.value?.title || t('careers.page_default_title', 'Careers'));
const description = computed(() => careersListing.value?.description || t('careers.intro_default', 'Join a team of property specialists helping people buy, rent and invest across the UAE. We look for curious, driven people who put clients first — and we give them the tools, training and support to grow.'));
const bannerUrl = computed(() => careersListing.value?.banner_url || '/frontend/assets/images/about/diversity.jpg');
const bannerAlt = computed(() => careersListing.value?.banner_alt || title.value);
const jobs = computed(() => careersListing.value?.jobs || []);
const filterOptions = computed(() => careersListing.value?.filters || { department: [], job_type: [], location: [] });
const totalOpenings = computed(() => careersListing.value?.total_openings ?? 0);
const departmentCount = computed(() => filterOptions.value.department.length);
const hasActiveFilters = computed(() => FILTER_KEYS.some((key) => filters[key]));

const perks = computed(() => [
    { icon: 'growth', title: t('careers.perks.growth_title', 'Career Growth'), text: t('careers.perks.growth_text', 'Clear progression paths, mentoring from senior consultants and ongoing RERA-aligned training.') },
    { icon: 'reward', title: t('careers.perks.reward_title', 'Rewarding Pay'), text: t('careers.perks.reward_text', 'Competitive packages and transparent commission structures that reward real results.') },
    { icon: 'tools', title: t('careers.perks.tools_title', 'Modern Tools'), text: t('careers.perks.tools_text', 'A built-in CRM, verified listings and marketing support so you can focus on your clients.') },
    { icon: 'team', title: t('careers.perks.team_title', 'Supportive Team'), text: t('careers.perks.team_text', 'A collaborative, multicultural team in the heart of Business Bay, Dubai.') },
]);

function jobMeta(job) {
    return [job.location, job.job_type_label, job.base_label].filter(Boolean);
}
</script>

<template>
    <main>
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ title }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" :aria-label="t('careers.breadcrumb_aria', 'Breadcrumb')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('careers.breadcrumb_home', 'Home') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ title }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-careers-intro">
            <div class="container-ctn">
                <div class="mw-careers-intro__layout">
                    <div class="mw-careers-intro__copy" data-reveal="left">
                        <p class="mw-careers-eyebrow">{{ t('careers.eyebrow', 'Work With Us') }}</p>
                        <h2 class="mw-about-title mw-careers-intro__title">{{ t('careers.intro_title', 'Build Your Future in UAE Real Estate') }}</h2>
                        <p class="mw-careers-intro__text">{{ description }}</p>
                        <div class="mw-careers-intro__stats">
                            <div class="mw-careers-stat">
                                <span class="mw-careers-stat__value">{{ totalOpenings }}</span>
                                <span class="mw-careers-stat__label">{{ t('careers.stats.openings', 'Open Positions') }}</span>
                            </div>
                            <div class="mw-careers-stat">
                                <span class="mw-careers-stat__value">{{ departmentCount }}</span>
                                <span class="mw-careers-stat__label">{{ t('careers.stats.departments', 'Teams Hiring') }}</span>
                            </div>
                        </div>
                        <div class="mw-careers-intro__actions">
                            <a href="#openings" class="mw-btn mw-btn--gradient">{{ t('careers.view_openings', 'View Open Roles') }}</a>
                            <a href="#apply" class="mw-btn mw-btn--outline">{{ t('careers.send_cv', 'Send Your CV') }}</a>
                        </div>
                    </div>
                    <div class="mw-careers-intro__media" data-reveal="right">
                        <img :src="bannerUrl" :alt="bannerAlt" loading="lazy">
                        <div class="mw-careers-intro__badge">
                            <strong>{{ t('careers.badge_title', 'We\'re Hiring') }}</strong>
                            <span>{{ t('careers.badge_text', 'Business Bay, Dubai') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-careers-perks">
            <div class="container-ctn">
                <div class="mw-careers-section-head">
                    <p class="mw-careers-eyebrow">{{ t('careers.perks_eyebrow', 'Why MW Realty') }}</p>
                    <h2 class="mw-about-title mw-careers-section-head__title" data-reveal>{{ t('careers.perks_title', 'More Than Just a Job') }}</h2>
                </div>
                <div class="mw-careers-perks__grid">
                    <article v-for="(perk, index) in perks" :key="perk.icon" class="mw-careers-perk" data-reveal :style="{ '--reveal-delay': index }">
                        <span class="mw-careers-perk__icon" aria-hidden="true">
                            <svg v-if="perk.icon === 'growth'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/></svg>
                            <svg v-else-if="perk.icon === 'reward'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="9" r="6"/><path d="M8.5 14 7 22l5-3 5 3-1.5-8"/></svg>
                            <svg v-else-if="perk.icon === 'tools'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                            <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.2a5 5 0 0 1 5.5 4.8"/></svg>
                        </span>
                        <h3 class="mw-careers-perk__title">{{ perk.title }}</h3>
                        <p class="mw-careers-perk__text">{{ perk.text }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="openings" class="mw-careers-openings">
            <div class="container-ctn">
                <div class="mw-careers-section-head mw-careers-section-head--split">
                    <div>
                        <p class="mw-careers-eyebrow">{{ t('careers.openings_eyebrow', 'Current Openings') }}</p>
                        <h2 class="mw-about-title mw-careers-section-head__title" data-reveal>{{ t('careers.openings_title', 'Find Your Next Role') }}</h2>
                    </div>
                    <form class="mw-careers-filters" @submit.prevent="applyFilters">
                        <label class="mw-careers-filters__field">
                            <span>{{ t('careers.filters.department', 'Department') }}</span>
                            <select v-model="filters.department" @change="applyFilters">
                                <option value="">{{ t('careers.filters.all_departments', 'All Departments') }}</option>
                                <option v-for="opt in filterOptions.department" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </label>
                        <label class="mw-careers-filters__field">
                            <span>{{ t('careers.filters.job_type', 'Job Type') }}</span>
                            <select v-model="filters.job_type" @change="applyFilters">
                                <option value="">{{ t('careers.filters.all_types', 'All Types') }}</option>
                                <option v-for="opt in filterOptions.job_type" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </label>
                        <label class="mw-careers-filters__field">
                            <span>{{ t('careers.filters.location', 'Location') }}</span>
                            <select v-model="filters.location" @change="applyFilters">
                                <option value="">{{ t('careers.filters.all_locations', 'All Locations') }}</option>
                                <option v-for="opt in filterOptions.location" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </label>
                        <button v-if="hasActiveFilters" type="button" class="mw-careers-filters__clear" @click="clearFilters">{{ t('careers.filters.clear', 'Clear') }}</button>
                    </form>
                </div>

                <div class="mw-careers-jobs" :class="{ 'is-loading': listingLoading }">
                    <article v-for="(job, index) in jobs" :key="job.slug" class="mw-career-card" data-reveal :style="{ '--reveal-delay': index % 3 }">
                        <div class="mw-career-card__main">
                            <span v-if="job.department_label" class="mw-career-card__tag">{{ job.department_label }}</span>
                            <h3 class="mw-career-card__title"><router-link :to="`/careers/${job.slug}`">{{ job.title }}</router-link></h3>
                            <p v-if="job.short_description" class="mw-career-card__text">{{ job.short_description }}</p>
                            <ul class="mw-career-card__meta">
                                <li v-for="item in jobMeta(job)" :key="item">{{ item }}</li>
                            </ul>
                        </div>
                        <div class="mw-career-card__side">
                            <span v-if="job.published_at" class="mw-career-card__date">{{ t('careers.posted', 'Posted') }} {{ job.published_at }}</span>
                            <router-link :to="`/careers/${job.slug}`" class="mw-btn mw-btn--outline mw-career-card__cta">
                                {{ t('careers.view_apply', 'View & Apply') }}
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </router-link>
                        </div>
                    </article>

                    <div v-if="careersListing && !jobs.length" class="mw-careers-empty">
                        <h3>{{ hasActiveFilters ? t('careers.empty_filtered_title', 'No roles match these filters') : t('careers.empty_title', 'No open positions right now') }}</h3>
                        <p>{{ t('careers.empty_text', 'We\'re always happy to meet talented people. Send us your CV and we\'ll reach out when a suitable role opens up.') }}</p>
                        <div class="mw-careers-empty__actions">
                            <button v-if="hasActiveFilters" type="button" class="mw-btn mw-btn--outline" @click="clearFilters">{{ t('careers.filters.clear_all', 'Clear Filters') }}</button>
                            <a href="#apply" class="mw-btn mw-btn--gradient">{{ t('careers.send_cv', 'Send Your CV') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="apply" class="mw-careers-apply">
            <div class="container-ctn">
                <div class="mw-careers-apply__layout">
                    <div class="mw-careers-apply__copy" data-reveal="left">
                        <p class="mw-careers-eyebrow mw-careers-eyebrow--light">{{ t('careers.apply_eyebrow', 'General Application') }}</p>
                        <h2 class="mw-about-title mw-careers-apply__title">{{ t('careers.apply_title', 'Don\'t See the Right Role?') }}</h2>
                        <p class="mw-careers-apply__text">{{ t('careers.apply_text', 'Send us your CV anyway. Our HR team reviews every application and will contact you as soon as a role that matches your experience becomes available.') }}</p>
                        <ul class="mw-careers-apply__steps">
                            <li><span>1</span>{{ t('careers.apply_step_1', 'Submit your details and CV') }}</li>
                            <li><span>2</span>{{ t('careers.apply_step_2', 'Our HR team reviews your profile') }}</li>
                            <li><span>3</span>{{ t('careers.apply_step_3', 'We get in touch for an interview') }}</li>
                        </ul>
                    </div>
                    <div class="mw-careers-apply__card" data-reveal="right">
                        <CareerApplyForm id-prefix="general-apply" />
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
