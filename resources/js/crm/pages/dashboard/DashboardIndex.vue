<template>
    <div v-if="!d" class="text-center py-5">
        <span v-if="!error" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ error }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else class="dl">
        <!-- ============ Hero · membership card · shortcut dock ============ -->
        <div class="dl-hero dl-anim">
            <div class="dl-hero-bg" :style="{ backgroundImage: `url('${d.hero_image}')` }"></div>
            <div class="dl-hero-inner">
                <div>
                    <span class="dl-eyebrow">{{ today }}{{ d.is_admin ? ' · Global view' : '' }}</span>
                    <h1 class="dl-hello serif">{{ greeting }}, <em>{{ d.name }}</em></h1>
                    <p class="dl-lede">
                        <strong>{{ d.trends.leads.current }} new {{ plural('enquiry', d.trends.leads.current) }}</strong>
                        and <strong>{{ d.trends.properties.current }} {{ plural('listing', d.trends.properties.current) }}</strong> in the last 30 days,
                        with <strong>{{ d.pipeline.open }} {{ plural('deal', d.pipeline.open) }}</strong> in motion.
                    </p>
                    <div class="dl-chips">
                        <template v-if="d.alerts.length">
                            <DashLink v-for="a in d.alerts" :key="a.label" :link="a" class="dl-chip" :style="{ '--c': dotColors[a.tone] ?? '#fff' }">
                                <span class="dot"></span>{{ a.count }} {{ a.label }}
                            </DashLink>
                        </template>
                        <span v-else class="dl-chip" style="--c: #6fd39b;"><span class="dot"></span>Everything is up to date</span>
                    </div>
                </div>
                <div v-if="d.plan_usage" class="dl-card">
                    <div class="dl-card-top"><span class="dl-card-brand">MW REALTY · PARTNER</span><span class="dl-chipicon"></span></div>
                    <a :href="d.links.plans" class="dl-card-link">{{ d.plan_usage.remaining === 0 ? 'UPGRADE' : 'MANAGE' }}</a>
                    <div class="dl-card-plan serif">{{ d.plan_usage.name }}</div>
                    <div class="dl-card-sub">
                        {{ d.plan_usage.limit !== null ? `${d.plan_usage.used} of ${d.plan_usage.limit} listings` : `${d.plan_usage.used} listings · unlimited` }}
                        · Reports {{ d.plan_usage.reports ? 'on' : 'off' }}{{ d.plan_usage.agents !== null ? ` · ${d.plan_usage.agents} ${plural('agent', d.plan_usage.agents)}` : '' }}
                    </div>
                    <div class="dl-card-bar"><span :style="{ width: `${d.plan_usage.pct ?? 100}%` }"></span></div>
                    <div class="dl-card-foot"><span class="holder">{{ d.name }}</span><span class="id">•••• {{ d.member_id }}</span></div>
                </div>
                <div v-else class="dl-card">
                    <div class="dl-card-top"><span class="dl-card-brand">MW REALTY · TOP PARTNERS</span><span class="dl-chipicon"></span></div>
                    <div class="mt-auto">
                        <div v-for="(row, i) in d.top_owners" :key="row.name" class="dl-card-row"><span class="text-truncate me-2">{{ i + 1 }}. {{ row.name }}</span><strong>{{ row.total }}</strong></div>
                        <div v-if="!d.top_owners.length" style="opacity: 0.8; font-size: 0.72rem;">No leads assigned yet.</div>
                    </div>
                    <div class="dl-card-foot mt-2"><span class="holder">Global view</span><span class="id">•••• {{ d.member_id }}</span></div>
                </div>
            </div>
            <nav class="dl-dock" aria-label="Shortcuts">
                <DashLink v-for="s in d.shortcuts" :key="s.label" :link="s" :class="`g-${s.tone}`"><i class="fas" :class="s.icon"></i>{{ s.label }}</DashLink>
            </nav>
        </div>

        <!-- ============ 6 compact KPIs ============ -->
        <div class="row mb-3">
            <div class="col-6 col-md-4 col-xl-2">
                <RouterLink :to="{ name: 'leads.index' }" class="dl-stat g-red">
                    <div class="dl-stat-top"><span class="dl-stat-ic"><i class="fas fa-address-book"></i></span><span class="dl-delta" :class="d.deltas.leads.dir">{{ d.deltas.leads.text }}</span></div>
                    <div class="dl-stat-value num"><CountUp :to="leadsTotal" /></div>
                    <div class="dl-stat-label">Total enquiries</div>
                    <div class="dl-stat-note">{{ d.trends.leads.current }} in the last 30 days</div>
                    <div class="dl-stat-spark"><ChartCanvas :config="sparkLeads" /></div>
                </RouterLink>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <RouterLink :to="{ name: 'leads.index', query: { status: 'active' } }" class="dl-stat g-navy">
                    <div class="dl-stat-top"><span class="dl-stat-ic"><i class="fas fa-bolt"></i></span><span class="dl-delta flat">{{ openPct }}%</span></div>
                    <div class="dl-stat-value num"><CountUp :to="d.pipeline.open" /></div>
                    <div class="dl-stat-label">Deals in progress</div>
                    <div class="dl-stat-note">of all enquiries still open</div>
                    <div class="dl-stat-meter"><span :style="{ width: `${openPct}%` }"></span></div>
                </RouterLink>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <RouterLink :to="{ name: 'leads.index' }" class="dl-stat g-green">
                    <div class="dl-stat-top"><span class="dl-stat-ic"><i class="fas fa-trophy"></i></span><span class="dl-delta up">{{ winRate !== null ? `${winRate}%` : '—' }}</span></div>
                    <div class="dl-stat-value num"><CountUp :to="d.pipeline.won" /></div>
                    <div class="dl-stat-label">Deals won</div>
                    <div class="dl-stat-note">{{ d.pipeline.lost }} lost · {{ winRate !== null ? `${winRate}% win rate` : 'none closed yet' }}</div>
                    <div class="dl-stat-meter"><span :style="{ width: `${winRate ?? 0}%` }"></span></div>
                </RouterLink>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <RouterLink :to="{ name: 'leads.index' }" class="dl-stat g-amber">
                    <div class="dl-stat-top"><span class="dl-stat-ic"><i class="fas fa-hourglass-half"></i></span><span v-if="d.stale_leads > 0" class="dl-delta warn">Act now</span></div>
                    <div class="dl-stat-value num"><CountUp :to="d.stale_leads" /></div>
                    <div class="dl-stat-label">Need follow-up</div>
                    <div class="dl-stat-note">untouched for 2+ days</div>
                    <div class="dl-stat-meter"><span :style="{ width: `${pct(d.stale_leads, leadsTotal)}%` }"></span></div>
                </RouterLink>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <a :href="d.links.properties" class="dl-stat g-teal">
                    <div class="dl-stat-top"><span class="dl-stat-ic"><i class="fas fa-building"></i></span><span class="dl-delta" :class="d.deltas.properties.dir">{{ d.deltas.properties.text }}</span></div>
                    <div class="dl-stat-value num"><CountUp :to="d.stats.total_properties" /></div>
                    <div class="dl-stat-label">Listings</div>
                    <div class="dl-stat-note">{{ d.stats.active_properties }} active · {{ d.stats.inactive_properties }} inactive</div>
                    <div class="dl-stat-spark"><ChartCanvas :config="sparkListings" /></div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <a :href="d.links.properties" class="dl-stat g-violet">
                    <div class="dl-stat-top"><span class="dl-stat-ic"><i class="fas fa-star"></i></span><span v-if="d.featured_expiring > 0" class="dl-delta warn">{{ d.featured_expiring }} expiring</span></div>
                    <div class="dl-stat-value num"><CountUp :to="d.stats.featured_properties" /></div>
                    <div class="dl-stat-label">Premium</div>
                    <div class="dl-stat-note">{{ pct(d.stats.featured_properties, d.stats.total_properties) }}% of listings promoted</div>
                    <div class="dl-stat-meter"><span :style="{ width: `${pct(d.stats.featured_properties, d.stats.total_properties)}%` }"></span></div>
                </a>
            </div>
        </div>

        <!-- ============ Performance · conversion · buyer insights ============ -->
        <div class="row mb-3">
            <div class="col-xl-6">
                <div class="dl-panel">
                    <div class="dl-head">
                        <div><h6 class="dl-title serif">Performance</h6><p class="dl-sub">Weekly enquiries &amp; new listings, last 12 weeks</p></div>
                        <div class="dl-legend"><span><i style="background: #ca2844;"></i> Enquiries</span><span><i style="background: #1b3358;"></i> Listings</span></div>
                    </div>
                    <div class="dl-chart"><ChartCanvas :config="trendChart" /></div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="dl-panel d-flex flex-column align-items-center justify-content-between">
                    <div class="dl-head w-100"><div><h6 class="dl-title serif">Conversion</h6><p class="dl-sub">Win rate on closed deals</p></div></div>
                    <div class="dl-gauge">
                        <svg viewBox="0 0 100 58" aria-hidden="true">
                            <defs><linearGradient id="dlGauge" x1="0" x2="1"><stop offset="0" stop-color="#ca2844" /><stop offset="1" stop-color="#2f855a" /></linearGradient></defs>
                            <path d="M10 50 A40 40 0 0 1 90 50" fill="none" stroke="#eef0f6" stroke-width="9" stroke-linecap="round" />
                            <path d="M10 50 A40 40 0 0 1 90 50" fill="none" stroke="url(#dlGauge)" stroke-width="9" stroke-linecap="round" :stroke-dasharray="`${gaugeLen} 200`" />
                        </svg>
                        <div class="c"><span class="num"><CountUp v-if="winRate !== null" :to="winRate" suffix="%" /><template v-else>—</template></span><small>Win rate</small></div>
                    </div>
                    <div class="dl-outcomes w-100">
                        <div style="--b: #edf6f1;"><span class="num">{{ d.pipeline.won }}</span><small>Won</small></div>
                        <div style="--b: #fbeff1;"><span class="num">{{ d.pipeline.lost }}</span><small>Lost</small></div>
                        <div style="--b: #eef2fa;"><span class="num">{{ d.pipeline.open }}</span><small>Open</small></div>
                    </div>
                    <div class="dl-conv-foot">{{ pct(d.pipeline.won, leadsTotal) }}% of all enquiries converted to a won deal</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="dl-panel">
                    <div class="dl-head"><div><h6 class="dl-title serif">Buyer Insights</h6><p class="dl-sub">When &amp; where enquiries come from</p></div></div>
                    <template v-if="bestDayIdx !== null">
                        <div class="dl-best"><i class="fas fa-calendar-day"></i><div><div class="l">Busiest day · 90 days</div><div class="v">{{ days[bestDayIdx] }} · {{ d.weekday_leads[bestDayIdx] }} {{ plural('enquiry', d.weekday_leads[bestDayIdx]) }}</div></div></div>
                        <div class="dl-wd"><ChartCanvas :config="weekdayChart" /></div>
                    </template>
                    <div class="mt-1">
                        <div v-for="r in d.sources.slice(0, 5)" :key="r.name" class="dl-src">
                            <span class="name">{{ r.name }}</span><span class="bar"><span :style="{ width: `${pct(r.total, srcMax)}%`, background: r.color }"></span></span><strong>{{ r.total }}</strong>
                        </div>
                        <div v-if="!d.sources.length" class="dl-empty">No enquiries yet.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ Pipeline strip ============ -->
        <div class="dl-panel mb-3" style="height: auto;">
            <div class="dl-pipe-head">
                <div style="min-width: 150px;"><h6 class="dl-title serif">Deal Pipeline</h6><p class="dl-sub">{{ d.is_admin ? 'All partners, by stage' : 'Your enquiries by stage' }}</p></div>
                <div v-if="!d.stage_flow.length && d.unstaged_leads === 0 && d.pipeline.won + d.pipeline.lost === 0" class="dl-empty flex-grow-1">No enquiries yet.</div>
                <template v-else>
                    <div class="dl-flow">
                        <RouterLink v-if="d.unstaged_leads > 0" :to="{ name: 'leads.index' }" class="dl-chev" style="--sc: #9aa1b8;"><span class="n">No stage</span><span class="v">{{ d.unstaged_leads }}</span></RouterLink>
                        <RouterLink v-for="stage in d.stage_flow" :key="stage.name" :to="{ name: 'leads.index' }" class="dl-chev" :style="{ '--sc': stage.color || '#1b3358' }" :title="`${stage.name}: ${stage.total}`">
                            <span class="n">{{ stage.name }}</span>
                            <span class="v">{{ stage.total }}<small>{{ pct(stage.total, leadsTotal) }}%</small></span>
                        </RouterLink>
                    </div>
                    <div class="dl-flow-end">
                        <span><i class="fas fa-circle-check" style="color: #2f855a;"></i> Won <strong>{{ d.pipeline.won }}</strong></span>
                        <span><i class="fas fa-circle-xmark" style="color: #b83232;"></i> Lost <strong>{{ d.pipeline.lost }}</strong></span>
                    </div>
                    <RouterLink :to="{ name: 'leads.index' }" class="dl-link">Work leads <i class="fas fa-arrow-right"></i></RouterLink>
                </template>
            </div>
        </div>

        <!-- ============ Enquiries · properties · inventory ============ -->
        <div class="row mb-3">
            <div class="col-xl-5">
                <div class="dl-panel">
                    <div class="dl-head">
                        <div><h6 class="dl-title serif">Latest Enquiries</h6><p class="dl-sub">Respond fast, fresh leads convert best</p></div>
                        <RouterLink :to="{ name: 'leads.index' }" class="dl-link">All <i class="fas fa-arrow-right"></i></RouterLink>
                    </div>
                    <RouterLink v-for="(lead, i) in d.recent_leads" :key="lead.id" :to="{ name: 'leads.show', params: { id: lead.id } }" class="dl-lead">
                        <span class="dl-av" :class="avatarG[i % avatarG.length]" :style="{ '--sc': lead.stage?.color || '#9aa1b8' }">{{ initials(lead.name, 1) }}</span>
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="n">{{ lead.name || 'Unknown' }}</div>
                            <div class="p"><i class="fas fa-house me-1"></i>{{ lead.property ?? 'General enquiry' }}{{ lead.source ? ` · ${lead.source}` : '' }}</div>
                        </div>
                        <span class="st" :style="{ color: lead.stage?.color || '#9aa1b8', background: `${lead.stage?.color || '#9aa1b8'}1a` }">{{ lead.stage?.name ?? ucfirst(lead.status) }}</span>
                        <span class="t">{{ timeAgo(lead.created_at, true) }}</span>
                    </RouterLink>
                    <div v-if="!d.recent_leads.length" class="dl-empty">No enquiries yet.</div>
                </div>
            </div>
            <div class="col-md-7 col-xl-4">
                <div class="dl-panel">
                    <div class="dl-head">
                        <div>
                            <h6 class="dl-title serif">{{ d.showcase.ranked ? 'Most Desired Properties' : 'Latest Properties' }}</h6>
                            <p class="dl-sub">{{ d.showcase.ranked ? 'Ranked by buyer enquiries' : 'Recently added to your portfolio' }}</p>
                        </div>
                        <a :href="d.links.properties" class="dl-link">Portfolio <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div v-if="!d.showcase.items.length" class="dl-empty">
                        No properties yet.<template v-if="d.is_approved"> <a :href="d.links.create_property">List your first property</a>.</template>
                    </div>
                    <div v-else class="dl-gallery" :class="`n-${d.showcase.items.length}`">
                        <a v-for="l in d.showcase.items" :key="l.id" :href="l.url" class="dl-gcard">
                            <img v-if="l.image" :src="l.image" alt="" loading="lazy">
                            <span v-else class="ph"><i class="fas fa-building"></i></span>
                            <div class="tag">
                                <span v-if="l.listing_type">{{ l.listing_type }}</span>
                                <span v-if="l.leads_count > 0" class="hot"><i class="fas fa-fire me-1"></i>{{ l.leads_count }}</span>
                            </div>
                            <div class="ov">
                                <div class="t">{{ l.title }}</div>
                                <div class="m"><span class="text-truncate">{{ l.location || '—' }}</span><span class="price">{{ l.price || 'On request' }}</span></div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-5 col-xl-3">
                <div class="dl-panel">
                    <div class="dl-head"><div><h6 class="dl-title serif">Inventory</h6><p class="dl-sub">{{ d.stats.total_properties }} {{ plural('listing', d.stats.total_properties) }} by purpose &amp; type</p></div></div>
                    <template v-if="d.stats.total_properties > 0">
                        <div class="dl-split-l"><span>For sale <strong>{{ d.listing_types.sale }}</strong></span><span>For rent <strong>{{ d.listing_types.rent }}</strong></span></div>
                        <div class="dl-split">
                            <span :style="{ width: `${(d.listing_types.sale / splitTotal) * 100}%`, background: '#1b3358' }"></span>
                            <span :style="{ width: `${(d.listing_types.rent / splitTotal) * 100}%`, background: '#ca2844' }"></span>
                        </div>
                        <div class="dl-split-l mb-2" style="font-size: 0.6rem; color: var(--muted);"><span>{{ pct(d.listing_types.sale, splitTotal) }}%</span><span>{{ pct(d.listing_types.rent, splitTotal) }}%</span></div>
                        <div v-for="t in d.property_types" :key="t.type" class="dl-type">
                            <span class="l">{{ t.type }}</span><span class="tr"><span :style="{ width: `${pct(t.total, typeMax)}%` }"></span></span><strong>{{ t.total }}</strong>
                        </div>
                        <div class="dl-mini">
                            <div><span class="num">{{ d.stats.active_properties }}</span><small>Active</small></div>
                            <div><span class="num">{{ d.stats.inactive_properties }}</span><small>Inactive</small></div>
                            <div><span class="num">{{ d.stats.featured_properties }}</span><small>Premium</small></div>
                        </div>
                    </template>
                    <div v-else class="dl-empty">No properties yet.</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, h, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import ChartCanvas from '../../components/ChartCanvas.vue';
