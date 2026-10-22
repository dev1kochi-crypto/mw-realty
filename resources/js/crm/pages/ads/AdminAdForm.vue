<template>
    <div>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <div class="portal-section-title mb-1">{{ id ? 'Edit MW Realty Ad' : 'Add MW Realty Ad' }}</div>
                <p class="text-muted small mb-0">Your own banner in a page's ad slider. It shows after any client ads booked for the same time, in the order you set.</p>
            </div>
            <RouterLink :to="{ name: 'ads.index' }" class="btn portal-btn-ghost btn-sm">Cancel</RouterLink>
        </div>

        <div v-if="!options" class="text-center py-5">
            <span v-if="!loadError" class="spinner-border text-primary"></span>
            <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
        </div>

        <template v-else>
            <div v-if="errorList.length" class="alert alert-danger">
                <ul class="mb-0"><li v-for="(message, i) in errorList" :key="i">{{ message }}</li></ul>
            </div>

            <form class="adp-form" @submit.prevent="save">
                <div>
                    <div class="portal-card p-4 mb-3">
                        <div class="adp-step"><span class="adp-step__num">1</span><div><div class="adp-step__title">Banner</div><div class="adp-step__hint">A wide image, with optional text over it per language.</div></div></div>
                        <AdBannerFields v-model:active-lang="activeLang" :form="form" :errors="errors" :languages="options.languages" :images="options.images" :text-limits="options.text_limits"
                                        :image-url="imageUrl" :mobile-url="mobileUrl" :image-required="!id" name-hint="Names the ad in the list and reports — visitors don't see it."
                                        @pick="pick" @remove-mobile="removeMobile" />
                    </div>

                    <div class="portal-card p-4 mb-3">
                        <div class="adp-step"><span class="adp-step__num">2</span><div><div class="adp-step__title">Where it links</div><div class="adp-step__hint">What opens when a visitor clicks the ad. Leave the address empty for an image-only ad.</div></div></div>
                        <div class="adp-options mb-3">
                            <label v-for="option in linkOptions" :key="option.value" class="adp-option" :class="{ 'is-active': form.link_type === option.value }">
                                <input v-model="form.link_type" type="radio" name="link_type" :value="option.value">
                                <span class="adp-option__icon"><i :class="option.icon"></i></span>
                                <span><span class="adp-option__title d-block">{{ option.title }}</span><span class="adp-option__sub">{{ option.sub }}</span></span>
                            </label>
                        </div>
                        <div v-if="form.link_type === 'property'">
                            <label class="form-label fw-semibold">Listing <span class="text-danger">*</span></label>
                            <RemoteSelect v-model="form.property_id" endpoint="/ads/manage/properties" :initial="propertyInitial" placeholder="Search active listings…" />
                            <div v-if="errors.property_id" class="invalid-feedback d-block">{{ errors.property_id[0] }}</div>
                        </div>
                        <div v-else>
                            <label class="form-label fw-semibold" for="adUrl">Address</label>
                            <input id="adUrl" v-model="form.link_url" type="text" maxlength="500" class="form-control" :class="{ 'is-invalid': errors.link_url }" placeholder="/properties  or  https://…">
                            <div v-if="errors.link_url" class="invalid-feedback">{{ errors.link_url[0] }}</div>
                            <div class="form-text">A page on this site (starting with /) opens in the same tab; another website opens in a new tab.</div>
                        </div>
                    </div>

                    <div class="portal-card p-4 mb-3">
                        <div class="adp-step"><span class="adp-step__num">3</span><div><div class="adp-step__title">Page and schedule</div><div class="adp-step__hint">Dates are whole days in Dubai time. Leave both empty to keep it running.</div></div></div>
                        <label class="form-label fw-semibold">Page <span class="text-danger">*</span></label>
                        <div class="adp-options mb-4">
                            <label v-for="p in options.placements" :key="p.value" class="adp-option" :class="{ 'is-active': form.placement === p.value }">
                                <input v-model="form.placement" type="radio" name="placement" :value="p.value">
                                <span class="adp-option__icon"><i class="fas" :class="placementIcon(p.value)"></i></span>
                                <span class="adp-option__title align-self-center">{{ p.label }}</span>
                            </label>
                        </div>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="adStart">From</label>
                                <input id="adStart" v-model="form.start_date" type="date" class="form-control" :class="{ 'is-invalid': errors.start_date }">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="adEnd">Until (including)</label>
                                <input id="adEnd" v-model="form.end_date" type="date" class="form-control" :min="form.start_date || null" :class="{ 'is-invalid': errors.end_date }">
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch mb-2">
                                    <input id="adStatus" v-model="form.status" class="form-check-input" type="checkbox">
                                    <label class="form-check-label fw-semibold" for="adStatus">{{ form.status ? 'On' : 'Off' }}</label>
                                </div>
                            </div>
                        </div>
                        <div v-if="errors.end_date || errors.start_date" class="invalid-feedback d-block">{{ (errors.end_date || errors.start_date)[0] }}</div>
                    </div>

                    <div class="d-flex gap-2 mb-4">
                        <button type="submit" class="btn btn-portal-primary px-4" :disabled="saving">
                            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span><i v-else class="fas fa-save me-1"></i>{{ id ? 'Save changes' : 'Add ad' }}
                        </button>
                        <RouterLink :to="{ name: 'ads.index' }" class="btn portal-btn-ghost">Cancel</RouterLink>
                    </div>
                </div>

                <aside class="adp-aside">
                    <AdPreview :image-url="imageUrl" :mobile-url="mobileUrl" :text="form.translations[activeLang] ?? {}" label="Advertisement" />
                    <div class="portal-card p-3">
                        <div class="adp-preview__title mb-2"><i class="fas fa-calendar-check me-1"></i>Summary</div>
                        <div class="adp-summary">
                            <div class="adp-summary__row"><span>Page</span><span>{{ placementLabel }}</span></div>
                            <div class="adp-summary__row"><span>Runs</span><span>{{ scheduleLabel }}</span></div>
                            <div class="adp-summary__row"><span>Status</span><span>{{ form.status ? 'On' : 'Off' }}</span></div>
                        </div>
                    </div>
                </aside>
            </form>
        </template>
    </div>
