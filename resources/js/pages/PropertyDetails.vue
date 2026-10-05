<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePropertyDetail } from '../composables/usePropertyDetail';
import { useLanguages } from '../composables/useLanguages';
import { useRecaptcha } from '../composables/useRecaptcha';
import { useStaticText } from '../composables/useStaticText';
import { useCurrency } from '../composables/useCurrency';
import AdBlock from '../components/AdBlock.vue';
import PhoneInput from '../components/PhoneInput.vue';
import PropertyNearbyMap from '../components/PropertyNearbyMap.vue';
import { contactError, responseError } from '../composables/useContactValidation';

const route = useRoute();
const router = useRouter();
const { property, notFound, fetchProperty } = usePropertyDetail();
const { selectedLanguage } = useLanguages();
const { getRecaptchaToken } = useRecaptcha();
const { t } = useStaticText();
// Prices arrive in AED (price_value) and are shown in the visitor's chosen currency.
const { selectedCurrency, formatAmount, formatPrice } = useCurrency();

function load() {
    fetchProperty(route.params.slug, selectedLanguage.value?.code);
}

onMounted(load);

// Sidebar (agent card, enquiry form, ad) floats while the page scrolls. If it fits on screen it
// pins under the header; if it is taller, it scrolls until its bottom reaches the bottom of the
// screen and pins there — so every part of it is reachable. Re-measured when its height changes.
const sidebarEl = ref(null);
const sidebarTop = ref(null);
const HEADER_OFFSET = 150;
function measureSidebar() {
    const el = sidebarEl.value;
    if (!el) return;
    const fits = el.offsetHeight + HEADER_OFFSET + 16 <= window.innerHeight;
    sidebarTop.value = fits ? `${HEADER_OFFSET}px` : `${window.innerHeight - el.offsetHeight - 16}px`;
}
let sidebarObserver = null;
watch(sidebarEl, (el) => {
    sidebarObserver?.disconnect();
    if (el && 'ResizeObserver' in window) { sidebarObserver = new ResizeObserver(measureSidebar); sidebarObserver.observe(el); }
    measureSidebar();
});
onMounted(() => window.addEventListener('resize', measureSidebar, { passive: true }));
onBeforeUnmount(() => { window.removeEventListener('resize', measureSidebar); sidebarObserver?.disconnect(); });
watch(() => route.params.slug, load);
watch(selectedLanguage, load);
watch(
    () => property.value?.name,
    (name) => {
        if (name) document.title = `${name} | MW Realty`;
    },
);

// See Blogs.vue for why this re-run is needed after the async fetch populates the page.
watch(property, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});

const enquiryForm = reactive({ name: '', email: '', phone: '', phone_country_code: '+971', message: '' });
const enquirySubmitting = ref(false);
const enquiryFeedback = ref(null);
// Brochure / floor plan downloads: both are gated by the same lead form (one modal, `kind` picks the copy
// and endpoint). Once the lead is saved the server hands back a short-lived same-origin link
// (PropertyFileDownloadController); the file is fetched with a live progress bar, saved automatically
// and the modal closes itself — there is no separate "download" step.
const DOWNLOADS = {
    brochure: {
        endpoint: '/leads/brochure-download', recaptcha: 'brochure_download', fileSuffix: 'brochure',
        title: 'Get the property brochure', text: 'Share your contact details to access the brochure.',
    },
    floor_plan: {
        endpoint: '/leads/floor-plan-download', recaptcha: 'floor_plan_download', fileSuffix: 'floor-plan',
        title: 'Get the floor plan', text: 'Share your contact details to download the floor plan.',
    },
};
const download = reactive({ open: false, kind: 'brochure', stage: 'form', progress: null, error: null });
const downloadForm = reactive({ name: '', email: '', phone: '', phone_country_code: '+971' });
const downloadConfig = computed(() => DOWNLOADS[download.kind]);
let downloadCloseTimer = null;

function openDownload(kind) {
    clearTimeout(downloadCloseTimer);
    Object.assign(download, { open: true, kind, stage: 'form', progress: null, error: null });
}
function closeDownload() {
    if (download.stage === 'sending' || download.stage === 'downloading') return;
    clearTimeout(downloadCloseTimer);
    download.open = false;
}

watch(
    () => property.value?.name,
    (name) => {
        if (name) enquiryForm.message = `I'm interested in "${name}" — please share more details.`;
    },
    { immediate: true },
);

async function handleEnquirySubmit() {
    if (enquirySubmitting.value) return;
    const problem = (!enquiryForm.name.trim() && 'Please enter your name.') || contactError(enquiryForm);
    if (problem) {
        enquiryFeedback.value = { type: 'error', text: problem };
        return;
    }
    enquirySubmitting.value = true;
    enquiryFeedback.value = null;

    try {
        const recaptcha_token = await getRecaptchaToken('property_enquiry');
        await window.axios.post('/leads/capture', {
            property_id: property.value.id,
            name: enquiryForm.name,
            email: enquiryForm.email,
            phone: enquiryForm.phone,
            phone_country_code: enquiryForm.phone_country_code,
            message: enquiryForm.message,
            page_source: 'property-detail',
            recaptcha_token,
        });
        // The lead is now in the listing agent's / agency's CRM. Show who has it — passed as history
        // state, not in the URL (see ThankYou.vue).
        const p = property.value;
        const c = p.contact;
        router.push({
            path: '/thank-you',
            state: {
                thankYou: {
                    type: 'property',
                    name: enquiryForm.name.trim().split(/\s+/)[0] || '',
                    property: { slug: route.params.slug, name: p.name, image: (p.images || [])[0] || null, location: p.location || null },
                    contact: c ? {
                        name: c.name, type: c.type, avatar_url: c.avatar_url || null, detail_url: c.detail_url || null,
                        phone: c.phone || null, whatsapp_number: c.whatsapp_number || null,
                    } : null,
                },
            },
        });
    } catch (error) {
        enquiryFeedback.value = { type: 'error', text: responseError(error, t('property_details.generic_error')) };
        enquirySubmitting.value = false;
    }
}

async function handleDownloadSubmit() {
    if (download.stage !== 'form') return;
    const problem = (!downloadForm.name.trim() && 'Please enter your name.') || contactError({ ...downloadForm, phoneRequired: true });
    if (problem) {
        download.error = problem;
        return;
    }
    const config = downloadConfig.value;
    download.stage = 'sending';
    download.error = null;
    let data;
    try {
        const recaptcha_token = await getRecaptchaToken(config.recaptcha);
        ({ data } = await window.axios.post(config.endpoint, { property_id: property.value.id, ...downloadForm, recaptcha_token }));
    } catch (error) {
        download.error = responseError(error, t('property_details.generic_error'));
        download.stage = 'form';
        return;
    }

    download.stage = 'downloading';
    download.progress = 0;
    try {
        await saveFile(data.download_url, `${property.value.slug}-${config.fileSuffix}`, (p) => { download.progress = p; });
    } catch {
        // Streaming failed — let the browser fetch the (attachment) link itself; the page stays put.
        window.location.href = data.download_url;
    }
    download.progress = 1;
    download.stage = 'done';
    // Fresh form for the next download (brochure ↔ floor plan share this modal).
    Object.assign(downloadForm, { name: '', email: '', phone: '', phone_country_code: '+971' });
    downloadCloseTimer = setTimeout(() => { download.open = false; }, 1800);
}

/** Fetch `url` and save it as a file, reporting progress 0–1 (null while the size is unknown). */
async function saveFile(url, fallbackName, onProgress) {
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const total = Number(response.headers.get('content-length')) || 0;
    const chunks = [];
    let received = 0;
    if (response.body?.getReader) {
        const reader = response.body.getReader();
        for (;;) {
            const { done, value } = await reader.read();
            if (done) break;
            chunks.push(value);
            received += value.length;
            onProgress(total ? Math.min(received / total, 1) : null);
        }
    } else {
        chunks.push(await response.arrayBuffer());
    }
    const blob = new Blob(chunks, { type: response.headers.get('content-type') || 'application/octet-stream' });
    // The server names the file (Content-Disposition); fall back to "<slug>-<kind>".
    const disposition = response.headers.get('content-disposition') || '';
    const named = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = named ? decodeURIComponent(named[1]) : fallbackName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 10000);
}

