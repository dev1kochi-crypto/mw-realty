<template>
    <div class="mc">
        <div v-if="loading && !demand" class="pi-loading" aria-busy="true"><span></span><span></span><span></span></div>

        <template v-else-if="demand?.available">
            <!-- AI overview (or the key findings, worded by the page, when AI isn't available). -->
            <div class="mc__card md-overview">
                <div class="md-overview__head">
                    <span class="md-overview__tag" :class="{ 'is-ai': summary?.source === 'ai' }">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.9 5.6L19.5 9.5l-5.6 1.9L12 17l-1.9-5.6L4.5 9.5l5.6-1.9L12 2zM19 15l.9 2.6 2.6.9-2.6.9L19 22l-.9-2.6-2.6-.9 2.6-.9L19 15z"/></svg>
                        {{ summary?.source === 'ai' ? t('property_details.demand.ai_title', 'AI market overview') : t('property_details.demand.quick_title', 'Market overview') }}
                    </span>
                    <span class="md-overview__scope">{{ scopeText }}</span>
                </div>

                <div v-if="summaryLoading" class="md-overview__wait" aria-busy="true"><span></span><span></span></div>
                <template v-else-if="summary?.source === 'ai'">
                    <p class="md-overview__text">{{ summary.summary }}</p>
                    <ul v-if="summary.highlights?.length" class="md-overview__points">
                        <li v-for="(point, i) in summary.highlights" :key="i">{{ point }}</li>
                    </ul>
                </template>
                <ul v-else-if="findings.length" class="pi-signals md-overview__findings">
                    <li v-for="(item, i) in findings" :key="i">
                        <span class="pi-signals__icon" :class="`pi-signals__icon--${item.tone}`" aria-hidden="true">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <path v-if="item.tone === 'positive'" d="M3 17l6-6 4 4 8-8" />
                                <path v-else-if="item.tone === 'caution'" d="M3 7l6 6 4-4 8 8" />
                                <path v-else d="M5 12h14" />
                            </svg>
                        </span>
                        <span class="pi-signals__text">{{ item.text }}</span>
                    </li>
                </ul>
                <p v-else class="pi-empty">{{ t('property_details.demand.quiet', 'There hasn’t been enough activity in this area on MW Realty yet to describe demand. Check back soon.') }}</p>
                <p v-if="summary?.source === 'ai' && findings.length" class="md-overview__note">{{ t('property_details.demand.ai_note', 'Written by AI from the figures below.') }}</p>
            </div>

            <!-- Demand level for the listing's segment. -->
            <div class="md-level" :class="level ? `md-level--${level.key}` : 'md-level--none'">
                <div class="md-level__meter" aria-hidden="true">
                    <span v-for="n in 3" :key="n" :class="{ 'is-on': levelBars >= n }"></span>
                </div>
                <div>
                    <div class="md-level__label">{{ levelLabel }}</div>
                    <p class="md-level__note">{{ levelNote }}</p>
                </div>
            </div>

            <div class="pi-stats">
                <div class="pi-stat">
                    <span class="pi-stat__label">{{ t('property_details.demand.stat_searches', 'Searches, last {days} days').replace('{days}', demand.window_days) }}</span>
                    <span class="pi-stat__value">{{ countText(main.searches) }}</span>
                    <span v-if="main.change.searches !== null" class="md-change" :class="changeClass(main.change.searches)">{{ changeText(main.change.searches) }}</span>
                </div>
                <div class="pi-stat">
                    <span class="pi-stat__label">{{ t('property_details.demand.stat_views', 'Listing views') }}</span>
                    <span class="pi-stat__value">{{ countText(main.views) }}</span>
                    <span v-if="main.change.views !== null" class="md-change" :class="changeClass(main.change.views)">{{ changeText(main.change.views) }}</span>
                </div>
                <div class="pi-stat">
                    <span class="pi-stat__label">{{ t('property_details.demand.stat_enquiries', 'Enquiries') }}</span>
                    <span class="pi-stat__value">{{ countText(main.enquiries) }}</span>
                    <span v-if="main.change.enquiries !== null" class="md-change" :class="changeClass(main.change.enquiries)">{{ changeText(main.change.enquiries) }}</span>
                </div>
                <div class="pi-stat">
                    <span class="pi-stat__label">{{ t('property_details.demand.stat_listings', 'Similar listings available') }}</span>
                    <span class="pi-stat__value">{{ main.listings }}</span>
                    <span class="md-stat-sub">{{ segmentLabel }}</span>
                </div>
                <div v-if="pricing" class="pi-stat">
                    <span class="pi-stat__label">{{ isRent ? t('property_details.demand.stat_median_rent', 'Median asking rent (a year)') : t('property_details.demand.stat_median_price', 'Median asking price') }}</span>
                    <template v-if="pricing.median">
                        <span class="pi-stat__value">{{ formatCompact(pricing.median) }}</span>
                        <span v-if="pricing.vs_median_pct !== null" class="md-stat-sub" :class="pricing.vs_median_pct <= 0 ? 'is-good' : ''">{{ vsMedianText }}</span>
                        <span v-if="pricing.average" class="md-stat-sub">{{ t('property_details.demand.average', 'Average {price} · {n} listings').replace('{price}', formatCompact(pricing.average)).replace('{n}', pricing.count) }}</span>
                    </template>
                    <span v-else class="pi-stat__empty">{{ t('property_details.market.not_enough', 'Not enough data yet') }}</span>
                </div>
                <div v-if="pricing?.growth" class="pi-stat">
                    <span class="pi-stat__label">{{ t('property_details.market.growth', 'Price change, last 12 months') }}</span>
                    <span class="pi-stat__value" :class="pricing.growth.pct >= 0 ? 'is-good' : 'is-bad'">{{ pricing.growth.pct > 0 ? '+' : '' }}{{ pricing.growth.pct }}%</span>
                    <SourceTag :source="pricing.growth.source" :count="pricing.growth.count" />
                </div>
            </div>

            <!-- Where interest is vs what's listed. -->
            <h4 class="pi-subtitle">
                <span>{{ t('property_details.demand.split_title', 'Interest vs listings in {area}').replace('{area}', areaName) }}</span>
                <span class="mc__toggle md-switch" role="tablist">
                    <button type="button" role="tab" :aria-selected="split === 'price' ? 'true' : 'false'" :class="{ 'is-active': split === 'price' }" @click="split = 'price'">{{ t('property_details.demand.split_price', 'Price range') }}</button>
                    <button type="button" role="tab" :aria-selected="split === 'beds' ? 'true' : 'false'" :class="{ 'is-active': split === 'beds' }" @click="split = 'beds'">{{ t('property_details.demand.split_beds', 'Bedrooms') }}</button>
                </span>
            </h4>
            <template v-if="splitData.enough && splitItems.length">
                <ul class="md-split">
                    <li v-for="item in splitItems" :key="item.key" :class="{ 'is-focus': item.is_focus }">
                        <span class="md-split__label">
                            {{ split === 'price' ? bandText(item.range) : bedsText(item.key) }}
                            <small v-if="item.is_focus">{{ t('property_details.demand.this_listing', 'This listing') }}</small>
                        </span>
                        <span class="md-split__bars">
                            <span class="md-split__bar md-split__bar--demand" :style="{ width: `${item.demand_share}%` }" :title="`${item.demand_share}%`"></span>
                            <span class="md-split__bar md-split__bar--supply" :style="{ width: `${item.supply_share}%` }" :title="`${item.supply_share}%`"></span>
                        </span>
                        <span class="md-split__nums">{{ item.demand_share }}% <i>/</i> {{ item.supply_share }}%</span>
                        <span v-if="item.underserved" class="md-tag">{{ t('property_details.demand.underserved', 'Underserved') }}</span>
                        <span v-else class="md-tag md-tag--blank" aria-hidden="true"></span>
                    </li>
                </ul>
                <ul class="pi-legend">
                    <li><span class="md-legend-key" style="background:#244373"></span>{{ t('property_details.demand.share_interest', 'Share of interest (last {days} days)').replace('{days}', demand.share_days) }}</li>
                    <li><span class="md-legend-key" style="background:#cdd6e6"></span>{{ t('property_details.demand.share_listings', 'Share of live listings') }}</li>
                </ul>
            </template>
            <p v-else class="pi-empty">{{ t('property_details.demand.split_empty', 'Not enough activity in this area yet to compare interest with listings.') }}</p>

            <h4 class="pi-subtitle">{{ t('property_details.demand.trend_title', 'Interest over time') }}</h4>
            <DemandTrendChart v-if="trendActive" :months="demand.trend" />
            <p v-else class="pi-empty">{{ t('property_details.demand.trend_empty', 'No activity recorded in this area yet.') }}</p>

            <template v-if="demand.amenities.length">
                <h4 class="pi-subtitle">{{ t('property_details.demand.amenities_title', 'Most wanted amenities nearby') }}</h4>
                <ul class="md-amenities">
                    <li v-for="amenity in demand.amenities" :key="amenity.key" :class="{ 'is-has': amenity.has }">
                        <svg v-if="amenity.has" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
                        <span>{{ amenityLabel(amenity) }}</span>
                        <small>{{ amenityStats(amenity) }}</small>
                    </li>
                </ul>
                <p v-if="demand.amenities.some((a) => a.has)" class="md-amenities__note">{{ t('property_details.demand.amenities_has', '✓ This listing has it.') }}</p>
            </template>

            <p class="mc__disclaimer">{{ t('property_details.demand.disclaimer', 'Based on anonymised, aggregated activity on MW Realty — searches, saved searches, listing views, favourites and enquiries, counted once per visitor per day — compared with the listings live today. No individual is identified, and small numbers are hidden. It shows interest on this website only, not the whole market, and is not a valuation or forecast.') }}</p>
        </template>

        <p v-else-if="demand" class="pi-empty">{{ t('property_details.demand.no_area', 'Demand figures need the listing’s community or city.') }}</p>
        <p v-else class="pi-empty">{{ t('property_details.market.unavailable', 'Market data is not available right now.') }}</p>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useCurrency } from '../../composables/useCurrency';
