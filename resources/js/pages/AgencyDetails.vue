<script setup>
import { nextTick, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAgencyDetail } from '../composables/useAgencyDetail';
import { useLanguages } from '../composables/useLanguages';
import { useWishlist } from '../composables/useWishlist';

const route = useRoute();
const { agency, notFound, fetchAgency } = useAgencyDetail();
const { selectedLanguage } = useLanguages();
const { isWishlisted, toggleWishlist } = useWishlist();

function load() {
    fetchAgency(route.params.slug, selectedLanguage.value?.code);
}

onMounted(load);
watch(() => route.params.slug, load);
watch(selectedLanguage, load);
watch(
    () => agency.value?.name,
    (name) => {
        if (name) document.title = `${name} | MW Realty`;
    },
);

// See Blogs.vue for why this re-run is needed after the async fetch populates the page.
watch(agency, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});
</script>

<template>
    <main v-if="agency">
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ agency.name }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><router-link to="/agencies">Agencies</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ agency.name }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-agency-detail">
            <div class="container-ctn">

                <article class="mw-agency-profile" data-reveal>
                    <div class="mw-agency-profile__logo">
                        <img :src="agency.logo_url || '/frontend/assets/images/agencies/logo-dxb-dubai.png'" :alt="agency.name" width="176" height="166">
                    </div>

                    <div class="mw-agency-profile__info">
                        <span v-if="agency.founding_year" class="mw-agency-profile__since">Since {{ agency.founding_year }}</span>

                        <div class="mw-agency-profile__head">
                            <h2 class="mw-agency-profile__name">{{ agency.name }}</h2>
                            <div class="mw-agency-profile__contacts">
                                <a v-if="agency.phone" :href="`tel:${agency.phone}`" class="mw-agency-profile__icon-btn" :aria-label="`Call ${agency.name}`">
                                    <img src="/frontend/assets/images/icons/phone.svg" alt="" width="24" height="24">
                                </a>
                                <a v-if="agency.email" :href="`mailto:${agency.email}`" class="mw-agency-profile__icon-btn" :aria-label="`Email ${agency.name}`">
                                    <img src="/frontend/assets/images/icons/email.svg" alt="" width="24" height="24">
                                </a>
                                <a v-if="agency.whatsapp_number" :href="`https://wa.me/${agency.whatsapp_number.replace(/[^0-9]/g, '')}`" target="_blank" rel="noopener" class="mw-agency-profile__icon-btn" :aria-label="`WhatsApp ${agency.name}`">
                                    <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="24" height="24">
                                </a>
                            </div>
                        </div>

                        <p class="mw-agency-profile__area">
                            <img src="/frontend/assets/images/icons/location.svg" alt="" width="18" height="18">
                            {{ agency.office_address || 'Services Area : Dubai' }}
                        </p>

                        <div class="mw-agency-profile__stats">
                            <span class="mw-agency-profile__stat"><img src="/frontend/assets/images/agency-details/icon-user-tie.svg" alt="" width="24" height="24">{{ agency.agent_count }} Total Agent</span>
                            <span class="mw-agency-profile__stat"><img src="/frontend/assets/images/agency-details/icon-user-tie.svg" alt="" width="24" height="24">{{ agency.total_properties_count }} Total Properties</span>
                            <span class="mw-agency-profile__stat"><img src="/frontend/assets/images/agency-details/icon-user-tie.svg" alt="" width="24" height="24">{{ agency.for_sale_count }} For Sale</span>
                            <span class="mw-agency-profile__stat"><img src="/frontend/assets/images/agency-details/icon-user-tie.svg" alt="" width="24" height="24">{{ agency.for_rent_count }} For Rent</span>
                        </div>

                        <p class="mw-agency-profile__desc">{{ agency.bio || `${agency.name} is a Dubai-based real estate agency.` }}</p>
                    </div>

                    <div class="mw-agency-profile__map">
                        <iframe src="https://www.google.com/maps?q=Dubai&output=embed" title="Services Area — Dubai" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                    </div>
                </article>

                <div v-if="agency.properties.length" class="mw-agency-panel" data-reveal>
                    <div class="mw-agency-panel__head">
                        <h2 class="mw-agency-panel__title">Properties</h2>
                        <div class="mw-agency-panel__actions">
                            <div class="mw-agent-more__nav">
                                <button type="button" class="mw-arrow-btn mw-arrow-btn--prev mw-agent-more__nav-btn" data-agency-properties-prev aria-label="Previous properties">
                                    <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                                </button>
                                <button type="button" class="mw-arrow-btn mw-arrow-btn--next mw-agent-more__nav-btn" data-agency-properties-next aria-label="Next properties">
                                    <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mw-agent-more__track" data-agency-properties-slider>
                        <div v-for="property in agency.properties" :key="property.slug" class="mw-agent-more__slide"><article class="mw-agent-prop">
                            <div class="mw-agent-prop__media" data-card-gallery role="link" tabindex="0" @click="$router.push(`/property-details/${property.slug}`)" @keydown.enter="$router.push(`/property-details/${property.slug}`)" style="cursor: pointer;">
                                <div class="mw-agent-prop__slides" data-gallery-track>
                                    <img v-for="(image, imgIndex) in property.images" :key="imgIndex" :src="image" :alt="property.name" class="mw-agent-prop__photo">
                                </div>
                                <span class="mw-badge mw-badge--success mw-agent-prop__verified">
                                    <img src="/frontend/assets/images/icons/verified.svg" alt="" width="18" height="18">
                                    Verified
                                </span>
                                <button type="button" class="mw-agent-prop__save" :class="{ 'is-saved': isWishlisted(property.id) }" aria-label="Save property" :aria-pressed="isWishlisted(property.id)" @click.stop="toggleWishlist(property.id)">
                                    <img src="/frontend/assets/images/icons/heart.svg" alt="" width="24" height="24">
                                </button>
                                <button type="button" class="mw-agent-prop__nav mw-agent-prop__nav--prev" data-gallery-prev aria-label="Previous photo" @click.stop>
                                    <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                                </button>
                                <button type="button" class="mw-agent-prop__nav mw-agent-prop__nav--next" data-gallery-next aria-label="Next photo" @click.stop>
                                    <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                                </button>
                                <span v-if="property.images_count" class="mw-agent-prop__count">
                                    <img src="/frontend/assets/images/icons/photo-count.svg" alt="" width="16" height="16">
                                    <span data-gallery-count>{{ property.images_count }}</span>
                                </span>
                                <div class="mw-agent-prop__overlay">
                                    <span class="mw-agent-prop__type">{{ property.type }}</span>
                                    <span class="mw-agent-prop__price">{{ property.price }}</span>
                                </div>
                            </div>
                            <div class="mw-agent-prop__body">
                                <h3 class="mw-agent-prop__name"><router-link :to="`/property-details/${property.slug}`">{{ property.name }}</router-link></h3>
                                <p class="mw-agent-prop__location">
                                    <img src="/frontend/assets/images/icons/location.svg" alt="" width="18" height="18">
                                    {{ property.location }}
                                </p>
                                <div class="mw-agent-prop__stats">
                                    <span class="mw-agent-prop__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="18" height="18">{{ property.beds }}</span>
                                    <span class="mw-agent-prop__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="18" height="18">{{ property.baths }}</span>
                                    <span class="mw-agent-prop__stat"><img src="/frontend/assets/images/icons/area.svg" alt="" width="18" height="18">{{ property.area }}</span>
                                </div>
                                <div class="mw-agent-prop__actions">
                                    <a v-if="agency.email" class="mw-agent-cta mw-agent-cta--email mw-agent-cta--soft" :href="`mailto:${agency.email}`">
                                        <img src="/frontend/assets/images/icons/email.svg" alt="" width="18" height="18">Email
                                    </a>
                                    <a v-if="agency.phone" class="mw-agent-cta mw-agent-cta--call" :href="`tel:${agency.phone}`">
                                        <img src="/frontend/assets/images/icons/phone.svg" alt="" width="18" height="18">Call Us
                                    </a>
                                    <a v-if="agency.whatsapp_number" class="mw-agent-cta mw-agent-cta--whatsapp" :href="`https://wa.me/${agency.whatsapp_number.replace(/[^0-9]/g, '')}`" target="_blank" rel="noopener">
                                        <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="18" height="18">WhatsApp
                                    </a>
                                </div>
                            </div>
                        </article></div>
                    </div>
                </div>

                <div v-if="agency.agents.length" class="mw-agency-panel" data-reveal>
                    <div class="mw-agency-panel__head">
                        <h2 class="mw-agency-panel__title">Agent</h2>
                        <div class="mw-agency-panel__actions">
                            <router-link to="/agents" class="mw-btn mw-btn--outline mw-agency-panel__view-all">View All</router-link>
                            <div class="mw-agent-more__nav">
                                <button type="button" class="mw-arrow-btn mw-arrow-btn--prev mw-agent-more__nav-btn" data-agency-agents-prev aria-label="Previous agents">
                                    <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                                </button>
                                <button type="button" class="mw-arrow-btn mw-arrow-btn--next mw-agent-more__nav-btn" data-agency-agents-next aria-label="Next agents">
                                    <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mw-agent-more__track" data-agency-agents-slider>
                        <div v-for="agent in agency.agents" :key="agent.slug" class="mw-agent-more__slide"><article class="mw-agent-card">
                            <div class="mw-agent-card__head">
                                <div class="mw-agent-card__avatar">
                                    <img :src="agent.avatar_url || '/frontend/assets/images/agents/ahmed.png'" :alt="agent.name" width="80" height="80">
                                </div>
                                <div class="mw-agent-card__intro">
                                    <h3 class="mw-agent-card__name">{{ agent.name }}</h3>
                                    <div class="mw-agent-card__tags">
                                        <span class="mw-agent-card__tag mw-agent-card__tag--fill">Serves in Dubai</span>
                                        <span class="mw-agent-card__tag">Rent : {{ agent.rent_count }}</span>
                                        <span class="mw-agent-card__tag">Sell : {{ agent.sell_count }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mw-agent-card__body">
                                <p>Years of Experience: {{ agent.years_of_experience ?? '—' }}</p>
                                <p>Preferred Areas: {{ (agent.preferred_areas || []).join(', ') || '—' }}</p>
                            </div>
                            <div class="mw-agent-card__foot">
                                <img class="mw-agent-card__logo" src="/frontend/assets/images/logo-dark.png" alt="MW Realty" width="50" height="28">
                                <span class="mw-agent-card__rule" aria-hidden="true"></span>
                                <router-link :to="`/agent-details/${agent.slug}`" class="mw-agent-card__link">
                                    View Details
                                    <img src="/frontend/assets/images/icons/find-cta-arrow.svg" alt="" width="18" height="18">
                                </router-link>
                            </div>
                        </article></div>
                    </div>
                </div>

                <div class="mw-agency-panel" data-reveal>
                    <div class="mw-agency-panel__head">
                        <h2 class="mw-agency-panel__title">Contact Info</h2>
                    </div>

                    <div class="mw-agency-contact">
                        <div class="mw-agency-contact__media">
                            <img :src="agency.logo_url || '/frontend/assets/images/agencies/logo-dxb-dubai.png'" :alt="agency.name" width="176" height="166">
                        </div>

                        <div class="mw-agency-contact__panel">
                            <div class="mw-agency-contact__col">
                                <p v-if="agency.phone" class="mw-agency-contact__row">
                                    <img src="/frontend/assets/images/icons/phone.svg" alt="" width="24" height="24">
                                    {{ agency.phone }}
                                </p>
                                <p v-if="agency.email" class="mw-agency-contact__row">
                                    <img src="/frontend/assets/images/icons/email.svg" alt="" width="24" height="24">
                                    {{ agency.email }}
                                </p>
                                <p v-if="agency.whatsapp_number" class="mw-agency-contact__row">
                                    <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="24" height="24">
                                    {{ agency.whatsapp_number }}
                                </p>
                            </div>

                            <div class="mw-agency-contact__col">
                                <h3 class="mw-agency-contact__subtitle">Agency Details</h3>
                                <p v-if="agency.orn_number" class="mw-agency-contact__row">
                                    <img src="/frontend/assets/images/agency-details/icon-info.svg" alt="" width="24" height="24">
                                    ORN No: {{ agency.orn_number }}
                                </p>
                                <p v-if="agency.website" class="mw-agency-contact__row">
                                    <img src="/frontend/assets/images/agency-details/icon-globe.svg" alt="" width="24" height="24">
                                    {{ agency.website }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </main>

    <main v-else-if="notFound">
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>Agency Not Found</h1>
                </div>
            </div>
        </section>
        <section class="mw-agency-detail">
            <div class="container-ctn">
                <p>This agency profile doesn't exist or is no longer active. <router-link to="/agencies">Back to Agencies</router-link></p>
            </div>
        </section>
    </main>
</template>