const pills = computed(() => {
    if (!property.value) return [];
    const p = property.value;
    const list = [
        { icon: '/frontend/assets/images/property-details/tag.svg', label: t('property_details.pills.rera_id'), value: p.rera_id || '—' },
        { icon: '/frontend/assets/images/property-details/status.svg', label: t('property_details.pills.status'), value: p.completion_status_label || '—' },
        { icon: '/frontend/assets/images/icons/filter.svg', label: t('property_details.pills.type'), value: p.type },
    ];
    if (p.beds) list.push({ icon: '/frontend/assets/images/icons/bed.svg', label: t('property_details.pills.bedrooms'), value: p.beds });
    if (p.baths) list.push({ icon: '/frontend/assets/images/icons/bathroom.svg', label: t('property_details.pills.bathrooms'), value: p.baths });
    list.push({ icon: '/frontend/assets/images/icons/location.svg', label: t('property_details.pills.location'), value: p.location });
    list.push({ icon: '/frontend/assets/images/icons/area.svg', label: t('property_details.pills.size'), value: p.area });
    // Everything else the listing form collects, only when it's filled in.
    if (p.category_label) list.push({ icon: '/frontend/assets/images/icons/building.svg', label: t('property_details.pills.category', 'Category'), value: p.category_label });
    if (p.furnishing) list.push({ icon: '/frontend/assets/images/property-details/info.svg', label: t('property_details.pills.furnishing', 'Furnishing'), value: p.furnishing });
    if (p.parking) list.push({ icon: '/frontend/assets/images/property-details/amenity-parking.svg', label: t('property_details.pills.parking', 'Parking'), value: p.parking });
    if (p.garage) list.push({ icon: '/frontend/assets/images/property-details/garage.svg', label: t('property_details.pills.garage'), value: p.garage });
    if (p.floor) list.push({ icon: '/frontend/assets/images/icons/building.svg', label: t('property_details.pills.floor', 'Floor'), value: p.floor });
    if (p.view) list.push({ icon: '/frontend/assets/images/property-details/eye.svg', label: t('property_details.pills.view', 'View'), value: p.view });
    if (p.developer) list.push({ icon: '/frontend/assets/images/icons/building.svg', label: t('property_details.pills.developer', 'Developer'), value: p.developer });
    if (p.year_built) list.push({ icon: '/frontend/assets/images/property-details/calendar.svg', label: t('property_details.pills.year_built'), value: p.property_age !== null && p.property_age !== undefined ? `${p.year_built} · ${p.property_age} ${t('property_details.pills.years_old', p.property_age === 1 ? 'year old' : 'years old')}` : p.year_built });
    if (p.direct_from_owner) list.push({ icon: '/frontend/assets/images/icons/user.svg', label: t('property_details.pills.direct_from_owner', 'Direct from owner'), value: p.direct_from_owner });
    if (p.emirate_label) list.push({ icon: '/frontend/assets/images/icons/location.svg', label: t('property_details.pills.emirate', 'Emirate'), value: p.emirate_label });
    return list.filter((row) => row.value !== '—' && row.value !== null && row.value !== '');
});

// Video / 360° tour links (new tab). A video file on this site links by path; anything else by its URL.
const tourLinks = computed(() => {
    const tours = property.value?.tours;
    if (!tours) return [];
    const links = [];
    if (tours.video) links.push({ key: 'video', icon: '▶', label: t('property_details.watch_video', 'Video tour'), href: tours.video.type === 'file' ? tours.video.src : tours.video.url });
    if (tours.virtual_url) links.push({ key: '360', icon: '360°', label: t('property_details.open_360', '360° virtual tour'), href: tours.virtual_url });
    return links;
});

// Price label: "Rent per month" etc. on a rent listing (rental period from the listing form).
const priceLabel = computed(() => {
    const p = property.value;
    if (!p) return '';
    if (p.listing_type !== 'rent') return t('property_details.price_starting_price');
    return p.rental_period_label ? `${t('property_details.price_rent', 'Rent')} · ${p.rental_period_label}` : t('property_details.price_yearly_rent');
});

// A Y-m-d day, formatted in the site language.
function formatDay(iso) {
    const date = new Date(`${iso}T00:00:00`);
    const locale = selectedLanguage.value?.code === 'ar' ? 'ar-AE' : 'en-GB';
    return {
        iso,
        day: date.toLocaleDateString(locale, { weekday: 'short' }),
        date: date.toLocaleDateString(locale, { day: 'numeric' }),
        month: date.toLocaleDateString(locale, { month: 'short', year: 'numeric' }),
        short: date.toLocaleDateString(locale, { weekday: 'short', day: 'numeric', month: 'short' }),
        long: date.toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
    };
}

// Open house / viewing days.
const viewingDates = computed(() => (property.value?.availability?.dates || []).map(formatDay));

// The header's "Open house" flag jumps to the dates (same offset maths as the section nav).
function scrollToAvailability() {
    document.querySelector('[data-property-tab="availability"]')?.click();
}

// Floor plan price: "AED 240,000 – 264,000" in the visitor's currency, or null.
function planPrice(plan) {
    if (!plan?.price_from && !plan?.price_to) return null;
    const from = plan.price_from || plan.price_to;
    const to = plan.price_to && plan.price_to !== from ? plan.price_to : null;
    return `${selectedCurrency.value.code} ${formatAmount(from)}${to ? ` – ${formatAmount(to)}` : ''}`;
}

// "Book a viewing": pick a day + time slot (LeadCaptureController::storeViewing — a lead for the
// listing's agent). Listings with open house days offer only those; otherwise the next 14 days.
const VIEWING_SLOTS = computed(() => [
    { key: 'morning', label: t('property_details.viewing.morning', 'Morning'), hours: '9 AM – 12 PM' },
    { key: 'afternoon', label: t('property_details.viewing.afternoon', 'Afternoon'), hours: '12 PM – 4 PM' },
    { key: 'evening', label: t('property_details.viewing.evening', 'Evening'), hours: '4 PM – 7 PM' },
]);
const viewing = reactive({ open: false, stage: 'form', date: '', time: '', error: null, when: '' });
const viewingForm = reactive({ name: '', email: '', phone: '', phone_country_code: '+971', note: '' });
const viewingDays = computed(() => {
    if (viewingDates.value.length) return viewingDates.value;
    const pad = (n) => String(n).padStart(2, '0');
    return Array.from({ length: 14 }, (_, i) => {
        const d = new Date();
        d.setDate(d.getDate() + i);
        return formatDay(`${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`);
    });
});
const viewingDay = computed(() => viewingDays.value.find((d) => d.iso === viewing.date) || null);

function bookViewing(day) {
    Object.assign(viewing, { open: true, stage: 'form', date: day?.iso || '', time: '', error: null });
}
function closeViewing() {
    if (viewing.stage !== 'sending') viewing.open = false;
}

async function handleViewingSubmit() {
    if (viewing.stage !== 'form') return;
    const problem = (!viewing.date && t('property_details.viewing.pick_day', 'Please pick a day.'))
        || (!viewing.time && t('property_details.viewing.pick_time', 'Please pick a time.'))
        || (!viewingForm.name.trim() && 'Please enter your name.')
        || contactError({ ...viewingForm, phoneRequired: true });
    if (problem) {
        viewing.error = problem;
        return;
    }
    viewing.stage = 'sending';
    viewing.error = null;
    try {
        const recaptcha_token = await getRecaptchaToken('book_viewing');
        await window.axios.post('/leads/viewing', {
            property_id: property.value.id,
            ...viewingForm,
            viewing_date: viewing.date,
            viewing_time: viewing.time,
            recaptcha_token,
        });
        viewing.when = `${viewingDay.value?.long || viewing.date} · ${VIEWING_SLOTS.value.find((s) => s.key === viewing.time)?.hours || ''}`;
        viewing.stage = 'done';
    } catch (error) {
        viewing.error = responseError(error, t('property_details.generic_error'));
        viewing.stage = 'form';
    }
}

const hasFeatures = computed(() => !!(property.value?.amenities?.length || property.value?.easy_access?.length || property.value?.attributes?.length));
const hasPricing = computed(() => {
    const p = property.value;
    return !!(p && (p.pricing?.cheques || p.pricing?.security_deposit || p.rental_period_label || p.availability));
});

// Floor Plans: the selected row drives the drawing, its zoom link, the download and the caption.
// Regulatory information rows (PropertyPageService::regulatory), labelled in the site language.
const REGULATORY_LABELS = {
    reference: ['property_details.regulatory.reference', 'Reference'],
    listed: ['property_details.regulatory.listed', 'Listed'],
    permit_number: ['property_details.regulatory.permit_number', 'Permit number'],
    broker_license: ['property_details.regulatory.broker_license', 'Broker License'],
    agency_name: ['property_details.regulatory.agency_name', 'Agency name'],
    zone_name: ['property_details.regulatory.zone_name', 'Zone name'],
    agent_license: ['property_details.regulatory.agent_license', 'Agent License'],
};
const regulatoryRows = computed(() => Object.entries(property.value?.regulatory?.rows || {})
    .filter(([key]) => REGULATORY_LABELS[key])
    .map(([key, value]) => ({
        key,
        value,
        label: key === 'permit_number' && property.value.regulatory.issuer
            ? `${property.value.regulatory.issuer} ${t('property_details.regulatory.permit_number_short', 'permit number')}`
            : t(...REGULATORY_LABELS[key]),
    })));

const activePlanIndex = ref(0);
watch(() => property.value?.slug, () => { activePlanIndex.value = 0; });
const activePlan = computed(() => property.value?.floor_plans?.[activePlanIndex.value] || {});

const descriptionFallback = computed(() => {
    if (!property.value) return '';
    return t('property_details.description_fallback')
        .replace('{type}', property.value.type)
        .replace('{location}', property.value.location);
});

function whatsappUrl(number) {
    return `https://wa.me/${(number || '').replace(/[^0-9]/g, '')}`;
}

function agentAria(template, name) {
    return t(template).replace('{name}', name);
}
</script>