import { useLanguages } from '../../composables/useLanguages';
import { useStaticText } from '../../composables/useStaticText';
import SourceTag from './SourceTag.vue';
import DemandTrendChart from './DemandTrendChart.vue';
import '../calculators/calculators.css';
import './insights.css';

/**
 * Property Details › Buyer Demand (GET /api/properties/{slug}/demand + /demand/summary): the AI
 * market overview, demand level for the listing's segment, activity and price benchmark tiles,
 * interest vs listings by price range / bedrooms, interest over time and most wanted amenities.
 */
const props = defineProps({
    demand: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    summary: { type: Object, default: null },
    summaryLoading: { type: Boolean, default: false },
});

const { t } = useStaticText();
const { formatCompact } = useCurrency();
const { selectedLanguage } = useLanguages();

const split = ref('price');
const lang = computed(() => selectedLanguage.value?.code || 'en');
const isRent = computed(() => props.demand?.purpose === 'rent');
const main = computed(() => props.demand?.segment || props.demand?.area_demand || {});
const level = computed(() => main.value.level || null);
const pricing = computed(() => props.demand?.pricing || null);
const areaName = computed(() => props.demand?.area?.name || '');
const info = computed(() => props.demand?.segment_info || {});
const typeLabel = computed(() => info.value.type_label?.[lang.value] || info.value.type_label?.en || '');
const bedsText = (beds) => {
    if (beds === 0) return t('property_details.demand.studio', 'Studio');
    const n = beds >= (props.demand?.max_bedrooms ?? 99) ? `${beds}+` : beds;
    return t('property_details.demand.beds', '{n} bed').replace('{n}', n);
};
const segmentLabel = computed(() => [info.value.beds !== null && info.value.beds !== undefined ? bedsText(info.value.beds) : null, typeLabel.value].filter(Boolean).join(' · '));
const scopeText = computed(() => [areaName.value, isRent.value ? t('property_details.demand.for_rent', 'For rent') : t('property_details.demand.for_sale', 'For sale')].filter(Boolean).join(' · '));

