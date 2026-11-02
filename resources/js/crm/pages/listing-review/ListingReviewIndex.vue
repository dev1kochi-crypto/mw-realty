<template>
    <div v-if="!page" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else>
        <div class="mb-3">
            <div class="portal-section-title mb-1">Listing Review</div>
            <div class="text-muted small" style="max-width: 760px;">
                Every listing is checked automatically when it is saved. When an agency lists a unit another agency already lists
                (same location and unit number, or the same photos), the first listing stays on the website and the new one waits here.
                Prices far off similar listings and ads that differ from their permit wait here too. You approve or reject each one.
            </div>
        </div>

        <div class="lr-metrics mb-3">
            <div class="lr-metric" title="Listings in an open same-unit case, out of all available listings">
                <div class="lr-metric__value">{{ page.metrics.duplicate_rate }}%</div>
                <div class="lr-metric__label">Duplicate rate</div>
                <div class="la-sub">{{ number(page.metrics.duplicate_listings) }} {{ plural('listing', page.metrics.duplicate_listings) }}</div>
            </div>
            <div class="lr-metric">
                <div class="lr-metric__value">{{ number(page.metrics.suspicious_30d) }}</div>
                <div class="lr-metric__label">Flagged · last 30 days</div>
                <div class="la-sub">Duplicates and risk cases</div>
            </div>
            <div class="lr-metric" title="Available listings whose permit is verified (or needs none)">
                <div class="lr-metric__value">{{ page.metrics.verification_rate }}%</div>
                <div class="lr-metric__label">Permit verification</div>
                <div class="la-sub">Of available listings</div>
            </div>
            <div class="lr-metric" :title="`Listings checked automatically that didn't need a person × ${page.metrics.minutes_per_check} min per manual check (LISTING_REVIEW_MINUTES_PER_CHECK)`">
                <div class="lr-metric__value">≈ {{ page.metrics.hours_saved_30d }} h</div>
                <div class="lr-metric__label">Time saved · last 30 days</div>
                <div class="la-sub">{{ number(page.metrics.screened_30d) }} checked automatically · {{ number(page.metrics.screened_clear_30d) }} needed no one · {{ page.metrics.minutes_per_check }} min each</div>
            </div>
            <div class="lr-metric">
                <div class="lr-metric__value">{{ page.metrics.avg_review_hours === null ? '—' : `${page.metrics.avg_review_hours} h` }}</div>
                <div class="lr-metric__label">Avg. time to resolve</div>
                <div class="la-sub">{{ number(page.metrics.resolved_90d) }} reviewed · {{ number(page.metrics.auto_resolved_90d) }} fixed by the agency (90 days)</div>
            </div>
        </div>

        <nav class="la-status-grid lr-tabs mb-4" aria-label="Review cases">
            <RouterLink v-for="t in tabs" :key="t.key" :to="{ query: { tab: t.key } }" class="la-status" :class="[`la-tone-${t.key}`, { 'is-active': page.tab === t.key }]" :aria-current="page.tab === t.key ? 'page' : null">
                <span class="la-status__icon"><i class="fas" :class="t.icon"></i></span>
                <span class="min-w-0">
                    <span class="la-status__count d-block">{{ number(page.counts[t.key] ?? 0) }}</span>
                    <span class="la-status__label d-block">{{ t.label }}</span>
                </span>
            </RouterLink>
        </nav>

        <div class="portal-card p-4" :class="`la-tone-${page.tab}`">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <div class="la-hint flex-grow-1"><i class="fas" :class="current.icon"></i><span>{{ current.hint }}</span></div>
                <form class="d-flex gap-2" style="min-width: min(100%, 420px);" @submit.prevent="applySearch">
                    <input v-model="searchInput" type="search" class="form-control form-control-sm flex-grow-1" maxlength="100" placeholder="Title, ref, agency or agent" aria-label="Search cases">
                    <button class="btn btn-sm btn-portal-primary px-3" type="submit"><i class="fas fa-search"></i></button>
                    <RouterLink v-if="page.search !== ''" :to="{ query: { tab: page.tab } }" class="btn btn-sm portal-btn-ghost" title="Clear search"><i class="fas fa-times"></i></RouterLink>
                </form>
            </div>

            <div v-if="!page.data.length" class="la-empty">
                <i class="fas" :class="current.icon"></i>
                {{ page.search !== '' ? 'No cases match your search.' : current.empty }}
            </div>
            <template v-else>
                <div class="la-row la-row--head lr-row">
                    <div>Listings</div><div>Why flagged</div><div>Price</div><div>{{ page.tab === 'resolved' ? 'Resolved' : 'Flagged' }}</div><div></div>
                </div>
                <div v-for="c in page.data" :key="c.id" class="la-row lr-row">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <div class="lr-stack">
                            <img v-for="l in c.listings.slice(0, 3)" :key="l.id" :src="l.thumb || 'https://placehold.co/128x96?text=%20'" alt="" loading="lazy">
                        </div>
                        <div class="min-w-0">
                            <RouterLink :to="{ name: 'listing-review.show', params: { id: c.id } }" class="la-title" :title="c.listings[0]?.title">{{ c.listings[0]?.title || 'Property' }}</RouterLink>
                            <div class="la-sub text-truncate">
                                <template v-if="c.type === 'duplicate'">{{ c.listings.length }} listings · {{ owners(c) }}</template>
                                <template v-else>{{ c.listings[0]?.reference_no }} · {{ c.listings[0]?.owner }}</template>
                            </div>
                        </div>
                    </div>
                    <div class="small">
                        <span class="la-chip" :class="c.type === 'duplicate' ? 'la-chip--warn' : 'la-chip--bad'"><i class="fas" :class="c.type === 'duplicate' ? 'fa-clone' : 'fa-triangle-exclamation'"></i>{{ c.type === 'duplicate' ? 'Same unit, another agency' : 'Risk' }}</span>
                        <div v-for="s in c.signals" :key="s" class="la-sub mt-1">{{ s }}</div>
                        <span v-if="c.status === 'open' && c.held" class="la-chip mt-1"><i class="fas fa-hourglass-half"></i>{{ c.held }} waiting for you</span>
                        <span v-if="c.status === 'open' && c.live_flagged" class="la-chip la-chip--ok mt-1" title="This flag doesn't take the listing off the website"><i class="fas fa-eye"></i>Still live</span>
                        <span v-if="c.status === 'open' && c.permit_waiting" class="la-chip la-chip--warn mt-1" title="Not approved on Listing Permits yet"><i class="fas fa-file-shield"></i>{{ c.permit_waiting }} also on Listing Permits</span>
                        <span v-if="c.resolution" class="la-chip la-chip--ok mt-1"><i class="fas fa-circle-check"></i>{{ RESOLUTIONS[c.resolution] }}</span>
                    </div>
                    <div class="small">
                        <template v-if="c.price">
                            <div class="fw-semibold">{{ c.price.min }} – {{ c.price.max }}</div>
                            <span class="la-chip mt-1" :class="c.price.conflict ? 'la-chip--bad' : 'la-chip--ok'"><i class="fas fa-arrows-left-right"></i>{{ c.price.variance_pct }}% apart</span>
                        </template>
                        <div v-else class="fw-semibold">{{ c.listings[0]?.price }}</div>
                    </div>
                    <div class="small">
                        <div class="fw-semibold">{{ formatDate(c.resolved_at || c.created_at) }}</div>
                        <div class="la-sub">{{ timeAgo(c.resolved_at || c.created_at) }}</div>
                    </div>
                    <div class="la-row__action text-end">
                        <RouterLink :to="{ name: 'listing-review.show', params: { id: c.id } }" class="btn btn-sm" :class="c.status === 'open' ? 'btn-portal-primary' : 'portal-btn-ghost'">
                            {{ c.status === 'open' ? 'Review' : 'Open' }} <i class="fas fa-arrow-right ms-1"></i>
                        </RouterLink>
                    </div>
                </div>

                <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="portal-muted small">Showing {{ page.meta.from }}–{{ page.meta.to }} of {{ number(page.meta.total) }}</span>
                    <div><CrmPagination :meta="page.meta" :on-each-side="1" @change="goToPage" /></div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
