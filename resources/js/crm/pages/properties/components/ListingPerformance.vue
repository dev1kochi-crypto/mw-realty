<template>
    <Teleport to="body">
        <template v-if="propertyId">
            <div class="offcanvas offcanvas-end lp-offcanvas show" tabindex="-1" role="dialog" aria-labelledby="listingPerformanceTitle" style="visibility: visible;">
                <div class="offcanvas-header">
                    <h5 id="listingPerformanceTitle" class="offcanvas-title">Listing Performance</h5>
                    <button type="button" class="btn-close" aria-label="Close" @click="$emit('close')"></button>
                </div>
                <div class="offcanvas-body">
                    <div v-if="loading" class="lp-loading"><span class="spinner-border spinner-border-sm me-2"></span>Loading performance…</div>
                    <div v-else-if="!data" class="lp-empty"><i class="fas fa-triangle-exclamation"></i>Couldn't load the performance — please try again.</div>
                    <template v-else>
                        <!-- Banner -->
                        <div class="lp-banner" :style="{ backgroundImage: `url('${data.property.thumb || ''}')` }">
                            <div class="lp-banner__shade"></div>
                            <div class="lp-banner__chips">
                                <span class="lp-chip" :class="{ 'is-live': data.property.status }"><i class="fas fa-circle"></i>{{ data.property.status_label }}</span>
                                <span v-if="data.property.featured" class="lp-chip is-premium"><i class="fas fa-star"></i>Premium</span>
                            </div>
                            <div class="lp-banner__text">
                                <div class="lp-banner__title" :title="data.property.title">{{ data.property.title }}</div>
                                <div class="lp-banner__meta">{{ data.property.reference_no ? `${data.property.reference_no} · ` : '' }}{{ data.property.price }}</div>
                            </div>
                        </div>

                        <div class="lp-seg" role="tablist">
                            <button type="button" role="tab" :class="{ active: tab === 'insights' }" @click="tab = 'insights'"><i class="fas fa-chart-simple"></i>Insights</button>
                            <button type="button" role="tab" :class="{ active: tab === 'overview' }" @click="tab = 'overview'"><i class="fas fa-circle-info"></i>Overview</button>
                            <button type="button" role="tab" :class="{ active: tab === 'leads' }" @click="tab = 'leads'"><i class="fas fa-user-group"></i>Leads<span class="lp-seg__count">{{ data.leads.length }}</span></button>
                        </div>

                        <div class="tab-content">
                            <!-- ============ Insights ============ -->
                            <div v-if="tab === 'insights'" class="tab-pane fade show active" role="tabpanel">
                                <div class="lp-kpis">
                                    <div v-for="step in steps" :key="step.label" class="lp-kpi" :class="`tone-${step.tone}`">
                                        <span class="lp-kpi__icon"><i class="fas" :class="step.icon"></i></span>
                                        <div class="lp-kpi__value">{{ step.label === 'Leads' ? number(step.value) : fmtNum(step.value) }}</div>
                                        <div class="lp-kpi__label">{{ step.label }}</div>
                                        <div class="lp-kpi__sub">{{ step.last30 !== null ? `${number(step.last30)} in 30 days` : 'all time' }}</div>
                                    </div>
                                </div>

                                <section class="lp-block">
                                    <div class="lp-block__head"><span>Conversion</span><small>{{ data.updated_at ? `Updated ${timeAgo(data.updated_at)}` : 'No activity yet' }}</small></div>
                                    <template v-for="step in steps" :key="step.label">
                                        <div v-if="step.rate !== null" class="lp-step-rate"><i class="fas fa-arrow-down"></i>{{ step.rate }}</div>
                                        <div class="lp-step" :class="`tone-${step.tone}`">
                                            <span class="lp-step__label">{{ step.label }}</span>
                                            <span class="lp-step__track"><span :style="{ width: `${step.value ? Math.max(3, Math.round((100 * step.value) / stepMax)) : 0}%` }"></span></span>
                                            <span class="lp-step__value">{{ number(step.value) }}</span>
                                        </div>
                                    </template>
                                </section>

                                <section class="lp-block">
                                    <div class="lp-block__head"><span>Last {{ data.days }} days</span>
                                        <small><i class="lp-key is-imp"></i>Impressions <i class="lp-key is-click ms-2"></i>Clicks</small></div>
                                    <template v-if="hasActivity">
                                        <svg class="lp-chart" :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none" role="img" aria-label="Impressions and clicks over the last 30 days">
                                            <polygon :points="`0,${H} ${impLine} ${W},${H}`" class="lp-chart__area" />
                                            <polyline :points="impLine" class="lp-chart__imp" />
                                            <polyline :points="clickLine" class="lp-chart__click" />
                                        </svg>
                                        <div class="lp-chart__axis"><span>{{ firstDay }}</span><span>Today</span></div>
                                    </template>
                                    <div v-else class="lp-chart-empty"><i class="fas fa-chart-line"></i>No views yet in the last {{ data.days }} days — activity shows here as visitors see and open this listing.</div>
                                    <div v-if="data.interested_visitors" class="lp-hint"><i class="fas fa-user-clock"></i>{{ data.interested_visitors }} identified website {{ plural('visitor', data.interested_visitors) }} opened this listing.</div>
                                </section>

                                <section class="lp-block">
                                    <div class="lp-quality">
                                        <div class="lp-gauge" :class="`is-${data.quality.tone}`" :style="{ '--pq': data.quality.score }"><span><strong>{{ data.quality.score }}</strong><small>/100</small></span></div>
                                        <div class="min-w-0">
                                            <div class="lp-quality__title">Listing quality</div>
                                            <div class="lp-quality__grade" :class="`is-${data.quality.tone}`">{{ grade }}</div>
                                            <div class="lp-quality__hint">Higher quality listings rank better and get more clicks.</div>
                                        </div>
                                    </div>
                                    <div v-if="data.fixes.length" class="lp-fixes">
                                        <div class="lp-fixes__title"><i class="fas fa-wand-magic-sparkles"></i>Improve next</div>
                                        <div v-for="(fix, i) in data.fixes" :key="i" class="lp-fix"><span>{{ fix.tip }}</span><b>+{{ fix.gain }}</b></div>
                                    </div>
                                    <div class="lp-checks">
                                        <div v-for="(check, i) in checks" :key="i" class="lp-check">
                                            <span class="lp-check__label">{{ check.label }}</span>
                                            <span class="lp-check__bar" :class="check.points >= check.max ? 'is-full' : (check.points > 0 ? 'is-part' : 'is-none')"><span :style="{ width: `${Math.round((100 * check.points) / check.max)}%` }"></span></span>
                                            <span class="lp-check__pts">{{ check.points }}<small>/{{ check.max }}</small></span>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <!-- ============ Overview ============ -->
                            <div v-if="tab === 'overview'" class="tab-pane fade show active" role="tabpanel">
                                <div class="lp-people">
                                    <div v-for="[label, icon, value] in data.people" :key="label" class="lp-people__row">
                                        <span class="lp-people__label">{{ label }}</span>
                                        <span class="lp-people__value" :title="value"><i class="fas" :class="icon"></i>{{ value }}</span>
                                    </div>
                                </div>
                                <div class="lp-tiles">
                                    <div v-for="[icon, label, value] in data.tiles" :key="label" class="lp-tile"><i :class="icon.startsWith('far') ? icon : `fas ${icon}`"></i><span>{{ label }}</span><strong>{{ value }}</strong></div>
                                </div>
                                <div class="lp-actions">
                                    <RouterLink :to="{ name: section.routes.edit, params: { id: data.property.id } }" class="btn btn-portal-primary" @click="$emit('close')"><i class="fas fa-edit me-1"></i>Edit listing</RouterLink>
                                    <a v-if="data.property.url" :href="data.property.url" target="_blank" rel="noopener" class="btn portal-btn-ghost"><i class="fas fa-arrow-up-right-from-square me-1"></i>View on website</a>
                                </div>
                            </div>

                            <!-- ============ Leads ============ -->
                            <div v-if="tab === 'leads'" class="tab-pane fade show active" role="tabpanel">
                                <RouterLink v-for="lead in data.leads" :key="lead.id" :to="{ name: 'leads.show', params: { id: lead.id } }" class="lp-lead" @click="$emit('close')">
                                    <span class="lp-lead__avatar">{{ lead.name.charAt(0).toUpperCase() }}</span>
                                    <span class="min-w-0 flex-grow-1">
                                        <span class="lp-lead__name">{{ lead.name }}</span>
                                        <span class="lp-lead__meta"><i class="fas fa-globe"></i>{{ lead.source }} · {{ timeAgo(lead.created_at) }}{{ lead.agent ? ` · ${lead.agent}` : '' }}</span>
                                    </span>
                                    <span v-if="lead.stage" class="lp-stage" :style="{ '--stage': lead.stage.color }">{{ lead.stage.name }}</span>
                                    <i class="fas fa-chevron-right lp-lead__go"></i>
                                </RouterLink>
                                <div v-if="!data.leads.length" class="lp-empty">
                                    <span class="lp-empty__icon"><i class="fas fa-user-group"></i></span>
                                    <strong>No leads yet</strong>
                                    <span>Enquiries, viewing requests and website visitors routed to you for this listing will show here.</span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div class="offcanvas-backdrop fade show" @click="$emit('close')"></div>
        </template>
    </Teleport>
