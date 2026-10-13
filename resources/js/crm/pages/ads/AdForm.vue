<template>
    <div>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <div class="portal-section-title mb-1">{{ id ? 'Edit Ad' : 'Book an Ad' }}</div>
                <p class="text-muted small mb-0">{{ id ? 'Change the banner, its text or where it links. It is reviewed again before it goes live.' : 'Your banner joins the advertisement slider on the page you pick, for the hours you book. MW Realty reviews it first.' }}</p>
            </div>
            <RouterLink :to="id ? { name: 'ads.show', params: { id } } : { name: 'ads.index' }" class="btn portal-btn-ghost btn-sm">Cancel</RouterLink>
        </div>

        <div v-if="!options" class="text-center py-5">
            <span v-if="!loadError" class="spinner-border text-primary"></span>
            <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
        </div>

        <PlanLockedCard v-else-if="!options.quota.included" title="Book an Ad" heading="Advertise on the MW Realty website" icon="fas fa-rectangle-ad"
                        description="Put your banner in the advertisement slider on the website's pages for a few hours or days."
                        feature="Website advertising" :locked="{ plan: options.quota.plan, agency: null }" />

        <template v-else>
            <div v-if="errorList.length" class="alert alert-danger">
                <ul class="mb-0"><li v-for="(message, i) in errorList" :key="i">{{ message }}</li></ul>
            </div>

            <form class="adp-form" @submit.prevent="save">
                <div>
                    <!-- 1. Banner -->
                    <div class="portal-card p-4 mb-3">
                        <div class="adp-step"><span class="adp-step__num">1</span><div><div class="adp-step__title">Your banner</div><div class="adp-step__hint">A wide image that works on its own, with short text over it if you like.</div></div></div>

                        <AdBannerFields v-model:active-lang="activeLang" :form="form" :errors="errors" :languages="options.languages" :images="options.images" :text-limits="options.text_limits"
                                        :image-url="imageUrl" :mobile-url="mobileUrl" :image-required="!id" @pick="pick" @remove-mobile="removeMobile" />
                    </div>

                    <!-- 2. Link -->
                    <div class="portal-card p-4 mb-3">
                        <div class="adp-step"><span class="adp-step__num">2</span><div><div class="adp-step__title">Where it links</div><div class="adp-step__hint">What opens when a visitor clicks your ad.</div></div></div>
                        <div class="adp-options mb-3">
                            <label v-for="option in linkOptions" :key="option.value" class="adp-option" :class="{ 'is-active': form.link_type === option.value }">
                                <input v-model="form.link_type" type="radio" name="link_type" :value="option.value">
                                <span class="adp-option__icon"><i :class="option.icon"></i></span>
                                <span><span class="adp-option__title d-block">{{ option.title }}</span><span class="adp-option__sub">{{ option.sub }}</span></span>
                            </label>
                        </div>
                        <div v-if="form.link_type === 'property'">
                            <label class="form-label fw-semibold">Listing <span class="text-danger">*</span></label>
                            <RemoteSelect v-model="form.property_id" endpoint="/ads/properties" :initial="propertyInitial" placeholder="Search your active listings…" :button-class="{ 'is-invalid': errors.property_id }" />
                            <div v-if="errors.property_id" class="invalid-feedback d-block">{{ errors.property_id[0] }}</div>
                        </div>
                        <div v-else-if="form.link_type === 'profile'" class="small portal-muted">
                            <i class="fas fa-arrow-up-right-from-square me-1"></i>
                            <a v-if="options.profile_url" :href="options.profile_url" target="_blank" rel="noopener">{{ options.profile_url }}</a>
                            <template v-else>Your public profile page.</template>
                        </div>
                        <div v-else>
                            <label class="form-label fw-semibold" for="adUrl">Web address <span class="text-danger">*</span></label>
                            <input id="adUrl" v-model="form.link_url" type="url" maxlength="500" class="form-control" :class="{ 'is-invalid': errors.link_url }" placeholder="https://your-website.com/offer">
                            <div v-if="errors.link_url" class="invalid-feedback">{{ errors.link_url[0] }}</div>
                            <div class="form-text">Opens in a new tab.</div>
                        </div>
                    </div>

                    <!-- 3. Page & time (new bookings) -->
                    <div v-if="!id" class="portal-card p-4 mb-3">
                        <div class="adp-step"><span class="adp-step__num">3</span><div><div class="adp-step__title">Page and time</div><div class="adp-step__hint">Times are Dubai time. Each page shows a few ads at once, so popular hours fill up.</div></div></div>

                        <label class="form-label fw-semibold">Page <span class="text-danger">*</span></label>
                        <div class="adp-options mb-4">
                            <label v-for="p in options.placements" :key="p.value" class="adp-option" :class="{ 'is-active': form.placement === p.value }">
                                <input v-model="form.placement" type="radio" name="placement" :value="p.value">
                                <span class="adp-option__icon"><i class="fas" :class="placementIcon(p.value)"></i></span>
                                <span>
                                    <span class="adp-option__title d-block">{{ p.label }}</span>
                                    <span class="adp-option__sub">{{ p.max_slots }} ads at a time<template v-if="options.quota.credits"> · {{ number(p.credits_per_hour) }} cr/hr</template></span>
                                </span>
                            </label>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="adDate">Day <span class="text-danger">*</span></label>
                                <input id="adDate" v-model="form.date" type="date" class="form-control" :min="minDate" :max="maxDate" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">How long <span class="text-danger">*</span></label>
                                <div class="adp-durations">
                                    <button v-for="d in options.all_durations" :key="d.hours" type="button" class="adp-duration" :class="{ 'is-active': form.hours === d.hours }"
                                            :disabled="!allowed(d.hours)" :title="allowed(d.hours) ? null : 'Available on a higher plan'" @click="form.hours = d.hours">
                                        {{ d.label }}<i v-if="!allowed(d.hours)" class="fas fa-lock"></i>
                                    </button>
                                </div>
                                <div class="form-text">Your {{ options.quota.plan }} plan books up to {{ options.quota.max_label }} per ad.</div>
                            </div>
                        </div>

                        <label class="form-label fw-semibold">Start hour <span class="text-danger">*</span></label>
                        <div v-if="!availability" class="text-center py-3"><span class="spinner-border spinner-border-sm text-muted"></span></div>
                        <template v-else>
                            <div class="adp-hours" role="listbox" aria-label="Start hour">
                                <button v-for="h in availability.hours" :key="h.hour" type="button" class="adp-hour" :class="hourClass(h)" :disabled="h.past || h.free === 0"
                                        :aria-selected="form.hour === h.hour ? 'true' : 'false'" :title="h.past ? 'Already passed' : `${h.free} of ${availability.max_slots} slots free`" @click="form.hour = h.hour">
                                    {{ pad(h.hour) }}:00
                                    <small>{{ h.past ? '—' : (h.free === 0 ? 'Full' : `${h.free} free`) }}</small>
                                </button>
                            </div>
                            <div class="adp-legend">
                                <span><i style="background: rgba(22,163,74,.25)"></i>Free</span>
                                <span><i style="background: rgba(245,158,11,.35)"></i>Last slot</span>
                                <span><i style="background: #e5e7f0"></i>Full / passed</span>
                                <span><i style="background: var(--portal-primary, #244373)"></i>Your ad</span>
                            </div>
                            <div v-if="rangeConflict" class="alert alert-warning small py-2 mt-2 mb-0"><i class="fas fa-triangle-exclamation me-1"></i>{{ rangeConflict }}</div>
                        </template>
                        <div v-if="errors.start || errors.hours || errors.placement" class="invalid-feedback d-block mt-2">{{ (errors.start || errors.hours || errors.placement)[0] }}</div>
                    </div>

                    <!-- 4. Payment (new bookings) -->
                    <div v-if="!id" class="portal-card p-4 mb-3">
                        <div class="adp-step"><span class="adp-step__num">4</span><div><div class="adp-step__title">How to pay</div><div class="adp-step__hint">Use an ad from your plan, or credits once those are used.</div></div></div>
                        <div class="adp-options">
                            <label class="adp-option" :class="{ 'is-active': form.pay_with === 'plan', 'is-disabled': !planAvailable }">
                                <input v-model="form.pay_with" type="radio" name="pay_with" value="plan" :disabled="!planAvailable">
                                <span class="adp-option__icon"><i class="fas fa-layer-group"></i></span>
                                <span>
                                    <span class="adp-option__title d-block">Plan ad</span>
                                    <span class="adp-option__sub">{{ options.quota.limit > 0 ? `${options.quota.remaining} of ${options.quota.limit} left this month` : 'Not included in your plan' }}</span>
                                </span>
                            </label>
                            <label class="adp-option" :class="{ 'is-active': form.pay_with === 'credits', 'is-disabled': !options.quota.credits }">
                                <input v-model="form.pay_with" type="radio" name="pay_with" value="credits" :disabled="!options.quota.credits">
                                <span class="adp-option__icon"><i class="fas fa-coins"></i></span>
                                <span>
                                    <span class="adp-option__title d-block">{{ number(cost) }} credits</span>
                                    <span class="adp-option__sub">{{ options.quota.credits ? `Balance ${number(options.quota.balance)}` : 'Credits aren\'t available' }}</span>
                                </span>
                            </label>
                        </div>
                        <div v-if="form.pay_with === 'credits' && cost > options.quota.balance" class="alert alert-warning small py-2 mt-3 mb-0">
                            <i class="fas fa-coins me-1"></i>You need {{ number(cost - options.quota.balance) }} more credits. <RouterLink :to="{ name: 'credits.index' }">Top up credits</RouterLink>
                        </div>
                        <div v-if="errors.pay_with || errors.credits" class="invalid-feedback d-block mt-2">{{ (errors.pay_with || errors.credits)[0] }}</div>
                    </div>

                    <!-- Booking (editing) -->
                    <div v-else class="portal-card p-4 mb-3">
                        <div class="adp-step"><span class="adp-step__num"><i class="fas fa-calendar-check"></i></span><div><div class="adp-step__title">Booking</div><div class="adp-step__hint">The page and time can't change. To move it, cancel this ad and book a new one.</div></div></div>
                        <div class="adp-summary">
                            <div class="adp-summary__row"><span>Page</span><span>{{ existing.placement_label }}</span></div>
                            <div class="adp-summary__row"><span>Starts</span><span>{{ adDateTime(existing.starts_at) }}</span></div>
                            <div class="adp-summary__row"><span>Runs for</span><span>{{ existing.duration_label }}</span></div>
                            <div class="adp-summary__row"><span>Paid with</span><span>{{ existing.paid_with === 'credits' ? `${number(existing.credits)} credits` : 'Plan ad' }}</span></div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mb-4">
                        <button type="submit" class="btn btn-portal-primary px-4" :disabled="saving || (!id && !canBook)">
                            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="fas fa-paper-plane me-1"></i>{{ id ? 'Save & send for review' : 'Book & send for review' }}
                        </button>
                        <RouterLink :to="id ? { name: 'ads.show', params: { id } } : { name: 'ads.index' }" class="btn portal-btn-ghost">Cancel</RouterLink>
                    </div>
                </div>

                <!-- Live preview + summary -->
                <aside class="adp-aside">
                    <AdPreview :image-url="imageUrl" :mobile-url="mobileUrl" :text="form.translations[activeLang] ?? {}" />

                    <div v-if="!id" class="portal-card p-3">
                        <div class="adp-preview__title mb-2"><i class="fas fa-receipt me-1"></i>Summary</div>
                        <div class="adp-summary">
                            <div class="adp-summary__row"><span>Page</span><span>{{ selectedPlacement?.label ?? '—' }}</span></div>
                            <div class="adp-summary__row"><span>Starts</span><span>{{ startLabel }}</span></div>
                            <div class="adp-summary__row"><span>Ends</span><span>{{ endLabel }}</span></div>
                            <div class="adp-summary__row"><span>Runs for</span><span>{{ form.hours ? durationLabel(form.hours) : '—' }}</span></div>
                            <div class="adp-summary__row adp-summary__total"><span>You pay</span><span>{{ form.pay_with === 'credits' ? `${number(cost)} credits` : '1 plan ad' }}</span></div>
                        </div>
                    </div>
                </aside>
            </form>
        </template>
    </div>