/**
 * Listing Review (Super Admin): cases the automated screening opened (App\Services\ListingReview) —
 * likely duplicates of one unit and risky listings. Tab and search live in the URL (?tab=&q=&page=).
 */
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import { formatDate, number, plural, timeAgo } from '../../utils/format';

const RESOLUTIONS = { approved: 'Approved', rejected: 'Rejected', auto_cleared: 'Fixed by the agency' };

const route = useRoute();
const router = useRouter();
const page = ref(null);
const loadError = ref('');
const searchInput = ref('');

const tabs = [
    { key: 'duplicate', label: 'Same unit, another agency', icon: 'fa-clone', hint: 'A unit another agency already lists. The first listing stays on the website; open a case to compare them side by side, then approve the new listing (both agencies advertise it) or reject it.', empty: 'No units listed twice right now.' },
    { key: 'risk', label: 'Risk signals', icon: 'fa-triangle-exclamation', hint: 'Price far off similar listings, details that don\'t add up or advertiser authority not confirmed — these stay live while you check. An ad that differs from its permit, or a rejected listing sent again, is off the website until you decide. Approve or reject each.', empty: 'No risky listings right now.' },
    { key: 'resolved', label: 'Resolved', icon: 'fa-circle-check', hint: 'Cases already reviewed — by you, or fixed by the agency (the flagged detail was changed).', empty: 'Nothing reviewed yet.' },
];
const current = computed(() => tabs.find((t) => t.key === page.value.tab) ?? tabs[0]);

function owners(c) {
    return [...new Set(c.listings.map((l) => l.owner))].join(', ');
}

function load() {
    loadError.value = '';
    return http.get('/listing-review', { params: { tab: route.query.tab, q: route.query.q, page: route.query.page } })
        .then((res) => {
            page.value = res.data;
            searchInput.value = res.data.search;
        })
        .catch((e) => {
            loadError.value = errorMessage(e);
        });
}

function applySearch() {
    router.push({ query: { tab: page.value.tab, q: searchInput.value.trim() || undefined } });
}

function goToPage(p) {
    router.push({ query: { ...route.query, page: p } });
}

watch(() => route.query, () => {
    if (route.name === 'listing-review.index') load();
}, { immediate: true });
</script>