</template>

<script setup>
/**
 * Listing Performance side panel (resources/views/portal/properties/_insights_panel) — photo banner,
 * then Insights (KPI tiles, conversion steps, 30-day chart, quality) / Overview / Leads.
 * GET /properties/{id}/insights.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import http from '../../../api/http';
import { number, plural, timeAgo } from '../../../utils/format';
import { sectionFor } from '../sections';

const props = defineProps({
    propertyId: { type: Number, default: null },
});
const emit = defineEmits(['close']);

const data = ref(null);
const loading = ref(false);
const tab = ref('insights');
let request = 0;

const section = computed(() => sectionFor(data.value?.property.segment));
const fmtNum = (n) => (n >= 10000 ? `${(n / 1000).toFixed(1)}k` : number(n));
const pct = (part, whole) => (whole ? `${Math.round((1000 * part) / whole) / 10}%` : '—');

// Conversion steps — bar widths relative to the biggest step (impressions, normally).
const steps = computed(() => {
    const f = data.value.funnel;
    const l = data.value.last30;
    return [
        { label: 'Impressions', value: f.impressions, icon: 'fa-eye', tone: 'navy', rate: null, last30: l.impressions },
        { label: 'Listing clicks', value: f.clicks, icon: 'fa-arrow-pointer', tone: 'red', rate: pct(f.clicks, f.impressions), last30: l.clicks },
        { label: 'Lead clicks', value: f.lead_clicks, icon: 'fa-phone-volume', tone: 'amber', rate: pct(f.lead_clicks, f.clicks), last30: l.lead_clicks },
        { label: 'Leads', value: f.leads, icon: 'fa-user-check', tone: 'green', rate: pct(f.leads, f.clicks), last30: null },
    ];
});
const stepMax = computed(() => Math.max(1, ...steps.value.map((s) => s.value)));

// 30-day chart as SVG polylines (impressions area + clicks line).
const W = 320;
const H = 84;
const line = (key) => {
    const series = data.value.series;
    const n = Math.max(1, series.length - 1);
    const max = Math.max(1, data.value.series_max);
    return series.map((d, i) => `${Math.round((i * W * 10) / n) / 10},${Math.round((H - 4 - (d[key] / max) * (H - 12)) * 10) / 10}`).join(' ');
};
const impLine = computed(() => line('impressions'));
const clickLine = computed(() => line('clicks'));
const hasActivity = computed(() => data.value.last30.impressions + data.value.last30.clicks + data.value.last30.lead_clicks > 0);
const firstDay = computed(() => {
    const [y, m, d] = data.value.series[0].date.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
});

const grade = computed(() => ({ good: 'Excellent', fair: 'Good — room to improve' }[data.value.quality.tone] ?? 'Needs work'));
const checks = computed(() => data.value.quality.groups.flat());

function onKey(event) {
    if (event.key === 'Escape') emit('close');
}

watch(() => props.propertyId, (id) => {
    document.body.classList.toggle('modal-open', !!id);
    if (!id) {
        document.removeEventListener('keydown', onKey);
        return;
    }
    document.addEventListener('keydown', onKey);
    const mine = ++request;
    tab.value = 'insights';
    data.value = null;
    loading.value = true;
    http.get(`/properties/${id}/insights`)
        .then((res) => {
            if (mine === request) data.value = res.data;
        })
        .catch(() => {})
        .finally(() => {
            if (mine === request) loading.value = false;
        });
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.body.classList.remove('modal-open');
});
</script>
