<template>
    <div>
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <div class="flex-grow-1" style="min-width: 0;">
                <div class="portal-section-title mb-1">Nearby Places</div>
                <p class="text-muted small mb-0">
                    Landmarks (schools, hospitals, restaurants, attractions) your properties can be tagged with.
                    Everyone can use every place; only the one who added a place can edit or delete it, and a place tagged on properties can't be deleted.
                </p>
            </div>
            <RouterLink :to="{ name: 'nearby-places.create' }" class="btn btn-portal-primary btn-sm flex-shrink-0 text-nowrap">
                <i class="fas fa-plus me-1"></i> Add Place
            </RouterLink>
        </div>

        <div v-if="!page" class="text-center py-5">
            <span v-if="!loadError" class="spinner-border text-primary"></span>
            <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
        </div>
        <template v-else>
            <ul class="nav portal-lang-tabs mb-3">
                <li v-for="(label, key) in scopes" :key="key" class="nav-item">
                    <RouterLink class="nav-link" :class="{ active: page.scope === key }" :to="{ query: key === 'all' ? {} : { scope: key } }">{{ label }}</RouterLink>
                </li>
            </ul>

            <div class="portal-card p-4">
                <div class="table-responsive">
                    <table class="table portal-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Address</th>
                                <th>Added By</th>
                                <th class="text-center">Properties</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(place, i) in page.data" :key="place.id">
                                <td class="text-muted">{{ page.meta.from + i }}</td>
                                <td class="fw-semibold">{{ place.name }}</td>
                                <td>{{ place.type ?? '—' }}</td>
                                <td>{{ place.address ?? '—' }}</td>
                                <td>
                                    <span v-if="place.shared" class="badge bg-light text-dark border"><i class="fas fa-globe me-1"></i>Shared</span>
                                    <span v-else-if="place.own" class="badge bg-primary-subtle text-primary border"><i class="fas fa-user me-1"></i>You</span>
                                    <template v-else>{{ place.owner ?? '—' }}</template>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill border" :class="place.properties_count ? 'bg-primary-subtle text-primary' : 'bg-light text-muted'" title="Properties tagged with this place">{{ number(place.properties_count) }}</span>
                                </td>
                                <td class="text-center">
                                    <div v-if="place.can_manage" class="form-check form-switch d-inline-block">
                                        <input class="form-check-input" type="checkbox" :checked="place.status" @change="toggle(place, $event.target)">
                                    </div>
                                    <span v-else class="small" :class="place.status ? 'text-success' : 'text-secondary'">{{ place.status ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="text-end">
                                    <template v-if="place.can_manage">
                                        <RouterLink :to="{ name: 'nearby-places.edit', params: { id: place.id } }" class="portal-btn-ghost btn btn-sm"><i class="fas fa-edit"></i></RouterLink>
                                        <span v-if="place.properties_count" class="d-inline-block" tabindex="0" :title="`Tagged on ${place.properties_count} ${plural('property', place.properties_count)} — remove it from those first, or switch it off`">
                                            <button type="button" class="portal-btn-ghost btn btn-sm" disabled style="pointer-events: none;"><i class="fas fa-trash"></i></button>
                                        </span>
                                        <button v-else type="button" class="portal-btn-ghost btn btn-sm" @click="destroy(place)"><i class="fas fa-trash"></i></button>
                                    </template>
                                    <span v-else class="portal-muted small" :title="`Only ${place.shared ? 'MW Realty' : 'the one who added it'} can change this place`"><i class="fas fa-lock"></i></span>
                                </td>
                            </tr>
                            <tr v-if="!page.data.length"><td colspan="8" class="portal-empty">No nearby places yet. <RouterLink :to="{ name: 'nearby-places.create' }">Add your first one</RouterLink>.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-3"><CrmPagination :meta="page.meta" @change="goToPage" /></div>
        </template>
    </div>
</template>

<script setup>
/** Nearby Places (resources/views/portal/nearby-places/index) — ?scope=mine|shared, ?page=. */
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { number, plural } from '../../utils/format';

const route = useRoute();
const router = useRouter();
const { confirm } = useConfirm();
const { error: toastError } = useToast();

const page = ref(null);
const loadError = ref('');

const scopes = computed(() => Object.fromEntries(Object.entries({
    all: 'All', mine: page.value?.has_own ? 'My Places' : null, shared: 'Shared',
}).filter(([, label]) => label)));

function load() {
    loadError.value = '';
    return http.get('/nearby-places', { params: { scope: route.query.scope, page: route.query.page } })
        .then((res) => { page.value = res.data; })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function goToPage(p) {
    router.push({ query: { ...route.query, page: p } });
}

function toggle(place, input) {
    http.post(`/nearby-places/${place.id}/toggle-status`)
        .then((res) => { place.status = res.data.status; })
        .catch(() => {
            input.checked = place.status;
            toastError('Could not update the status.');
        });
}

async function destroy(place) {
    if (!(await confirm({ title: 'Delete this nearby place?', message: 'This can\'t be undone.', confirmText: 'Delete', tone: 'danger' }))) return;
    http.delete(`/nearby-places/${place.id}`)
        .then(() => load())
        .catch((e) => toastError(errorMessage(e, 'Could not delete this place.')));
}

watch(() => route.query, () => {
    if (route.name === 'nearby-places.index') load();
}, { immediate: true });
</script>