import CountUp from '../../components/CountUp.vue';
import { initials, plural, timeAgo, ucfirst } from '../../utils/format';

const d = ref(null);
const error = ref('');

const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const dotColors = { red: '#ff6b86', navy: '#8fb3ee', amber: '#f5c26b', grey: '#c3c7d6' };
const avatarG = ['g-red', 'g-navy', 'g-teal', 'g-amber', 'g-violet', 'g-green'];
const tooltip = { backgroundColor: '#1f2340', padding: 10, cornerRadius: 8, boxPadding: 4, titleFont: { size: 11, weight: '700' }, bodyFont: { size: 12, weight: '600' } };

// Greet by the visitor's own clock.
const hour = new Date().getHours();
const greeting = hour < 5 ? 'Good evening' : hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
const today = new Date().toLocaleDateString('en-GB', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' }).replace(',', '');

const pct = (part, whole) => (whole ? Math.round((part / whole) * 100) : 0);
const leadsTotal = computed(() => d.value.stats.total_leads);
const winRate = computed(() => d.value.pipeline.win_rate);
const openPct = computed(() => pct(d.value.pipeline.open, leadsTotal.value));
const gaugeLen = computed(() => Math.round(((winRate.value ?? 0) / 100) * 125.66 * 10) / 10); // r=40 half circle ≈ 125.66
const bestDayIdx = computed(() => {
    const values = d.value.weekday_leads;
    return values.reduce((a, b) => a + b, 0) > 0 ? values.indexOf(Math.max(...values)) : null;
});
const splitTotal = computed(() => Math.max(d.value.listing_types.sale + d.value.listing_types.rent, 1));
const typeMax = computed(() => Math.max(...d.value.property_types.map((t) => t.total), 1));
const srcMax = computed(() => Math.max(...d.value.sources.map((s) => s.total), 1));

/** An alert / shortcut: a CRM screen (`route`) or a page not in the CRM app yet (`url`). */
const DashLink = (props, { slots, attrs }) => (props.link.route
    ? h(RouterLink, { ...attrs, to: props.link.route }, slots.default)
    : h('a', { ...attrs, href: props.link.url }, slots.default?.()));
DashLink.props = ['link'];

const weeks = computed(() => d.value.activity.leads.labels);

function spark(values, color) {
    return (canvas) => {
        const g = canvas.getContext('2d').createLinearGradient(0, 0, 0, canvas.parentElement.clientHeight || 32);
        g.addColorStop(0, `${color}40`);
        g.addColorStop(1, `${color}00`);
        return {
            type: 'line',
            data: { labels: weeks.value, datasets: [{ data: values, borderColor: color, backgroundColor: g, borderWidth: 2, fill: true, cubicInterpolationMode: 'monotone', pointRadius: 0, pointHoverRadius: 3, pointBackgroundColor: color }] },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: { ...tooltip, displayColors: false, callbacks: { title: (i) => `Week of ${i[0].label}` } } },
                scales: { x: { display: false }, y: { display: false, beginAtZero: true } },
            },
        };
    };
}

