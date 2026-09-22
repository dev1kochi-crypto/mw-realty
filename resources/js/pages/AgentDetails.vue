<script setup>
import { computed, nextTick, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAgentDetail } from '../composables/useAgentDetail';
import { useLanguages } from '../composables/useLanguages';
import { useWishlist } from '../composables/useWishlist';

const route = useRoute();
const { agent, notFound, fetchAgent } = useAgentDetail();
const { selectedLanguage } = useLanguages();
const { isWishlisted, toggleWishlist } = useWishlist();

function load() {
    fetchAgent(route.params.slug, selectedLanguage.value?.code);
}

onMounted(load);
watch(() => route.params.slug, load);
watch(selectedLanguage, load);
watch(
    () => agent.value?.name,
    (name) => {
        if (name) document.title = `${name} | MW Realty`;
    },
);

// See Blogs.vue for why this re-run is needed after the async fetch populates the page.
watch(agent, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});

const badgeClass = (badge) => ({
    'MW Broker': 'mw-agent-profile__badge--broker',
    'Quality Lister': 'mw-agent-profile__badge--quality',
    'Responsive Broker': 'mw-agent-profile__badge--responsive',
}[badge] || 'mw-agent-profile__badge--broker');

const preferredAreasText = computed(() => (agent.value?.preferred_areas || []).join(', ') || '—');
</script>

<template>
    <main v-if="agent">
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ agent.name }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><router-link to="/agents">Agent</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ agent.name }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-agent-detail">
            <div class="container-ctn">
                <article class="mw-agent-profile" data-reveal>
                    <div class="mw-agent-profile__top">
                        <div class="mw-agent-profile__avatar">
                            <img :src="agent.avatar_url || '/frontend/assets/images/agents/ahmed.png'" :alt="agent.name" width="80" height="80">
                        </div>
                        <div class="mw-agent-profile__meta">
                            <div class="mw-agent-profile__row">
                                <h2 class="mw-agent-profile__name">{{ agent.name }}</h2>
                                <span v-for="badge in agent.badges" :key="badge" class="mw-agent-profile__badge" :class="badgeClass(badge)">{{ badge }}</span>
                            </div>
                            <div class="mw-agent-profile__row">
                                <span class="mw-agent-profile__badge mw-agent-profile__badge--area">Serves in Dubai</span>
                                <router-link v-if="agent.company" :to="`/agency-details/${agent.company.slug}`" class="mw-agent-profile__badge mw-agent-profile__badge--area">{{ agent.company.name }}</router-link>
                                <div class="mw-agent-profile__share">
                                    Share
                                    <a href="#" aria-label="Share on X"><img src="/frontend/assets/images/icons/contact-twitter.svg" alt="" width="18" height="18"></a>
                                    <a href="#" aria-label="Share on Facebook"><img src="/frontend/assets/images/icons/contact-facebook.svg" alt="" width="18" height="18"></a>
                                    <a href="#" aria-label="Share on Instagram"><img src="/frontend/assets/images/icons/contact-instagram.svg" alt="" width="18" height="18"></a>
                                    <a href="#" aria-label="Share on LinkedIn"><img src="/frontend/assets/images/icons/contact-linkedin.svg" alt="" width="18" height="18"></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mw-agent-profile__bar">
                        <span class="mw-agent-profile__chip">Years of Experience: {{ agent.years_of_experience ?? '—' }}</span>
                        <span class="mw-agent-profile__chip">Preferred Areas: {{ preferredAreasText }}</span>
                        <span class="mw-agent-profile__chip mw-agent-profile__chip--stat">Rent : {{ agent.rent_count }}</span>
                        <span class="mw-agent-profile__chip mw-agent-profile__chip--stat">Sell : {{ agent.sell_count }}</span>
                        <div class="mw-agent-profile__actions">
                            <a v-if="agent.email" class="mw-agent-cta mw-agent-cta--email" :href="`mailto:${agent.email}`">
                                <img src="/frontend/assets/images/icons/email.svg" alt="" width="18" height="18">
                                Email
                            </a>
                            <a v-if="agent.phone" class="mw-agent-cta mw-agent-cta--call" :href="`tel:${agent.phone}`">
                                <img src="/frontend/assets/images/icons/phone.svg" alt="" width="18" height="18">
                                Call Us
                            </a>
                            <a v-if="agent.whatsapp_number" class="mw-agent-cta mw-agent-cta--whatsapp" :href="`https://wa.me/${agent.whatsapp_number.replace(/[^0-9]/g, '')}`" target="_blank" rel="noopener">
                                <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="18" height="18">
                                WhatsApp
                            </a>
                        </div>
                    </div>
                </article>

                <div class="mw-agent-detail__tabs" data-agent-tabs>
                    <button type="button" class="mw-agent-detail__tab is-active" data-agent-tab="about">About Me</button>
                    <button type="button" class="mw-agent-detail__tab" data-agent-tab="listings">My Listing Properties</button>
                </div>

                <div class="mw-agent-detail__bio" data-agent-panel="about">
                    <p>{{ agent.bio || `${agent.name} is a Dubai-based real estate professional ready to help you buy, sell or rent.` }}</p>
                </div>
            </div>
        </section>

        <section v-if="agent.properties.length" class="mw-agent-more">
            <div class="container-ctn">
                <div class="mw-agent-more__head">
                    <h2 class="mw-agent-more__title">Other Properties</h2>
                    <div class="mw-agent-more__nav">
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--prev mw-agent-more__nav-btn" data-agent-listings-prev aria-label="Previous properties">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--next mw-agent-more__nav-btn" data-agent-listings-next aria-label="Next properties">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                    </div>
                </div>

                <div class="mw-agent-more__track" data-agent-listings-slider>
                    <div v-for="property in agent.properties" :key="property.slug" class="mw-agent-more__slide"><article class="mw-agent-prop">
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
                                <a v-if="agent.email" class="mw-agent-cta mw-agent-cta--email mw-agent-cta--soft" :href="`mailto:${agent.email}`">
                                    <img src="/frontend/assets/images/icons/email.svg" alt="" width="18" height="18">Email
                                </a>
                                <a v-if="agent.phone" class="mw-agent-cta mw-agent-cta--call" :href="`tel:${agent.phone}`">
                                    <img src="/frontend/assets/images/icons/phone.svg" alt="" width="18" height="18">Call Us
                                </a>
                                <a v-if="agent.whatsapp_number" class="mw-agent-cta mw-agent-cta--whatsapp" :href="`https://wa.me/${agent.whatsapp_number.replace(/[^0-9]/g, '')}`" target="_blank" rel="noopener">
                                    <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="18" height="18">WhatsApp
                                </a>
                            </div>
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
                    <h1 class="mw-about-title" data-reveal>Agent Not Found</h1>
                </div>
            </div>
        </section>
        <section class="mw-agent-detail">
            <div class="container-ctn">
                <p>This agent profile doesn't exist or is no longer active. <router-link to="/agents">Back to Agents</router-link></p>
            </div>
        </section>
    </main>
</template>
