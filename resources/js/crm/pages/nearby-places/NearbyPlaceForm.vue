<template>
    <div>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <div class="portal-section-title mb-1">{{ id ? 'Edit Nearby Place' : 'Add Nearby Place' }}</div>
                <p class="text-muted small mb-0">{{ id ? placeName : 'Fill in the details below.' }}</p>
            </div>
            <RouterLink :to="{ name: 'nearby-places.index' }" class="btn portal-btn-ghost btn-sm">Cancel</RouterLink>
        </div>

        <div v-if="!options" class="text-center py-5">
            <span v-if="!loadError" class="spinner-border text-primary"></span>
            <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
        </div>
        <template v-else>
            <div v-if="errorList.length" class="alert alert-danger">
                <ul class="mb-0">
                    <li v-for="(message, i) in errorList" :key="i">{{ message }}</li>
                </ul>
            </div>

            <form ref="formEl" @submit.prevent="save" @invalid.capture="onInvalid">
                <div class="nearby-place-form">
                    <div class="portal-card p-4 mb-3">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-baseline">
                                    <label class="form-label fw-semibold" for="placeType">Type <span class="text-danger">*</span></label>
                                    <RouterLink v-if="options.is_admin" :to="{ name: 'master.property-options', query: { list: options.filter_key } }" class="small" target="_blank"><i class="fas fa-cog me-1"></i>Manage types</RouterLink>
                                </div>
                                <select id="placeType" v-model="form.category" class="form-select" :class="{ 'is-invalid': errors.category }" required>
                                    <option value="">Select type</option>
                                    <option v-for="option in options.types" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                                <div v-if="errors.category" class="invalid-feedback">{{ errors.category[0] }}</div>
                                <div v-if="!options.is_admin" class="form-text">Missing a type? Ask MW Realty to add it via a support ticket.</div>
                            </div>
                        </div>

                        <ul v-if="options.languages.length > 1" class="nav portal-lang-tabs mb-4" role="tablist">
                            <li v-for="lang in options.languages" :key="lang.code" class="nav-item" role="presentation">
                                <button class="nav-link" :class="{ active: activeLang === lang.code }" type="button" role="tab" @click="activeLang = lang.code">
                                    <i class="fas fa-language me-1 opacity-75"></i>{{ lang.name }}
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div v-for="lang in options.languages" :key="lang.code" class="tab-pane fade" :class="{ 'show active': activeLang === lang.code }" :data-lang="lang.code" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                                        <input v-model="form.translations[lang.code].name" type="text" class="form-control" :class="{ 'is-invalid': errors[`translations.${lang.code}.name`] }" required>
                                        <div v-if="errors[`translations.${lang.code}.name`]" class="invalid-feedback">{{ errors[`translations.${lang.code}.name`][0] }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Address</label>
                                        <input v-model="form.translations[lang.code].address" type="text" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Latitude <span class="text-danger">*</span></label>
                                <input v-model="form.latitude" type="text" class="form-control" :class="{ 'is-invalid': errors.latitude }" required>
                                <div v-if="errors.latitude" class="invalid-feedback">{{ errors.latitude[0] }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Longitude <span class="text-danger">*</span></label>
                                <input v-model="form.longitude" type="text" class="form-control" :class="{ 'is-invalid': errors.longitude }" required>
                                <div v-if="errors.longitude" class="invalid-feedback">{{ errors.longitude[0] }}</div>
                            </div>
                        </div>

                        <hr class="my-4">
                        <div class="form-check form-switch">
                            <input id="placeStatus" v-model="form.status" class="form-check-input" type="checkbox">
                            <label class="form-check-label fw-semibold" for="placeStatus">Active</label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-portal-primary px-4" :disabled="saving"><i class="fas fa-save me-1"></i> Save Place</button>
                        <RouterLink :to="{ name: 'nearby-places.index' }" class="btn portal-btn-ghost">Cancel</RouterLink>
                    </div>
                </div>
            </form>
        </template>
    </div>
</template>

<script setup>
/**
 * Add / edit a nearby place (resources/views/portal/nearby-places/create, edit + _form) — a name and
 * address per active language, the type, coordinates and status.
 */
import { computed, nextTick, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import { useToast } from '../../composables/useToast';

const props = defineProps({ id: { type: Number, default: null } });

const router = useRouter();
const { success, error: toastError } = useToast();

const options = ref(null);
const loadError = ref('');
const placeName = ref('');
const activeLang = ref('');
const formEl = ref(null);
const form = reactive({ category: '', translations: {}, latitude: '', longitude: '', status: true });
const errors = ref({});
const errorList = computed(() => Object.values(errors.value).flat());
const saving = ref(false);

function load() {
    loadError.value = '';
    Promise.all([
        http.get('/nearby-places/form-options'),
        props.id ? http.get(`/nearby-places/${props.id}`) : Promise.resolve(null),
    ])
        .then(([opts, place]) => {
            opts.data.languages.forEach((lang) => {
                const saved = place?.data.translations?.[lang.code] ?? {};
                form.translations[lang.code] = { name: saved.name ?? '', address: saved.address ?? '' };
            });
            if (place) {
                placeName.value = place.data.name;
                Object.assign(form, { category: place.data.category ?? '', latitude: place.data.latitude ?? '', longitude: place.data.longitude ?? '', status: place.data.status });
            }
            activeLang.value = opts.data.languages[0]?.code ?? '';
            options.value = opts.data;
        })
        .catch((e) => { loadError.value = errorMessage(e); });
}

/** A required field on a hidden language tab → show that tab so the browser can point at it. */
function onInvalid(event) {
    const pane = event.target.closest('.tab-pane');
    if (pane && pane.dataset.lang !== activeLang.value) {
        activeLang.value = pane.dataset.lang;
        nextTick(() => setTimeout(() => event.target.focus(), 150));
    }
}

function save() {
    saving.value = true;
    errors.value = {};
    const request = props.id ? http.put(`/nearby-places/${props.id}`, form) : http.post('/nearby-places', form);
    request
        .then((res) => {
            success(res.data.message);
            router.push({ name: 'nearby-places.index' });
        })
        .catch((e) => {
            if (e.response?.status === 422 && e.response.data.errors) {
                errors.value = e.response.data.errors;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                toastError(errorMessage(e));
            }
        })
        .finally(() => { saving.value = false; });
}

load();
</script>
