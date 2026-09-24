<script setup>
import { computed, nextTick, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAgentDetail } from '../composables/useAgentDetail';
import { useLanguages } from '../composables/useLanguages';
import { useWishlist } from '../composables/useWishlist';
import { useStaticText } from '../composables/useStaticText';

const route = useRoute();
const { agent, notFound, fetchAgent } = useAgentDetail();
const { selectedLanguage } = useLanguages();
const { isWishlisted, toggleWishlist } = useWishlist();
const { t } = useStaticText();

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
const bioFallback = computed(() => t('agent_details.bio_fallback').replace('{name}', agent.value?.name || ''));
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
            <nav class="mw-about-crumb" :aria-label="t('agent_details.breadcrumb_aria')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('agent_details.breadcrumb_home') }}</router-link><span class="mw-about-crumb__sep"> / </span><router-link to="/agents">{{ t('agent_details.breadcrumb_agents') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ agent.name }}</span></p>
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
                                <span class="mw-agent-profile__badge mw-agent-profile__badge--area">{{ t('agent_details.serves_in_dubai') }}</span>
                                <router-link v-if="agent.company" :to="`/agency-details/${agent.company.slug}`" class="mw-agent-profile__badge mw-agent-profile__badge--area">{{ agent.company.name }}</router-link>
                                <div class="mw-agent-profile__share">
                                    {{ t('agent_details.share_label') }}
                                    <a href="#" :aria-label="t('agent_details.share_x_aria')"><img src="/frontend/assets/images/icons/contact-twitter.svg" alt="" width="18" height="18"></a>
                                    <a href="#" :aria-label="t('agent_details.share_facebook_aria')"><img src="/frontend/assets/images/icons/contact-facebook.svg" alt="" width="18" height="18"></a>
                                    <a href="#" :aria-label="t('agent_details.share_instagram_aria')"><img src="/frontend/assets/images/icons/contact-instagram.svg" alt="" width="18" height="18"></a>
                                    <a href="#" :aria-label="t('agent_details.share_linkedin_aria')"><img src="/frontend/assets/images/icons/contact-linkedin.svg" alt="" width="18" height="18"></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mw-agent-profile__bar">
                        <span class="mw-agent-profile__chip">{{ t('agent_details.years_of_experience_prefix') }}: {{ agent.years_of_experience ?? '—' }}</span>
                        <span class="mw-agent-profile__chip">{{ t('agent_details.preferred_areas_prefix') }}: {{ preferredAreasText }}</span>
                        <span class="mw-agent-profile__chip mw-agent-profile__chip--stat">{{ t('agent_details.rent_prefix') }} : {{ agent.rent_count }}</span>
                        <span class="mw-agent-profile__chip mw-agent-profile__chip--stat">{{ t('agent_details.sell_prefix') }} : {{ agent.sell_count }}</span>
                        <div class="mw-agent-profile__actions">
                            <a v-if="agent.email" class="mw-agent-cta mw-agent-cta--email" :href="`mailto:${agent.email}`">
                                <img src="/frontend/assets/images/icons/email.svg" alt="" width="18" height="18">
                                {{ t('agent_details.email') }}
                            </a>
                            <a v-if="agent.phone" class="mw-agent-cta mw-agent-cta--call" :href="`tel:${agent.phone}`">
                                <img src="/frontend/assets/images/icons/phone.svg" alt="" width="18" height="18">
                                {{ t('agent_details.call_us') }}
                            </a>
                            <a v-if="agent.whatsapp_number" class="mw-agent-cta mw-agent-cta--whatsapp" :href="`https://wa.me/${agent.whatsapp_number.replace(/[^0-9]/g, '')}`" target="_blank" rel="noopener">
                                <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="18" height="18">
                                {{ t('agent_details.whatsapp') }}
                            </a>
                        </div>
                    </div>
                </article>

                <div class="mw-agent-detail__tabs" data-agent-tabs>
                    <button type="button" class="mw-agent-detail__tab is-active" data-agent-tab="about">{{ t('agent_details.tabs.about') }}</button>
                    <button type="button" class="mw-agent-detail__tab" data-agent-tab="listings">{{ t('agent_details.tabs.listings') }}</button>
                </div>

                <div class="mw-agent-detail__bio" data-agent-panel="about">
                    <p>{{ agent.bio || bioFallback }}</p>
                </div>
            </div>
        </section>

        <section v-if="agent.properties.length" class="mw-agent-more">
            <div class="container-ctn">
                <div class="mw-agent-more__head">
                    <h2 class="mw-agent-more__title">{{ t('agent_details.other_properties_title') }}</h2>
                    <div class="mw-agent-more__nav">
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--prev mw-agent-more__nav-btn" data-agent-listings-prev :aria-label="t('agent_details.previous_properties_aria')">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--next mw-agent-more__nav-btn" data-agent-listings-next :aria-label="t('agent_details.next_properties_aria')">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                    </div>
                </div>

                <div class="mw-agent-more__track" data-agent-listings-slider>
                    <div v-for="property in agent.properties" :key="property.slug" class="mw-agent-more__slide"><article class="mw-agent-prop">
                        <div class="mw-agent-prop__media" data-card-gallery role="link" tabindex="0" @click="$router.push(`/property-details/${property.slug}`)" @keydown.enter="$router.push(`/property-details/${property.slug}`)" style="cursor: pointer;">
                            <div class="mw-agent-prop__slides" data-gallery-track>
                                <img v-for="(image, imgIndex) in property.images" :key="imgIndex" :src="image" :alt="property.name" class="mw-agent-prop__photo" loading="lazy">
                            </div>
                            <span class="mw-badge mw-badge--success mw-agent-prop__verified">
                                <img src="/frontend/assets/images/icons/verified.svg" alt="" width="18" height="18">
                                {{ t('agent_details.verified') }}
                            </span>
                            <button type="button" class="mw-agent-prop__save" :class="{ 'is-saved': isWishlisted(property.id) }" :aria-label="t('agent_details.save_property_aria')" :aria-pressed="isWishlisted(property.id)" @click.stop="toggleWishlist(property.id)">
                                <img src="/frontend/assets/images/icons/heart.svg" alt="" width="24" height="24">
                            </button>
                            <button type="button" class="mw-agent-prop__nav mw-agent-prop__nav--prev" data-gallery-prev :aria-label="t('agent_details.previous_photo_aria')" @click.stop>
                                <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                            </button>
                            <button type="button" class="mw-agent-prop__nav mw-agent-prop__nav--next" data-gallery-next :aria-label="t('agent_details.next_photo_aria')" @click.stop>
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
                                    <img src="/frontend/assets/images/icons/email.svg" alt="" width="18" height="18">{{ t('agent_details.email') }}
                                </a>
                                <a v-if="agent.phone" class="mw-agent-cta mw-agent-cta--call" :href="`tel:${agent.phone}`">
                                    <img src="/frontend/assets/images/icons/phone.svg" alt="" width="18" height="18">{{ t('agent_details.call_us') }}
                                </a>
                                <a v-if="agent.whatsapp_number" class="mw-agent-cta mw-agent-cta--whatsapp" :href="`https://wa.me/${agent.whatsapp_number.replace(/[^0-9]/g, '')}`" target="_blank" rel="noopener">
                                    <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="18" height="18">{{ t('agent_details.whatsapp') }}
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
                    <h1 class="mw-about-title" data-reveal>{{ t('agent_details.not_found.title') }}</h1>
                </div>
            </div>
        </section>
        <section class="mw-agent-detail">
            <div class="container-ctn">
                <p>{{ t('agent_details.not_found.message') }} <router-link to="/agents">{{ t('agent_details.not_found.back_link') }}</router-link></p>
            </div>
        </section>
    </main>
</template>
