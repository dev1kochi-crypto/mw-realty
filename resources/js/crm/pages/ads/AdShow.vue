<template>
    <div v-if="!data" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="min-w-0">
                <RouterLink :to="{ name: 'ads.index' }" class="small text-decoration-none"><i class="fas fa-arrow-left me-1"></i>Advertisements</RouterLink>
                <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                    <div class="portal-section-title mb-0">{{ ad.name }}</div>
                    <span class="adp-chip" :class="`tone-${state.tone}`"><i class="fas" :class="state.icon"></i>{{ state.label }}</span>
                </div>
                <div v-if="isAdmin" class="adp-sub mt-1">
                    <template v-if="ad.is_client"><i class="fas fa-user-tie me-1"></i>{{ ad.client?.name }} · {{ ad.client?.type }}<template v-if="ad.client?.email"> · {{ ad.client.email }}</template></template>
                    <template v-else><i class="fas fa-bullhorn me-1"></i>MW Realty ad</template>
                </div>
            </div>
            <div v-if="isAdmin" class="d-flex gap-2">
                <button v-if="ad.can_approve" type="button" class="btn btn-sm adm-approve" :disabled="busy" @click="approve"><i class="fas fa-check me-1"></i>Approve</button>
                <button v-if="ad.can_reject" type="button" class="btn btn-sm adm-reject" @click="rejecting = ad"><i class="fas fa-xmark me-1"></i>{{ ad.state === 'live' ? 'Take down' : 'Reject' }}</button>
                <RouterLink v-if="ad.editable" :to="{ name: 'ads.edit', params: { id: ad.id } }" class="btn portal-btn-ghost btn-sm"><i class="fas fa-edit me-1"></i>Edit</RouterLink>
                <button v-if="!ad.is_client" type="button" class="btn btn-sm portal-featured-stop" :disabled="busy" @click="remove">Delete</button>
            </div>
            <div v-else class="d-flex gap-2">
                <RouterLink v-if="ad.editable" :to="{ name: 'ads.edit', params: { id: ad.id } }" class="btn portal-btn-ghost btn-sm"><i class="fas fa-edit me-1"></i>Edit</RouterLink>
                <button v-if="ad.cancellable" type="button" class="btn btn-sm portal-featured-stop" :disabled="busy" @click="cancel">{{ ad.state === 'live' ? 'Stop ad' : 'Cancel ad' }}</button>
                <RouterLink :to="{ name: 'ads.create' }" class="btn btn-portal-primary btn-sm"><i class="fas fa-plus me-1"></i>Book another</RouterLink>
            </div>
        </div>

        <div v-if="isAdmin && ad.state === 'pending'" class="alert alert-warning small d-flex gap-2 align-items-start">
            <i class="fas fa-hourglass-half mt-1"></i>
            <div>Waiting for your review. Check the banner, its text and where it links, then approve it or reject it with a reason. Not reviewed before {{ adDateTime(ad.ends_at) }} → it's closed and the client is refunded automatically.</div>
        </div>
        <div v-else-if="ad.state === 'pending'" class="alert alert-info small d-flex gap-2 align-items-start">
            <i class="fas fa-hourglass-half mt-1"></i>
            <div>MW Realty is reviewing this ad. Once approved it goes live by itself on {{ adDateTime(ad.starts_at) }}. If it isn't approved, or isn't reviewed before its time ends, {{ refundText }}.</div>
        </div>
        <div v-else-if="['rejected', 'expired'].includes(ad.state)" class="alert alert-danger small d-flex gap-2 align-items-start">
            <i class="fas fa-circle-xmark mt-1"></i>
            <div><strong>{{ ad.state === 'expired' ? 'Not reviewed in time.' : 'Not approved.' }}</strong> {{ ad.review_note }} <template v-if="!report.totals.impressions">{{ refundText.charAt(0).toUpperCase() + refundText.slice(1) }}.</template></div>
        </div>

        <!-- Banner + booking -->
        <div class="portal-card p-3 mb-3">
            <div class="adp-hero">
                <div class="adp-hero__banner"><img :src="ad.image_url" :alt="ad.image_alt || ad.name"></div>
                <div class="d-flex flex-column justify-content-between gap-3 p-1">
                    <div class="adp-facts">
                        <div><div class="adp-fact__label">Page</div><div class="adp-fact__value">{{ ad.placement_label }}</div></div>
                        <div><div class="adp-fact__label">Runs for</div><div class="adp-fact__value">{{ ad.duration_label || (ad.ends_at ? 'Until the end date' : 'Always on') }}</div></div>
                        <div><div class="adp-fact__label">Starts (Dubai)</div><div class="adp-fact__value">{{ adDateTime(ad.starts_at) }}</div></div>
                        <div><div class="adp-fact__label">Ends (Dubai)</div><div class="adp-fact__value">{{ adDateTime(ad.ends_at) }}</div></div>
                        <div v-if="ad.paid_with"><div class="adp-fact__label">Paid with</div><div class="adp-fact__value">{{ ad.paid_with === 'credits' ? `${number(ad.credits)} credits` : 'Plan ad' }}</div></div>
                        <div><div class="adp-fact__label">Links to</div><div class="adp-fact__value">
                            <a v-if="ad.preview.link_url" :href="ad.preview.link_url" target="_blank" rel="noopener">{{ ad.link.label }} <i class="fas fa-arrow-up-right-from-square small"></i></a>
                            <template v-else>{{ ad.link.label }}</template>
                        </div></div>
                    </div>
                    <div v-if="['live', 'ended'].includes(ad.state)">
                        <div class="d-flex justify-content-between small portal-muted"><span>{{ ad.state === 'live' ? 'Running' : 'Finished' }}</span><span>{{ progress }}%</span></div>
                        <div class="adp-timeline"><span :style="{ width: `${progress}%` }"></span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPIs -->
        <div class="adp-kpis" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
            <div class="adp-kpi">
                <span class="adp-kpi__icon is-imp"><i class="far fa-eye"></i></span>
                <div><div class="adp-kpi__value"><CountUp :to="report.totals.impressions" /></div><div class="adp-kpi__label">Impressions — times shown on screen</div></div>
            </div>
            <div class="adp-kpi">
                <span class="adp-kpi__icon is-click"><i class="fas fa-arrow-pointer"></i></span>
                <div><div class="adp-kpi__value"><CountUp :to="report.totals.clicks" /></div><div class="adp-kpi__label">Clicks</div></div>
            </div>
            <div class="adp-kpi">
                <span class="adp-kpi__icon is-ctr"><i class="fas fa-percent"></i></span>
                <div><div class="adp-kpi__value">{{ report.totals.ctr }}%</div><div class="adp-kpi__label">Click rate (CTR)</div></div>
            </div>
            <div class="adp-kpi">
                <span class="adp-kpi__icon is-live"><i class="fas fa-gauge-high"></i></span>
                <div><div class="adp-kpi__value">{{ number(perHour) }}</div><div class="adp-kpi__label">Impressions per hour</div></div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="portal-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-baseline mb-2">
                        <div class="fw-bold">Hour by hour <span class="portal-muted fw-normal small">(Dubai time)</span></div>
                        <small class="portal-muted">{{ report.updated_at ? `Updated ${timeAgo(report.updated_at)}` : 'No activity yet' }}</small>
                    </div>
                    <div v-if="hasActivity" class="adp-chart"><ChartCanvas :config="hourlyChart" /></div>
                    <div v-else class="adp-empty"><i class="fas fa-chart-line"></i>{{ emptyText }}</div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="portal-card p-3 mb-3">
                    <div class="fw-bold mb-2">Busiest hours</div>
                    <div v-if="report.top_hours.length" class="adp-bars">
                        <div v-for="h in report.top_hours" :key="h.hour" class="adp-bar">
                            <span>{{ pad(h.hour) }}:00</span>
                            <span class="adp-bar__track"><span :style="{ width: `${Math.max(4, Math.round((h.impressions / topMax) * 100))}%` }"></span></span>
                            <strong>{{ number(h.impressions) }}</strong>
                        </div>
                    </div>
                    <div v-else class="adp-empty py-3">Shows once your ad has been seen.</div>
                </div>
                <div class="portal-card p-0 overflow-hidden">
                    <div class="fw-bold p-3 pb-2">By day</div>
                    <table v-if="report.daily.length" class="table table-sm mb-0 small">
                        <thead><tr><th class="ps-3">Day</th><th class="text-end">Impr.</th><th class="text-end">Clicks</th><th class="text-end pe-3">CTR</th></tr></thead>
                        <tbody>
                            <tr v-for="d in report.daily" :key="d.date">
                                <td class="ps-3">{{ adDay(`${d.date}T12:00:00+04:00`) }}</td>
                                <td class="text-end">{{ number(d.impressions) }}</td>
                                <td class="text-end">{{ number(d.clicks) }}</td>
                                <td class="text-end pe-3">{{ d.ctr }}%</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="adp-empty py-3">No days yet.</div>
                </div>
            </div>
        </div>
        <RejectAdModal v-if="isAdmin" :ad="rejecting" @close="rejecting = null" @done="rejecting = null; load()" />
    </div>