const sparkLeads = computed(() => spark(d.value.activity.leads.data, '#ca2844'));
const sparkListings = computed(() => spark(d.value.activity.properties.data, '#1f8a8a'));

const trendChart = (canvas) => {
    const leadColor = '#ca2844';
    const listingColor = '#1b3358';
    const fill = canvas.getContext('2d').createLinearGradient(0, 0, 0, canvas.parentElement.clientHeight || 200);
    fill.addColorStop(0, `${leadColor}33`);
    fill.addColorStop(1, `${leadColor}00`);
    const line = (label, data, color, area) => ({ label, data, borderColor: color, backgroundColor: area ? fill : 'transparent', fill: area, borderWidth: 2, cubicInterpolationMode: 'monotone', pointRadius: 0, pointHoverRadius: 5, pointBackgroundColor: color, pointBorderColor: '#fff', pointBorderWidth: 2 });
    return {
        type: 'line',
        data: { labels: weeks.value, datasets: [line('Enquiries', d.value.activity.leads.data, leadColor, true), line('Listings', d.value.activity.properties.data, listingColor, false)] },
        options: {
            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false }, tooltip: { ...tooltip, callbacks: { title: (i) => `Week of ${i[0].label}` } } },
            scales: {
                x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 }, maxRotation: 0, autoSkip: true } },
                y: { beginAtZero: true, grid: { color: 'rgba(31,35,64,0.05)' }, border: { display: false }, ticks: { font: { size: 10 }, precision: 0 } },
            },
        },
    };
};

const weekdayChart = () => {
    const values = d.value.weekday_leads;
    const max = Math.max(...values, 0);
    return {
        type: 'bar',
        data: { labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], datasets: [{ label: 'Enquiries', data: values, backgroundColor: values.map((v) => (v === max && max > 0 ? '#ca2844' : '#dfe4f0')), borderRadius: 5, borderSkipped: false, maxBarThickness: 22 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { ...tooltip, displayColors: false } },
            scales: { x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 9 } } }, y: { display: false, beginAtZero: true } },
        },
    };
};

function load() {
    error.value = '';
    http.get('/dashboard')
        .then((res) => {
            d.value = res.data;
        })
        .catch((e) => {
            error.value = errorMessage(e, 'The dashboard could not be loaded.');
        });
}

onMounted(load);
</script>