const bandText = (range) => {
    if (!range) return '—';
    const [from, to] = range;
    if (!from) return t('property_details.demand.band_under', 'Under {to}').replace('{to}', formatCompact(to));
    if (!to) return t('property_details.demand.band_over', '{from}+').replace('{from}', formatCompact(from));
    return `${formatCompact(from)} – ${formatCompact(to, false)}`;
};

const splitData = computed(() => (split.value === 'price' ? props.demand?.price_bands : props.demand?.bedrooms) || { enough: false, items: [] });
const splitItems = computed(() => splitData.value.items.filter((i) => i.demand_share > 0 || i.listings > 0));
const trendActive = computed(() => (props.demand?.trend || []).some((m) => m.searches || m.views || m.enquiries));

const levelBars = computed(() => ({ high: 3, medium: 2, low: 1 }[level.value?.key] || 0));
const levelLabel = computed(() => {
    if (!level.value) return t('property_details.demand.level_none', 'Demand not rated yet');
    const who = isRent.value ? 'tenant' : 'buyer';
    return {
        high: t(`property_details.demand.level_high_${who}`, isRent.value ? 'High tenant interest' : 'High buyer interest'),
        medium: t(`property_details.demand.level_medium_${who}`, isRent.value ? 'Moderate tenant interest' : 'Moderate buyer interest'),
        low: t(`property_details.demand.level_low_${who}`, isRent.value ? 'Low tenant interest' : 'Low buyer interest'),
    }[level.value.key];
});
const levelNote = computed(() => {
    if (!level.value) return t('property_details.demand.level_none_note', 'Rated once there is enough activity for {segment} here.').replace('{segment}', segmentLabel.value || areaName.value);
    const place = level.value.baseline || t('property_details.demand.platform', 'MW Realty');
    return t('property_details.demand.level_note', '{segment} in {area} get {ratio}× the interest per listing of similar listings across {place}, last {days} days.')
        .replace('{segment}', segmentLabel.value || t('property_details.demand.listings', 'Listings'))
        .replace('{area}', areaName.value).replace('{ratio}', level.value.ratio).replace('{place}', place).replace('{days}', props.demand.window_days);
});

