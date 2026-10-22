<template>
    <div>
        <div class="st-head">
            <div>
                <h1 class="st-head__title">Contact Us</h1>
                <p class="st-head__sub">Need help with your account, a listing, or billing? Raise a ticket and track it here until it's solved.</p>
            </div>
            <RouterLink :to="{ name: 'contact.create' }" class="btn btn-portal-primary"><i class="fas fa-plus me-1"></i> Raise a Ticket</RouterLink>
        </div>

        <div v-if="!page" class="text-center py-5">
            <span v-if="!loadError" class="spinner-border text-primary"></span>
            <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
        </div>
        <template v-else>
            <div class="st-stats">
                <div v-for="[label, value, icon, style] in stats" :key="label" class="st-stat">
                    <span class="st-stat__icon" :style="style"><i class="fas" :class="icon"></i></span>
                    <div><div class="st-stat__value">{{ value }}</div><div class="st-stat__label">{{ label }}</div></div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="portal-card p-3 p-md-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <div class="portal-section-title mb-0">Ticket History</div>
                            <form class="d-flex gap-2 flex-wrap" @submit.prevent="applySearch">
                                <select v-model="category" class="form-select form-select-sm" style="width: auto;" @change="applySearch">
                                    <option value="">All issue types</option>
                                    <option v-for="c in meta?.categories ?? []" :key="c.key" :value="c.key">{{ c.label }}</option>
                                </select>
                                <input v-model="search" type="search" class="form-control form-control-sm" style="width: 190px;" placeholder="Subject or ticket no.">
                            </form>
                        </div>

                        <div class="st-tabs mb-3">
                            <RouterLink v-for="(label, key) in tabs" :key="key" :to="{ query: clean({ status: key, category: route.query.category, search: route.query.search }) }" class="st-tab" :class="{ 'is-active': (route.query.status ?? '') === key }">{{ label }}</RouterLink>
                        </div>

                        <RouterLink v-for="ticket in page.data" :key="ticket.id" :to="{ name: 'contact.show', params: { id: ticket.id } }" class="st-row" :class="{ 'st-row--attention': ticket.status === 'awaiting_client' }">
                            <div class="st-row__main">
                                <div class="st-row__subject">{{ ticket.subject }}</div>
                                <div class="st-row__meta">
                                    <span class="st-ref">{{ ticket.reference }}</span>
                                    <span class="st-chip"><i class="fas fa-tag"></i>{{ ticket.category_label }}</span>
                                    <span v-if="['high', 'urgent'].includes(ticket.priority)" class="st-chip" :class="`st-prio--${ticket.priority}`"><i class="fas fa-flag"></i>{{ ticket.priority_label }}</span>
                                    <span><i class="far fa-comments me-1"></i>{{ ticket.replies_count }}</span>
                                    <span>Updated {{ timeAgo(ticket.updated_at) }}</span>
                                </div>
                            </div>
                            <span class="st-badge" :class="`st-badge--${ticket.status_tone}`">{{ ticket.status_label }}</span>
                        </RouterLink>
                        <div v-if="!page.data.length" class="st-empty">
                            <i class="fas fa-headset d-block"></i>
                            <div class="fw-bold mb-1">{{ filtered ? 'No tickets match these filters' : 'No tickets yet' }}</div>
                            <div class="small">Something not working? <RouterLink :to="{ name: 'contact.create' }">Raise a ticket</RouterLink> and our team will help.</div>
                        </div>

                        <div class="mt-3"><CrmPagination :meta="page.meta" @change="goToPage" /></div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <ContactCard :contact="meta?.contact" />
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
/** Contact Us (resources/views/portal/contact/index) — the account's tickets; ?status=&category=&search=&page=. */
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import { timeAgo } from '../../utils/format';
import ContactCard from './ContactCard.vue';

const route = useRoute();
const router = useRouter();

const tabs = { '': 'All', active: 'Open', awaiting_client: 'Awaiting Reply', resolved: 'Solved', closed: 'Closed' };
const page = ref(null);
const meta = ref(null);
const loadError = ref('');
const category = ref('');
const search = ref('');

const clean = (query) => Object.fromEntries(Object.entries(query).filter(([, v]) => v));
const filtered = computed(() => ['status', 'category', 'search'].some((key) => route.query[key]));
const stats = computed(() => [
    ['Total Tickets', page.value.stats.total, 'fa-ticket-alt', null],
    ['Open', page.value.stats.active, 'fa-folder-open', 'background: rgba(4,161,204,0.12); color: #0284a8;'],
    ['Awaiting Your Reply', page.value.stats.awaiting, 'fa-reply', 'background: rgba(245,158,11,0.15); color: #b45309;'],
    ['Solved', page.value.stats.solved, 'fa-check-circle', 'background: rgba(22,163,74,0.12); color: #16a34a;'],
]);

function load() {
    loadError.value = '';
    category.value = route.query.category ?? '';
    search.value = route.query.search ?? '';
    return http.get('/support/tickets', { params: route.query })
        .then((res) => { page.value = res.data; })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function applySearch() {
    router.push({ query: clean({ status: route.query.status, category: category.value, search: search.value.trim() }) });
}

function goToPage(p) {
    router.push({ query: { ...route.query, page: p } });
}

http.get('/support/meta').then((res) => { meta.value = res.data; }).catch(() => {});

watch(() => route.query, () => {
    if (route.name === 'contact.index') load();
}, { immediate: true });
</script>
