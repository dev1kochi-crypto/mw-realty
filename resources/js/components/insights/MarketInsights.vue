<template>
    <div class="mc">
        <div v-if="loading && !insights" class="pi-loading" aria-busy="true"><span></span><span></span><span></span></div>

        <template v-else-if="insights">
            <div class="mc__card">
                <div v-if="assessment" class="pi-verdict">
                    <span class="pi-badge" :class="`pi-badge--${assessment.headline.tone}`">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path v-if="assessment.headline.tone === 'positive'" d="M3 17l6-6 4 4 8-8M15 7h6v6" />
                            <path v-else-if="assessment.headline.tone === 'caution'" d="M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
                            <path v-else d="M5 12h14" />
                        </svg>
                        {{ badgeLabel(t, assessment.headline.key) }}
                    </span>
                    <p class="pi-verdict__note">{{ t('property_details.market.verdict_note', 'Automated assessment from market data — not a valuation.') }}</p>
                </div>
                <p v-else class="pi-empty">{{ t('property_details.market.no_assessment', 'There aren’t enough similar listings in this area yet to assess this property against the market.') }}</p>

                <ul v-if="assessment" class="pi-signals">
                    <li v-for="signal in assessment.signals" :key="signal.key">
                        <span class="pi-signals__icon" :class="`pi-signals__icon--${signal.tone}`" aria-hidden="true">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <path v-if="signal.tone === 'positive'" d="M20 6L9 17l-5-5" />
                                <path v-else-if="signal.tone === 'caution'" d="M12 8v5M12 17h.01" />
                                <path v-else d="M5 12h14" />
                            </svg>
                        </span>
                        <span class="pi-signals__text">
                            {{ signalText(t, signal) }}
                            <SourceTag v-if="signalSource(signal)" :source="signalSource(signal).source" :count="signalSource(signal).count" />
                        </span>
                    </li>
                </ul>
            </div>

            <div class="pi-stats">
                <div class="pi-stat">
                    <span class="pi-stat__label">{{ isRent ? t('property_details.market.this_rent_psf', 'This listing, rent / sq.ft a year') : t('property_details.market.this_psf', 'This listing, per sq.ft') }}</span>
                    <span v-if="insights.subject.psf" class="pi-stat__value">{{ formatPrice(insights.subject.psf, '—') }}</span>
                    <span v-else class="pi-stat__empty">{{ t('property_details.market.size_missing', 'Size not listed') }}</span>
                </div>
                <div class="pi-stat">
                    <span class="pi-stat__label">{{ t('property_details.market.asking_psf', 'Area asking, per sq.ft') }}</span>
                    <template v-if="asking.median_psf">
                        <span class="pi-stat__value">{{ formatPrice(asking.median_psf, '—') }}</span>
                        <SourceTag source="asking" :count="asking.count" />
                    </template>
                    <span v-else class="pi-stat__empty">{{ t('property_details.market.not_enough', 'Not enough data yet') }}</span>
                </div>
                <div class="pi-stat">
                    <span class="pi-stat__label">{{ isRent ? t('property_details.market.verified_rent_psf', 'Area rented, per sq.ft') : t('property_details.market.verified_psf', 'Area sold, per sq.ft') }}</span>
                    <template v-if="verified.median_psf">
                        <span class="pi-stat__value">{{ formatPrice(verified.median_psf, '—') }}</span>
                        <SourceTag source="verified" :count="verified.count" />
                    </template>
                    <span v-else class="pi-stat__empty">{{ t('property_details.market.no_deals', 'No closed deals yet') }}</span>
                </div>
                <div v-if="estimates.value" class="pi-stat">
                    <span class="pi-stat__label">{{ isRent ? t('property_details.market.est_rent', 'Estimated market rent (a year)') : t('property_details.market.est_value', 'Estimated market value') }}</span>
                    <span class="pi-stat__value">{{ formatPrice(estimates.value.amount, '—') }}</span>
                    <SourceTag :source="estimates.value.source" :count="estimates.value.count" />
                </div>
                <div v-if="estimates.gross_yield" class="pi-stat">
                    <span class="pi-stat__label">{{ t('property_details.investment.gross_yield', 'Gross rental yield') }}</span>
                    <span class="pi-stat__value">{{ estimates.gross_yield.pct.toFixed(2) }}%</span>
                    <SourceTag :source="estimates.gross_yield.source" :count="estimates.gross_yield.count" />
                </div>
                <div v-if="estimates.growth" class="pi-stat">
                    <span class="pi-stat__label">{{ t('property_details.market.growth', 'Price change, last 12 months') }}</span>
                    <span class="pi-stat__value" :class="estimates.growth.pct >= 0 ? 'is-good' : 'is-bad'">{{ estimates.growth.pct > 0 ? '+' : '' }}{{ estimates.growth.pct }}%</span>
                    <SourceTag :source="estimates.growth.source" :count="estimates.growth.count" />
                </div>
            </div>

            <h4 class="pi-subtitle">{{ isRent ? t('property_details.market.trend_rent_title', 'Rent per sq.ft over time') : t('property_details.market.trend_title', 'Price per sq.ft over time') }}</h4>
            <PriceTrendChart v-if="trendPoints >= 2" :points="insights.trend" />
            <p v-else class="pi-empty">{{ t('property_details.market.trend_empty', 'Not enough listings or deals in this area over the last two years to chart a trend.') }}</p>

            <h4 class="pi-subtitle">{{ isRent ? t('property_details.market.recent_rented', 'Recently rented nearby') : t('property_details.market.recent_sold', 'Recently sold nearby') }}</h4>
            <div v-if="insights.recent.length" class="pi-table-wrap">
                <table class="pi-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ t('property_details.market.col_date', 'Date') }}</th>
                            <th scope="col">{{ t('property_details.market.col_beds', 'Bedrooms') }}</th>
                            <th scope="col" class="pi-table__num">{{ t('property_details.market.col_size', 'Size') }}</th>
                            <th scope="col" class="pi-table__num">{{ isRent ? t('property_details.market.col_rent', 'Annual rent') : t('property_details.market.col_price', 'Price') }}</th>
                            <th scope="col" class="pi-table__num">{{ t('property_details.market.col_psf', 'Per sq.ft') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(deal, i) in insights.recent" :key="i" :class="{ 'is-active': deal.same_beds }">
                            <td>{{ formatDate(deal.date) }}</td>
                            <td>{{ deal.beds ?? '—' }}</td>
                            <td class="pi-table__num">{{ deal.sqft.toLocaleString() }} sq.ft</td>
                            <td class="pi-table__num">{{ formatPrice(deal.amount, '—') }}</td>
                            <td class="pi-table__num">{{ formatPrice(deal.psf, '—') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="pi-empty">{{ t('property_details.market.recent_empty', 'No deals closed in this area on MW Realty in the last 24 months, so the figures above come from asking prices and are estimates.') }}</p>

            <p class="mc__disclaimer">{{ t('property_details.market.disclaimer', '“Verified” figures come from deals closed on MW Realty with the title deed and DLD contract on file. “Estimate” figures come from asking prices of similar listings (same property type in the same area) and show where the market is pitched, not what buyers paid. Rents are annualised. Not a valuation or investment advice.') }}</p>
        </template>

        <p v-else class="pi-empty">{{ t('property_details.market.unavailable', 'Market data is not available right now.') }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useCurrency } from '../../composables/useCurrency';
import { useStaticText } from '../../composables/useStaticText';
import SourceTag from './SourceTag.vue';
import PriceTrendChart from './PriceTrendChart.vue';
import { SIGNAL_ESTIMATE, badgeLabel, signalText } from './assessment';
import '../calculators/calculators.css';
import './insights.css';

/** Property Details › Market: assessment badge, area figures, price trend and recent closed deals. */
const props = defineProps({
    insights: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const { t } = useStaticText();
const { formatPrice } = useCurrency();

const isRent = computed(() => props.insights?.purpose === 'rent');
const assessment = computed(() => props.insights?.assessment || null);
const estimates = computed(() => props.insights?.estimates || {});
const asking = computed(() => props.insights?.market?.asking || {});
const verified = computed(() => props.insights?.market?.verified || {});
const trendPoints = computed(() => (props.insights?.trend || []).reduce((n, p) => n + (p.asking_psf !== null) + (p.verified_psf !== null), 0));

const signalSource = (signal) => estimates.value[SIGNAL_ESTIMATE[signal.key]] || null;
const formatDate = (date) => new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
</script>