const countText = (n) => (n === null ? t('property_details.demand.fewer_than', 'Fewer than {n}').replace('{n}', props.demand.fewer_than) : Number(n).toLocaleString());
const changeText = (pct) => t('property_details.demand.change', '{sign}{value}% vs previous {days} days')
    .replace('{sign}', pct > 0 ? '+' : pct < 0 ? '−' : '').replace('{value}', Math.abs(pct)).replace('{days}', props.demand.window_days);
const changeClass = (pct) => (pct > 0 ? 'is-up' : pct < 0 ? 'is-down' : '');
const vsMedianText = computed(() => {
    const pct = pricing.value.vs_median_pct;
    if (pct === 0) return t('property_details.demand.at_median', 'This listing: at the median');
    return (pct < 0 ? t('property_details.demand.below_median', 'This listing: {value}% below') : t('property_details.demand.above_median', 'This listing: {value}% above')).replace('{value}', Math.abs(pct));
});

const AMENITY_NAMES = { furnished: 'Furnished', parking: 'Parking' };
const amenityLabel = (a) => {
    if (a.label && typeof a.label === 'object') return a.label[lang.value] || a.label.en || Object.values(a.label)[0];
    return t(`property_details.demand.amenity_${a.key}`, AMENITY_NAMES[a.key] || String(a.key).replace(/[_-]+/g, ' ').replace(/^./, (c) => c.toUpperCase()));
};
const amenityStats = (a) => [
    a.searches ? t('property_details.demand.amenity_searches', '{n} searches').replace('{n}', a.searches) : null,
    a.views ? t('property_details.demand.amenity_views', '{n} views').replace('{n}', a.views) : null,
].filter(Boolean).join(' · ');

