<template>
    <div v-if="!page" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <div class="portal-section-title mb-1">Premium Listings</div>
                <p class="portal-muted small mb-0">Live and scheduled premium listings across Properties and Commercial. Premium listings get a badge and appear in the Premium section on the website.</p>
            </div>
            <button v-if="canAdd" type="button" class="btn btn-portal-primary btn-sm" :disabled="!page.is_admin && (quota?.remaining ?? 0) === 0 && !quota?.per_month" @click="openAdd">
                <i class="fas fa-plus me-1"></i> Add Premium
            </button>
        </div>

        <!-- Tabs + quota chip on one line (details in the chip's tooltip). -->
        <div class="portal-list-toolbar mb-3">
            <div class="portal-featured-tabs" role="tablist">
                <RouterLink v-for="tab in tabs" :key="tab.key" :to="{ query: tab.key === 'all' ? {} : { status: tab.key } }" class="portal-featured-tabs__tab" :class="{ 'is-active': page.filter === tab.key }"
                            role="tab" :aria-selected="page.filter === tab.key ? 'true' : 'false'">
                    {{ tab.label }} <span>{{ tab.count }}</span>
                </RouterLink>
            </div>
            <div v-if="quota" class="portal-list-toolbar__chips">
                <UsageChips :quota="quota" :show-manage="false" />
            </div>
        </div>

        <div class="portal-card p-0 overflow-hidden">
            <div v-if="!page.data.length" class="p-5 text-center portal-empty">
                <i class="far fa-star fa-2x mb-2 d-block" style="color:#f59e0b"></i>
                {{ page.filter === 'scheduled' ? 'Nothing scheduled.' : (page.filter === 'live' ? 'No listings are premium right now.' : 'No premium listings yet.') }}
                <template v-if="canAdd"><a href="#" @click.prevent="openAdd">Make a listing premium</a>.</template>
            </div>
            <div v-else class="table-responsive">
                <table class="table portal-featured-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Listing</th>
                            <th>Menu</th>
                            <th v-if="page.is_admin">Owner</th>
                            <th>Status</th>
                            <th>Period</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in page.data" :key="row.id">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img :src="row.thumb || 'https://placehold.co/96x72?text=No+Image'" alt="" class="portal-featured-table__thumb">
                                    <div class="min-w-0">
                                        <RouterLink :to="{ name: row.segment === 'commercial' ? 'commercial.show' : 'properties.show', params: { id: row.id } }" class="portal-featured-table__title">{{ row.title }}</RouterLink>
                                        <div class="small portal-muted">Ref: {{ row.reference_no }}<template v-if="!row.status"> &middot; <span class="text-danger">Inactive</span></template></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="fm__tag" :class="{ 'fm__tag--commercial': row.segment === 'commercial' }">{{ row.segment === 'commercial' ? 'Commercial' : 'Property' }}</span></td>
                            <td v-if="page.is_admin" class="small">{{ row.owner }}</td>
                            <td>
                                <span v-if="row.live" class="portal-featured-status is-live"><i class="fas fa-circle"></i> Live</span>
                                <span v-else class="portal-featured-status is-scheduled"><i class="far fa-clock"></i> Scheduled</span>
                            </td>
                            <td class="small">
                                <div class="fw-semibold">
                                    <template v-if="row.featured_from">{{ formatDate(row.featured_from) }} &rarr; </template>
                                    {{ row.featured_until ? formatDate(row.featured_until) : 'No end date' }}
                                </div>
                                <div class="portal-muted">
                                    <template v-if="row.live && row.days_left !== null">{{ row.days_left <= 1 ? 'Ends within a day' : `${row.days_left} days left` }}</template>
                                    <template v-else-if="!row.live">Goes live {{ row.starts_in }}</template>
                                    <template v-else>Until stopped</template>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <!-- Edit = change this feature's dates (the listing itself opens from its title). -->
                                <button v-if="row.editable && canAdd" type="button" class="portal-btn-ghost btn btn-sm" title="Edit premium dates" aria-label="Edit premium dates" @click="openEdit(row)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <span v-else class="d-inline-block" tabindex="0" title="Set by MW Realty — contact us to change these dates">
                                    <button type="button" class="portal-btn-ghost btn btn-sm" disabled aria-label="Premium dates set by MW Realty"><i class="fas fa-edit"></i></button>
                                </span>
                                <button type="button" class="btn btn-sm portal-featured-stop" :disabled="stopping === row.id" @click="stop(row)">{{ row.live ? 'Stop' : 'Cancel' }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3"><CrmPagination :meta="page.meta" @change="goToPage" /></div>

        <FeatureModal v-if="canAdd" :item="featureItem" :mode="featureMode" :is-admin="page.is_admin" :quota="quota" :admin-max-days="page.feature_max_days" :batch-limit="page.batch_limit"
                      @close="featureItem = null" @saved="featureItem = null; load()" />
    </div>
</template>

<script setup>
/**
 * Premium (resources/views/portal/featured/index) — live and scheduled premium listings across
 * Properties and Commercial, "Add Premium" (several listings at once), edit dates, stop / cancel.
 */
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import FeatureModal from '../properties/components/FeatureModal.vue';
import UsageChips from '../properties/components/UsageChips.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { formatDate } from '../../utils/format';

const route = useRoute();
const router = useRouter();
const { confirm } = useConfirm();
const { error: toastError } = useToast();

const page = ref(null);
const loadError = ref('');
const featureItem = ref(null);
const featureMode = ref('create');
const stopping = ref(null);

const quota = computed(() => page.value?.featured_quota ?? null);
const canAdd = computed(() => page.value.is_admin || (quota.value && quota.value.limit > 0));
const tabs = computed(() => [
    { key: 'all', label: 'All', count: page.value.counts.live + page.value.counts.scheduled },
    { key: 'live', label: 'Live', count: page.value.counts.live },
    { key: 'scheduled', label: 'Scheduled', count: page.value.counts.scheduled },
]);

function load() {
    loadError.value = '';
    return http.get('/premium', { params: { status: route.query.status, page: route.query.page } })
        .then((res) => { page.value = res.data; })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function goToPage(p) {
    router.push({ query: { ...route.query, page: p } });
}

function openAdd() {
    featureMode.value = 'create';
    featureItem.value = { picker: true };
}

function openEdit(row) {
    featureMode.value = 'edit';
    featureItem.value = { id: row.id, title: row.title, thumb: row.thumb, ref: row.reference_no, live: row.live, start: row.featured_from || new Date().toLocaleDateString('en-CA'), end: row.featured_until };
}

async function stop(row) {
    const scheduled = !row.live;
    const ok = await confirm(scheduled ? {
        title: 'Cancel scheduled premium?',
        message: `${row.title ? `“${row.title}” won't be premium. ` : ''}The booking is removed, so it no longer counts toward your plan.`,
        confirmText: 'Cancel premium',
        cancelText: 'Keep it',
        tone: 'warning',
    } : {
        title: 'Remove premium now?',
        message: `${row.title ? `“${row.title}” ` : 'This listing '}loses its Premium badge and top placement on the website right away.${quota.value?.per_month ? ' It still counts toward this month\'s quota.' : ''}`,
        confirmText: 'Remove premium',
        cancelText: 'Keep premium',
        tone: 'warning',
    });
    if (!ok) return;
    stopping.value = row.id;
    http.delete(`/properties/${row.id}/feature`)
        .then(() => load())
        .catch(() => toastError('Could not update premium. Please try again.'))
        .finally(() => { stopping.value = null; });
}

watch(() => route.query, () => {
    if (route.name === 'premium.index') load();
}, { immediate: true });
</script>