</template>

<script setup>
/**
 * One ad: its banner and booking, review state, and the report — impressions (times the slide was on
 * screen), clicks and CTR, hour by hour over its run, per day, and its busiest hours (Dubai time).
 */
import { computed, ref, watch } from 'vue';
import http, { errorMessage } from '../../api/http';
import ChartCanvas from '../../components/ChartCanvas.vue';
import CountUp from '../../components/CountUp.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { timeAgo } from '../../utils/format';
import { AD_STATES, AD_TZ, adDateTime, adDay, number } from './adFormat';
import { cancelAdPrompt } from './cancelAd';
import RejectAdModal from './RejectAdModal.vue';
import { useSession } from '../../composables/useSession';
import { useRouter } from 'vue-router';

const props = defineProps({ id: { type: Number, required: true } });

const { confirm } = useConfirm();
const { success, error: toastError } = useToast();

const data = ref(null);
const loadError = ref('');
const busy = ref(false);

const ad = computed(() => data.value.ad);
const report = computed(() => data.value.report);
const { user, refreshNavigation } = useSession();
const router = useRouter();
const isAdmin = computed(() => !!user.value?.is_super_admin);
const base = computed(() => (isAdmin.value ? '/ads/manage' : '/ads'));
const rejecting = ref(null);
const state = computed(() => (isAdmin.value && !ad.value.is_client && ad.value.state === 'scheduled'
    ? { label: 'Scheduled', icon: 'fa-calendar', tone: 'blue' }
    : AD_STATES[ad.value.state] ?? AD_STATES.ended));
