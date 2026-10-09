<template>
    <div v-if="!page" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else>
        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
            <div>
                <div class="portal-section-title mb-1">Listing Permits</div>
                <div class="text-muted small" style="max-width: 720px;">
                    <template v-if="page.approval_on">Every listing's advertising permit. Agencies and agents validate the permit with DLD / ADREC in the property form, then the listing waits here for your approval before it shows on the website.</template>
                    <template v-else>Every listing's advertising permit. Agencies and agents validate the permit with DLD / ADREC in the property form; a verified listing goes live by itself. DTCM and None (DIFC / JAFZA) listings can't be validated online, so they wait for your approval.</template>
                    Expired listings never show on the website.
                </div>
            </div>
            <a :href="DLD_URL" target="_blank" rel="noopener" class="btn btn-sm btn-portal-primary"><i class="fas fa-shield-halved me-1"></i>Verify a permit on DLD</a>
        </div>

        <nav class="la-status-grid mb-4" aria-label="Permit status">
            <RouterLink v-for="t in tabs" :key="t.key" :to="{ query: { tab: t.key } }" class="la-status" :class="[`la-tone-${t.key}`, { 'is-active': page.tab === t.key }]" :aria-current="page.tab === t.key ? 'page' : null">
                <span class="la-status__icon"><i class="fas" :class="t.icon"></i></span>
                <span class="min-w-0">
                    <span class="la-status__count d-block">{{ number(page.counts[t.key] ?? 0) }}</span>
                    <span class="la-status__label d-block">{{ page.labels[t.key] }}</span>
                </span>
            </RouterLink>
        </nav>

        <div class="portal-card p-4" :class="`la-tone-${page.tab}`">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <div class="la-hint flex-grow-1"><i class="fas" :class="current.icon"></i><span>{{ current.hint }}</span></div>
                <form class="d-flex gap-2" style="min-width: min(100%, 420px);" @submit.prevent="applySearch">
                    <input v-model="searchInput" type="search" class="form-control form-control-sm flex-grow-1" maxlength="100" placeholder="Title, ref, permit no, agency or agent" aria-label="Search listings">
                    <button class="btn btn-sm btn-portal-primary px-3" type="submit"><i class="fas fa-search"></i></button>
                    <RouterLink v-if="page.search !== ''" :to="{ query: { tab: page.tab } }" class="btn btn-sm portal-btn-ghost" title="Clear search"><i class="fas fa-times"></i></RouterLink>
                </form>
            </div>

            <div v-if="!page.data.length" class="la-empty">
                <i class="fas" :class="current.icon"></i>
                {{ page.search !== '' ? 'No listings match your search.' : `Nothing in ${page.labels[page.tab].toLowerCase()} right now.` }}
            </div>
            <template v-else>
                <div class="la-row la-row--head">
                    <div>Property</div><div>Agency / Agent</div><div>Permit</div><div>{{ page.tab === 'approved' ? 'Verified' : 'Saved' }}</div><div></div>
                </div>
                <div v-for="p in page.data" :key="p.id" class="la-row">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <img :src="p.thumb || 'https://placehold.co/128x96?text=%20'" alt="" class="la-thumb" loading="lazy">
                        <div class="min-w-0">
                            <RouterLink :to="{ name: 'listing-permits.show', params: { id: p.id } }" class="la-title" :title="p.title">{{ p.title || 'Property' }}</RouterLink>
                            <div class="la-sub">{{ p.reference_no }} &middot; {{ p.price }} &middot; {{ p.purpose }}</div>
                        </div>
                    </div>
                    <div class="min-w-0 small">
                        <div class="fw-semibold text-truncate">{{ p.owner }}</div>
                        <div class="la-sub text-truncate">{{ p.agent ? `Agent: ${p.agent}` : 'No agent assigned' }}</div>
                    </div>
                    <div class="small">
                        <template v-if="p.permit_number">
                            <div class="la-permit">{{ p.permit_number }}</div>
                            <span v-if="p.days_left === null" class="la-chip">No expiry date</span>
                            <span v-else-if="p.days_left < 0" class="la-chip la-chip--bad"><i class="fas fa-ban"></i>Expired {{ formatDate(p.permit_expires_at) }}</span>
                            <span v-else-if="p.days_left <= 30" class="la-chip la-chip--warn"><i class="fas fa-hourglass-half"></i>{{ p.days_left }} day{{ p.days_left === 1 ? '' : 's' }} left</span>
                            <span v-else class="la-chip la-chip--ok"><i class="far fa-calendar"></i>Until {{ formatDate(p.permit_expires_at) }}</span>
                            <template v-if="p.validates">
                                <span v-if="p.verified === 'online'" class="la-chip la-chip--ok mt-1"><i class="fas fa-circle-check"></i>Verified with {{ p.issuer }}</span>
                                <span v-else-if="p.verified === 'manual'" class="la-chip la-chip--ok mt-1" title="Approved before online validation"><i class="fas fa-circle-check"></i>Verified</span>
                                <span v-else class="la-chip la-chip--warn mt-1" title="Waiting for the agency / agent to validate it"><i class="fas fa-shield-halved"></i>Not verified</span>
                            </template>
                        </template>
                        <span v-else-if="p.no_permit_needed" class="la-chip"><i class="fas fa-circle-info"></i>No permit needed</span>
                        <span v-else class="la-chip la-chip--warn"><i class="fas fa-triangle-exclamation"></i>No permit</span>
                        <span v-if="p.awaiting_approval" class="la-chip la-chip--warn mt-1"><i class="fas fa-user-shield"></i>Awaiting approval</span>
                    </div>
                    <div class="small">
                        <template v-if="p.when">
                            <div class="fw-semibold">{{ formatDate(p.when) }}</div>
                            <div class="la-sub">{{ timeAgo(p.when) }}</div>
                        </template>
                        <span v-else class="la-sub">—</span>
                    </div>
                    <div class="la-row__action text-end">
                        <RouterLink :to="{ name: 'listing-permits.show', params: { id: p.id } }" class="btn btn-sm" :class="page.tab === 'pending' ? 'btn-portal-primary' : 'portal-btn-ghost'">
                            Open <i class="fas fa-arrow-right ms-1"></i>
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
 * Listing Permits (Super Admin) — resources/views/portal/listing-approvals/index: every listing's
 * advertising permit by status. The status tab and search live in the URL (?tab=&q=&page=).
 */
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import { formatDate, number, timeAgo } from '../../utils/format';

