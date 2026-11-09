<template>
    <div ref="root" class="pi-chart" @mouseleave="hover = null">
        <svg :viewBox="`0 0 ${W} ${H}`" :width="W" :height="H" role="img" :aria-label="t('property_details.demand.trend_aria', 'Searches, views and enquiries by month')">
            <g v-for="tick in ticks" :key="tick">
                <line class="pi-chart__grid" :x1="PAD_L" :x2="W - PAD_R" :y1="y(tick)" :y2="y(tick)" />
                <text class="pi-chart__axis" :x="PAD_L - 8" :y="y(tick) + 4" text-anchor="end">{{ tick }}</text>
            </g>
            <g v-for="(month, i) in months" :key="month.month">
                <rect v-for="(series, s) in SERIES" :key="series.key" class="md-bar" :fill="series.color"
                    :x="groupX(i) + s * (barW + 2)" :y="y(month[series.key])" :width="barW" :height="Math.max(0, y(0) - y(month[series.key]))" rx="2" />
                <text class="pi-chart__axis" :x="groupX(i) + groupW / 2" :y="H - 6" text-anchor="middle">{{ monthLabel(month.month) }}</text>
                <rect class="pi-chart__hit" :x="PAD_L + i * slot" :y="PAD_T" :width="slot" :height="H - PAD_T - PAD_B"
                    tabindex="0" @mouseenter="hover = i" @focus="hover = i" @blur="hover = null" />
            </g>
        </svg>

        <div v-if="hover !== null" class="pi-chart__tip" :style="tipStyle">
            <strong>{{ monthLabel(months[hover].month, true) }}</strong>
            <p v-for="series in SERIES" :key="series.key">
                <i :style="{ background: series.color, height: '8px' }"></i>
                <span>{{ series.label }}</span>
                <b>{{ months[hover][series.key] }}</b>
            </p>
        </div>

        <ul class="pi-legend">
            <li v-for="series in SERIES" :key="series.key"><span class="md-legend-key" :style="{ background: series.color }"></span>{{ series.label }}</li>
        </ul>

        <table class="sr-only">
            <caption>{{ t('property_details.demand.trend_aria', 'Searches, views and enquiries by month') }}</caption>
            <thead><tr><th scope="col">{{ t('property_details.demand.month', 'Month') }}</th><th v-for="series in SERIES" :key="series.key" scope="col">{{ series.label }}</th></tr></thead>
            <tbody>
                <tr v-for="month in months" :key="month.month">
                    <th scope="row">{{ monthLabel(month.month, true) }}</th>
                    <td v-for="series in SERIES" :key="series.key">{{ month[series.key] }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useStaticText } from '../../composables/useStaticText';

/**
 * Buyer Demand › demand over time: searches, listing views and enquiries (incl. favourites) per
 * month, as grouped bars. `months` from MarketDemandService::trend. Drawn at the container's width.
 */
const props = defineProps({
    months: { type: Array, required: true },
});

const { t } = useStaticText();

const root = ref(null);
const W = ref(640);
let resizeObserver = null;
onMounted(() => {
    const measure = () => { W.value = Math.max(280, Math.round(root.value?.clientWidth || 640)); };
    measure();
    if ('ResizeObserver' in window) { resizeObserver = new ResizeObserver(measure); resizeObserver.observe(root.value); }
});
onBeforeUnmount(() => resizeObserver?.disconnect());

const H = computed(() => (W.value < 520 ? 190 : 220));
const PAD_L = 40;
const PAD_R = 12;
const PAD_T = 12;
const PAD_B = 28;

const SERIES = computed(() => [
    { key: 'searches', color: '#244373', label: t('property_details.demand.series_searches', 'Searches') },
    { key: 'views', color: '#7fa2d9', label: t('property_details.demand.series_views', 'Listing views') },
    { key: 'enquiries', color: '#1f9d6b', label: t('property_details.demand.series_enquiries', 'Enquiries & favourites') },
]);

const hover = ref(null);
const max = computed(() => Math.max(1, ...props.months.flatMap((m) => [m.searches, m.views, m.enquiries])));
// Whole-number ticks on a round step.
const ticks = computed(() => {
    const raw = max.value / 4;
    const mag = 10 ** Math.floor(Math.log10(Math.max(raw, 1)));
    const step = Math.max(1, [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw));
    const out = [];
    for (let v = 0; v <= max.value + step / 2; v += step) out.push(Math.round(v));
    return [...new Set(out)];
});
const top = computed(() => ticks.value[ticks.value.length - 1] || 1);
const slot = computed(() => (W.value - PAD_L - PAD_R) / Math.max(1, props.months.length));
const groupW = computed(() => Math.min(slot.value * 0.72, 66));
const barW = computed(() => (groupW.value - 4) / 3);
const groupX = (i) => PAD_L + i * slot.value + (slot.value - groupW.value) / 2;
const y = (v) => H.value - PAD_B - (v / top.value) * (H.value - PAD_T - PAD_B);

// The current month is only partly over — say so, or it reads as a drop.
const thisMonth = new Date().toISOString().slice(0, 7);
const monthLabel = (ym, long = false) => {
    const label = new Date(`${ym}-01T00:00:00`).toLocaleDateString(undefined, long ? { month: 'long', year: 'numeric' } : { month: 'short' });
    return ym === thisMonth ? t('property_details.demand.month_so_far', '{month} (so far)').replace('{month}', label) : label;
};

const tipStyle = computed(() => {
    const px = PAD_L + (hover.value + 0.5) * slot.value;
    const edge = 100;
    return { left: `${Math.min(Math.max(px, edge), W.value - edge)}px` };
});
</script>