</template>

<script setup>
/**
 * Book a website ad (or change one's banner / text / link before it starts): the banner with a live
 * preview of the website slider, where it links, the page + day + start hour (free slots per hour)
 * + length allowed by the plan, and paying with a plan ad or credits. AdBookingService has the rules.
 */
import { computed, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import PlanLockedCard from '../../components/PlanLockedCard.vue';
import RemoteSelect from '../../components/RemoteSelect.vue';
import { useToast } from '../../composables/useToast';
import AdBannerFields from './AdBannerFields.vue';
import AdPreview from './AdPreview.vue';
import { AD_TZ, adDateTime, durationLabel, number } from './adFormat';
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
const availability = ref(null);

const form = reactive({
    name: '', image: null, mobile_image: null, remove_mobile_image: false, image_alt: '', translations: {},
    link_type: 'property', property_id: null, link_url: '',
    placement: '', date: '', hour: null, hours: null, pay_with: 'plan',
});

const pad = (n) => String(n).padStart(2, '0');
const todayDubai = () => new Date().toLocaleDateString('en-CA', { timeZone: AD_TZ });

const linkOptions = [
    { value: 'property', title: 'One of my listings', sub: 'Opens the listing page', icon: 'fas fa-building' },
    { value: 'profile', title: 'My profile page', sub: 'Your agent / agency page', icon: 'fas fa-id-badge' },
    { value: 'url', title: 'Another website', sub: 'Any https:// address', icon: 'fas fa-globe' },
];

const { pick, removeMobile, imageUrl, mobileUrl } = useAdImages(form, existing);

const propertyInitial = computed(() => (existing.value?.link_type === 'property' && existing.value.property_id
    ? { id: existing.value.property_id, name: existing.value.link.label } : null));

// ---- booking
const selectedPlacement = computed(() => options.value?.placements.find((p) => p.value === form.placement) ?? null);
const allowed = (hours) => options.value?.quota.durations.includes(hours);
const minDate = computed(() => options.value?.earliest_start.slice(0, 10));
const maxDate = computed(() => {
    const d = new Date(`${todayDubai()}T00:00:00`);
    d.setDate(d.getDate() + (options.value?.max_days_ahead ?? 60));
    return d.toLocaleDateString('en-CA');
});
const cost = computed(() => {
    const p = selectedPlacement.value;
    if (!p || !form.hours) return 0;
    return form.hours >= 24 ? Math.ceil(form.hours / 24) * p.credits_per_day : form.hours * p.credits_per_hour;
});
// Plan ads are counted per month: next month's allowance is fresh.
const planAvailable = computed(() => {
    const q = options.value?.quota;
    if (!q || q.limit <= 0) return false;
    return q.remaining > 0 || (form.date && form.date.slice(0, 7) !== todayDubai().slice(0, 7));
});
const inRange = (hour) => form.hour !== null && form.hours && hour >= form.hour && hour < form.hour + form.hours;
const hourClass = (h) => ({
    'is-past': h.past,
    'is-full': !h.past && h.free === 0,
    'is-few': !h.past && h.free === 1,
    'is-free': !h.past && h.free > 1,
    'in-range': inRange(h.hour) && form.hour !== h.hour,
    'is-start': form.hour === h.hour,
});
const rangeConflict = computed(() => {
    if (form.hour === null || !availability.value) return '';
    const full = availability.value.hours.find((h) => inRange(h.hour) && h.hour !== form.hour && h.free === 0);
    return full ? `${pad(full.hour)}:00 is fully booked on this page — pick an earlier start, a shorter time, or another page.` : '';
});
const startLabel = computed(() => (form.date && form.hour !== null ? adDateTime(`${form.date}T${pad(form.hour)}:00:00+04:00`) : '—'));
const endLabel = computed(() => {
    if (!form.date || form.hour === null || !form.hours) return '—';
    const end = new Date(new Date(`${form.date}T${pad(form.hour)}:00:00+04:00`).getTime() + form.hours * 3600000);
    return adDateTime(end.toISOString());
});
const canBook = computed(() => form.placement && form.date && form.hour !== null && form.hours && !rangeConflict.value
    && (form.pay_with === 'plan' ? planAvailable.value : options.value?.quota.credits));

function loadAvailability() {
    if (props.id || !form.placement || !form.date) return;
    availability.value = null;
    const key = `${form.placement}|${form.date}`;
    http.get('/ads/availability', { params: { placement: form.placement, date: form.date } })
        .then((res) => {
            if (`${form.placement}|${form.date}` !== key) return;
            availability.value = res.data;
            const chosen = res.data.hours.find((h) => h.hour === form.hour);
            if (chosen && (chosen.past || chosen.free === 0)) form.hour = null;
        })
        .catch(() => { availability.value = { max_slots: 0, hours: [] }; });
}
watch(() => [form.placement, form.date], loadAvailability);
watch(planAvailable, (ok) => { if (!ok && form.pay_with === 'plan' && options.value?.quota.credits) form.pay_with = 'credits'; });

// ---- load / save
function load() {
    loadError.value = '';
    Promise.all([
        http.get('/ads/form-options'),
        props.id ? http.get(`/ads/${props.id}`) : Promise.resolve(null),
    ])
        .then(([opts, ad]) => {
            const o = opts.data;
            o.languages.forEach((lang) => {
                const saved = ad?.data.ad.translations?.[lang.code] ?? {};
                form.translations[lang.code] = { eyebrow: saved.eyebrow ?? '', title: saved.title ?? '', text: saved.text ?? '' };
            });
            activeLang.value = o.languages[0]?.code ?? 'en';
            if (ad) {
                const a = ad.data.ad;
                existing.value = a;
                Object.assign(form, { name: a.name, image_alt: a.image_alt ?? '', link_type: a.link_type, property_id: a.property_id, link_url: a.link_url ?? '' });
            } else {
                form.placement = o.placements[0]?.value ?? '';
                form.date = o.earliest_start.slice(0, 10);
                form.hours = o.quota.durations[o.quota.durations.length - 1] ?? null;
                form.pay_with = o.quota.limit > 0 && o.quota.remaining > 0 ? 'plan' : (o.quota.credits ? 'credits' : 'plan');
            }
            options.value = o;
        })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function payload() {
    const data = new FormData();
    appendBanner(data, form);
    data.append('link_type', form.link_type);
    if (form.link_type === 'property' && form.property_id) data.append('property_id', String(form.property_id));
    if (form.link_type === 'url') data.append('link_url', form.link_url);
    if (props.id) {
        data.append('_method', 'PUT');
        data.append('remove_mobile_image', form.remove_mobile_image ? '1' : '0');
    } else {
        data.append('placement', form.placement);
        data.append('start', `${form.date} ${pad(form.hour)}:00`);
        data.append('hours', String(form.hours));
        data.append('pay_with', form.pay_with);
    }
    return data;
}

function save() {
    if (!props.id && !form.image) {
        errors.value = { image: ['Choose a banner image.'] };
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    saving.value = true;
    errors.value = {};
    http.post(props.id ? `/ads/${props.id}` : '/ads', payload())
        .then((res) => {
            success(res.data.message);
            router.push({ name: 'ads.show', params: { id: props.id ?? res.data.id } });
        })
        .catch((e) => {
            if (e.response?.status === 422 && e.response.data.errors) {
                errors.value = e.response.data.errors;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                if (errors.value.start) loadAvailability();
            } else {
                toastError(errorMessage(e));
            }
        })
        .finally(() => { saving.value = false; });
}

load();
</script>
