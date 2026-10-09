<template>
    <div v-if="!f" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <RouterLink :to="{ name: section.routes.index }">Back to {{ section.title.toLowerCase() }}</RouterLink></div>
    </div>
    <div v-else>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <div class="portal-section-title mb-1">{{ isEdit ? 'Edit' : 'Add' }} {{ section.itemLabel }}</div>
                <p class="text-muted small mb-0">{{ isEdit ? f.state.property.title : 'Fill in the details below — you can always come back and edit them later.' }}</p>
            </div>
            <div class="d-flex gap-2">
                <RouterLink :to="{ name: section.routes.index }" class="btn portal-btn-ghost btn-sm">Cancel</RouterLink>
                <button type="button" class="btn btn-portal-primary btn-sm px-4" :disabled="f.state.saving" @click="submit">
                    <template v-if="f.state.saving"><span class="spinner-border spinner-border-sm me-1"></span></template><i v-else class="fas fa-save me-1"></i> {{ isEdit ? 'Update' : 'Save' }} {{ section.itemLabel }}
                </button>
            </div>
        </div>

        <!-- Why this listing isn't live (permit not verified / expired / taken down) — the permit is in Core details. -->
        <div v-if="f.state.property?.review_alert" class="alert d-flex gap-3 align-items-start" :class="`alert-${f.state.property.review_alert[0]}`">
            <i class="fas mt-1" :class="f.state.property.review_alert[1]"></i>
            <div class="flex-grow-1">
                <div class="fw-bold">{{ f.state.property.compliance_label }}</div>
                <div class="small">{{ f.state.property.review_alert[2] }}</div>
            </div>
            <button type="button" class="btn btn-sm btn-light flex-shrink-0" @click="openTab('tab-basic', true)">Open permit details</button>
        </div>

        <div v-if="serverErrors.length" class="alert alert-danger">
            <ul class="mb-0">
                <li v-for="(message, i) in serverErrors" :key="i">{{ message }}</li>
            </ul>
        </div>

        <form id="propertyForm" novalidate @submit.prevent="submit" @input.capture="clearInvalid">
            <div v-if="f.state.banner" class="alert alert-danger property-validation-banner">{{ f.state.banner }}</div>

            <LangBar v-if="f.state.languages.length > 1" />

            <div class="property-tabs-shell">
                <div class="nav property-tabs-sidebar" role="tablist" aria-orientation="vertical">
                    <template v-for="group in TABS" :key="group.label">
                        <div class="property-tabs-group-label">{{ group.label }}</div>
                        <button v-for="tab in group.tabs" :key="tab.id" type="button" role="tab" class="property-tab-link" :class="{ active: f.state.tab === tab.id }" @click="openTab(tab.id)">
                            <span class="property-tab-icon"><i class="fas" :class="tab.icon"></i></span> <span>{{ tab.label }}</span>
                        </button>
                    </template>
                </div>

                <div class="property-tabs-content tab-content">
                    <div v-show="f.state.tab === 'tab-basic'" id="tab-basic" class="tab-pane fade show active" role="tabpanel"><CoreTab ref="coreTab" /></div>
                    <div v-show="f.state.tab === 'tab-specifications'" id="tab-specifications" class="tab-pane fade show active" role="tabpanel"><DetailsTab /></div>
                    <div v-show="f.state.tab === 'tab-agent'" id="tab-agent" class="tab-pane fade show active" role="tabpanel"><AgentTab /></div>
                    <div v-show="f.state.tab === 'tab-location'" id="tab-location" class="tab-pane fade show active" role="tabpanel"><LocationTab /></div>
                    <div v-show="f.state.tab === 'tab-amenities'" id="tab-amenities" class="tab-pane fade show active" role="tabpanel"><OptionListTab list-key="amenity" title="Amenities" hint="Tick everything this property offers." /></div>
                    <div v-show="f.state.tab === 'tab-easy-access'" id="tab-easy-access" class="tab-pane fade show active" role="tabpanel"><OptionListTab list-key="easy_access" title="Easy Access" hint="What is close by and easy to reach." /></div>
                    <div v-show="f.state.tab === 'tab-attributes'" id="tab-attributes" class="tab-pane fade show active" role="tabpanel"><OptionListTab list-key="property_attribute" title="Attributes" hint="Other notable features of this unit." /></div>
                    <div v-show="f.state.tab === 'tab-floorplans'" id="tab-floorplans" class="tab-pane fade show active" role="tabpanel"><FloorPlansTab /></div>
                    <div v-show="f.state.tab === 'tab-images'" id="tab-images" class="tab-pane fade show active" role="tabpanel"><ImagesTab /></div>
                    <div v-show="f.state.tab === 'tab-brochure'" id="tab-brochure" class="tab-pane fade show active" role="tabpanel">
                        <div class="property-tab-pane-head">
                            <div class="property-tab-pane-title">Property Brochure</div>
                            <div class="property-tab-pane-hint">Upload a brochure (PDF, image, Word, Excel or PowerPoint) for visitors to download after submitting their contact details.</div>
                        </div>
                        <div class="p-3 border rounded-3">
                            <label class="form-label fw-semibold" for="brochureUpload">Brochure File</label>
                            <input id="brochureUpload" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx" @change="f.state.files.brochure = $event.target.files[0] || null">
                            <div class="form-text">PDF, JPG, PNG, WEBP, DOC, DOCX, XLS, XLSX, CSV, PPT or PPTX. Maximum file size: 20 MB.</div>
                            <a v-if="f.state.property?.files.brochure" class="small d-inline-block mt-2" :href="f.state.property.files.brochure" target="_blank" rel="noopener">View current brochure</a>
                            <div v-if="f.fieldError('brochure')" class="text-danger small mt-1">{{ f.fieldError('brochure') }}</div>
                        </div>
                    </div>
                    <div v-show="f.state.tab === 'tab-nearby'" id="tab-nearby" class="tab-pane fade show active" role="tabpanel"><NearbyTab /></div>
                    <div v-show="f.state.tab === 'tab-publishing'" id="tab-publishing" class="tab-pane fade show active" role="tabpanel"><PublishingTab /></div>
                </div>
            </div>

            <div class="mt-4 pt-2">
                <button type="submit" class="btn btn-portal-primary px-4" :disabled="f.state.saving">{{ isEdit ? 'Update' : 'Save' }} {{ section.itemLabel }}</button>
                <RouterLink :to="{ name: section.routes.index }" class="portal-btn-ghost btn">Cancel</RouterLink>
            </div>
        </form>
    </div>
