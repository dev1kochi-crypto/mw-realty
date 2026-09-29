<script setup>
import { computed } from 'vue';
import { useAd } from '../composables/useAd';
import { useStaticText } from '../composables/useStaticText';

/**
 * An Admin › Ads slot. `placement` = the Placement chosen in admin (home, property-details,
 * properties-dubai, …). `variant`:
 *   - "banner" — full-width strip between page sections (wrapped in its own section + container)
 *   - "card"   — the tall sidebar card (e.g. property page, under the enquiry form)
 * Shows the ad's image, plus its optional small heading / title / text over it. The whole ad is
 * the link — there's no button. Renders nothing when no ad is running for the placement.
 */
const props = defineProps({
    placement: { type: String, default: '' },
    variant: { type: String, default: 'banner' },
    // Already-loaded ad (from WithAdSidebar) — skips the fetch.
    preloaded: { type: Object, default: null },
    // Banner without its own <section>/container (when placed inside a page's container).
    bare: { type: Boolean, default: false },
});

const ad = props.preloaded ? computed(() => props.preloaded) : useAd(props.placement).ad;
const { t } = useStaticText();

const hasText = computed(() => !!(ad.value && (ad.value.eyebrow || ad.value.title || ad.value.text)));
const label = computed(() => t('home.badges.advertisement') || 'Advertisement');
const linkAttrs = computed(() => {
    const url = ad.value?.link_url;
    if (!url) return { href: null, role: 'img' };
    const external = /^https?:\/\//i.test(url) && !url.startsWith(window.location.origin);
    return { href: url, target: external ? '_blank' : null, rel: external ? 'noopener sponsored' : null };
});
</script>

<template>
    <template v-if="ad && ad.image_url">
        <!-- Sidebar card -->
        <component :is="ad.link_url ? 'a' : 'div'" v-if="variant === 'card'" v-bind="linkAttrs"
                   class="mw-property-ad mw-ad-card" :class="{ 'mw-ad-card--plain': !hasText }" :aria-label="ad.title || ad.name">
            <span class="mw-property-ad__label">{{ label }}</span>
            <picture>
                <source v-if="ad.mobile_image_url" media="(max-width: 767px)" :srcset="ad.mobile_image_url">
                <img :src="ad.image_url" :alt="ad.image_alt || ad.name" class="mw-property-ad__bg" loading="lazy">
            </picture>
            <span v-if="hasText" class="mw-property-ad__content">
                <span v-if="ad.eyebrow" class="mw-property-ad__eyebrow">{{ ad.eyebrow }}</span>
                <span v-if="ad.title" class="mw-property-ad__title">{{ ad.title }}</span>
                <span v-if="ad.text" class="mw-property-ad__text">{{ ad.text }}</span>
            </span>
        </component>

        <!-- Full-width banner -->
        <component :is="bare ? 'div' : 'section'" v-else class="mw-ad-banner" :class="{ 'mw-home-banner': !bare }">
            <div :class="{ 'container-ctn': !bare }">
                <component :is="ad.link_url ? 'a' : 'div'" v-bind="linkAttrs" class="mw-home-banner__card" :class="{ 'mw-ad-banner--text': hasText }" :aria-label="ad.title || ad.name">
                    <span class="mw-home-banner__label">{{ label }}</span>
                    <picture>
                        <source v-if="ad.mobile_image_url" media="(max-width: 767px)" :srcset="ad.mobile_image_url">
                        <img :src="ad.image_url" :alt="ad.image_alt || ad.name" class="mw-home-banner__image" loading="lazy">
                    </picture>
                    <span v-if="hasText" class="mw-ad-banner__content">
                        <span v-if="ad.eyebrow" class="mw-property-ad__eyebrow">{{ ad.eyebrow }}</span>
                        <span v-if="ad.title" class="mw-ad-banner__title">{{ ad.title }}</span>
                        <span v-if="ad.text" class="mw-ad-banner__text">{{ ad.text }}</span>
                    </span>
                </component>
            </div>
        </component>
    </template>
</template>

<style scoped>
/* Image-only card: no dark gradient needed over an image that carries its own design. */
.mw-ad-card--plain::after { display: none; }

/* Banner with text: a fixed-height strip, the image fills it, the text sits on a gradient. */
.mw-ad-banner--text { min-height: clamp(180px, 22vw, 260px); }
.mw-ad-banner--text .mw-home-banner__image { position: absolute; inset: 0; width: 100%; height: 100%; max-height: none; object-fit: cover; }
.mw-ad-banner--text::after {
    content: ''; position: absolute; inset: 0; z-index: 1;
    background: linear-gradient(90deg, rgba(10, 15, 26, 0.88) 0%, rgba(10, 15, 26, 0.55) 45%, rgba(10, 15, 26, 0.05) 100%);
}
:global(html.is-rtl) .mw-ad-banner--text::after { background: linear-gradient(270deg, rgba(10, 15, 26, 0.88) 0%, rgba(10, 15, 26, 0.55) 45%, rgba(10, 15, 26, 0.05) 100%); }
.mw-ad-banner__content {
    position: relative; z-index: 2; display: flex; flex-direction: column; justify-content: center; gap: 6px;
    min-height: inherit; max-width: min(560px, 70%); padding: clamp(40px, 4vw, 56px) clamp(22px, 3vw, 44px) clamp(22px, 2.4vw, 32px);
}
.mw-ad-banner__title { color: #fff; font-family: "Playfair Display", Georgia, serif; font-size: clamp(22px, 2.6vw, 32px); font-weight: 600; line-height: 1.2; }
.mw-ad-banner__text { color: rgba(255, 255, 255, 0.85); font-family: "Plus Jakarta Sans", sans-serif; font-size: clamp(13px, 1.3vw, 15px); line-height: 1.5; }
@media (max-width: 575.98px) {
    .mw-ad-banner__content { max-width: 100%; }
    .mw-ad-banner--text::after { background: linear-gradient(0deg, rgba(10, 15, 26, 0.9) 0%, rgba(10, 15, 26, 0.45) 70%, rgba(10, 15, 26, 0.15) 100%); }
    .mw-ad-banner__content { justify-content: flex-end; }
}
</style>
