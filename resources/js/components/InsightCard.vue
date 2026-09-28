<script setup>
import InsightTrend from './InsightTrend.vue';
import { useStaticText } from '../composables/useStaticText';

// One Market Insights post as a grid card — used on the listing and in "Related Insights".
defineProps({
    insight: { type: Object, required: true },
});

const { t } = useStaticText();
</script>

<template>
    <article class="mw-insight-card">
        <router-link :to="`/market-insights/${insight.slug}`" class="mw-insight-card__media" tabindex="-1" aria-hidden="true">
            <img :src="insight.image_url" :alt="insight.image_alt || insight.title" loading="lazy">
            <span v-if="insight.topic_label" class="mw-insight-chip">{{ insight.topic_label }}</span>
            <span v-if="insight.lead_stat" class="mw-insight-card__stat">
                <strong>{{ insight.lead_stat.value }}</strong>
                <InsightTrend :trend="insight.lead_stat.trend" />
                <small>{{ insight.lead_stat.label }}</small>
            </span>
        </router-link>
        <div class="mw-insight-card__body">
            <p class="mw-insight-card__meta">
                <span v-if="insight.region_label" class="mw-insight-card__region">{{ insight.region_label }}</span>
                <span>{{ insight.published_at }}</span>
            </p>
            <h3 class="mw-insight-card__title"><router-link :to="`/market-insights/${insight.slug}`">{{ insight.title }}</router-link></h3>
            <p class="mw-insight-card__summary">{{ insight.summary }}</p>
            <div class="mw-insight-card__foot">
                <span class="mw-insight-card__read">
                    {{ insight.read_time }}
                    <span v-if="insight.has_report" class="mw-insight-card__pdf">PDF</span>
                </span>
                <router-link :to="`/market-insights/${insight.slug}`" class="mw-insight-card__link">
                    {{ t('market_insights.read_insight', 'Read insight') }}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </router-link>
            </div>
        </div>
    </article>
</template>
