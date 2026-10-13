<template>
    <div>
        <div class="portal-card p-3 mb-3 adm-explain">
            <i class="fas fa-circle-info"></i>
            <div>
                Every page of the website has an ad slider. Choose where agencies and agents can book ads, how many of their ads can run
                <strong>at the same time</strong> (MW Realty's own ads always show as well and don't use a slot), and what a booking costs in credits.
                Under a day they pay the hourly price × hours; from a day, the daily price × days. Ads included in their plan cost no credits —
                how long each plan can book is set on the plan.
            </div>
        </div>

        <div v-if="!rows" class="text-center py-5">
            <span v-if="!loadError" class="spinner-border text-primary"></span>
            <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
        </div>
        <form v-else @submit.prevent="save">
            <div class="adm-places">
                <div v-for="row in rows" :key="row.placement" class="adm-place" :class="{ 'is-closed': !row.client_bookable }">
                    <div class="adm-place__head">
                        <span class="adp-option__icon"><i class="fas" :class="placementIcon(row.placement)"></i></span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-bold text-truncate">{{ row.label }}</div>
                            <div class="adp-sub">{{ row.running }} {{ row.running === 1 ? 'ad' : 'ads' }} running now</div>
                        </div>
                        <div class="form-check form-switch m-0" :title="row.client_bookable ? 'Clients can book' : 'Closed to clients'">
                            <input :id="`pl-${row.placement}`" v-model="row.client_bookable" class="form-check-input" type="checkbox">
                        </div>
                    </div>
                    <div class="adm-place__label">{{ row.client_bookable ? 'Open to clients' : 'MW Realty ads only' }}</div>
                    <div class="adm-place__fields">
                        <label>
                            <span>At once</span>
                            <input v-model.number="row.max_slots" type="number" min="0" max="50" class="form-control form-control-sm" :disabled="!row.client_bookable" required>
                        </label>
                        <label>
                            <span>Credits / hour</span>
                            <input v-model.number="row.credits_per_hour" type="number" min="0" class="form-control form-control-sm" :disabled="!row.client_bookable" required>
                        </label>
                        <label>
                            <span>Credits / day</span>
                            <input v-model.number="row.credits_per_day" type="number" min="0" class="form-control form-control-sm" :disabled="!row.client_bookable" required>
                        </label>
                    </div>
                </div>
            </div>
            <div class="adm-savebar">
                <span class="small portal-muted"><i class="fas fa-circle-info me-1"></i>Changes apply to new bookings; booked ads keep their price.</span>
                <button type="submit" class="btn btn-portal-primary btn-sm px-4" :disabled="saving">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span><i v-else class="fas fa-save me-1"></i>Save placements
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
/** Super Admin › Advertisements › Placements & pricing (ad_placements): per page, open to clients, slots, credit prices. */
import { ref } from 'vue';
import http, { errorMessage } from '../../api/http';
import { useToast } from '../../composables/useToast';
import { placementIcon } from './placements';

const { success, error: toastError } = useToast();
const rows = ref(null);
const loadError = ref('');
const saving = ref(false);

function load() {
    loadError.value = '';
    http.get('/ads/manage/placements')
        .then((res) => { rows.value = res.data.data; })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function save() {
    saving.value = true;
    const placements = Object.fromEntries(rows.value.map((r) => [r.placement, {
        client_bookable: r.client_bookable, max_slots: r.max_slots, credits_per_hour: r.credits_per_hour, credits_per_day: r.credits_per_day,
    }]));
    http.put('/ads/manage/placements', { placements })
        .then((res) => success(res.data.message))
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { saving.value = false; });
}

load();
</script>