<template>
    <main v-if="property">
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ property.name }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" :aria-label="t('property_details.breadcrumb_aria')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('property_details.breadcrumb_home') }}</router-link><span class="mw-about-crumb__sep"> / </span><router-link to="/properties">{{ t('property_details.breadcrumb_properties') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ property.name }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-property">
            <div class="mw-property__gallery-bleed" data-reveal>
                <div class="mw-property__gallery">
                    <div class="mw-agent-more__track" data-property-gallery-slider>
                        <div v-for="(src, index) in property.images" :key="index" class="mw-agent-more__slide">
                            <div class="mw-property__gallery-item">
                                <img :src="src" :alt="`${property.name} — photo ${index + 1}`" loading="lazy">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="mw-property__gallery-arrow mw-property__gallery-arrow--prev" data-property-gallery-prev :aria-label="t('property_details.gallery_prev_aria')">
                        <img src="/frontend/assets/images/icons/chevron.svg" alt="" width="18" height="18">
                    </button>
                    <button type="button" class="mw-property__gallery-arrow mw-property__gallery-arrow--next" data-property-gallery-next :aria-label="t('property_details.gallery_next_aria')">
                        <img src="/frontend/assets/images/icons/chevron.svg" alt="" width="18" height="18">
                    </button>
                </div>
            </div>

            <div class="container-ctn">

                <div class="mw-property__head" data-reveal>
                    <h2 class="sr-only">{{ property.name }}</h2>
                    <div class="mw-property__head-main">
                        <span class="mw-property__status-badge">{{ property.listing_type_label }}</span>
                        <span v-if="property.premium" class="mw-property__status-badge mw-property__status-badge--premium">{{ t('property_details.premium', 'Premium') }}</span>
                        <span v-if="property.upgraded" class="mw-property__status-badge mw-property__status-badge--upgraded">{{ t('property_details.upgraded', 'Upgraded') }}</span>
                        <a v-if="viewingDates.length" href="#availability" class="mw-property__openhouse-flag" @click.prevent="scrollToAvailability">
                            <span class="mw-property__openhouse-dot" aria-hidden="true"></span>{{ t('property_details.open_house_short', 'Open house') }} · {{ viewingDates[0].short }}<template v-if="viewingDates.length > 1"> +{{ viewingDates.length - 1 }}</template>
                        </a>
                        <span class="mw-property__meta-item"><img src="/frontend/assets/images/icons/location.svg" alt="" width="16" height="16">{{ property.location }}</span>
                        <span v-if="property.beds" class="mw-property__meta-item"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="16" height="16">{{ property.beds }} {{ t('property_details.meta_bedrooms_suffix') }}</span>
                        <span v-if="property.baths" class="mw-property__meta-item"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="16" height="16">{{ property.baths }} {{ t('property_details.meta_bathrooms_suffix') }}</span>
                        <span v-if="property.published_at" class="mw-property__meta-item"><img src="/frontend/assets/images/property-details/calendar.svg" alt="" width="16" height="16">{{ t('property_details.meta_published_prefix') }}: {{ property.published_at }}</span>
                    </div>
                    <div class="mw-property__price">
                        <span class="mw-property__price-label">{{ priceLabel }}</span>
                        <strong v-if="property.price" class="mw-property__price-value">
                            <span class="mw-property__price-currency">{{ property.price_value ? selectedCurrency.code : property.currency }}</span>
                            <span class="mw-property__price-amount">{{ property.price_value ? formatAmount(property.price_value) : property.price }}</span>
                        </strong>
                        <strong v-else class="mw-property__price-value">{{ t('property_details.price_on_request') }}</strong>
                        <button v-if="property.brochure_available" type="button" class="mw-brochure-button" @click="openDownload('brochure')">
                            <span class="mw-download-arrow" aria-hidden="true">↓</span> Download Brochure
                        </button>
                    </div>
                </div>

                <div class="mw-property__tabs-bar" data-property-tabs-bar>
                    <nav class="mw-property__tabs" role="tablist" :aria-label="t('property_details.tabs_aria')">
                        <a href="#overview" class="mw-property__tab is-active" role="tab" aria-selected="true" data-property-tab="overview">{{ t('property_details.tab_overview') }}</a>
                        <a v-if="hasPricing" href="#availability" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="availability">{{ t('property_details.tab_availability', 'Price & Availability') }}</a>
                        <a v-if="hasFeatures" href="#features" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="features">{{ t('property_details.tab_features') }}</a>
                        <a v-if="property.floor_plans?.length" href="#floor-plans" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="floor-plans">{{ t('property_details.tab_floor_plans', 'Floor Plans') }}</a>
                        <a href="#gallery" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="gallery">{{ t('property_details.tab_gallery') }}</a>
                        <a v-if="property.map || property.full_address" href="#location" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="location">{{ t('property_details.tab_location', 'Location & Nearby') }}</a>
                    </nav>

                    <select class="mw-property__tabs-select" :aria-label="t('property_details.tabs_aria')" data-property-tabs-select>
                        <option value="overview">{{ t('property_details.tab_overview') }}</option>
                        <option v-if="hasPricing" value="availability">{{ t('property_details.tab_availability', 'Price & Availability') }}</option>
                        <option v-if="hasFeatures" value="features">{{ t('property_details.tab_features') }}</option>
                        <option v-if="property.floor_plans?.length" value="floor-plans">{{ t('property_details.tab_floor_plans', 'Floor Plans') }}</option>
                        <option value="gallery">{{ t('property_details.tab_gallery') }}</option>
                        <option v-if="property.map || property.full_address" value="location">{{ t('property_details.tab_location', 'Location & Nearby') }}</option>
                    </select>
                </div>

                <div class="mw-property__body">
                    <div class="mw-property__main">

                        <section id="overview" class="mw-property__section" data-reveal data-property-panel="overview">
                            <h3 class="mw-property__section-title">{{ t('property_details.overview_title') }}</h3>
                            <div class="mw-property__about-text">
                                <!-- description arrives already sanitised (SafeHtml::clean) — render its formatting, not its tags. -->
                                <div v-if="property.description" class="mw-property__rich" v-html="property.description"></div>
                                <p v-else>{{ descriptionFallback }}</p>
                            </div>

                            <!-- Key features (one per line in the listing form). -->
                            <div v-if="property.key_features?.length" class="mw-property__keyfeatures">
                                <h4 class="mw-property__subtitle">{{ t('property_details.key_features', 'Key features') }}</h4>
                                <ul>
                                    <li v-for="feature in property.key_features" :key="feature">{{ feature }}</li>
                                </ul>
                            </div>

                            <!-- Property details: label / value rows, two columns on wide screens. -->
                            <h4 class="mw-property__subtitle mw-property__specs-title">{{ t('property_details.property_details', 'Property details') }}</h4>
                            <dl class="mw-property__specs">
                                <div v-for="pill in pills" :key="pill.label" class="mw-property__spec">
                                    <dt><span class="mw-property__spec-icon" :style="{ '--icon': `url('${pill.icon}')` }" aria-hidden="true"></span>{{ pill.label }}</dt>
                                    <dd>{{ pill.value }}</dd>
                                </div>
                            </dl>

                            <!-- Video / 360° tour: plain links, opened in a new tab. -->
                            <div v-if="tourLinks.length" class="mw-property__tours">
                                <a v-for="link in tourLinks" :key="link.key" :href="link.href" target="_blank" rel="noopener noreferrer" class="mw-property__tour-link">
                                    <span class="mw-property__tour-link-icon" aria-hidden="true">{{ link.icon }}</span>{{ link.label }}<span class="mw-property__tour-link-ext" aria-hidden="true">↗</span>
                                </a>
                            </div>
                        </section>

                        <!-- Price & availability: price terms + open house / viewing days. -->
                        <section v-if="hasPricing" id="availability" class="mw-property__section" data-reveal data-property-panel="availability">
                            <h3 class="mw-property__section-title">{{ t('property_details.tab_availability', 'Price & Availability') }}</h3>
                            <div class="mw-property__terms">
                                <div class="mw-property__term">
                                    <span class="mw-property__term-label">{{ priceLabel }}</span>
                                    <span class="mw-property__term-value">
                                        <template v-if="property.price_value">{{ selectedCurrency.code }} {{ formatAmount(property.price_value) }}</template>
                                        <template v-else-if="property.price">{{ property.currency }} {{ property.price }}</template>
                                        <template v-else>{{ t('property_details.price_on_request') }}</template>
                                    </span>
                                </div>
                                <div v-if="property.pricing?.cheques" class="mw-property__term">
                                    <span class="mw-property__term-label">{{ t('property_details.cheques', 'Number of cheques') }}</span>
                                    <span class="mw-property__term-value">{{ property.pricing.cheques }}</span>
                                </div>
                                <div v-if="property.pricing?.security_deposit" class="mw-property__term">
                                    <span class="mw-property__term-label">{{ t('property_details.security_deposit', 'Security deposit') }}</span>
                                    <span class="mw-property__term-value">{{ selectedCurrency.code }} {{ formatAmount(property.pricing.security_deposit) }}</span>
                                </div>
                                <div v-if="property.completion_status_label" class="mw-property__term">
                                    <span class="mw-property__term-label">{{ t('property_details.pills.status') }}</span>
                                    <span class="mw-property__term-value">{{ property.completion_status_label }}</span>
                                </div>
                            </div>

                            <div class="mw-property__openhouse">
                                <div class="mw-property__openhouse-head">
                                    <h4 class="mw-property__subtitle">{{ viewingDates.length ? t('property_details.open_house', 'Open house & viewing days') : t('property_details.availability', 'Availability') }}</h4>
                                    <span v-if="property.availability?.immediate" class="mw-property__avail-badge">{{ t('property_details.available_now', 'Available immediately') }}</span>
                                </div>
                                <div v-if="viewingDates.length" class="mw-property__dates">
                                    <button v-for="day in viewingDates" :key="day.iso" type="button" class="mw-property__date" :title="day.long" @click="bookViewing(day)">
                                        <span class="mw-property__date-day">{{ day.day }}</span>
                                        <span class="mw-property__date-num">{{ day.date }}</span>
                                        <span class="mw-property__date-month">{{ day.month }}</span>
                                        <span class="mw-property__date-cta">{{ t('property_details.book_viewing', 'Book viewing') }}</span>
                                    </button>
                                </div>
                                <p v-else class="mw-property__openhouse-text">
                                    {{ property.availability?.immediate ? t('property_details.available_now_text_book', 'Ready to move in or view any time — pick a day and time that suits you.') : t('property_details.no_upcoming_dates', 'No upcoming open house dates — ask the agent for a private viewing.') }}
                                </p>
                                <button type="button" class="mw-property__viewing-btn" @click="bookViewing(null)">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="2"/><path d="M3 10h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                    {{ t('property_details.book_a_viewing', 'Book a Viewing') }}
                                </button>
                            </div>
                        </section>

                        <section v-if="hasFeatures" id="features" class="mw-property__section" data-reveal data-property-panel="features">
                            <h3 class="mw-property__section-title">{{ t('property_details.tab_features') }}</h3>
                            <template v-for="group in [
                                { key: 'amenities', title: t('property_details.amenities', 'Amenities'), items: property.amenities },
                                { key: 'easy_access', title: t('property_details.easy_access', 'Easy access'), items: property.easy_access },
                                { key: 'attributes', title: t('property_details.highlights', 'Highlights'), items: property.attributes },
                            ]" :key="group.key">
                                <div v-if="group.items?.length" class="mw-property__feature-group">
                                    <h4 class="mw-property__subtitle">{{ group.title }}</h4>
                                    <div class="mw-property__chips">
                                        <span v-for="item in group.items" :key="item.label" class="mw-property__chip">
                                            <img :src="item.icon" alt="" width="22" height="22" loading="lazy">{{ item.label }}
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </section>

                        <section v-if="property.floor_plans?.length" id="floor-plans" class="mw-property__section" data-reveal data-property-panel="floor-plans">
                            <h3 class="mw-property__section-title">{{ t('property_details.floor_plans_title', 'Floor Plans') }}</h3>
                            <div class="mw-property__floorplan">
                                <div class="mw-property__floorplan-list" role="tablist" :aria-label="t('property_details.floor_plans_title', 'Floor Plans')">
                                    <div v-for="(plan, index) in property.floor_plans" :key="index" class="mw-property__floorplan-row" :class="{ 'is-active': index === activePlanIndex }" role="tab" tabindex="0" :aria-selected="index === activePlanIndex ? 'true' : 'false'" @click="activePlanIndex = index" @keydown.enter.space.prevent="activePlanIndex = index">
                                        <div class="mw-property__floorplan-row-head">
                                            <div>
                                                <p class="mw-property__floorplan-name">{{ plan.label }}</p>
                                                <p v-if="plan.size" class="mw-property__floorplan-range">{{ plan.size }}</p>
                                                <p v-if="planPrice(plan)" class="mw-property__floorplan-price">{{ planPrice(plan) }}</p>
                                            </div>
                                            <span class="mw-property__floorplan-arrow" aria-hidden="true"><img src="/frontend/assets/images/icons/chevron.svg" alt="" width="16" height="16"></span>
                                        </div>
                                    </div>
                                    <!-- Only when the listing has a "Downloadable Floor Plan File"; the lead form comes first. -->
                                    <button v-if="property.floor_plan_download" type="button" class="mw-property__floorplan-download mw-floorplan-download-btn" @click="openDownload('floor_plan')">
                                        <span class="mw-download-arrow" aria-hidden="true">↓</span>{{ t('property_details.floor_plan_download', 'Download Floor Plan') }}
                                    </button>
                                </div>
                                <div class="mw-property__floorplan-media">
                                    <a v-if="activePlan.image" :href="activePlan.image" class="mw-property__floorplan-zoom" data-fancybox="property-floor-plans" :data-caption="activePlan.label">
                                        <img :src="activePlan.image" :alt="`${activePlan.label} floor plan`" loading="lazy">
                                    </a>
                                    <div class="mw-property__floorplan-caption">
                                        <span class="mw-property__floorplan-caption-name">{{ activePlan.label }}</span>
                                        <span v-if="activePlan.size" class="mw-property__floorplan-caption-range">{{ activePlan.size }}</span>
                                        <span v-if="planPrice(activePlan)" class="mw-property__floorplan-caption-range">{{ planPrice(activePlan) }}</span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section id="gallery" class="mw-property__section" data-reveal data-property-panel="gallery">
                            <h3 class="mw-property__section-title">{{ t('property_details.gallery_title') }}</h3>
                            <div class="mw-property__photo-gallery">
                                <div class="mw-property__photo-gallery-main">
                                    <a :href="property.images[0]" data-fancybox="property-gallery" :data-caption="`${property.name} — photo 1`">
                                        <img :src="property.images[0]" :alt="`${property.name} photo gallery — main view`">
                                    </a>
                                </div>
                                <!-- Up to 4 thumbnails; the last one shows "+N" for the rest. Every photo stays in the
                                     Fancybox group (the extra ones hidden), so the viewer slides through all of them. -->
                                <div v-if="property.images.length > 1" class="mw-property__photo-gallery-grid mw-gallery-grid--compact" :class="`mw-gallery-grid--n${Math.min(property.images.length - 1, 4)}`">
                                    <a v-for="(src, index) in property.images.slice(1)" :key="index" v-show="index < 4" :href="src" data-fancybox="property-gallery" :data-caption="`${property.name} — photo ${index + 2}`" class="mw-gallery-thumb">
                                        <img :src="src" :alt="`${property.name} photo`" loading="lazy">
                                        <span v-if="index === 3 && property.images.length > 5" class="mw-gallery-thumb__more">+{{ property.images.length - 5 }} {{ property.images.length - 5 === 1 ? t('property_details.gallery_more_photo', 'photo') : t('property_details.gallery_more_photos', 'photos') }}</span>
                                    </a>
                                </div>
                            </div>
                        </section>


                        <section v-if="property.map || property.full_address" id="location" class="mw-property__section" data-reveal data-property-panel="location">
                            <h3 class="mw-property__section-title">{{ t('property_details.location_title', 'Location & Nearby') }}</h3>
                            <p v-if="property.full_address" class="mw-property__address">
                                <img src="/frontend/assets/images/icons/location.svg" alt="" width="18" height="18">{{ property.full_address }}
                            </p>
                            <PropertyNearbyMap v-if="property.map" :key="property.id" :location="property.map" :groups="property.nearby || []" :title="property.name" :address="property.address || property.location" />
                        </section>

                        <!-- Regulatory information: reference, permit, broker / agent licenses + the permit QR buyers can scan. -->
                        <section v-if="regulatoryRows.length" class="mw-property__section mw-property-regulatory" data-reveal>
                            <div class="mw-property-regulatory__body">
                                <h3 class="mw-property__section-title">{{ t('property_details.regulatory.title', 'Regulatory information') }}</h3>
                                <dl class="mw-property-regulatory__list">
                                    <template v-for="row in regulatoryRows" :key="row.key">
                                        <dt>{{ row.label }}</dt>
                                        <dd>{{ row.value }}</dd>
                                    </template>
                                </dl>
                                <a v-if="property.regulatory.verify_url" :href="property.regulatory.verify_url" target="_blank" rel="noopener noreferrer" class="mw-property-regulatory__link">
                                    {{ t('property_details.regulatory.verify', 'Verify this ad') }}<template v-if="property.regulatory.issuer"> — {{ property.regulatory.issuer }}</template>
                                </a>
                            </div>
                            <img v-if="property.regulatory.qr_url" :src="property.regulatory.qr_url" :alt="t('property_details.regulatory.qr_alt', 'Advertising permit QR code')" width="132" height="132" class="mw-property-regulatory__qr" loading="lazy" @error="(e) => (e.target.style.display = 'none')">
                        </section>

                    </div>

                    <aside ref="sidebarEl" class="mw-property__agent-wrap" data-reveal :style="{ top: sidebarTop }">
                        <article v-if="property.contact" class="mw-agent-card mw-property-agent">
                            <div class="mw-agent-card__head">
                                <div class="mw-agent-card__avatar">
                                    <!-- The listing's agent, or its agency when no agent is available (PropertyPageService::mapContact). -->
                                    <img :src="property.contact.avatar_url || (property.contact.type === 'company' ? '/frontend/assets/images/icons/building.svg' : '/frontend/assets/images/placeholders/avatar.svg')" :alt="property.contact.name" width="80" height="80" :class="{ 'mw-agent-card__avatar-img--agency': property.contact.type === 'company' && !property.contact.avatar_url }">
                                </div>
                                <div class="mw-agent-card__intro">
                                    <h4 class="mw-agent-card__name">{{ property.contact.name }}</h4>
                                    <div class="mw-agent-card__tags">
                                        <span v-if="property.contact.type === 'company'" class="mw-agent-card__tag mw-agent-card__tag--fill">{{ t('property_details.agent_card.agency_tag', 'Agency') }}</span>
                                        <span class="mw-agent-card__tag mw-agent-card__tag--fill">{{ t('property_details.agent_card.serves_in_dubai') }}</span>
                                        <span v-for="badge in property.contact.badges" :key="badge" class="mw-agent-card__tag">{{ badge }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mw-agent-card__body">
                                <p v-if="property.contact.years_of_experience" class="mw-property-agent__stat">{{ t('property_details.agent_card.years_of_experience_prefix') }}: {{ property.contact.years_of_experience }}</p>
                                <p v-if="property.contact.preferred_areas?.length" class="mw-property-agent__stat">{{ t('property_details.agent_card.preferred_areas_prefix') }}: {{ property.contact.preferred_areas.join(', ') }}</p>
                                <div class="mw-property-agent__cta">
                                    <a v-if="property.contact.phone" :href="`tel:${property.contact.phone}`" class="mw-property-agent__cta-btn mw-property-agent__cta-btn--call" :aria-label="agentAria('property_details.agent_card.call_aria', property.contact.name)">
                                        <img src="/frontend/assets/images/icons/phone.svg" alt="" width="18" height="18">
                                    </a>
                                    <a v-if="property.contact.whatsapp_number" :href="whatsappUrl(property.contact.whatsapp_number)" target="_blank" rel="noopener" class="mw-property-agent__cta-btn mw-property-agent__cta-btn--whatsapp" :aria-label="agentAria('property_details.agent_card.whatsapp_aria', property.contact.name)">
                                        <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="18" height="18">
                                    </a>
                                    <a v-if="property.contact.email" :href="`mailto:${property.contact.email}`" class="mw-property-agent__cta-btn mw-property-agent__cta-btn--email" :aria-label="agentAria('property_details.agent_card.email_aria', property.contact.name)">
                                        <img src="/frontend/assets/images/icons/email.svg" alt="" width="18" height="18">
                                    </a>
                                </div>
                            </div>
                            <div class="mw-agent-card__foot">
                                <img class="mw-agent-card__logo" src="/frontend/assets/images/logo-dark.png" alt="MW Realty" width="50" height="28">
                                <span class="mw-agent-card__rule" aria-hidden="true"></span>
                                <router-link :to="property.contact.detail_url" class="mw-agent-card__link">
                                    {{ t('property_details.agent_card.view_details') }}
                                    <img src="/frontend/assets/images/icons/find-cta-arrow.svg" alt="" width="18" height="18">
                                </router-link>
                            </div>
                        </article>

                        <div class="mw-property-enquiry">
                            <h4 class="mw-property-enquiry__title">{{ t('property_details.enquiry.title') }}</h4>
                            <form class="mw-property-enquiry__form" novalidate @submit.prevent="handleEnquirySubmit">
                                <div class="mw-property-enquiry__field">
                                    <label for="enquiry-name">{{ t('property_details.enquiry.name_label') }}</label>
                                    <input type="text" id="enquiry-name" name="name" :placeholder="t('property_details.enquiry.name_placeholder')" v-model="enquiryForm.name" required>
                                </div>
                                <div class="mw-property-enquiry__field">
                                    <label for="enquiry-email">{{ t('property_details.enquiry.email_label') }}</label>
                                    <input type="email" id="enquiry-email" name="email" :placeholder="t('property_details.enquiry.email_placeholder')" v-model="enquiryForm.email" required>
                                </div>
                                <div class="mw-property-enquiry__field">
                                    <label for="enquiry-phone">{{ t('property_details.enquiry.phone_label') }}</label>
                                    <PhoneInput id="enquiry-phone" v-model="enquiryForm.phone" v-model:country-code="enquiryForm.phone_country_code" />
                                </div>
                                <div class="mw-property-enquiry__field">
                                    <label for="enquiry-property">{{ t('property_details.enquiry.property_label') }}</label>
                                    <input type="text" id="enquiry-property" name="property" :value="property.name" disabled>
                                </div>
                                <div class="mw-property-enquiry__field">
                                    <label for="enquiry-message">{{ t('property_details.enquiry.message_label') }}</label>
                                    <textarea id="enquiry-message" name="message" rows="4" v-model="enquiryForm.message"></textarea>
                                </div>

                                <p v-if="enquiryFeedback" class="mw-form-feedback" :class="`mw-form-feedback--${enquiryFeedback.type}`">{{ enquiryFeedback.text }}</p>
                                <button type="submit" class="mw-btn mw-btn--gradient mw-property-enquiry__submit" :disabled="enquirySubmitting">{{ enquirySubmitting ? t('property_details.enquiry.sending') : t('property_details.enquiry.submit') }}</button>
                                <button type="button" class="mw-property-enquiry__viewing" @click="bookViewing(null)">{{ t('property_details.book_a_viewing', 'Book a Viewing') }}</button>
                            </form>
                        </div>

                        <!-- Admin › Ads, placement "Property Details" (image + optional text; hidden when none runs). -->
                        <AdBlock placement="property-details" variant="card" />
                    </aside>
                </div>

            </div>
        </section>

        <section v-if="property.similar.length" class="mw-agent-more">
            <div class="container-ctn">
                <div class="mw-agent-more__head">
                    <h2 class="mw-agent-more__title">{{ t('property_details.similar.title') }}</h2>
                    <div class="mw-agent-more__nav">
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--prev mw-agent-more__nav-btn" data-similar-properties-prev :aria-label="t('property_details.similar.previous_aria')">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--next mw-agent-more__nav-btn" data-similar-properties-next :aria-label="t('property_details.similar.next_aria')">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                    </div>
                </div>

                <div class="mw-agent-more__track" data-similar-properties-slider>

                    <div v-for="similar in property.similar" :key="similar.slug" class="mw-agent-more__slide"><article class="mw-agent-prop">
                        <router-link :to="`/property-details/${similar.slug}`" class="mw-agent-prop__media" data-card-gallery>
                            <div class="mw-agent-prop__slides" data-gallery-track>
                                <img v-for="(src, pIndex) in similar.images" :key="pIndex" :src="src" :alt="similar.name" class="mw-agent-prop__photo" loading="lazy">
                            </div>
                            <span v-if="similar.images_count" class="mw-agent-prop__count">
                                <img src="/frontend/assets/images/icons/photo-count.svg" alt="" width="16" height="16">
                                <span data-gallery-count>{{ similar.images_count }}</span>
                            </span>
                            <div class="mw-agent-prop__overlay">
                                <span class="mw-agent-prop__type">{{ similar.type }}</span>
                                <span class="mw-agent-prop__price">{{ formatPrice(similar.price_value, similar.price) }}</span>
                            </div>
                        </router-link>
                        <div class="mw-agent-prop__body">
                            <h3 class="mw-agent-prop__name"><router-link :to="`/property-details/${similar.slug}`">{{ similar.name }}</router-link></h3>
                            <p class="mw-agent-prop__location">
                                <img src="/frontend/assets/images/icons/location.svg" alt="" width="18" height="18">
                                {{ similar.location }}
                            </p>
                            <div class="mw-agent-prop__stats">
                                <span class="mw-agent-prop__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="18" height="18">{{ similar.beds }}</span>
                                <span class="mw-agent-prop__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="18" height="18">{{ similar.baths }}</span>
                                <span class="mw-agent-prop__stat"><img src="/frontend/assets/images/icons/area.svg" alt="" width="18" height="18">{{ similar.area }}</span>
                            </div>
                            <router-link :to="`/property-details/${similar.slug}`" class="mw-agent-cta mw-agent-cta--soft">
                                {{ t('property_details.agent_card.view_details') }}
                            </router-link>
                        </div>
                    </article></div>

                </div>
            </div>
        </section>
    </main>

    <main v-else-if="notFound">
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ t('property_details.not_found.title') }}</h1>
                </div>
            </div>
        </section>
        <section class="mw-property">
            <div class="container-ctn">
                <p>{{ t('property_details.not_found.message') }} <router-link to="/properties">{{ t('property_details.not_found.back_link') }}</router-link></p>
            </div>
        </section>
    </main>

    <Teleport to="body">
        <div v-if="viewing.open" class="mw-brochure-overlay" @click.self="closeViewing" @keydown.esc="closeViewing">
            <section class="mw-brochure-dialog mw-viewing-dialog" role="dialog" aria-modal="true" aria-labelledby="viewing-title">
                <button type="button" class="mw-brochure-close" aria-label="Close" :disabled="viewing.stage === 'sending'" @click="closeViewing">×</button>
                <template v-if="viewing.stage !== 'done'">
                    <div class="mw-brochure-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="2"/><path d="M3 10h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </div>
                    <h2 id="viewing-title">{{ t('property_details.book_a_viewing', 'Book a Viewing') }}</h2>
                    <p>{{ property.name }}</p>

                    <form class="mw-brochure-form" novalidate @submit.prevent="handleViewingSubmit">
                        <div class="mw-viewing__group">
                            <span class="mw-viewing__label">{{ viewingDates.length ? t('property_details.viewing.open_house_days', 'Open house days') : t('property_details.viewing.choose_day', 'Choose a day') }}</span>
                            <div class="mw-viewing__days" role="radiogroup">
                                <button v-for="day in viewingDays" :key="day.iso" type="button" role="radio" class="mw-viewing__day" :class="{ 'is-active': viewing.date === day.iso }" :aria-checked="viewing.date === day.iso" :title="day.long" @click="viewing.date = day.iso">
                                    <span class="mw-viewing__day-name">{{ day.day }}</span>
                                    <strong>{{ day.date }}</strong>
                                    <span class="mw-viewing__day-month">{{ day.month.split(' ')[0] }}</span>
                                </button>
                            </div>
                        </div>
                        <div class="mw-viewing__group">
                            <span class="mw-viewing__label">{{ t('property_details.viewing.choose_time', 'Preferred time') }}</span>
                            <div class="mw-viewing__slots" role="radiogroup">
                                <button v-for="slot in VIEWING_SLOTS" :key="slot.key" type="button" role="radio" class="mw-viewing__slot" :class="{ 'is-active': viewing.time === slot.key }" :aria-checked="viewing.time === slot.key" @click="viewing.time = slot.key">
                                    <strong>{{ slot.label }}</strong><span>{{ slot.hours }}</span>
                                </button>
                            </div>
                        </div>
                        <label>{{ t('property_details.viewing.name', 'Name') }}<input v-model="viewingForm.name" required autocomplete="name" maxlength="255" :disabled="viewing.stage === 'sending'"></label>
                        <label>{{ t('property_details.viewing.email', 'Email') }}<input v-model="viewingForm.email" required type="email" autocomplete="email" maxlength="255" :disabled="viewing.stage === 'sending'"></label>
                        <div class="mw-brochure-form__field"><label for="viewing-phone">{{ t('property_details.viewing.phone', 'Phone') }}</label>
                            <PhoneInput id="viewing-phone" v-model="viewingForm.phone" v-model:country-code="viewingForm.phone_country_code" required :disabled="viewing.stage === 'sending'" /></div>
                        <label>{{ t('property_details.viewing.note', 'Note for the agent (optional)') }}<textarea v-model="viewingForm.note" rows="2" maxlength="1000" :disabled="viewing.stage === 'sending'"></textarea></label>
                        <p v-if="viewing.error" class="mw-brochure-feedback is-error" role="alert">{{ viewing.error }}</p>
                        <button type="submit" class="mw-brochure-submit" :class="{ 'is-busy': viewing.stage === 'sending' }" :disabled="viewing.stage === 'sending'">
                            <span v-if="viewing.stage === 'sending'" class="mw-download-spinner" aria-hidden="true"></span>
                            {{ viewing.stage === 'sending' ? t('property_details.viewing.sending', 'Sending…') : t('property_details.viewing.submit', 'Request Viewing') }}
                        </button>
                    </form>
                </template>
                <div v-else class="mw-viewing__done" role="status" aria-live="polite">
                    <div class="mw-brochure-icon is-done" aria-hidden="true"><span class="mw-download-check">✓</span></div>
                    <h2 id="viewing-title">{{ t('property_details.viewing.done_title', 'Viewing requested') }}</h2>
                    <p class="mw-viewing__when">{{ viewing.when }}</p>
                    <p>{{ t('property_details.viewing.done_text', 'The agent will contact you shortly to confirm the exact time.') }}</p>
                    <button type="button" class="mw-brochure-submit" @click="viewing.open = false">{{ t('property_details.viewing.close', 'Done') }}</button>
                </div>
            </section>
        </div>

        <div v-if="download.open" class="mw-brochure-overlay" @click.self="closeDownload" @keydown.esc="closeDownload">
            <section class="mw-brochure-dialog" role="dialog" aria-modal="true" aria-labelledby="download-title">
                <button type="button" class="mw-brochure-close" aria-label="Close" :disabled="download.stage === 'sending' || download.stage === 'downloading'" @click="closeDownload">×</button>
                <div class="mw-brochure-icon" :class="`is-${download.stage}`" aria-hidden="true">
                    <span v-if="download.stage === 'done'" class="mw-download-check">✓</span>
                    <span v-else class="mw-download-arrow">↓</span>
                </div>
                <h2 id="download-title">{{ downloadConfig.title }}</h2>
                <p>{{ downloadConfig.text }}</p>

                <form v-if="download.stage === 'form' || download.stage === 'sending'" class="mw-brochure-form" novalidate @submit.prevent="handleDownloadSubmit">
                    <label>Name<input v-model="downloadForm.name" required autocomplete="name" maxlength="255" :disabled="download.stage === 'sending'"></label>
                    <label>Email<input v-model="downloadForm.email" required type="email" autocomplete="email" maxlength="255" :disabled="download.stage === 'sending'"></label>
                    <div class="mw-brochure-form__field"><label for="download-phone">Phone</label>
                        <PhoneInput id="download-phone" v-model="downloadForm.phone" v-model:country-code="downloadForm.phone_country_code" required :disabled="download.stage === 'sending'" /></div>
                    <p v-if="download.error" class="mw-brochure-feedback is-error" role="alert">{{ download.error }}</p>
                    <button type="submit" class="mw-brochure-submit" :class="{ 'is-busy': download.stage === 'sending' }" :disabled="download.stage === 'sending'">
                        <span v-if="download.stage === 'sending'" class="mw-download-spinner" aria-hidden="true"></span>
                        {{ download.stage === 'sending' ? 'Preparing…' : 'Continue to download' }}
                    </button>
                </form>

                <div v-else class="mw-brochure-success" role="status" aria-live="polite">
                    <template v-if="download.stage === 'downloading'">
                        <p class="mw-download-status">Downloading<span class="mw-download-dots"><i>.</i><i>.</i><i>.</i></span>
                            <strong v-if="download.progress !== null">{{ Math.round(download.progress * 100) }}%</strong></p>
                        <div class="mw-download-bar" :class="{ 'is-indeterminate': download.progress === null }">
                            <span :style="download.progress !== null ? { width: `${download.progress * 100}%` } : null"></span>
                        </div>
                    </template>
                    <template v-else>
                        <p class="mw-download-done">Download complete — check your downloads folder.</p>
                        <div class="mw-download-bar is-complete"><span style="width: 100%"></span></div>
                    </template>
                </div>
            </section>
        </div>
    </Teleport>
