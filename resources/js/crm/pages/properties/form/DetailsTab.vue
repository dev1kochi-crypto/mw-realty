<template>
    <div class="property-tab-pane-head">
        <div class="property-tab-pane-title">Property Details</div>
        <div class="property-tab-pane-hint">Description, specifications and price</div>
    </div>

    <!-- Description (title, key features, description — per language) -->
    <div id="section-description" class="property-subsection-title"><i class="fas fa-align-left"></i> Description <span>Title, key features and description, per language</span></div>
    <ul id="langTabs" class="nav property-lang-tabs mb-4" role="tablist">
        <li v-for="lang in f.state.languages" :key="lang.code" class="nav-item" role="presentation">
            <button :id="`${lang.code}-tab`" class="nav-link" :class="{ active: f.state.lang === lang.code }" type="button" role="tab" @click="f.state.lang = lang.code">{{ lang.name }}</button>
        </li>
    </ul>

    <div class="tab-content">
        <div v-for="lang in f.state.languages" v-show="f.state.lang === lang.code" :key="lang.code" class="tab-pane fade show active" role="tabpanel">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                    <input v-model="form.translations[lang.code].title" type="text" class="form-control" :name="`translations[${lang.code}][title]`"
                           :class="{ 'is-invalid': f.fieldError(`translations.${lang.code}.title`) || f.state.invalid === `translations.${lang.code}.title`, 'is-auto-translated': auto(lang.code, 'title') }"
                           @input="typed(lang.code, 'title')">
                    <div v-if="f.fieldError(`translations.${lang.code}.title`)" class="invalid-feedback">{{ f.fieldError(`translations.${lang.code}.title`) }}</div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Key Features</label>
                    <textarea v-model="form.translations[lang.code].key_features" class="form-control" :class="{ 'is-auto-translated': auto(lang.code, 'key_features') }" rows="2" placeholder="Short highlights, one per line" @input="typed(lang.code, 'key_features')"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <RichEditor :id="`description-${lang.code}`" v-model="form.translations[lang.code].description" :auto-filled="auto(lang.code, 'description')" @user-input="typed(lang.code, 'description')" />
                </div>
            </div>
        </div>
    </div>
    <div class="form-text mt-3">The language picked here also sets the language of the dropdown options in the other tabs.</div>

    <div id="section-specifications" class="property-subsection-title"><i class="fas fa-ruler-combined"></i> Specifications <span>Rooms, size, furnishing, building and media links</span></div>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Rooms (bedrooms) <i v-if="f.isLocked('bedrooms')" class="fas fa-lock text-muted ms-1 small" :title="f.lockTitle('bedrooms')"></i></label>
            <input v-model="form.bedrooms" type="number" class="form-control" min="0" :readonly="f.isLocked('bedrooms')">
            <div class="form-text">0 = Studio</div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Bathrooms</label>
            <input v-model="form.bathrooms" type="number" class="form-control" min="0">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Property size (sqft) <i v-if="f.isLocked('sqft')" class="fas fa-lock text-muted ms-1 small" :title="f.lockTitle('sqft')"></i></label>
            <input v-model="form.sqft" type="number" class="form-control" min="0" :readonly="f.isLocked('sqft')">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Developer</label>
            <input v-model="form.developer" type="text" class="form-control" maxlength="255" placeholder="e.g. Emaar">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Unit number</label>
            <input v-model="form.unit_number" type="text" class="form-control" :class="{ 'is-invalid': f.fieldError('unit_number') }" maxlength="100">
            <div v-if="f.fieldError('unit_number')" class="invalid-feedback">{{ f.fieldError('unit_number') }}</div>
            <div class="form-text">For internal use only — never shown on the website.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">No. of parking spaces</label>
            <input v-model="form.parking" type="number" class="form-control" min="0">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Garage</label>
            <input v-model="form.garage" type="number" class="form-control" min="0">
        </div>
        <div class="col-md-4"><OptionSelectField field="furnishing" name="furnished" label="Furnishing type" /></div>
        <div class="col-12">
            <label class="form-label fw-semibold mb-1">Property enhancements</label>
            <div class="form-text mt-0 mb-2">Select the key improvements to highlight.</div>
            <label class="property-check-card">
                <input v-model="form.upgraded" type="checkbox" class="form-check-input">
                <span><strong>Upgraded</strong><small>Renovated finishes, fixtures or appliances</small></span>
            </label>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Year built</label>
            <input v-model="form.year_built" type="number" class="form-control" min="1800" max="2200" placeholder="e.g. 2019">
            <div class="form-text">The property's age is worked out from this.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Floor number</label>
            <input v-model="form.floor" type="text" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">View</label>
            <input v-model="form.view" type="text" class="form-control" placeholder="e.g. Sea View">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Owner name</label>
            <input v-model="form.owner_name" type="text" class="form-control" maxlength="255" placeholder="Enter owner name">
            <div class="form-text">For internal use only — never shown on the website.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Direct from owner</label>
            <input v-model="form.direct_from_owner" type="text" class="form-control" placeholder="Example: Yes, Owner Listed">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">360 URL link</label>
            <input v-model="form.virtual_tour_url" type="url" class="form-control" placeholder="https://example.com/tour">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Video tour URL</label>
            <input v-model="form.video_tour_url" type="url" class="form-control" :class="{ 'is-invalid': f.fieldError('video_tour_url') }" placeholder="https://youtube.com/...">
            <div v-if="f.fieldError('video_tour_url')" class="invalid-feedback">{{ f.fieldError('video_tour_url') }}</div>
        </div>
    </div>

    <div id="section-price" class="property-subsection-title"><i class="fas fa-tag"></i> Price <span>Price, currency, cheques and deposit</span></div>
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label fw-semibold">Property price <span class="text-danger">*</span></label>
            <input v-model="form.price" name="price" type="number" step="0.01" class="form-control" :class="{ 'is-invalid': f.state.invalid === 'price' }" min="0">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
            <input v-model="form.currency" name="currency" type="text" class="form-control" :class="{ 'is-invalid': f.state.invalid === 'currency' }">
        </div>
        <div v-if="f.isRent.value" class="col-md-6 rent-only">
            <label class="form-label fw-semibold">Number of cheques</label>
            <select v-model="form.cheques" class="form-select">
                <option value="">Select</option>
                <option v-for="n in [1, 2, 3, 4, 6, 12]" :key="n" :value="String(n)">{{ n }}</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Security deposit</label>
            <input v-model="form.security_deposit" type="number" step="0.01" class="form-control">
        </div>
    </div>
</template>

<script setup>
/** Property form › Property Details: description per language, specifications and price. */
import { inject } from 'vue';
import OptionSelectField from './OptionSelectField.vue';
import RichEditor from './RichEditor.vue';

const f = inject('listingForm');
const form = f.form;
const auto = (code, field) => !!f.state.autoFilled[code]?.[field];

function typed(code, field) {
    if (f.state.invalid === `translations.${code}.${field}`) f.state.invalid = null;
    f.onTranslatedInput(code, field);
}
</script>