// Key findings (MarketDemandService::insightList), worded here so they follow the site language.
const FINDINGS = {
    segment_searches_up: 'Searches for {segment} in {area} are up {value}% on the previous {days} days',
    segment_searches_down: 'Searches for {segment} in {area} are down {value}% on the previous {days} days',
    segment_views_up: 'Views of {segment} listings in {area} are up {value}% on the previous {days} days',
    segment_views_down: 'Views of {segment} listings in {area} are down {value}% on the previous {days} days',
    area_searches_up: 'Searches in {area} are up {value}% on the previous {days} days',
    area_searches_down: 'Searches in {area} are down {value}% on the previous {days} days',
    area_views_up: 'Listing views in {area} are up {value}% on the previous {days} days',
    area_views_down: 'Listing views in {area} are down {value}% on the previous {days} days',
    level_high: 'Each listing like this one here gets {value}× the interest of similar listings across {place}',
    level_low: 'Each listing like this one here gets {value}× the interest of similar listings across {place}, so there is more choice',
    level_high_platform: 'Each listing like this one here gets {value}× the interest of similar listings on MW Realty',
    level_low_platform: 'Each listing like this one here gets {value}× the interest of similar listings on MW Realty, so there is more choice',
    enquiries_up: 'Enquiries are up {value}% on the previous {days} days',
    enquiries_down: 'Enquiries are down {value}% on the previous {days} days',
    own_band_underserved: 'This price range ({range}) draws more interest than there are listings for it',
    band_underserved: '{range} is underserved: {demand}% of interest but only {supply}% of listings',
    beds_underserved: '{beds} homes are underserved: {demand}% of interest but only {supply}% of listings',
    beds_searches_up: 'Searches for {beds} homes are up {value}%',
    top_amenity: 'Most wanted amenity here: {amenity}',
    top_amenity_has: 'Most wanted amenity here: {amenity}, and this listing has it',
    priced_below_segment: 'Priced {value}% below the median asking price of similar listings here',
    priced_above_segment: 'Priced {value}% above the median asking price of similar listings here',
};
const findings = computed(() => (props.demand?.insights || []).map((item) => {
    const key = item.key === 'top_amenity' && item.has ? 'top_amenity_has' : item.key;
    const text = t(`property_details.demand.finding.${key}`, FINDINGS[key] || key)
        .replace('{segment}', segmentLabel.value || typeLabel.value)
        .replace('{area}', areaName.value)
        .replace('{days}', props.demand.window_days)
        .replace('{value}', item.value ?? '')
        .replace('{place}', item.place ?? '')
        .replace('{range}', bandText(item.range))
        .replace('{demand}', item.demand ?? '')
        .replace('{supply}', item.supply ?? '')
        .replace('{beds}', item.beds !== undefined ? bedsText(item.beds) : '')
        .replace('{amenity}', item.amenity ? amenityLabel({ key: item.amenity, label: item.label }) : '');
    return { tone: item.tone, text };
}));
</script>

