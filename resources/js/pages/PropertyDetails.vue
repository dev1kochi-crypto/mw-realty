<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePropertyDetail } from '../composables/usePropertyDetail';
import { useLanguages } from '../composables/useLanguages';
import { useRecaptcha } from '../composables/useRecaptcha';
import { useStaticText } from '../composables/useStaticText';
import { useCurrency } from '../composables/useCurrency';
import AdBlock from '../components/AdBlock.vue';

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

const enquiryForm = reactive({ name: '', email: '', phone: '', message: '' });
const enquirySubmitting = ref(false);
const enquiryFeedback = ref(null);
const brochureOpen = ref(false);
const brochureSubmitting = ref(false);
const brochureFeedback = ref(null);
const brochureForm = reactive({ name: '', email: '', phone: '' });

watch(
    () => property.value?.name,
    (name) => {
        if (name) enquiryForm.message = `I'm interested in "${name}" — please share more details.`;
    },
    { immediate: true },
);

async function handleEnquirySubmit() {
    if (enquirySubmitting.value) return;
    enquirySubmitting.value = true;
    enquiryFeedback.value = null;

    try {
        const recaptcha_token = await getRecaptchaToken('property_enquiry');
        await window.axios.post('/leads/capture', {
            property_id: property.value.id,
            name: enquiryForm.name,
            email: enquiryForm.email,
            phone: enquiryForm.phone,
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
        enquiryFeedback.value = { type: 'error', text: error.response?.data?.message || t('property_details.generic_error') };
        enquirySubmitting.value = false;
    }
}

async function handleBrochureSubmit() {
    if (brochureSubmitting.value || !property.value?.brochure_available) return;
    brochureSubmitting.value = true;
    brochureFeedback.value = null;
    try {
        const recaptcha_token = await getRecaptchaToken('brochure_download');
        const { data } = await window.axios.post('/leads/brochure-download', {
            property_id: property.value.id,
            ...brochureForm,
            recaptcha_token,
        });
        brochureFeedback.value = { type: 'success', text: data.message, url: data.brochure_url };
    } catch (error) {
        const errors = error.response?.data?.errors;
        brochureFeedback.value = {
            type: 'error',
            text: errors ? Object.values(errors).flat()[0] : (error.response?.data?.message || t('property_details.generic_error')),
        };
    } finally {
        brochureSubmitting.value = false;
    }
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
                        <button v-if="property.brochure_available" type="button" class="mw-brochure-button" @click="brochureOpen = true; brochureFeedback = null">
                            <span aria-hidden="true">↓</span> Download Brochure
                        </button>
                    </div>
                </div>

                <div class="mw-property__tabs-bar" data-property-tabs-bar>
                    <nav class="mw-property__tabs" role="tablist" :aria-label="t('property_details.tabs_aria')">
                        <a href="#overview" class="mw-property__tab is-active" role="tab" aria-selected="true" data-property-tab="overview">{{ t('property_details.tab_overview') }}</a>
                        <a v-if="property.amenities.length" href="#features" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="features">{{ t('property_details.tab_features') }}</a>
                        <a href="#gallery" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="gallery">{{ t('property_details.tab_gallery') }}</a>
                    </nav>

                    <select class="mw-property__tabs-select" :aria-label="t('property_details.tabs_aria')" data-property-tabs-select>
                        <option value="overview">{{ t('property_details.tab_overview') }}</option>
                        <option v-if="property.amenities.length" value="features">{{ t('property_details.tab_features') }}</option>
                        <option value="gallery">{{ t('property_details.tab_gallery') }}</option>
                    </select>
                </div>

                <div class="mw-property__body">
                    <div class="mw-property__main">

                        <section id="overview" class="mw-property__section" data-reveal data-property-panel="overview">
                            <h3 class="mw-property__section-title">{{ t('property_details.overview_title') }}</h3>
                            <div class="mw-property__about-text">
                                <p>{{ property.description || descriptionFallback }}</p>
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
                                <span v-for="amenity in property.amenities" :key="amenity" class="mw-property__chip">{{ amenity }}</span>
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
                                <div class="mw-property__photo-gallery-grid">
                                    <a v-for="(src, index) in property.images.slice(1)" :key="index" :href="src" data-fancybox="property-gallery" :data-caption="`${property.name} — photo ${index + 2}`"><img :src="src" :alt="`${property.name} photo`" loading="lazy"></a>
                                </div>
                            </div>
                        </section>

                    </div>

                    <aside class="mw-property__agent-wrap" data-reveal>
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
                                    <input type="tel" id="enquiry-phone" name="phone" :placeholder="t('property_details.enquiry.phone_placeholder')" v-model="enquiryForm.phone">
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
        <div v-if="brochureOpen" class="mw-brochure-overlay" @click.self="brochureOpen = false" @keydown.esc="brochureOpen = false">
            <section class="mw-brochure-dialog" role="dialog" aria-modal="true" aria-labelledby="brochure-title">
                <button type="button" class="mw-brochure-close" aria-label="Close" @click="brochureOpen = false">×</button>
                <div class="mw-brochure-icon" aria-hidden="true">↓</div>
                <h2 id="brochure-title">Get the property brochure</h2>
                <p>Share your contact details to access the brochure.</p>
                <form v-if="!brochureFeedback || brochureFeedback.type !== 'success'" class="mw-brochure-form" @submit.prevent="handleBrochureSubmit">
                    <label>Name<input v-model="brochureForm.name" required autocomplete="name" maxlength="255"></label>
                    <label>Email<input v-model="brochureForm.email" required type="email" autocomplete="email" maxlength="255"></label>
                    <label>Phone<input v-model="brochureForm.phone" required type="tel" autocomplete="tel" maxlength="50"></label>
                    <p v-if="brochureFeedback" class="mw-brochure-feedback is-error" role="alert">{{ brochureFeedback.text }}</p>
                    <button type="submit" class="mw-brochure-submit" :disabled="brochureSubmitting">
                        {{ brochureSubmitting ? 'Preparing…' : 'Continue to download' }}
                    </button>
                </form>
                <div v-else class="mw-brochure-success" role="status">
                    <p>{{ brochureFeedback.text }}</p>
                    <a :href="brochureFeedback.url" target="_blank" rel="noopener" @click="brochureOpen = false">Download PDF</a>
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
@keyframes brochureFade{from{opacity:0}to{opacity:1}}@keyframes brochurePop{from{opacity:0;transform:translateY(18px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
@media(max-width:500px){.mw-brochure-dialog{padding:28px 22px;border-radius:18px}}
</style>
