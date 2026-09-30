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
    if (p.garage) list.push({ icon: '/frontend/assets/images/property-details/garage.svg', label: t('property_details.pills.garage'), value: p.garage });
    if (p.year_built) list.push({ icon: '/frontend/assets/images/property-details/calendar.svg', label: t('property_details.pills.year_built'), value: p.year_built });
    return list;
});

// Floor Plans: the selected row drives the drawing, its zoom link, the download and the caption.
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
                        <span class="mw-property__meta-item"><img src="/frontend/assets/images/icons/location.svg" alt="" width="16" height="16">{{ property.location }}</span>
                        <span v-if="property.beds" class="mw-property__meta-item"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="16" height="16">{{ property.beds }} {{ t('property_details.meta_bedrooms_suffix') }}</span>
                        <span v-if="property.baths" class="mw-property__meta-item"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="16" height="16">{{ property.baths }} {{ t('property_details.meta_bathrooms_suffix') }}</span>
                        <span v-if="property.published_at" class="mw-property__meta-item"><img src="/frontend/assets/images/property-details/calendar.svg" alt="" width="16" height="16">{{ t('property_details.meta_published_prefix') }}: {{ property.published_at }}</span>
                    </div>
                    <div class="mw-property__price">
                        <span class="mw-property__price-label">{{ property.listing_type === 'rent' ? t('property_details.price_yearly_rent') : t('property_details.price_starting_price') }}</span>
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
                        <a v-if="property.amenities.length" href="#features" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="features">{{ t('property_details.tab_features') }}</a>
                        <a v-if="property.floor_plans?.length" href="#floor-plans" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="floor-plans">{{ t('property_details.tab_floor_plans', 'Floor Plans') }}</a>
                        <a href="#gallery" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="gallery">{{ t('property_details.tab_gallery') }}</a>
                    </nav>

                    <select class="mw-property__tabs-select" :aria-label="t('property_details.tabs_aria')" data-property-tabs-select>
                        <option value="overview">{{ t('property_details.tab_overview') }}</option>
                        <option v-if="property.amenities.length" value="features">{{ t('property_details.tab_features') }}</option>
                        <option v-if="property.floor_plans?.length" value="floor-plans">{{ t('property_details.tab_floor_plans', 'Floor Plans') }}</option>
                        <option value="gallery">{{ t('property_details.tab_gallery') }}</option>
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

                            <div class="mw-property__pills">
                                <div v-for="pill in pills" :key="pill.label" class="mw-property__pill">
                                    <span class="mw-property__pill-icon"><span class="mw-property__pill-icon-glyph" :style="{ '--icon': `url('${pill.icon}')` }" aria-hidden="true"></span></span>
                                    <span class="mw-property__pill-text"><span class="mw-property__pill-label">{{ pill.label }}</span><span class="mw-property__pill-value">{{ pill.value }}</span></span>
                                </div>
                            </div>
                        </section>

                        <section v-if="property.amenities.length" id="features" class="mw-property__section" data-reveal data-property-panel="features">
                            <h3 class="mw-property__section-title">{{ t('property_details.tab_features') }}</h3>
                            <div class="mw-property__chips">
                                <span v-for="amenity in property.amenities" :key="amenity.label" class="mw-property__chip">
                                    <img :src="amenity.icon" alt="" width="22" height="22" loading="lazy">{{ amenity.label }}
                                </span>
                            </div>
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

                        <!-- DLD advertising permit (Madmoun): lets buyers verify the ad with DLD. -->
                        <div v-if="property.permit" class="mw-property-permit">
                            <div class="mw-property-permit__text">
                                <h4 class="mw-property-permit__title">{{ t('property_details.permit.title', 'Advertisement Verification') }}</h4>
                                <p class="mw-property-permit__number">{{ t('property_details.permit.number_label', 'DLD Permit Number') }}: <strong>{{ property.permit.number }}</strong></p>
                                <a v-if="property.permit.verify_url" :href="property.permit.verify_url" target="_blank" rel="noopener noreferrer" class="mw-property-permit__link">{{ t('property_details.permit.verify', 'Verify with DLD') }}</a>
                            </div>
                            <img v-if="property.permit.qr_url" :src="property.permit.qr_url" :alt="t('property_details.permit.qr_alt', 'DLD permit QR code')" width="96" height="96" class="mw-property-permit__qr" loading="lazy">
                        </div>

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
/* DLD permit card — same frame as the enquiry card below it. */
.mw-property-permit{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:clamp(16px,1.8vw,20px);padding:clamp(16px,1.6vw,22px);border:1px solid #e1e8ed;border-radius:14px;background:#fff;box-shadow:0 6px 16px rgba(20,20,21,.04)}
.mw-property-permit__title{margin:0 0 6px;color:#141415;font-family:"Plus Jakarta Sans",sans-serif;font-size:16px;font-weight:700}
.mw-property-permit__number{margin:0;color:#475569;font-size:14px;overflow-wrap:anywhere}.mw-property-permit__number strong{color:#141415}
.mw-property-permit__link{display:inline-block;margin-top:6px;color:#203f68;font-size:13px;font-weight:600}
.mw-property-permit__qr{flex-shrink:0;width:96px;height:96px;object-fit:contain;border-radius:8px;background:#fff}
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
