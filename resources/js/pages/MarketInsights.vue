<script setup>
import WithAdSidebar from '../components/WithAdSidebar.vue';
import { computed, nextTick, onMounted, watch } from 'vue';
import { usePageWindow } from '../composables/usePageWindow';
import { useRoute, useRouter } from 'vue-router';
import InsightCard from '../components/InsightCard.vue';
import InsightTrend from '../components/InsightTrend.vue';
import { useMarketInsights } from '../composables/useMarketInsights';
import { useLanguages } from '../composables/useLanguages';
import { useStaticText } from '../composables/useStaticText';

const route = useRoute();
const router = useRouter();
const { insightsListing, listingLoading, fetchInsights } = useMarketInsights();
const { selectedLanguage } = useLanguages();
const { t } = useStaticText();

// Topic / region / page live in the URL (?topic=&region=&page=) so a filtered view can be shared,
// and all filtering + paging runs server-side (GET /api/market-insights).
const activeTopic = computed(() => String(route.query.topic || ''));
const activeRegion = computed(() => String(route.query.region || ''));
const currentPage = computed(() => Math.max(1, parseInt(route.query.page, 10) || 1));

function load() {
    fetchInsights({ page: currentPage.value, topic: activeTopic.value, region: activeRegion.value }, selectedLanguage.value?.code);
}

function setQuery(changes) {
    const query = { ...route.query, ...changes };
    Object.keys(query).forEach((key) => { if (!query[key] || (key === 'page' && Number(query[key]) === 1)) delete query[key]; });
    router.push({ query });
}

function selectTopic(key) {
    if (key !== activeTopic.value) setQuery({ topic: key, page: 1 });
}

function selectRegion(event) {
    setQuery({ region: event.target.value, page: 1 });
}

function goToPage(page) {
    if (page < 1 || page > (pagination.value?.last_page || 1) || page === currentPage.value) return;
    setQuery({ page });
    window.scrollTo({ top: document.getElementById('insights-grid')?.offsetTop - 140 || 0, behavior: 'smooth' });
}

onMounted(load);
watch(() => route.query, load);
watch(selectedLanguage, load);

// Cards use the legacy reveal-on-scroll (assets/js/script.js), which only scans the DOM once —
// re-run it whenever freshly fetched cards are rendered.
watch(insightsListing, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});

const title = computed(() => insightsListing.value?.title || t('market_insights.page_default_title', 'Market Insights'));
const heading = computed(() => insightsListing.value?.heading || t('market_insights.heading_default', 'Research & Reports'));
const description = computed(() => insightsListing.value?.description || '');
const topics = computed(() => insightsListing.value?.topics || []);
const regions = computed(() => insightsListing.value?.regions || []);
const featured = computed(() => insightsListing.value?.featured || null);
const posts = computed(() => insightsListing.value?.posts || []);
const pagination = computed(() => insightsListing.value?.pagination || null);
const pageNumbers = usePageWindow(currentPage, computed(() => pagination.value?.last_page));
const hasFilters = computed(() => Boolean(activeTopic.value || activeRegion.value));
</script>