<style>
/* Buyer Demand — builds on insights.css (.pi-*) and calculators.css (.mc*). */
.md-overview { border-color: #dfe6f3; background: linear-gradient(180deg, #f7f9fd 0%, #fff 70%); }
.md-overview__head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 14px; margin-bottom: 12px; }
.md-overview__tag { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; background: #eef1f6; color: #244373; font-size: 12px; font-weight: 800; letter-spacing: 0.03em; text-transform: uppercase; }
.md-overview__tag.is-ai { background: linear-gradient(90deg, rgba(36, 67, 115, 0.12), rgba(31, 157, 107, 0.14)); }
.md-overview__scope { color: #6b7080; font-size: 13px; font-weight: 600; }
.md-overview__text { margin: 0; color: #35373c; font-size: 15px; line-height: 1.65; }
.md-overview__points { display: grid; gap: 6px; margin: 12px 0 0; padding-inline-start: 18px; color: #35373c; font-size: 14px; line-height: 1.5; }
.md-overview__points li::marker { color: #1f9d6b; }
.md-overview__findings { margin-top: 0; }
.md-overview__note { margin: 12px 0 0; color: #8a8f9c; font-size: 12px; }
.md-overview__wait { display: grid; gap: 8px; }
.md-overview__wait span { display: block; height: 14px; border-radius: 7px; background: linear-gradient(90deg, #f2f4f8 0%, #e8ecf3 50%, #f2f4f8 100%) 0 0 / 200% 100%; animation: pi-shimmer 1.4s ease-in-out infinite; }
.md-overview__wait span:last-child { width: 70%; }

.md-level { display: flex; align-items: center; gap: 16px; margin-top: clamp(16px, 1.4vw, 24px); padding: 16px 18px; border: 1px solid #e3e7f0; border-radius: 14px; background: #fff; }
.md-level__meter { display: flex; flex-shrink: 0; align-items: flex-end; gap: 4px; height: 34px; }
.md-level__meter span { width: 10px; border-radius: 3px; background: #e3e7f0; }
.md-level__meter span:nth-child(1) { height: 40%; }
.md-level__meter span:nth-child(2) { height: 70%; }
.md-level__meter span:nth-child(3) { height: 100%; }
.md-level--high .md-level__meter span.is-on { background: #1f9d6b; }
.md-level--medium .md-level__meter span.is-on { background: #3563b0; }
.md-level--low .md-level__meter span.is-on { background: #c98a28; }
.md-level__label { color: #244373; font-size: 17px; font-weight: 800; }
.md-level--high .md-level__label { color: #17774f; }
.md-level--low .md-level__label { color: #9a6514; }
.md-level--none .md-level__label { color: #6b7080; }
.md-level__note { margin: 2px 0 0; color: #6b7080; font-size: 13px; line-height: 1.5; }

.md-change { align-self: flex-start; padding: 2px 8px; border-radius: 999px; background: #eef1f6; color: #4c5568; font-size: 12px; font-weight: 700; }
.md-change.is-up { background: rgba(31, 157, 107, 0.12); color: #17774f; }
.md-change.is-down { background: rgba(201, 40, 68, 0.09); color: #a91f39; }
.md-stat-sub { color: #6b7080; font-size: 12px; font-weight: 600; }
.md-stat-sub.is-good { color: #17774f; }

.md-switch { margin: 0; }
.md-switch button { padding: 6px 12px; font-size: 13px; }
.md-split { display: grid; gap: 10px; margin: 14px 0 0; padding: 0; list-style: none; }
.md-split li { display: grid; grid-template-columns: minmax(96px, 150px) 1fr auto 92px; align-items: center; gap: 12px; padding: 6px 10px; border-radius: 10px; }
.md-split li.is-focus { background: rgba(36, 67, 115, 0.05); }
.md-split__label { color: #244373; font-size: 14px; font-weight: 700; line-height: 1.3; }
.md-split__label small { display: block; color: #1f9d6b; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; }
.md-split__bars { display: grid; gap: 4px; min-width: 0; }
.md-split__bar { display: block; min-width: 2px; height: 8px; border-radius: 4px; transition: width .3s; }
.md-split__bar--demand { background: #244373; }
.md-split__bar--supply { background: #cdd6e6; }
.md-split__nums { color: #4c5568; font-size: 13px; font-variant-numeric: tabular-nums; white-space: nowrap; }
.md-split__nums i { color: #b6bdcb; font-style: normal; }
.md-tag { justify-self: start; padding: 3px 9px; border-radius: 999px; background: rgba(31, 157, 107, 0.12); color: #17774f; font-size: 11px; font-weight: 800; white-space: nowrap; }
.md-tag--blank { background: none; }
.md-legend-key { display: inline-block; width: 12px; height: 8px; border-radius: 2px; }

.md-amenities { display: flex; flex-wrap: wrap; gap: 10px; margin: 14px 0 0; padding: 0; list-style: none; }
.md-amenities li { display: inline-flex; flex-wrap: wrap; align-items: baseline; gap: 4px 8px; padding: 8px 14px; border: 1px solid #e3e7f0; border-radius: 999px; background: #fff; color: #244373; font-size: 14px; font-weight: 700; }
.md-amenities li svg { align-self: center; color: #17774f; }
.md-amenities li.is-has { border-color: #bfe3d3; background: #edf8f3; }
.md-amenities small { color: #6b7080; font-size: 12px; font-weight: 600; }
.md-amenities__note { margin: 8px 0 0; color: #6b7080; font-size: 12px; }

@media (max-width: 575px) {
    .md-split li { grid-template-columns: 1fr auto; gap: 6px 10px; }
    .md-split__bars { grid-column: 1 / -1; grid-row: 2; }
    .md-tag--blank { display: none; }
}
@media (prefers-reduced-motion: reduce) { .md-overview__wait span { animation: none; } .md-split__bar { transition: none; } }
</style>
