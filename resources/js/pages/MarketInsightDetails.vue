<script setup>
import AdBlock from '../components/AdBlock.vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import InsightCard from '../components/InsightCard.vue';
import InsightTrend from '../components/InsightTrend.vue';
import { useMarketInsights } from '../composables/useMarketInsights';
import { useLanguages } from '../composables/useLanguages';
import { useStaticText } from '../composables/useStaticText';

const route = useRoute();
const { insightPost, postNotFound, fetchInsight } = useMarketInsights();
const { selectedLanguage } = useLanguages();
const { t } = useStaticText();

function load() {
    fetchInsight(route.params.slug, selectedLanguage.value?.code);
}

onMounted(load);
watch(() => route.params.slug, (slug) => { if (slug) load(); });
watch(selectedLanguage, load);
watch(() => insightPost.value?.post?.title, (title) => {
    if (title) document.title = `${title} | Market Insights | MW Realty`;
});

// Gated behind v-if="post" — re-run the legacy reveal-on-scroll scan once it renders.
watch(insightPost, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});

// Only show the post the route asks for — never the previous one while the next is loading.
const post = computed(() => (insightPost.value?.post?.slug === route.params.slug ? insightPost.value.post : null));
const related = computed(() => insightPost.value?.related || []);
const topics = computed(() => insightPost.value?.topics || []);
const glance = computed(() => {
    if (!post.value) return [];
    return [
        { label: t('market_insight_details.glance_topic', 'Topic'), value: post.value.topic_label },
        { label: t('market_insight_details.glance_market', 'Market'), value: post.value.region_label },
        { label: t('market_insight_details.glance_published', 'Published'), value: post.value.published_at },
        { label: t('market_insight_details.glance_read', 'Reading time'), value: post.value.read_time },
        { label: t('market_insight_details.glance_author', 'Author'), value: post.value.author_name },
    ].filter((row) => row.value);
});
const initials = computed(() => (post.value?.author_name || '').split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase());

const pageUrl = computed(() => (post.value ? `${window.location.origin}/market-insights/${post.value.slug}` : ''));
const shareLinks = computed(() => {
    const url = encodeURIComponent(pageUrl.value);
    const text = encodeURIComponent(post.value?.title || '');
    return [
        { key: 'linkedin', label: 'LinkedIn', href: `https://www.linkedin.com/sharing/share-offsite/?url=${url}` },
        { key: 'x', label: 'X', href: `https://twitter.com/intent/tweet?url=${url}&text=${text}` },
        { key: 'whatsapp', label: 'WhatsApp', href: `https://wa.me/?text=${text}%20${url}` },
    ];
});

const copied = ref(false);
function copyLink() {
    navigator.clipboard?.writeText(pageUrl.value).then(() => {
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    });
}
</script>

