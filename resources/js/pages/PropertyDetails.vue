<script setup>
import { computed, nextTick, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { usePropertyDetail } from '../composables/usePropertyDetail';
import { useLanguages } from '../composables/useLanguages';

const route = useRoute();
const { property, notFound, fetchProperty } = usePropertyDetail();
const { selectedLanguage } = useLanguages();

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

const pills = computed(() => {
    if (!property.value) return [];
    const p = property.value;
    const list = [
        { icon: '/frontend/assets/images/property-details/tag.svg', label: 'Rera ID', value: p.rera_id || '—' },
        { icon: '/frontend/assets/images/property-details/status.svg', label: 'Status', value: p.completion_status_label || '—' },
        { icon: '/frontend/assets/images/icons/filter.svg', label: 'Type', value: p.type },
    ];
    if (p.beds) list.push({ icon: '/frontend/assets/images/icons/bed.svg', label: 'Bedrooms', value: p.beds });
    if (p.baths) list.push({ icon: '/frontend/assets/images/icons/bathroom.svg', label: 'Bathrooms', value: p.baths });
    list.push({ icon: '/frontend/assets/images/icons/location.svg', label: 'Location', value: p.location });
    list.push({ icon: '/frontend/assets/images/icons/area.svg', label: 'Size', value: p.area });
    if (p.garage) list.push({ icon: '/frontend/assets/images/property-details/garage.svg', label: 'Garage', value: p.garage });
    if (p.year_built) list.push({ icon: '/frontend/assets/images/property-details/calendar.svg', label: 'Year Built', value: p.year_built });
    return list;
});

function whatsappUrl(number) {
    return `https://wa.me/${(number || '').replace(/[^0-9]/g, '')}`;
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
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><router-link to="/properties-dubai">Properties in Dubai</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ property.name }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-property">
            <div class="mw-property__gallery-bleed" data-reveal>
                <div class="mw-property__gallery">
                    <div class="mw-agent-more__track" data-property-gallery-slider>
                        <div v-for="(src, index) in property.images" :key="index" class="mw-agent-more__slide">
                            <div class="mw-property__gallery-item">
                                <img :src="src" :alt="`${property.name} — photo ${index + 1}`">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="mw-property__gallery-arrow mw-property__gallery-arrow--prev" data-property-gallery-prev aria-label="Previous photo">
                        <img src="/frontend/assets/images/icons/chevron.svg" alt="" width="18" height="18">
                    </button>
                    <button type="button" class="mw-property__gallery-arrow mw-property__gallery-arrow--next" data-property-gallery-next aria-label="Next photo">
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
                        <span v-if="property.beds" class="mw-property__meta-item"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="16" height="16">{{ property.beds }} Bedrooms</span>
                        <span v-if="property.baths" class="mw-property__meta-item"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="16" height="16">{{ property.baths }} Bathrooms</span>
                        <span v-if="property.published_at" class="mw-property__meta-item"><img src="/frontend/assets/images/property-details/calendar.svg" alt="" width="16" height="16">Published: {{ property.published_at }}</span>
                    </div>
                    <div class="mw-property__price">
                        <span class="mw-property__price-label">{{ property.listing_type === 'rent' ? 'Yearly Rent' : 'Starting Price' }}</span>
                        <strong v-if="property.price" class="mw-property__price-value">
                            <span class="mw-property__price-currency">{{ property.currency }}</span>
                            <span class="mw-property__price-amount">{{ property.price }}</span>
                        </strong>
                        <strong v-else class="mw-property__price-value">Price on request</strong>
                    </div>
                </div>

                <div class="mw-property__tabs-bar" data-property-tabs-bar>
                    <nav class="mw-property__tabs" role="tablist" aria-label="Property sections">
                        <a href="#overview" class="mw-property__tab is-active" role="tab" aria-selected="true" data-property-tab="overview">Overview</a>
                        <a v-if="property.amenities.length" href="#features" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="features">Features &amp; Amenities</a>
                        <a href="#gallery" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="gallery">Gallery</a>
                    </nav>

                    <select class="mw-property__tabs-select" aria-label="Property sections" data-property-tabs-select>
                        <option value="overview">Overview</option>
                        <option v-if="property.amenities.length" value="features">Features &amp; Amenities</option>
                        <option value="gallery">Gallery</option>
                    </select>
                </div>

                <div class="mw-property__body">
                    <div class="mw-property__main">

                        <section id="overview" class="mw-property__section" data-reveal data-property-panel="overview">
                            <h3 class="mw-property__section-title">About This Property</h3>
                            <div class="mw-property__about-text">
                                <p>{{ property.description || `A ${property.type} located in ${property.location}.` }}</p>
                            </div>

                            <div class="mw-property__pills">
                                <div v-for="pill in pills" :key="pill.label" class="mw-property__pill">
                                    <span class="mw-property__pill-icon"><span class="mw-property__pill-icon-glyph" :style="{ '--icon': `url('${pill.icon}')` }" aria-hidden="true"></span></span>
                                    <span class="mw-property__pill-text"><span class="mw-property__pill-label">{{ pill.label }}</span><span class="mw-property__pill-value">{{ pill.value }}</span></span>
                                </div>
                            </div>
                        </section>

                        <section v-if="property.amenities.length" id="features" class="mw-property__section" data-reveal data-property-panel="features">
                            <h3 class="mw-property__section-title">Features &amp; Amenities</h3>
                            <div class="mw-property__chips">
                                <span v-for="amenity in property.amenities" :key="amenity" class="mw-property__chip">{{ amenity }}</span>
                            </div>
                        </section>

                        <section id="gallery" class="mw-property__section" data-reveal data-property-panel="gallery">
                            <h3 class="mw-property__section-title">Photo Gallery</h3>
                            <div class="mw-property__photo-gallery">
                                <div class="mw-property__photo-gallery-main">
                                    <a :href="property.images[0]" data-fancybox="property-gallery" :data-caption="`${property.name} — photo 1`">
                                        <img :src="property.images[0]" :alt="`${property.name} photo gallery — main view`">
                                    </a>
                                </div>
                                <div class="mw-property__photo-gallery-grid">
                                    <a v-for="(src, index) in property.images.slice(1)" :key="index" :href="src" data-fancybox="property-gallery" :data-caption="`${property.name} — photo ${index + 2}`"><img :src="src" :alt="`${property.name} photo`"></a>
                                </div>
                            </div>
                        </section>

                    </div>

                    <aside class="mw-property__agent-wrap" data-reveal>
                        <article v-if="property.contact" class="mw-agent-card mw-property-agent">
                            <div class="mw-agent-card__head">
                                <div class="mw-agent-card__avatar">
                                    <img :src="property.contact.avatar_url || '/frontend/assets/images/agents/ahmed.png'" :alt="property.contact.name" width="80" height="80">
                                </div>
                                <div class="mw-agent-card__intro">
                                    <h4 class="mw-agent-card__name">{{ property.contact.name }}</h4>
                                    <div class="mw-agent-card__tags">
                                        <span class="mw-agent-card__tag mw-agent-card__tag--fill">Serves in Dubai</span>
                                        <span v-for="badge in property.contact.badges" :key="badge" class="mw-agent-card__tag">{{ badge }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mw-agent-card__body">
                                <p v-if="property.contact.years_of_experience" class="mw-property-agent__stat">Years of Experience: {{ property.contact.years_of_experience }}</p>
                                <p v-if="property.contact.preferred_areas?.length" class="mw-property-agent__stat">Preferred Areas: {{ property.contact.preferred_areas.join(', ') }}</p>
                                <div class="mw-property-agent__cta">
                                    <a v-if="property.contact.phone" :href="`tel:${property.contact.phone}`" class="mw-property-agent__cta-btn mw-property-agent__cta-btn--call" :aria-label="`Call ${property.contact.name}`">
                                        <img src="/frontend/assets/images/icons/phone.svg" alt="" width="18" height="18">
                                    </a>
                                    <a v-if="property.contact.whatsapp_number" :href="whatsappUrl(property.contact.whatsapp_number)" target="_blank" rel="noopener" class="mw-property-agent__cta-btn mw-property-agent__cta-btn--whatsapp" :aria-label="`WhatsApp ${property.contact.name}`">
                                        <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="18" height="18">
                                    </a>
                                    <a v-if="property.contact.email" :href="`mailto:${property.contact.email}`" class="mw-property-agent__cta-btn mw-property-agent__cta-btn--email" :aria-label="`Email ${property.contact.name}`">
                                        <img src="/frontend/assets/images/icons/email.svg" alt="" width="18" height="18">
                                    </a>
                                </div>
                            </div>
                            <div class="mw-agent-card__foot">
                                <img class="mw-agent-card__logo" src="/frontend/assets/images/logo-dark.png" alt="MW Realty" width="50" height="28">
                                <span class="mw-agent-card__rule" aria-hidden="true"></span>
                                <router-link :to="property.contact.detail_url" class="mw-agent-card__link">
                                    View Details
                                    <img src="/frontend/assets/images/icons/find-cta-arrow.svg" alt="" width="18" height="18">
                                </router-link>
                            </div>
                        </article>

                        <div class="mw-property-enquiry">
                            <h4 class="mw-property-enquiry__title">Enquire About This Property</h4>
                            <form class="mw-property-enquiry__form" novalidate @submit.prevent>
                                <div class="mw-property-enquiry__field">
                                    <label for="enquiry-name">Name*</label>
                                    <input type="text" id="enquiry-name" name="name" placeholder="Your name" required>
                                </div>
                                <div class="mw-property-enquiry__field">
                                    <label for="enquiry-email">Email*</label>
                                    <input type="email" id="enquiry-email" name="email" placeholder="Your email" required>
                                </div>
                                <div class="mw-property-enquiry__field">
                                    <label for="enquiry-phone">Phone</label>
                                    <input type="tel" id="enquiry-phone" name="phone" placeholder="Your phone">
                                </div>
                                <div class="mw-property-enquiry__field">
                                    <label for="enquiry-property">Property</label>
                                    <input type="text" id="enquiry-property" name="property" :value="property.name" disabled>
                                </div>
                                <div class="mw-property-enquiry__field">
                                    <label for="enquiry-message">Message</label>
                                    <textarea id="enquiry-message" name="message" rows="4" :placeholder="`I'm interested in this property...`">{{ `I'm interested in "${property.name}" — please share more details.` }}</textarea>
                                </div>

                                <button type="submit" class="mw-btn mw-btn--gradient mw-property-enquiry__submit">Send Enquiry</button>
                            </form>
                        </div>

                        <router-link to="/#post-property" class="mw-property-ad">
                            <span class="mw-property-ad__label">Advertisement</span>
                            <img src="/frontend/assets/images/home/post-property.jpg" alt="" class="mw-property-ad__bg">
                            <span class="mw-property-ad__content">
                                <span class="mw-property-ad__eyebrow">MW Realty</span>
                                <span class="mw-property-ad__title">Sell Your Property Faster</span>
                                <span class="mw-property-ad__text">List with MW Realty and reach thousands of verified buyers across the UAE.</span>
                                <span class="mw-property-ad__cta">
                                    Post Your Property
                                    <img src="/frontend/assets/images/icons/find-cta-arrow.svg" alt="" width="16" height="16">
                                </span>
                            </span>
                        </router-link>
                    </aside>
                </div>

            </div>
        </section>

        <section v-if="property.similar.length" class="mw-agent-more">
            <div class="container-ctn">
                <div class="mw-agent-more__head">
                    <h2 class="mw-agent-more__title">Similar Properties</h2>
                    <div class="mw-agent-more__nav">
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--prev mw-agent-more__nav-btn" data-similar-properties-prev aria-label="Previous properties">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--next mw-agent-more__nav-btn" data-similar-properties-next aria-label="Next properties">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                    </div>
                </div>

                <div class="mw-agent-more__track" data-similar-properties-slider>

                    <div v-for="similar in property.similar" :key="similar.slug" class="mw-agent-more__slide"><article class="mw-agent-prop">
                        <router-link :to="`/property-details/${similar.slug}`" class="mw-agent-prop__media" data-card-gallery>
                            <div class="mw-agent-prop__slides" data-gallery-track>
                                <img v-for="(src, pIndex) in similar.images" :key="pIndex" :src="src" :alt="similar.name" class="mw-agent-prop__photo">
                            </div>
                            <span v-if="similar.images_count" class="mw-agent-prop__count">
                                <img src="/frontend/assets/images/icons/photo-count.svg" alt="" width="16" height="16">
                                <span data-gallery-count>{{ similar.images_count }}</span>
                            </span>
                            <div class="mw-agent-prop__overlay">
                                <span class="mw-agent-prop__type">{{ similar.type }}</span>
                                <span class="mw-agent-prop__price">{{ similar.price }}</span>
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
                                View Details
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
                    <h1 class="mw-about-title" data-reveal>Property Not Found</h1>
                </div>
            </div>
        </section>
        <section class="mw-property">
            <div class="container-ctn">
                <p>This property listing doesn't exist or is no longer available. <router-link to="/properties-dubai">Back to Properties</router-link></p>
            </div>
        </section>
    </main>
</template>