const pad = (n) => String(n).padStart(2, '0');
const refundText = computed(() => (ad.value.paid_with === 'credits' ? 'your credits are refunded' : 'the plan ad is given back'));

const hasActivity = computed(() => report.value.hourly.some((h) => h.impressions || h.clicks));
const emptyText = computed(() => (['pending', 'scheduled'].includes(ad.value.state)
    ? 'The report fills in hour by hour once your ad is live.'
    : 'No impressions recorded for this ad.'));
const topMax = computed(() => Math.max(1, ...report.value.top_hours.map((h) => h.impressions)));
const liveHours = computed(() => Math.max(1, report.value.hourly.filter((h) => h.impressions > 0).length));
const perHour = computed(() => Math.round(report.value.totals.impressions / liveHours.value));
const progress = computed(() => {
    const start = new Date(ad.value.starts_at).getTime();
    const end = new Date(ad.value.ends_at).getTime();
    return Math.max(0, Math.min(100, Math.round(((Date.now() - start) / Math.max(1, end - start)) * 100)));
});

const hourlyChart = (canvas) => {
    const imp = '#1b3358';
    const click = '#ca2844';
    const fill = canvas.getContext('2d').createLinearGradient(0, 0, 0, canvas.parentElement.clientHeight || 260);
    fill.addColorStop(0, `${imp}40`);
    fill.addColorStop(1, `${imp}00`);
    const labels = report.value.hourly.map((h) => new Date(h.at).toLocaleString('en-GB', { timeZone: AD_TZ, day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', hour12: false }));
    return {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { type: 'line', label: 'Impressions', data: report.value.hourly.map((h) => h.impressions), borderColor: imp, backgroundColor: fill, fill: true, borderWidth: 2, tension: 0.35, pointRadius: 0, pointHoverRadius: 5, pointBackgroundColor: imp, pointBorderColor: '#fff', pointBorderWidth: 2, yAxisID: 'y' },
                { type: 'bar', label: 'Clicks', data: report.value.hourly.map((h) => h.clicks), backgroundColor: `${click}cc`, borderRadius: 4, maxBarThickness: 14, yAxisID: 'y1' },
            ],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            animation: { duration: 900, easing: 'easeOutQuart' },
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } },
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
                y: { beginAtZero: true, grid: { color: '#eef0f6' }, ticks: { precision: 0 }, title: { display: true, text: 'Impressions' } },
                y1: { beginAtZero: true, position: 'right', grid: { display: false }, ticks: { precision: 0 }, title: { display: true, text: 'Clicks' } },
            },
        },
    };
};

function load() {
    loadError.value = '';
    return http.get(`${base.value}/${props.id}`)
        .then((res) => { data.value = res.data; })
        .catch((e) => { loadError.value = errorMessage(e); });
}

async function cancel() {
    if (!(await confirm(cancelAdPrompt(ad.value)))) return;
    busy.value = true;
    http.delete(`/ads/${ad.value.id}`)
        .then((res) => { success(res.data.message); load(); })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { busy.value = false; });
}


async function approve() {
    const ok = await confirm({ title: 'Approve this ad?', message: `“${ad.value.name}” runs on ${ad.value.placement_label} from ${adDateTime(ad.value.starts_at)} for ${ad.value.duration_label}.`, confirmText: 'Approve', tone: 'primary' });
    if (!ok) return;
    busy.value = true;
    http.post(`/ads/manage/${ad.value.id}/approve`)
        .then((res) => { success(res.data.message); refreshNavigation(); load(); })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { busy.value = false; });
}

async function remove() {
    const ok = await confirm({ title: 'Delete this ad?', message: `“${ad.value.name}” and its report are removed for good.`, confirmText: 'Delete', tone: 'danger' });
    if (!ok) return;
    busy.value = true;
    http.delete(`/ads/manage/${ad.value.id}`)
        .then((res) => { success(res.data.message); router.push({ name: 'ads.index' }); })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { busy.value = false; });
}
watch(() => props.id, () => { data.value = null; load(); }, { immediate: true });
</script>
