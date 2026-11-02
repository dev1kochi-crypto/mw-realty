<template>
    <div ref="root" class="pi-chart" @mouseleave="hover = null">
        <svg :viewBox="`0 0 ${W} ${H}`" :width="W" :height="H" role="img" :aria-label="t('property_details.market.trend_aria', 'Median price per sq.ft by quarter')">
            <g v-for="tick in scale.ticks" :key="tick">
                <line class="pi-chart__grid" :x1="PAD_L" :x2="W - PAD_R" :y1="y(tick)" :y2="y(tick)" />
                <text class="pi-chart__axis" :x="PAD_L - 8" :y="y(tick) + 4" text-anchor="end">{{ compact(tick) }}</text>
            </g>
            <template v-for="(point, i) in points" :key="point.label">
                <text v-if="i % labelEvery === 0 || i === points.length - 1" class="pi-chart__axis" :x="x(i)" :y="H - 6" text-anchor="middle">{{ axisLabel(point.label) }}</text>
            </template>

            <line v-if="hover !== null" class="pi-chart__cross" :x1="x(hover)" :x2="x(hover)" :y1="PAD_T" :y2="H - PAD_B" />

            <template v-for="series in SERIES" :key="series.key">
                <path v-if="paths[series.key]" class="pi-chart__line" :class="`pi-chart__line--${series.key}`" :d="paths[series.key]" />
                <template v-for="(point, i) in points" :key="`${series.key}-${i}`">
                    <circle v-if="point[`${series.key}_psf`] !== null" class="pi-chart__dot" :class="`pi-chart__dot--${series.key}`"
                        :cx="x(i)" :cy="y(point[`${series.key}_psf`])" :r="hover === i ? 5.5 : 4" />
                </template>
            </template>
            <!-- Direct label on each series' latest point, nudged apart when they'd overlap. -->
            <text v-for="label in endLabels" :key="label.key" class="pi-chart__label" :x="label.x" :y="label.y">{{ label.text }}</text>

            <rect v-for="(point, i) in points" :key="`hit-${i}`" class="pi-chart__hit" :x="x(i) - step / 2" :y="PAD_T" :width="step" :height="H - PAD_T - PAD_B"
                tabindex="0" @mouseenter="hover = i" @focus="hover = i" @blur="hover = null" />
        </svg>

        <div v-if="hover !== null" class="pi-chart__tip" :style="tipStyle">
            <strong>{{ points[hover].label }}</strong>
            <p v-for="series in SERIES" :key="series.key">
                <i :style="{ background: series.color }"></i>
                <span>{{ series.short }}</span>
                <b>{{ points[hover][`${series.key}_psf`] !== null ? formatPrice(points[hover][`${series.key}_psf`], '—') : '—' }}</b>
                <small v-if="points[hover][`${series.key}_count`]">({{ points[hover][`${series.key}_count`] }})</small>
            </p>
        </div>

        <ul class="pi-legend">
            <li v-for="series in SERIES" :key="series.key"><span class="pi-legend__key" :class="`pi-legend__key--${series.key}`"></span>{{ series.label }}</li>
        </ul>

        <table class="sr-only">
            <caption>{{ t('property_details.market.trend_aria', 'Median price per sq.ft by quarter') }}</caption>
            <thead><tr><th scope="col">{{ t('property_details.market.quarter', 'Quarter') }}</th><th v-for="series in SERIES" :key="series.key" scope="col">{{ series.label }}</th></tr></thead>
            <tbody>
                <tr v-for="point in points" :key="point.label">
                    <th scope="row">{{ point.label }}</th>
                    <td v-for="series in SERIES" :key="series.key">{{ point[`${series.key}_psf`] !== null ? formatPrice(point[`${series.key}_psf`], '—') : '—' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useCurrency } from '../../composables/useCurrency';
import { useStaticText } from '../../composables/useStaticText';

/**
 * Median price (or annual rent) per sq.ft per quarter: asking prices (dashed, an estimate) and
 * closed deals (solid, verified). `points` from PropertyInsightService::trend. Drawn at the
 * container's real width so the text stays readable on phones.
 */
const props = defineProps({
    points: { type: Array, required: true },
});

const { t } = useStaticText();
const { formatPrice, formatCompact } = useCurrency();

const root = ref(null);
const W = ref(640);
let resizeObserver = null;
onMounted(() => {
    const measure = () => { W.value = Math.max(280, Math.round(root.value?.clientWidth || 640)); };
    measure();
    if ('ResizeObserver' in window) { resizeObserver = new ResizeObserver(measure); resizeObserver.observe(root.value); }
});
onBeforeUnmount(() => resizeObserver?.disconnect());

const narrow = computed(() => W.value < 520);
const H = computed(() => (narrow.value ? 200 : 240));
const PAD_L = 48;
const PAD_R = 44;
const PAD_T = 12;
const PAD_B = 28;

const SERIES = computed(() => [
    { key: 'asking', color: '#3563b0', label: t('property_details.market.asking_series', 'Asking price (estimate)'), short: t('property_details.market.asking_short', 'Asking') },
    { key: 'verified', color: '#c92844', label: t('property_details.market.verified_series', 'Closed deals (verified)'), short: t('property_details.market.verified_short', 'Closed deals') },
]);

const hover = ref(null);
const values = computed(() => props.points.flatMap((p) => [p.asking_psf, p.verified_psf]).filter((v) => v !== null));

// A price trend reads from its own range, not from zero: fit the axis to the data on round steps.
const scale = computed(() => {
    const lo = Math.min(...values.value);
    const hi = Math.max(...values.value);
    const span = Math.max(hi - lo, hi * 0.08, 1);
    const raw = span / 4;
    const mag = 10 ** Math.floor(Math.log10(raw));
    const stepSize = [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw);
    const min = Math.max(0, Math.floor((lo - span * 0.1) / stepSize) * stepSize);
    const max = Math.ceil((hi + span * 0.1) / stepSize) * stepSize;
    const ticks = [];
    for (let v = min; v <= max + stepSize / 2; v += stepSize) ticks.push(Math.round(v * 100) / 100);
    return { min, max, ticks };
});
const step = computed(() => (W.value - PAD_L - PAD_R) / Math.max(1, props.points.length - 1));
const x = (i) => PAD_L + i * step.value;
const y = (v) => H.value - PAD_B - ((v - scale.value.min) / (scale.value.max - scale.value.min || 1)) * (H.value - PAD_T - PAD_B);
// Every quarter labelled when there's room; every other one on phones.
const labelEvery = computed(() => (step.value < 58 ? 2 : 1));
const axisLabel = (label) => (narrow.value ? label.replace(/ (\d\d)(\d\d)$/, " '$2") : label);

// A line through the quarters that have data; a gap in the data leaves a gap in the line.
const paths = computed(() => Object.fromEntries(['asking', 'verified'].map((key) => {
    let d = '';
    let pen = false;
    props.points.forEach((p, i) => {
        const v = p[`${key}_psf`];
        if (v === null) { pen = false; return; }
        d += `${pen ? 'L' : 'M'}${x(i).toFixed(1)},${y(v).toFixed(1)}`;
        pen = true;
    });
    return [key, d.includes('L') ? d : ''];
})));

const endLabels = computed(() => {
    const labels = ['asking', 'verified'].map((key) => {
        const i = props.points.map((p) => p[`${key}_psf`]).findLastIndex((v) => v !== null);
        if (i < 0) return null;
        const v = props.points[i][`${key}_psf`];
        return { key, x: x(i) + 8, y: y(v) + 4, text: compact(v) };
    }).filter(Boolean);
    if (labels.length === 2 && Math.abs(labels[0].y - labels[1].y) < 13) {
        const [upper, lower] = labels[0].y <= labels[1].y ? labels : [labels[1], labels[0]];
        const mid = (upper.y + lower.y) / 2;
        upper.y = mid - 6.5;
        lower.y = mid + 6.5;
    }
    return labels;
});

// Tooltip beside the hovered quarter, kept inside the chart at both ends.
const tipStyle = computed(() => {
    const px = x(hover.value);
    const edge = 100;
    return { left: `${Math.min(Math.max(px, edge), W.value - edge)}px` };
});

const compact = (aed) => formatCompact(aed, false);
</script>