</template>

<script setup>
/**
 * Super Admin: add / edit an MW Realty ad (replaced CMS › Ad Management's form) — banner + text per
 * language, link (a listing or an address), page, optional date window and on/off. Live preview.
 */
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import RemoteSelect from '../../components/RemoteSelect.vue';
import { useToast } from '../../composables/useToast';
import AdBannerFields from './AdBannerFields.vue';
import AdPreview from './AdPreview.vue';
import { adDate } from './adFormat';
import { placementIcon } from './placements';
import { appendBanner, useAdImages } from './useAdImages';

const props = defineProps({ id: { type: Number, default: null } });

const router = useRouter();
const { success, error: toastError } = useToast();

const options = ref(null);
const existing = ref(null);
const loadError = ref('');
const activeLang = ref('');
const errors = ref({});
const errorList = computed(() => Object.values(errors.value).flat());
const saving = ref(false);

const form = reactive({
    name: '', image: null, mobile_image: null, remove_mobile_image: false, image_alt: '', translations: {},
    link_type: 'url', property_id: null, link_url: '', placement: 'home', start_date: '', end_date: '', status: true,
});
const { pick, removeMobile, imageUrl, mobileUrl } = useAdImages(form, existing);

const linkOptions = [
    { value: 'url', title: 'A page or website', sub: '/properties, https://…', icon: 'fas fa-globe' },
    { value: 'property', title: 'A listing', sub: 'Opens the listing page', icon: 'fas fa-building' },
];
const propertyInitial = computed(() => (existing.value?.link_type === 'property' && existing.value.property_id
    ? { id: existing.value.property_id, name: existing.value.link.label } : null));
const placementLabel = computed(() => options.value?.placements.find((p) => p.value === form.placement)?.label ?? '—');
const scheduleLabel = computed(() => {
    if (!form.start_date && !form.end_date) return 'Always on';
    const day = (d) => (d ? adDate(`${d}T12:00:00+04:00`) : '…');
    return `${day(form.start_date)} → ${day(form.end_date)}`;
});

function load() {
    loadError.value = '';
    Promise.all([
        http.get('/ads/manage/form-options'),
        props.id ? http.get(`/ads/manage/${props.id}`) : Promise.resolve(null),
    ])
        .then(([opts, ad]) => {
            const a = ad?.data.ad;
            opts.data.languages.forEach((lang) => {
                const saved = a?.translations?.[lang.code] ?? {};
                form.translations[lang.code] = { eyebrow: saved.eyebrow ?? '', title: saved.title ?? '', text: saved.text ?? '' };
            });
            activeLang.value = opts.data.languages[0]?.code ?? 'en';
            if (a) {
                existing.value = a;
                Object.assign(form, {
                    name: a.name, image_alt: a.image_alt ?? '', link_type: a.link_type === 'property' ? 'property' : 'url', property_id: a.property_id,
                    link_url: a.link_url ?? '', placement: a.placement, start_date: a.start_date ?? '', end_date: a.end_date ?? '', status: a.status,
                });
            }
            options.value = opts.data;
        })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function save() {
    if (!props.id && !form.image) {
        errors.value = { image: ['Choose a banner image.'] };
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    const data = new FormData();
    appendBanner(data, form);
    data.append('link_type', form.link_type);
    if (form.link_type === 'property' && form.property_id) data.append('property_id', String(form.property_id));
    if (form.link_type === 'url') data.append('link_url', form.link_url ?? '');
    data.append('placement', form.placement);
    data.append('start_date', form.start_date ?? '');
    data.append('end_date', form.end_date ?? '');
    data.append('status', form.status ? '1' : '0');
    if (props.id) {
        data.append('_method', 'PUT');
        data.append('remove_mobile_image', form.remove_mobile_image ? '1' : '0');
    }

    saving.value = true;
    errors.value = {};
    http.post(props.id ? `/ads/manage/${props.id}` : '/ads/manage', data)
        .then((res) => {
            success(res.data.message);
            router.push({ name: 'ads.show', params: { id: props.id ?? res.data.id } });
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
