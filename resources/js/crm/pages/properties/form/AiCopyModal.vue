<template>
    <CrmModal :open="open" size="lg" scrollable content-class="border-0 shadow-lg" bare @close="$emit('close')">
        <template #header="{ close }">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 46px; height: 46px; background: rgba(79, 70, 229, 0.1);">
                        <i class="fas fa-wand-magic-sparkles" style="color: var(--portal-primary); font-size: 1.1rem;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0">Write with AI</h5>
                        <p class="text-muted mb-0" style="font-size: 0.8rem;">Title, key features and description in {{ langName }}, from this listing's details</p>
                    </div>
                </div>
                <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
            </div>
        </template>

        <div class="modal-body pt-3">
            <div v-if="error" class="alert alert-danger d-flex gap-2 align-items-start mb-3" role="alert" style="font-size: 0.85rem; border-radius: 10px;">
                <i class="fas fa-triangle-exclamation mt-1"></i><div>{{ error }}</div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold d-block">What should it do?</label>
                    <div class="property-segmented" role="radiogroup" aria-label="Mode">
                        <input id="aiModeWrite" v-model="mode" type="radio" class="btn-check" value="write"><label for="aiModeWrite">Write new</label>
                        <input id="aiModeImprove" v-model="mode" type="radio" class="btn-check" value="improve" :disabled="!hasDraft"><label for="aiModeImprove">Improve my description</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="aiTone">Tone</label>
                    <select id="aiTone" v-model="tone" class="form-select">
                        <option value="professional">Professional</option>
                        <option value="luxury">Luxury</option>
                        <option value="friendly">Friendly</option>
                        <option value="concise">Short &amp; concise</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="aiNotes">Anything else to mention? <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea id="aiNotes" v-model="notes" class="form-control" rows="2" maxlength="2000" placeholder="e.g. Upgraded kitchen, vacant on transfer, walking distance to the metro"></textarea>
                    <div class="form-text">The AI only uses the details filled in on this form and what you write here — it won't invent amenities, views or prices.</div>
                </div>
            </div>

            <div v-if="result" class="mt-4">
                <div class="property-subsection-title mt-0"><i class="fas fa-eye"></i> Preview</div>
                <div class="portal-card p-3" :dir="dir">
                    <div class="fw-semibold mb-2">{{ result.title }}</div>
                    <ul v-if="result.key_features" class="small mb-2 ps-3">
                        <li v-for="line in result.key_features.split('\n')" :key="line">{{ line }}</li>
                    </ul>
                    <!-- Built server-side from escaped text (ListingTextNormalizer::fromParts). -->
                    <div class="small" v-html="result.description"></div>
                </div>
                <div class="d-flex flex-wrap gap-3 mt-2 small">
                    <label class="form-check mb-0"><input v-model="use.title" class="form-check-input" type="checkbox"> <span class="form-check-label">Use the title</span></label>
                    <label class="form-check mb-0"><input v-model="use.key_features" class="form-check-input" type="checkbox"> <span class="form-check-label">Use the key features</span></label>
                    <label class="form-check mb-0"><input v-model="use.description" class="form-check-input" type="checkbox"> <span class="form-check-label">Use the description</span></label>
                </div>
            </div>
        </div>
        <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-outline-secondary px-4" @click="$emit('close')">Cancel</button>
            <button type="button" class="btn portal-btn-ghost px-4" :disabled="busy" @click="generate">
                <span v-if="busy" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                <i v-else class="fas fa-wand-magic-sparkles me-1"></i> {{ busy ? 'Writing…' : (result ? 'Try again' : 'Write') }}
            </button>
            <button v-if="result" type="button" class="btn btn-portal-primary px-4" :disabled="busy || !(use.title || use.key_features || use.description)" @click="apply">
                <i class="fas fa-check me-1"></i> Use this
            </button>
        </div>
    </CrmModal>
</template>

<script setup>
/**
 * Property form › Description › Write with AI — POST /properties/write-copy with the form's current
 * specs; the preview is applied to the chosen language's fields only when the agent clicks "Use this"
 * (then auto-translate, if on, carries it to the other languages as if typed).
 */
import { computed, inject, reactive, ref, watch } from 'vue';
import CrmModal from '../../../components/CrmModal.vue';
import http, { errorMessage } from '../../../api/http';

const props = defineProps({
    open: { type: Boolean, default: false },
    lang: { type: String, required: true },
});
const emit = defineEmits(['close']);

const f = inject('listingForm');
const form = f.form;
const mode = ref('write');
const tone = ref('professional');
const notes = ref('');
const busy = ref(false);
const error = ref('');
const result = ref(null);
const use = reactive({ title: true, key_features: true, description: true });

const language = computed(() => f.state.languages.find((l) => l.code === props.lang));
const langName = computed(() => language.value?.name ?? props.lang);
const dir = computed(() => (props.lang === 'ar' ? 'rtl' : 'ltr'));
const current = computed(() => form.translations[props.lang] ?? {});
const hasDraft = computed(() => String(current.value.description ?? '').replace(/<[^>]*>/g, '').trim() !== '');

watch(() => props.open, (open) => {
    if (!open) return;
    error.value = '';
    result.value = null;
    mode.value = hasDraft.value ? 'improve' : 'write';
    // Keep the agent's own title unless the field is empty.
    Object.assign(use, { title: !String(current.value.title ?? '').trim(), key_features: !String(current.value.key_features ?? '').trim(), description: true });
});

function specs() {
    // Location text: this language's, else the first language's.
    const t = current.value;
    const first = form.translations[f.state.langs[0]] ?? {};
    return {
        listing_type: form.listing_type, rental_period: form.listing_type === 'rent' ? form.rental_period : '', property_type: form.property_type,
        category: form.category, completion_status: form.completion_status, bedrooms: form.bedrooms, bathrooms: form.bathrooms, sqft: form.sqft,
        price: form.price, currency: form.currency, location: form.location, emirate: form.emirate, furnished: form.furnished, view: form.view,
        floor: form.floor, parking: form.parking, year_built: form.year_built, developer: form.developer, amenities: [...form.amenities],
        community: t.community || first.community, address: t.address || first.address, city: t.city || first.city,
        notes: [notes.value, t.key_features].filter((s) => s && s.trim()).join('\n'),
    };
}

function generate() {
    busy.value = true;
    error.value = '';
    http.post('/properties/write-copy', { lang: props.lang, mode: mode.value, tone: tone.value, specs: specs(), draft: mode.value === 'improve' ? current.value.description : null })
        .then((res) => {
            result.value = res.data;
        })
        .catch((e) => {
            error.value = errorMessage(e, 'The AI couldn\'t write this right now — please try again.');
        })
        .finally(() => {
            busy.value = false;
        });
}

function apply() {
    ['title', 'key_features', 'description'].forEach((field) => {
        if (!use[field] || !result.value[field]) return;
        form.translations[props.lang][field] = result.value[field];
        f.onTranslatedInput(props.lang, field);
    });
    emit('close');
}
</script>
