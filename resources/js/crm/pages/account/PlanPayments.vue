<template>
    <div>
        <div class="ph-head">
            <div>
                <h1 class="ph-head__title">Payment History</h1>
                <p class="ph-head__sub">Your plan payments from the last 12 months<template v-if="d"> ({{ formatDate(d.since) }} &ndash; today)</template>.</p>
            </div>
            <div class="d-flex gap-2">
                <RouterLink :to="{ name: 'plans.index' }" class="btn btn-portal-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Plans</RouterLink>
            </div>
        </div>

        <div v-if="!d" class="text-center py-5">
            <span v-if="!loadError" class="spinner-border text-primary"></span>
            <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
        </div>
        <template v-else>
            <div class="ph-stats">
                <div class="ph-stat ph-stat--hero">
                    <span class="ph-stat__icon"><i class="fas fa-wallet"></i></span>
                    <div><div class="ph-stat__value">AED {{ fmt(d.summary.total) }}</div><div class="ph-stat__label">Paid in 12 months</div></div>
                </div>
                <div class="ph-stat">
                    <span class="ph-stat__icon"><i class="fas fa-receipt"></i></span>
                    <div><div class="ph-stat__value">{{ d.summary.count }}</div><div class="ph-stat__label">Payments</div></div>
                </div>
                <div class="ph-stat">
                    <span class="ph-stat__icon" style="background: rgba(22,163,74,0.1); color: #16a34a;"><i class="fas fa-tags"></i></span>
                    <div><div class="ph-stat__value">AED {{ fmt(d.summary.saved) }}</div><div class="ph-stat__label">Saved with coupons</div></div>
                </div>
                <div class="ph-stat">
                    <span class="ph-stat__icon"><i class="fas fa-calendar-check"></i></span>
                    <div><div class="ph-stat__value">{{ d.summary.last ? formatDate(d.summary.last) : '—' }}</div><div class="ph-stat__label">Last payment</div></div>
                </div>
            </div>

            <div v-for="month in d.months" :key="month.month" class="ph-month">
                <div class="ph-month__label">{{ month.label }}</div>
                <div v-for="payment in month.payments" :key="payment.id" class="ph-row">
                    <div class="ph-row__date">
                        <div class="ph-row__day">{{ day(payment.paid_at) }}</div>
                        <div class="ph-row__mon">{{ mon(payment.paid_at) }}</div>
                    </div>
                    <div class="ph-row__main">
                        <div class="ph-row__plan">{{ payment.plan }} plan</div>
                        <div class="ph-row__meta d-flex align-items-center gap-2 flex-wrap">
                            <span>{{ payment.cycle }}</span>
                            <span>&middot;</span>
                            <span>{{ payment.method }}</span>
                            <span v-if="payment.coupon_code" class="ph-coupon"><i class="fas fa-ticket-alt"></i>{{ payment.coupon_code }} &minus;AED {{ fmt(payment.discount) }}</span>
                        </div>
                    </div>
                    <div class="ph-row__amount">
                        <strong>AED {{ fmt(payment.amount) }}</strong>
                        <s v-if="payment.original_amount">AED {{ fmt(payment.original_amount) }}</s>
                    </div>
                    <span v-if="payment.paid" class="ph-paid"><i class="fas fa-check-circle"></i>Paid</span>
                    <span v-else class="ph-paid ph-paid--failed" :title="payment.failure_reason"><i class="fas fa-exclamation-circle"></i>Failed</span>
                    <div class="ph-row__action d-flex gap-1 justify-content-end">
                        <RouterLink :to="{ name: 'plans.invoice', params: { id: payment.id } }" class="btn btn-portal-light btn-sm"><i class="fas fa-file-invoice me-1"></i>Invoice</RouterLink>
                        <button type="button" class="btn btn-portal-light btn-sm" title="Download PDF" @click="pdf(payment)"><i class="fas fa-file-pdf text-danger"></i></button>
                    </div>
                </div>
            </div>
            <div v-if="!d.months.length" class="ph-empty">
                <i class="fas fa-receipt d-block"></i>
                <div class="fw-bold mb-1">No payments in the last 12 months</div>
                <div class="small">Payments for paid plans will appear here. <RouterLink :to="{ name: 'plans.index' }">View plans</RouterLink></div>
            </div>
        </template>
    </div>
</template>

<script setup>
/** Payment History (resources/views/portal/plans/payments) — the last 12 months, grouped by month. */
import { ref } from 'vue';
import http, { errorMessage } from '../../api/http';
import { useToast } from '../../composables/useToast';
import { download } from '../../utils/download';
import { formatDate } from '../../utils/format';

const { error: toastError } = useToast();

const d = ref(null);
const loadError = ref('');

const fmt = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const day = (iso) => String(new Date(iso).getDate()).padStart(2, '0');
const mon = (iso) => new Date(iso).toLocaleDateString('en-GB', { month: 'short' });

function load() {
    loadError.value = '';
    return http.get('/plans/payments')
        .then((res) => { d.value = res.data; })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function pdf(payment) {
    download('get', `/plans/payments/${payment.id}/invoice.pdf`).catch(() => toastError('Could not download the invoice.'));
}

load();
</script>