<template>
    <main>
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ title }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" :aria-label="t('market_insights.breadcrumb_aria', 'Breadcrumb')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('market_insights.breadcrumb_home', 'Home') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ title }}</span></p>
                </div>
            </nav>
        </section>

        <section v-if="featured" class="mw-insights-featured">
            <div class="container-ctn">
                <article class="mw-insights-featured__card" data-reveal>
                    <router-link :to="`/market-insights/${featured.slug}`" class="mw-insights-featured__media" tabindex="-1" aria-hidden="true">
                        <img :src="featured.image_url" :alt="featured.image_alt || featured.title">
                        <span class="mw-insights-featured__badge">{{ t('market_insights.featured', 'Featured Report') }}</span>
                    </router-link>
                    <div class="mw-insights-featured__body">
                        <p class="mw-insights-featured__tags">
                            <span v-if="featured.topic_label" class="mw-insight-chip mw-insight-chip--solid">{{ featured.topic_label }}</span>
                            <span v-if="featured.region_label" class="mw-insights-featured__region">{{ featured.region_label }}</span>
                        </p>
                        <h2 class="mw-insights-featured__title"><router-link :to="`/market-insights/${featured.slug}`">{{ featured.title }}</router-link></h2>
                        <p class="mw-insights-featured__summary">{{ featured.summary }}</p>
                        <p class="mw-insights-featured__meta">{{ [featured.author_name, featured.published_at, featured.read_time].filter(Boolean).join(' · ') }}</p>
                        <router-link :to="`/market-insights/${featured.slug}`" class="mw-btn mw-btn--gradient mw-insights-featured__cta">
                            {{ t('market_insights.read_report', 'Read the report') }}
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </router-link>
                        <div v-if="featured.lead_stat" class="mw-insights-featured__stat">
                            <InsightTrend :trend="featured.lead_stat.trend" />
                            <strong>{{ featured.lead_stat.value }}</strong>
                            <span>{{ featured.lead_stat.label }}</span>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section id="insights-grid" class="mw-insights-list">
            <div class="container-ctn">
                <div class="mw-insights-list__head">
                    <div>
                        <h2 class="mw-insights-list__title" data-reveal>{{ heading }}</h2>
                        <p v-if="description" class="mw-insights-list__text">{{ description }}</p>
                    </div>
                    <label v-if="regions.length > 1" class="mw-insights-region">
                        <span>{{ t('market_insights.region_label', 'Market') }}</span>
                        <select :value="activeRegion" @change="selectRegion">
                            <option value="">{{ t('market_insights.all_regions', 'All markets') }}</option>
                            <option v-for="region in regions" :key="region.key" :value="region.key">{{ region.label }}</option>
                        </select>
                    </label>
                </div>

                <div v-if="topics.length" class="mw-insights-topics" role="group" :aria-label="t('market_insights.topics_aria', 'Filter by topic')">
                    <button type="button" class="mw-insights-topics__item" :class="{ 'is-active': !activeTopic }" :aria-pressed="!activeTopic" @click="selectTopic('')">{{ t('market_insights.all_topics', 'All topics') }}</button>
                    <button v-for="topic in topics" :key="topic.key" type="button" class="mw-insights-topics__item" :class="{ 'is-active': activeTopic === topic.key }" :aria-pressed="activeTopic === topic.key" @click="selectTopic(topic.key)">{{ topic.label }}</button>
                </div>

                <WithAdSidebar placement="market-insights">
                <div class="mw-insights-grid" :class="{ 'is-loading': listingLoading }">
                    <InsightCard v-for="(post, index) in posts" :key="post.slug" :insight="post" data-reveal :style="{ '--reveal-delay': index % 3 }" />
                </div>

                <div v-if="insightsListing && !posts.length && !featured" class="mw-insights-empty">
                    <h3>{{ t('market_insights.empty_title', 'No insights found') }}</h3>
                    <p>{{ hasFilters ? t('market_insights.empty_filtered', 'Nothing matches these filters yet — try another topic or market.') : t('market_insights.empty_text', 'New research is on its way. Check back soon.') }}</p>
                    <button v-if="hasFilters" type="button" class="mw-btn mw-btn--outline" @click="router.push({ query: {} })">{{ t('market_insights.clear_filters', 'Clear filters') }}</button>
                </div>

                <nav v-if="pagination && pagination.last_page > 1" class="mw-blog__pagination" :aria-label="t('market_insights.pagination_aria', 'Market insights pagination')">
                    <button type="button" class="mw-blog__page mw-blog__page--prev" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">{{ t('blogs.previous') }}</button>
                    <button v-for="page in pageNumbers" :key="page" type="button" class="mw-blog__page" :class="{ 'is-active': page === currentPage }" @click="goToPage(page)">{{ page }}</button>
                    <button type="button" class="mw-blog__page mw-blog__page--next" :disabled="currentPage === pagination.last_page" @click="goToPage(currentPage + 1)">{{ t('blogs.next') }}</button>
                </nav>
                </WithAdSidebar>
            </div>
        </section>
    </main>
</template>