</template>

<script setup>
/**
 * Add / edit a Properties or Commercial listing (resources/views/portal/properties/create, edit,
 * _form): a tabbed form whose tabs share one state (form/useListingForm.js). Saves multipart to
 * POST /properties (create) or /properties/{id} (_method=PUT), then back to the menu's list.
 */
import { computed, nextTick, provide, ref, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import { useToast } from '../../composables/useToast';
import AgentTab from './form/AgentTab.vue';
import CoreTab from './form/CoreTab.vue';
import DetailsTab from './form/DetailsTab.vue';
import FloorPlansTab from './form/FloorPlansTab.vue';
import ImagesTab from './form/ImagesTab.vue';
import LangBar from './form/LangBar.vue';
import LocationTab from './form/LocationTab.vue';
import NearbyTab from './form/NearbyTab.vue';
import OptionListTab from './form/OptionListTab.vue';
import PublishingTab from './form/PublishingTab.vue';
import { tabOf, useListingForm } from './form/useListingForm';
import { sectionFor } from './sections';

const props = defineProps({
    id: { type: Number, default: null }, // null = add a new listing
});

const TABS = [
    { label: 'Listing Info', tabs: [
        { id: 'tab-basic', icon: 'fa-file-lines', label: 'Core details' },
        { id: 'tab-specifications', icon: 'fa-list-check', label: 'Property Details' },
        { id: 'tab-agent', icon: 'fa-user-tie', label: 'Agent & Agency' },
        { id: 'tab-location', icon: 'fa-map-pin', label: 'Location' },
        { id: 'tab-amenities', icon: 'fa-swimming-pool', label: 'Amenities' },
        { id: 'tab-easy-access', icon: 'fa-route', label: 'Easy Access' },
        { id: 'tab-attributes', icon: 'fa-star', label: 'Attributes' },
        { id: 'tab-floorplans', icon: 'fa-border-all', label: 'Floor Plans' },
    ] },
    { label: 'Media & Publishing', tabs: [
        { id: 'tab-images', icon: 'fa-images', label: 'Images' },
        { id: 'tab-brochure', icon: 'fa-file-pdf', label: 'Brochure' },
        { id: 'tab-nearby', icon: 'fa-location-dot', label: 'Nearby Places' },
        { id: 'tab-publishing', icon: 'fa-bullhorn', label: 'Publishing' },
    ] },
];
const TAB_IDS = TABS.flatMap((g) => g.tabs.map((t) => t.id));

const route = useRoute();
const router = useRouter();
const { success, error: toastError } = useToast();

const f = shallowRef(null);
const loadError = ref('');
const coreTab = ref(null);
const isEdit = computed(() => props.id !== null);
const section = computed(() => sectionFor(f.value?.state.property?.segment ?? route.meta.segment));
const serverErrors = computed(() => Object.values(f.value?.state.errors ?? {}).flat().filter(Boolean));

// The tab components read and change the form through this.
const formProxy = {};
provide('listingForm', formProxy);

function openTab(id, scroll = false) {
    f.value.state.tab = id;
    if (scroll) nextTick(() => document.querySelector(`.property-tab-link.active`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
}

function load() {
    f.value = null;
    loadError.value = '';
    const request = isEdit.value ? http.get(`/properties/${props.id}/edit`) : http.get('/properties/create', { params: { segment: route.meta.segment } });
    request
        .then((res) => {
            const data = res.data;
            const property = data.property ?? null;
            // A listing opened through the other menu's URL goes to its own menu.
            if (property && sectionFor(property.segment).routes.edit !== route.name) {
                router.replace({ name: sectionFor(property.segment).routes.edit, params: { id: property.id }, hash: route.hash });
                return;
            }
            const form = useListingForm(data, property, property?.segment ?? route.meta.segment);
            Object.assign(formProxy, form);
            // Open a tab from the URL (#tab-basic, e.g. the "Fix now" link on a listing card).
            const hash = route.hash === '#tab-compliance' ? '#tab-basic' : route.hash;
            if (TAB_IDS.includes(hash.slice(1))) form.state.tab = hash.slice(1);
            f.value = form;
            if (property) document.title = `Edit ${sectionFor(property.segment).itemLabel} - Partner Portal`;
        })
        .catch((e) => {
            const status = e.response?.status;
            // Not approved yet / plan limit reached: back to where the Blade pages sent you, with the reason.
            if (!isEdit.value && (status === 403 || status === 422)) {
                toastError(errorMessage(e));
                router.replace(status === 403 ? { name: 'dashboard' } : { name: section.value.routes.index });
                return;
            }
            loadError.value = errorMessage(e);
        });
}

/** A required field that's still empty is red until typed in. */
function clearInvalid(event) {
    const invalid = f.value?.state.invalid;
    if (!invalid) return;
    const name = invalid.startsWith('translations.') ? invalid.replace(/^translations\.(\w+)\.(\w+)$/, 'translations[$1][$2]') : invalid;
    if (event.target.name === name) f.value.state.invalid = null;
}

/** Jump to the tab (and language) holding a field, then focus it — or open its picker. */
function focusField(target) {
    const state = f.value.state;
    state.tab = target.tab;
    if (target.lang) state.lang = target.lang;
    nextTick(() => setTimeout(() => {
        if (target.kind === 'select') {
            coreTab.value?.openSelect(target.name);
            return;
        }
        const selector = target.kind === 'radio'
            ? `[data-option-buttons="${target.name}"]`
            : `[name="${target.name.startsWith('translations.') ? target.name.replace(/^translations\.(\w+)\.(\w+)$/, 'translations[$1][$2]') : target.name}"]`;
        const el = document.querySelector(selector);
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (target.kind !== 'radio') {
            state.invalid = target.name;
            el.focus();
        }
    }, 150));
}

function submit() {
    const form = f.value;
    const missing = form.missingRequired();
    if (missing.length) {
        const first = missing[0];
        form.state.banner = missing.length > 1
            ? `Please fill in "${first.label}" — ${missing.length} required fields are still empty. Save will work once they're all filled.`
            : `Please fill in "${first.label}" — it's required before this can be saved.`;
        focusField(first);
        return;
    }
    form.state.banner = '';
    form.save()
        .then((data) => {
            success(data.message);
            router.push({ name: sectionFor(data.segment).routes.index });
        })
        .catch((e) => {
            const errors = form.state.errors;
            const first = Object.keys(errors)[0];
            if (first) {
                // After a failed save, show the tab holding the first field with an error.
                form.state.tab = tabOf(first);
                const lang = first.match(/^translations\.(\w+)\./)?.[1];
                if (lang) form.state.lang = lang;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                toastError(errorMessage(e));
            }
        });
}

watch(() => [props.id, route.name], load, { immediate: true });
</script>