<template>
    <main v-if="post">
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ post.title }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" :aria-label="t('market_insight_details.breadcrumb_aria', 'Breadcrumb')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('market_insights.breadcrumb_home', 'Home') }}</router-link><span class="mw-about-crumb__sep"> / </span><router-link to="/market-insights">{{ t('nav.market_insights', 'Market Insights') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ post.topic_label }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-insight-article">
            <div class="container-ctn">
                <div class="mw-insight-article__layout">
                    <article class="mw-insight-article__main">
                        <figure class="mw-insight-banner">
                            <img :src="post.detail_image_url" :alt="post.image_alt || post.title">
                            <figcaption class="mw-insight-banner__tags">
                                <router-link v-if="post.topic_label" :to="{ path: '/market-insights', query: { topic: post.topic } }" class="mw-insight-chip mw-insight-chip--solid">{{ post.topic_label }}</router-link>
                                <span v-if="post.region_label" class="mw-insight-chip">{{ post.region_label }}</span>
                            </figcaption>
                        </figure>

                        <div class="mw-insight-byline">
                            <span v-if="post.author_name" class="mw-insight-byline__author">
                                <span class="mw-insight-byline__avatar" aria-hidden="true">{{ initials }}</span>
                                <span>
                                    <strong>{{ post.author_name }}</strong>
                                    <small v-if="post.author_role">{{ post.author_role }}</small>
                                </span>
                            </span>
                            <span class="mw-insight-byline__item">{{ post.published_at_long }}</span>
                            <span class="mw-insight-byline__item">{{ post.read_time }}</span>
                        </div>

                        <div v-if="post.summary" class="mw-insight-lead">{{ post.summary }}</div>

                        <div v-if="post.stats.length" class="mw-insight-figures">
                            <div v-for="(stat, index) in post.stats" :key="index" class="mw-insight-figure" :class="`mw-insight-figure--${stat.trend}`">
                                <span class="mw-insight-figure__value">{{ stat.value }} <InsightTrend :trend="stat.trend" /></span>
                                <span class="mw-insight-figure__label">{{ stat.label }}</span>
                            </div>
                        </div>

                        <div v-if="post.takeaways.length" class="mw-insight-takeaways">
                            <h2 class="mw-insight-takeaways__title">{{ t('market_insight_details.takeaways_title', 'Key Takeaways') }}</h2>
                            <ul>
                                <li v-for="(item, index) in post.takeaways" :key="index">{{ item }}</li>
                            </ul>
                        </div>

                        <div class="mw-blog-details__content mw-insight-content" v-html="post.content"></div>

                        <div class="mw-insight-sharebar">
                            <span class="mw-insight-sharebar__label">{{ t('market_insight_details.share_title', 'Share this insight') }}</span>
                            <a v-for="link in shareLinks" :key="link.key" :href="link.href" target="_blank" rel="noopener" class="mw-insight-share__item">{{ link.label }}</a>
                            <button type="button" class="mw-insight-share__item" @click="copyLink">{{ copied ? t('market_insight_details.copied', 'Copied!') : t('market_insight_details.copy_link', 'Copy link') }}</button>
                        </div>
                    </article>

                    <aside class="mw-insight-article__aside">
                        <div class="mw-insight-panel mw-insight-glance">
                            <h3 class="mw-insight-panel__title">{{ t('market_insight_details.glance_title', 'At a glance') }}</h3>
                            <dl class="mw-insight-glance__list">
                                <div v-for="row in glance" :key="row.label" class="mw-insight-glance__row">
                                    <dt>{{ row.label }}</dt>
                                    <dd>{{ row.value }}</dd>
                                </div>
                            </dl>
                            <a v-if="post.report_url" :href="post.report_url" target="_blank" rel="noopener" class="mw-btn mw-btn--gradient mw-insight-panel__cta">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M5 20h14"/></svg>
                                {{ t('market_insight_details.download', 'Download full report') }}
                            </a>
                        </div>

                        <div v-if="topics.length" class="mw-insight-panel">
                            <h3 class="mw-insight-panel__title">{{ t('market_insight_details.topics_title', 'Explore topics') }}</h3>
                            <div class="mw-insight-panel__topics">
                                <router-link v-for="topic in topics" :key="topic.key" :to="{ path: '/market-insights', query: { topic: topic.key } }" class="mw-insight-chip" :class="{ 'mw-insight-chip--solid': topic.key === post.topic }">{{ topic.label }}</router-link>
                            </div>
                        </div>

                        <div class="mw-insight-panel mw-insight-panel--expert">
                            <h3 class="mw-insight-panel__title">{{ t('market_insight_details.advice_title', 'Talk to an expert') }}</h3>
                            <p class="mw-insight-panel__text">{{ t('market_insight_details.advice_text', 'Want to know what this means for your next purchase or investment? Our consultants can walk you through it.') }}</p>
                            <router-link to="/contact" class="mw-btn mw-btn--gradient mw-insight-panel__cta">{{ t('market_insight_details.advice_cta', 'Contact our team') }}</router-link>
                        </div>
                        <!-- Admin › Ads, placement "Market Insights" -->
                        <AdBlock placement="market-insights" variant="card" />
                    </aside>
                </div>
            </div>
        </section>

        <section v-if="related.length" class="mw-insights-list mw-insights-list--related">
            <div class="container-ctn">
                <div class="mw-insights-list__head">
                    <h2 class="mw-insights-list__title">{{ t('market_insight_details.related_title', 'Related Insights') }}</h2>
                    <router-link to="/market-insights" class="mw-insight-card__link">{{ t('market_insight_details.view_all', 'View all insights') }}</router-link>
                </div>
                <div class="mw-insights-grid">
                    <InsightCard v-for="item in related" :key="item.slug" :insight="item" />
                </div>
            </div>
        </section>
    </main>

    <main v-else-if="postNotFound">
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ t('market_insight_details.not_found.title', 'Insight Not Found') }}</h1>
                </div>
            </div>
        </section>
        <section class="mw-blog-details">
            <div class="container-ctn">
                <p>{{ t('market_insight_details.not_found.message', "This market insight doesn't exist or has been removed.") }} <router-link to="/market-insights">{{ t('market_insight_details.not_found.back_link', 'Back to Market Insights') }}</router-link></p>
            </div>
        </section>
    </main>
</template>