</template>

<style scoped>
.mw-brochure-button{display:flex;align-items:center;gap:9px;margin-top:16px;padding:11px 17px;border:0;border-radius:10px;background:#203f68;color:#fff;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;box-shadow:0 8px 20px #203f6830;transition:transform .2s,box-shadow .2s,background .2s}
.mw-brochure-button:hover{transform:translateY(-2px);background:#c3264b;box-shadow:0 12px 24px #c3264b40}
.mw-brochure-button span{font-size:22px;line-height:14px}
.mw-brochure-overlay{position:fixed;z-index:10000;inset:0;display:grid;place-items:center;padding:20px;background:#101c2dcc;backdrop-filter:blur(6px);animation:brochureFade .18s ease-out}
.mw-brochure-dialog{position:relative;width:min(100%,450px);padding:36px;border-radius:22px;background:#fff;box-shadow:0 28px 90px #08162d55;animation:brochurePop .28s cubic-bezier(.2,.8,.2,1)}
.mw-brochure-close{position:absolute;top:14px;right:17px;border:0;background:transparent;color:#64748b;font-size:28px;cursor:pointer}
.mw-brochure-icon{display:grid;place-items:center;width:48px;height:48px;margin-bottom:17px;border-radius:15px;background:#edf3fa;color:#203f68;font-size:30px}
.mw-brochure-dialog h2{margin:0 0 8px;color:#142943;font-size:24px}.mw-brochure-dialog>p{margin:0 0 22px;color:#64748b}
.mw-brochure-form{display:grid;gap:14px}.mw-brochure-form label{display:grid;gap:6px;color:#263951;font-size:13px;font-weight:600}.mw-brochure-form input{width:100%;height:46px;padding:0 12px;border:1px solid #d7dfeb;border-radius:9px;font-family:inherit;font-size:15px;font-weight:400}.mw-brochure-form input:focus{outline:2px solid #203f6833;border-color:#203f68}
.mw-brochure-submit,.mw-brochure-success a{display:inline-flex;justify-content:center;align-items:center;min-height:48px;padding:0 18px;border:0;border-radius:10px;background:#203f68;color:#fff;text-decoration:none;font-weight:700;cursor:pointer;transition:transform .2s,background .2s}.mw-brochure-submit:hover,.mw-brochure-success a:hover{transform:translateY(-2px);background:#c3264b}.mw-brochure-submit:disabled{opacity:.65;cursor:wait}
.mw-brochure-feedback{margin:0;font-size:13px}.is-error{color:#b4233f}.mw-brochure-success p{color:#18794e}.mw-brochure-success a{width:100%}
/* Download animations: arrow nudges on hover, drops while working; spinner, progress bar, check pop. */
.mw-floorplan-download-btn{gap:10px;border:0;cursor:pointer;transition:transform .2s,box-shadow .2s,opacity .2s}
.mw-floorplan-download-btn:hover{transform:translateY(-2px);box-shadow:0 12px 24px #24437340}
.mw-download-arrow{display:inline-block;font-size:18px;line-height:1}
.mw-brochure-button:hover .mw-download-arrow,.mw-floorplan-download-btn:hover .mw-download-arrow{animation:downloadNudge .8s ease-in-out infinite}
.mw-brochure-icon{overflow:hidden;transition:background .3s,color .3s}
.mw-brochure-icon.is-sending .mw-download-arrow,.mw-brochure-icon.is-downloading .mw-download-arrow{animation:downloadDrop 1s cubic-bezier(.5,0,.5,1) infinite}
.mw-brochure-icon.is-done{background:#e5f6ed;color:#18794e}
.mw-download-check{display:inline-block;font-size:26px;animation:downloadPop .45s cubic-bezier(.2,1.6,.4,1)}
.mw-brochure-submit{gap:10px}
.mw-download-spinner{width:16px;height:16px;border:2px solid #ffffff55;border-top-color:#fff;border-radius:50%;animation:downloadSpin .7s linear infinite}
.mw-download-status{display:flex;align-items:baseline;gap:6px;margin:0 0 12px;color:#263951!important;font-weight:600}
.mw-download-status strong{margin-left:auto;color:#203f68;font-variant-numeric:tabular-nums}
.mw-download-dots i{font-style:normal;animation:downloadDot 1.2s infinite}.mw-download-dots i:nth-child(2){animation-delay:.2s}.mw-download-dots i:nth-child(3){animation-delay:.4s}
.mw-download-bar{position:relative;height:10px;overflow:hidden;border-radius:999px;background:#edf3fa}
.mw-download-bar span{position:absolute;inset:0 auto 0 0;width:0;border-radius:inherit;background:linear-gradient(90deg,#ca2844,#244373);background-size:200% 100%;transition:width .2s ease-out;animation:downloadShimmer 1.2s linear infinite}
.mw-download-bar.is-indeterminate span{width:40%;animation:downloadSlide 1.1s ease-in-out infinite,downloadShimmer 1.2s linear infinite}
.mw-brochure-success{animation:brochureFade .25s ease-out}
.mw-brochure-form__field{display:grid;gap:6px;color:#263951;font-size:13px;font-weight:600}
.mw-brochure-form__field :deep(input){height:46px;border:1px solid #d7dfeb;border-radius:9px;font-family:inherit;font-size:15px;font-weight:400}
.mw-download-done{margin:0 0 12px;color:#18794e!important;font-weight:600;animation:brochureFade .3s ease-out}
.mw-download-bar.is-complete span{background:#18794e;animation:none}
.mw-brochure-close:disabled{opacity:.3;cursor:not-allowed}
@keyframes downloadNudge{0%,100%{transform:translateY(0)}50%{transform:translateY(3px)}}
@keyframes downloadDrop{0%{transform:translateY(-140%);opacity:0}25%{opacity:1}70%{transform:translateY(0);opacity:1}100%{transform:translateY(140%);opacity:0}}
@keyframes downloadPop{from{transform:scale(0)}to{transform:scale(1)}}
@keyframes downloadSpin{to{transform:rotate(360deg)}}
@keyframes downloadDot{0%,100%{opacity:.2}50%{opacity:1}}
@keyframes downloadShimmer{from{background-position:200% 0}to{background-position:0 0}}
@keyframes downloadSlide{from{left:-40%}to{left:100%}}
@media (prefers-reduced-motion:reduce){.mw-download-arrow,.mw-download-check,.mw-download-spinner,.mw-download-dots i,.mw-download-bar span{animation:none!important}}
@keyframes brochureFade{from{opacity:0}to{opacity:1}}@keyframes brochurePop{from{opacity:0;transform:translateY(18px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
@media(max-width:500px){.mw-brochure-dialog{padding:28px 22px;border-radius:18px}}
/* Regulatory information — reference, permit and licenses, with the permit QR. */
.mw-property-regulatory{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:clamp(18px,2vw,28px);border-radius:16px;background:#f5f6f8}
.mw-property-regulatory__body{flex:1;min-width:0}
.mw-property-regulatory .mw-property__section-title{margin-bottom:14px}
.mw-property-regulatory__list{display:grid;grid-template-columns:minmax(120px,170px) 1fr;gap:10px 16px;margin:0;font-size:14px}
.mw-property-regulatory__list dt{color:#35373c;font-weight:600}
.mw-property-regulatory__list dd{margin:0;color:#35373c;overflow-wrap:anywhere}
.mw-property-regulatory__link{display:inline-block;margin-top:14px;color:#203f68;font-size:13px;font-weight:600}
.mw-property-regulatory__qr{flex-shrink:0;width:132px;height:132px;object-fit:contain;border-radius:10px;background:#fff;padding:6px}
@media (max-width:575.98px){.mw-property-regulatory{flex-direction:column;align-items:flex-start}.mw-property-regulatory__list{grid-template-columns:1fr 1.4fr}}

/* Head badges: Premium / Upgraded, and the "Open house" flag that jumps to the dates. */
.mw-property__status-badge--premium{background:linear-gradient(90deg,#c9a24a,#a8812d)}
.mw-property__status-badge--upgraded{background:#203f68}
.mw-property__openhouse-flag{display:inline-flex;align-items:center;gap:8px;padding:6px 13px;border-radius:999px;border:1px solid #f0d2d9;background:#fdf3f5;color:#b9233c;font-family:"Plus Jakarta Sans",sans-serif;font-size:13px;font-weight:700;line-height:1.2;text-decoration:none;white-space:nowrap;transition:background .2s,border-color .2s}
.mw-property__openhouse-flag:hover{background:#fff;border-color:#b9233c}
.mw-property__openhouse-dot{width:8px;height:8px;border-radius:50%;background:#b9233c;box-shadow:0 0 0 0 #b9233c66;animation:openhousePulse 1.8s ease-out infinite}
@keyframes openhousePulse{0%{box-shadow:0 0 0 0 #b9233c66}100%{box-shadow:0 0 0 8px #b9233c00}}

/* Key features — one per line on the listing form — in a soft panel. */
.mw-property__keyfeatures{margin:0 0 clamp(24px,2.4vw,32px);padding:clamp(16px,2vw,22px) clamp(16px,2.2vw,24px);border-radius:14px;background:#f4f8fb;border:1px solid #e1e8ed}
.mw-property__keyfeatures .mw-property__subtitle{margin-bottom:12px}
.mw-property__keyfeatures ul{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 28px;margin:0;padding:0;list-style:none}
.mw-property__keyfeatures li{position:relative;padding-inline-start:26px;color:#35373c;font-family:"Plus Jakarta Sans",sans-serif;font-size:14px;font-weight:500;line-height:1.55}
.mw-property__keyfeatures li::before{content:"";position:absolute;inset-inline-start:0;top:4px;width:16px;height:16px;border-radius:50%;background:#b9233c url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fff' stroke-width='3.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12.5l4.5 4.5L19 7.5'/%3E%3C/svg%3E") center/10px no-repeat}

/* Property details: label left, value right, thin dividers; two columns on wide screens. */
.mw-property__specs-title{margin-bottom:6px}
.mw-property__specs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:clamp(24px,3vw,48px);margin:0}
.mw-property__spec{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:13px 2px;border-bottom:1px solid #e1e8ed;font-family:"Plus Jakarta Sans",sans-serif;font-size:14px;line-height:1.4}
.mw-property__spec dt{display:flex;align-items:center;gap:10px;min-width:0;color:#6a6a6a;font-weight:500}
.mw-property__spec dd{margin:0;color:#35373c;font-weight:700;text-align:end;overflow-wrap:anywhere}
.mw-property__spec-icon{flex-shrink:0;width:18px;height:18px;background-color:#b9233c;-webkit-mask:var(--icon) center/contain no-repeat;mask:var(--icon) center/contain no-repeat}

/* Tour links (open in a new tab). */
.mw-property__tours{display:flex;flex-wrap:wrap;gap:10px;margin-top:clamp(20px,2vw,26px)}
.mw-property__tour-link{display:inline-flex;align-items:center;gap:9px;padding:8px 14px 8px 8px;border-radius:999px;border:1px solid #e1e8ed;background:#fff;color:#35373c;font-family:"Plus Jakarta Sans",sans-serif;font-size:14px;font-weight:600;text-decoration:none;transition:border-color .2s,color .2s}
.mw-property__tour-link:hover{border-color:#b9233c;color:#b9233c}
.mw-property__tour-link-icon{display:inline-grid;place-items:center;min-width:30px;height:26px;padding:0 6px;border-radius:999px;background:#f8e9ec;color:#b9233c;font-size:11px;font-weight:700}
.mw-property__tour-link-ext{color:#9aa3ad;font-size:13px}
@media (max-width:767.98px){.mw-property__specs,.mw-property__keyfeatures ul{grid-template-columns:minmax(0,1fr)}}

/* Price & availability: price terms grid, then the open house / viewing days. */
.mw-property__terms{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:clamp(12px,1.4vw,16px)}
.mw-property__term{display:flex;flex-direction:column;gap:6px;padding:clamp(14px,1.6vw,18px) clamp(14px,1.8vw,20px);border-radius:12px;border:1px solid #e1e8ed;background:#fff;box-shadow:0 6px 16px rgba(20,20,21,.04)}
.mw-property__term:first-child{background:linear-gradient(135deg,#f8e9ec 0%,#f0d2d9 100%);border-color:#f0d2d9}
.mw-property__term-label{color:#6a6a6a;font-family:"Plus Jakarta Sans",sans-serif;font-size:13px;font-weight:600}
.mw-property__term-value{color:#35373c;font-family:"Plus Jakarta Sans",sans-serif;font-size:clamp(15px,1.6vw,17px);font-weight:700;overflow-wrap:anywhere}
.mw-property__term:first-child .mw-property__term-value{color:#b9233c}
.mw-property__subtitle{margin:0;color:#35373c;font-family:"Plus Jakarta Sans",sans-serif;font-size:clamp(15px,1.6vw,17px);font-weight:600}
.mw-property__openhouse{margin-top:clamp(20px,2vw,28px);padding:clamp(16px,2vw,24px);border-radius:16px;background:#f4f8fb;border:1px solid #e1e8ed}
.mw-property__openhouse-head{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}
.mw-property__avail-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;background:#e5f6ed;color:#18794e;font-family:"Plus Jakarta Sans",sans-serif;font-size:12px;font-weight:700}
.mw-property__avail-badge::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
.mw-property__dates{display:flex;flex-wrap:wrap;gap:12px}
.mw-property__date{flex:0 0 auto;display:flex;flex-direction:column;align-items:center;gap:2px;min-width:104px;padding:12px 14px 10px;border:1px solid #e1e8ed;border-radius:14px;background:#fff;color:#35373c;font-family:"Plus Jakarta Sans",sans-serif;cursor:pointer;transition:transform .2s,border-color .2s,box-shadow .2s}
.mw-property__date:hover,.mw-property__date:focus-visible{transform:translateY(-3px);border-color:#b9233c;box-shadow:0 12px 24px rgba(185,35,60,.14);outline:none}
.mw-property__date-day{color:#b9233c;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
.mw-property__date-num{font-size:28px;font-weight:700;line-height:1.1}
.mw-property__date-month{color:#6a6a6a;font-size:12px;font-weight:500}
.mw-property__date-cta{margin-top:8px;padding:5px 10px;border-radius:999px;background:#b9233c;color:#fff;font-size:11px;font-weight:700;white-space:nowrap;transition:background .2s}
.mw-property__date:hover .mw-property__date-cta{background:#203f68}
.mw-property__openhouse-text{margin:0;color:#6a6a6a;font-family:"Plus Jakarta Sans",sans-serif;font-size:14px;line-height:1.7}
.mw-property__link-btn{padding:0;margin-inline-start:4px;border:0;background:none;color:#b9233c;font:inherit;font-weight:700;text-decoration:underline;text-underline-offset:3px;cursor:pointer}
.mw-property__link-btn:hover{color:#203f68}
.mw-property__viewing-btn{display:inline-flex;align-items:center;gap:8px;margin-top:16px;padding:11px 20px;border:0;border-radius:10px;background:#b9233c;color:#fff;font-family:"Plus Jakarta Sans",sans-serif;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 8px 20px rgba(185,35,60,.22);transition:transform .2s,background .2s}
.mw-property__viewing-btn:hover{transform:translateY(-2px);background:#203f68}
.mw-property-enquiry__viewing{width:100%;margin-top:10px;min-height:48px;border:1.5px solid #203f68;border-radius:8px;background:#fff;color:#203f68;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;transition:background .2s,color .2s}
.mw-property-enquiry__viewing:hover{background:#203f68;color:#fff}
.mw-viewing-dialog{width:min(100%,520px);max-height:calc(100vh - 40px);overflow-y:auto}
.mw-viewing__group{display:grid;gap:8px}
.mw-viewing__label{color:#263951;font-size:13px;font-weight:600}
.mw-viewing__days{display:flex;gap:8px;overflow-x:auto;padding:2px 2px 6px;scrollbar-width:thin}
.mw-viewing__day{flex:0 0 62px;display:flex;flex-direction:column;align-items:center;gap:1px;padding:8px 4px;border:1.5px solid #d7dfeb;border-radius:12px;background:#fff;color:#263951;font-family:inherit;cursor:pointer;transition:border-color .15s,background .15s,transform .15s}
.mw-viewing__day strong{font-size:20px;line-height:1.15}
.mw-viewing__day-name,.mw-viewing__day-month{font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase}
.mw-viewing__day:hover{border-color:#203f68;transform:translateY(-2px)}
.mw-viewing__day.is-active{border-color:#b9233c;background:#b9233c;color:#fff}
.mw-viewing__day.is-active span{color:#fde8ec}
.mw-viewing__slots{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.mw-viewing__slot{display:flex;flex-direction:column;gap:2px;padding:10px 6px;border:1.5px solid #d7dfeb;border-radius:10px;background:#fff;color:#263951;font-family:inherit;cursor:pointer;transition:border-color .15s,background .15s}
.mw-viewing__slot strong{font-size:13px}.mw-viewing__slot span{font-size:11px;color:#64748b}
.mw-viewing__slot:hover{border-color:#203f68}
.mw-viewing__slot.is-active{border-color:#203f68;background:#203f68;color:#fff}.mw-viewing__slot.is-active span{color:#dbe5f3}
.mw-brochure-form textarea{width:100%;padding:10px 12px;border:1px solid #d7dfeb;border-radius:9px;font-family:inherit;font-size:15px;font-weight:400;resize:vertical}
.mw-viewing__done{text-align:center;display:grid;justify-items:center}
.mw-viewing__done p{margin:0 0 14px;color:#64748b}
.mw-viewing__when{display:inline-block;padding:8px 14px;border-radius:999px;background:#f4f8fb;color:#203f68 !important;font-weight:700}
.mw-viewing__done .mw-brochure-submit{width:100%}
@media(max-width:500px){.mw-viewing__slots{grid-template-columns:1fr}}

/* Features: Amenities / Easy access / Highlights, each its own chip row. */
.mw-property__feature-group+.mw-property__feature-group{margin-top:clamp(18px,2vw,26px)}
.mw-property__feature-group .mw-property__subtitle{margin-bottom:12px}

/* Floor plan price under the size. */
.mw-property__floorplan-price{margin:4px 0 0;color:#b9233c;font-size:14px;font-weight:700}


/* Location: full address above the map. */
.mw-property__address{display:flex;align-items:flex-start;gap:10px;margin:0 0 16px;color:#35373c;font-family:"Plus Jakarta Sans",sans-serif;font-size:15px;font-weight:500;line-height:1.6}
.mw-property__address img{flex-shrink:0;margin-top:3px;opacity:.7}

@media (prefers-reduced-motion:reduce){.mw-property__openhouse-dot{animation:none}}
</style>

<style>
/* The site sets overflow-x: hidden on body / main, which stops a sticky sidebar from sticking
   (it becomes relative to a box that never scrolls). clip hides sideways overflow without that. */
body:has(.mw-property__agent-wrap),
main:has(.mw-property__agent-wrap) { overflow-x: clip; }

/* Photo Gallery: main photo + up to 4 thumbnails (2 × 2) filling the same height; "+N" on the last. */
.mw-gallery-grid--compact { grid-template-columns: repeat(2, minmax(0, 1fr)); align-content: start; }
.mw-gallery-grid--n1 { grid-template-columns: minmax(0, 1fr); }
.mw-gallery-grid--n3 .mw-gallery-thumb:first-child { grid-column: span 2; }
.mw-gallery-grid--compact .mw-gallery-thumb { position: relative; }
.mw-gallery-grid--compact .mw-gallery-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.mw-gallery-thumb__more {
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    background: rgba(10, 15, 26, 0.55); color: #fff; font-weight: 700; font-size: clamp(15px, 1.4vw, 19px);
    letter-spacing: 0.01em; transition: background 0.2s ease;
}
.mw-gallery-thumb:hover .mw-gallery-thumb__more { background: rgba(10, 15, 26, 0.68); }

/* Listing description (rich text from the listing editor). */
.mw-property__rich > :first-child { margin-top: 0; }
.mw-property__rich > :last-child { margin-bottom: 0; }
.mw-property__rich p { margin: 0 0 0.9em; }
.mw-property__rich ul, .mw-property__rich ol { margin: 0 0 0.9em; padding-inline-start: 1.3em; }
.mw-property__rich li { margin-bottom: 0.3em; }
.mw-property__rich h2, .mw-property__rich h3, .mw-property__rich h4 { margin: 1em 0 0.5em; font-size: 1.05em; font-weight: 700; }
.mw-property__rich a { color: inherit; text-decoration: underline; }
.mw-property__rich img { max-width: 100%; height: auto; border-radius: 10px; }
</style>
