<template>
    <div class="property-tab-pane-head d-flex justify-content-between align-items-start">
        <div>
            <div class="property-tab-pane-title">Nearby Places</div>
            <div class="property-tab-pane-hint">Pick a type, then a specific place, and add it. Can't find it? Add your own place (opens in a new tab), then re-pick the type.</div>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
            <a :href="links.nearby_create" target="_blank" class="btn btn-sm portal-btn-ghost"><i class="fas fa-plus me-1"></i>New Place</a>
            <a :href="links.nearby_index" target="_blank" class="btn btn-sm portal-btn-ghost">Manage</a>
        </div>
    </div>

    <div class="row g-2 align-items-end mb-3">
        <div class="col-5">
            <label class="form-label small mb-1">Type</label>
            <select v-model="type" class="form-select form-select-sm" @change="loadPlaces">
                <option value="">Select type</option>
                <option v-for="option in f.state.options.nearby_place_types" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
        </div>
        <div class="col-5">
            <label class="form-label small mb-1">Place</label>
            <select v-model="placeId" class="form-select form-select-sm" :disabled="!places.length">
                <option value="">{{ placeholder }}</option>
                <option v-for="place in places" :key="place.id" :value="String(place.id)">{{ place.name }}{{ place.own ? ' (my place)' : '' }}</option>
            </select>
        </div>
        <div class="col-2">
            <button type="button" class="btn btn-sm btn-portal-primary w-100" :disabled="!placeId" @click="add">Add</button>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <span v-for="place in f.state.nearby" :key="place.id" class="nearby-chip">
            {{ place.name }}
            <button type="button" class="nearby-chip-remove" @click="f.state.nearby.splice(f.state.nearby.indexOf(place), 1)"><i class="fas fa-times"></i></button>
        </span>
    </div>
</template>

<script setup>
/** Property form › Nearby Places: Type → Place cascading picker (places load per type). */
import { inject, ref } from 'vue';
import http from '../../../api/http';

const f = inject('listingForm');
const links = f.state.options.links;
const type = ref('');
const placeId = ref('');
const places = ref([]);
const placeholder = ref('Select type first');

function loadPlaces() {
    places.value = [];
    placeId.value = '';
    if (!type.value) {
        placeholder.value = 'Select type first';
        return;
    }
    placeholder.value = 'Loading...';
    http.get('/properties/nearby-places', { params: { type: type.value } }).then((res) => {
        places.value = res.data.places || [];
        placeholder.value = places.value.length ? 'Select place' : 'No places of this type yet';
    });
}

function add() {
    const place = places.value.find((p) => String(p.id) === placeId.value);
    if (place && !f.state.nearby.some((p) => p.id === place.id)) f.state.nearby.push({ id: place.id, name: place.name });
    placeId.value = '';
}
</script>
