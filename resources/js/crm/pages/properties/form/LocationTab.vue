<template>
    <div class="property-tab-pane-head">
        <div class="property-tab-pane-title">Location</div>
        <div class="property-tab-pane-hint">Area, address and map details, per language</div>
    </div>

    <ul id="locationLangTabs" class="nav property-lang-tabs mb-4" role="tablist">
        <li v-for="lang in f.state.languages" :key="lang.code" class="nav-item" role="presentation">
            <button :id="`loc-${lang.code}-tab`" class="nav-link" :class="{ active: f.state.lang === lang.code }" type="button" role="tab" @click="f.state.lang = lang.code">{{ lang.name }}</button>
        </li>
    </ul>

    <div class="tab-content">
        <div v-for="lang in f.state.languages" v-show="f.state.lang === lang.code" :key="lang.code" class="tab-pane fade show active" role="tabpanel">
            <div class="row g-3">
                <div v-for="[part, label, required, col] in parts" :key="part" :class="col">
                    <label class="form-label fw-semibold">{{ label }} <span v-if="required" class="text-danger">*</span></label>
                    <input v-model="form.translations[lang.code][part]" type="text" class="form-control address-preview-field" :name="`translations[${lang.code}][${part}]`"
                           :class="{ 'is-invalid': f.fieldError(`translations.${lang.code}.${part}`) || f.state.invalid === `translations.${lang.code}.${part}`, 'is-auto-translated': !!f.state.autoFilled[lang.code]?.[part] }"
                           @input="typed(lang.code, part)">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Full Address Preview</label>
                    <input type="text" class="form-control address-preview-output" :value="preview(lang.code)" readonly>
                </div>
            </div>
        </div>
    </div>

    <hr class="my-4">
    <div class="property-tab-pane-title mb-3" style="font-size: 0.95rem;">Map</div>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Postal Code</label>
            <input v-model="form.postal_code" type="text" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Latitude <span class="text-danger">*</span></label>
            <input v-model="form.latitude" name="latitude" type="text" class="form-control" :class="{ 'is-invalid': f.fieldError('latitude') || f.state.invalid === 'latitude' }">
            <div v-if="f.fieldError('latitude')" class="invalid-feedback">{{ f.fieldError('latitude') }}</div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Longitude <span class="text-danger">*</span></label>
            <input v-model="form.longitude" name="longitude" type="text" class="form-control" :class="{ 'is-invalid': f.fieldError('longitude') || f.state.invalid === 'longitude' }">
            <div v-if="f.fieldError('longitude')" class="invalid-feedback">{{ f.fieldError('longitude') }}</div>
        </div>
    </div>
</template>

<script setup>
/** Property form › Location: address per language (with a live preview) and the map coordinates. */
import { inject } from 'vue';

const f = inject('listingForm');
const form = f.form;
const parts = [
    ['address', 'Address', true, 'col-md-6'],
    ['community', 'Community', false, 'col-md-6'],
    ['city', 'City', true, 'col-md-6'],
    ['country', 'Country', true, 'col-md-6'],
];

/** "Full Address Preview": address, community, city, postal code, country. */
function preview(code) {
    const t = form.translations[code];
    const items = ['address', 'community', 'city'].map((part) => String(t[part] || '').trim()).filter(Boolean);
    if (String(form.postal_code || '').trim()) items.push(String(form.postal_code).trim());
    if (String(t.country || '').trim()) items.push(String(t.country).trim());
    return items.join(', ');
}

function typed(code, part) {
    if (f.state.invalid === `translations.${code}.${part}`) f.state.invalid = null;
    f.onTranslatedInput(code, part);
}
</script>