const DLD_URL = 'https://dubailand.gov.ae/en/eservices/validate-real-estate-licenses-and-permits/';

const route = useRoute();
const router = useRouter();
const page = ref(null);
const loadError = ref('');
const searchInput = ref('');

const tabs = computed(() => [
    { key: 'approved', icon: 'fa-circle-check', hint: 'Permit verified (or none needed) — these can be live. Take one down any time if something is wrong.' },
    { key: 'pending', icon: 'fa-shield-halved', hint: page.value.approval_on
        ? 'Waiting for your approval — not on the website until you approve them. Open one, check the permit, then Approve & publish.'
        : 'Not on the website yet. DTCM and None (DIFC / JAFZA) listings wait for your approval; RERA / ADREC ones go live by themselves once the agency / agent validates the permit (or you can check and approve them).' },
    { key: 'draft', icon: 'fa-file-circle-exclamation', hint: 'Saved without complete permit details — not on the website until the agent adds and validates the permit.' },
    { key: 'expired', icon: 'fa-ban', hint: 'Taken offline automatically when the permit expired. They come back once a renewed permit is validated.' },
    { key: 'changes_requested', icon: 'fa-rotate-left', hint: 'Taken down by you. They stay offline until the agent changes the permit details and validates it again.' },
]);
const current = computed(() => tabs.value.find((t) => t.key === page.value.tab) ?? tabs.value[0]);

function load() {
    loadError.value = '';
    return http.get('/listing-permits', { params: { tab: route.query.tab, q: route.query.q, page: route.query.page } })
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
    if (route.name === 'listing-permits.index') load();
}, { immediate: true });
</script>
