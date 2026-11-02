<template>
    <span class="pi-source" :class="`pi-source--${source}`" :title="title">
        <svg v-if="source === 'verified'" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
        {{ text }}
    </span>
</template>

<script setup>
import { computed } from 'vue';
import { useStaticText } from '../../composables/useStaticText';

/**
 * Where a figure comes from, next to the figure:
 *   verified   — closed deals on the platform (title deed + DLD contract on file)
 *   asking     — listing prices: an estimate of the market, not what was paid
 *   assumption — the visitor's own input (or our starting guess they can change)
 */
const props = defineProps({
    source: { type: String, required: true },
    count: { type: Number, default: 0 },
});
const { t } = useStaticText();

const text = computed(() => {
    if (props.source === 'verified') return t('property_details.insights.source_verified', 'Verified · {n} deals').replace('{n}', props.count);
    if (props.source === 'asking') return t('property_details.insights.source_asking', 'Estimate · {n} listings').replace('{n}', props.count);
    return t('property_details.insights.source_assumption', 'Assumption');
});
const title = computed(() => ({
    verified: t('property_details.insights.source_verified_help', 'From deals closed on MW Realty with the title deed and DLD contract on file.'),
    asking: t('property_details.insights.source_asking_help', 'From asking prices of similar listings, not prices actually paid.'),
}[props.source] || t('property_details.insights.source_assumption_help', 'A starting value you can change.')));
</script>
