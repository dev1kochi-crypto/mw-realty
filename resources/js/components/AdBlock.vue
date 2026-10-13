<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useAd } from '../composables/useAd';
import { recordAdImpression, trackAdClick } from '../composables/useAdTracking';
import { useStaticText } from '../composables/useStaticText';

/**
 * A page's ad slot, shown as a slider of every ad running there (MW Realty's own from Admin › Ads,
 * plus clients' booked ads from CRM › Advertisements). `placement` = the page key (home,
 * property-details, …). `variant`:
 *   - "banner" — full-width strip between page sections (wrapped in its own section + container)
 *   - "card"   — the tall sidebar card (e.g. property page, under the enquiry form)
 * Each slide is the ad's image plus its optional heading / title / text; the whole slide is the link.
 * Several ads: a wipe-in transition with a slow zoom on the image, story-style progress bars (click
 * to jump), arrows on hover, swipe on touch; it pauses while hovered, focused or off screen.
 * A slide counts as an impression once at least half of it was visible for a second (once per
 * slide per page visit); opening it counts a click. Renders nothing when no ad is running.
 */
const props = defineProps({
    placement: { type: String, default: '' },
    variant: { type: String, default: 'banner' },
    // Already-loaded slides (from WithAdSidebar) — an array, or a single ad — skips the fetch.
    preloaded: { type: [Array, Object], default: null },
    // Seconds per slide when preloaded.
    interval: { type: Number, default: 6 },
    // Banner without its own <section>/container (when placed inside a page's container).
    bare: { type: Boolean, default: false },
});

const loaded = props.preloaded ? null : useAd(props.placement);
const slides = computed(() => {
    const list = props.preloaded ? (Array.isArray(props.preloaded) ? props.preloaded : [props.preloaded]) : loaded.slides.value;
    return list.filter((s) => s && s.image_url);
});
const seconds = computed(() => (loaded ? loaded.interval.value : props.interval) || 6);
const multi = computed(() => slides.value.length > 1);

const { t } = useStaticText();
const adLabel = computed(() => t('home.badges.advertisement') || 'Advertisement');
const sponsoredLabel = computed(() => t('home.badges.sponsored') || 'Sponsored');

const hasText = (ad) => !!(ad.eyebrow || ad.title || ad.text);
const linkAttrs = (ad) => {
    const url = ad.link_url;
    if (!url) return { href: null, role: 'img' };
    const external = /^https?:\/\//i.test(url) && !url.startsWith(window.location.origin);
    return { href: url, target: external ? '_blank' : null, rel: external ? 'noopener sponsored' : null };
};

// ---- slider state
const index = ref(0);
const leaving = ref(-1);
const backwards = ref(false);
const hovered = ref(false);
const focused = ref(false);
const visible = ref(false);
const pageHidden = ref(typeof document !== 'undefined' && document.visibilityState === 'hidden');
const paused = computed(() => hovered.value || focused.value || !visible.value || pageHidden.value);
const active = computed(() => slides.value[index.value] ?? null);

watch(slides, (list) => {
    if (index.value >= list.length) index.value = 0;
    leaving.value = -1;
});

function go(to, back = false) {
    const count = slides.value.length;
    if (count < 2) return;
    const next = (to + count) % count;
    if (next === index.value) return;
    backwards.value = back;
    leaving.value = index.value;
    index.value = next;
}
const next = () => go(index.value + 1);
const prev = () => go(index.value - 1, true);
const jump = (i) => go(i, i < index.value);

// ---- swipe (touch / pen), without breaking the link's click
let startX = null;
let swiped = false;
function onPointerDown(e) {
    if (!multi.value || e.pointerType === 'mouse') return;
    startX = e.clientX;
    swiped = false;
}
function onPointerUp(e) {
    if (startX === null) return;
    const dx = e.clientX - startX;
    startX = null;
    if (Math.abs(dx) > 40) {
        swiped = true;
        const rtl = document.documentElement.classList.contains('is-rtl');
        (dx < 0) !== rtl ? next() : prev();
    }
}
function onSlideClick(e, ad) {
    if (swiped) {
        e.preventDefault();
        swiped = false;
        return;
    }
    if (ad.link_url) trackAdClick(ad.id);
}

// ---- visibility + impressions
const root = ref(null);
let observer = null;
let impressionTimer = null;
const seen = new Set();

function scheduleImpression() {
    clearTimeout(impressionTimer);
    const ad = active.value;
    if (!ad?.id || !visible.value || pageHidden.value || seen.has(ad.id)) return;
    impressionTimer = setTimeout(() => {
        if (active.value?.id === ad.id && visible.value && !pageHidden.value) {
            seen.add(ad.id);
            recordAdImpression(ad.id);
        }
    }, 1000);
}
watch([() => active.value?.id, visible, pageHidden], scheduleImpression);

const onVisibility = () => { pageHidden.value = document.visibilityState === 'hidden'; };

function observe(el) {
    observer?.disconnect();
    if (!el || typeof IntersectionObserver === 'undefined') {
        visible.value = !!el;
        return;
    }
    observer = new IntersectionObserver(([entry]) => { visible.value = entry.isIntersecting; }, { threshold: 0.5 });
    observer.observe(el);
}
watch(root, observe);

onMounted(() => document.addEventListener('visibilitychange', onVisibility));
onBeforeUnmount(() => {
    observer?.disconnect();
    clearTimeout(impressionTimer);
    document.removeEventListener('visibilitychange', onVisibility);
});

const slideClass = (i) => ({
    'is-active': i === index.value,
    'is-leaving': i === leaving.value,
    'is-back': backwards.value,
});
const dotClass = (i) => ({ 'is-active': i === index.value, 'is-done': i < index.value });
</script>

<template>
    <template v-if="slides.length">
        <!-- Sidebar card -->
        <div v-if="variant === 'card'" ref="root" class="mw-ad-slider mw-ad-slider--card" :class="{ 'is-multi': multi, 'is-paused': paused }"
             :style="{ '--mw-ad-seconds': `${seconds}s` }" role="region" :aria-roledescription="multi ? 'carousel' : null" :aria-label="adLabel"
             @mouseenter="hovered = true" @mouseleave="hovered = false" @focusin="focused = true" @focusout="focused = false"
             @pointerdown="onPointerDown" @pointerup="onPointerUp">
            <div class="mw-ad-slider__stack">
                <component :is="ad.link_url ? 'a' : 'div'" v-for="(ad, i) in slides" :key="ad.id ?? i" v-bind="linkAttrs(ad)"
                           class="mw-property-ad mw-ad-card mw-ad-slide" :class="[slideClass(i), { 'mw-ad-card--plain': !hasText(ad) }]"
                           :aria-label="ad.title || ad.name" :aria-hidden="i === index ? null : 'true'" :tabindex="i === index ? null : -1"
                           draggable="false" @click="onSlideClick($event, ad)">
                    <span class="mw-property-ad__label">{{ ad.sponsor ? sponsoredLabel : adLabel }}</span>
                    <picture>
                        <source v-if="ad.mobile_image_url" media="(max-width: 767px)" :srcset="ad.mobile_image_url">
                        <img :src="ad.image_url" :alt="ad.image_alt || ad.name" class="mw-property-ad__bg mw-ad-slide__img" loading="lazy" draggable="false">
                    </picture>
                    <span v-if="hasText(ad)" class="mw-property-ad__content mw-ad-slide__text">
                        <span v-if="ad.eyebrow" class="mw-property-ad__eyebrow">{{ ad.eyebrow }}</span>
                        <span v-if="ad.title" class="mw-property-ad__title">{{ ad.title }}</span>
                        <span v-if="ad.text" class="mw-property-ad__text">{{ ad.text }}</span>
                    </span>
                </component>
                <template v-if="multi">
                    <div class="mw-ad-slider__dots">
                        <button type="button" class="mw-ad-slider__arrow" aria-label="Previous" @click="prev"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" /></svg></button>
                        <button v-for="(ad, i) in slides" :key="`dot-${ad.id ?? i}`" type="button" class="mw-ad-slider__dot" :class="dotClass(i)"
                                :aria-label="`${i + 1} / ${slides.length}`" :aria-current="i === index ? 'true' : null" @click="jump(i)">
                            <span class="mw-ad-slider__fill" @animationend="i === index && next()"></span>
                        </button>
                        <button type="button" class="mw-ad-slider__arrow" aria-label="Next" @click="next"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 5 5-5 5" /></svg></button>
                    </div>
                </template>
            </div>
        </div>

        <!-- Full-width banner -->
        <component :is="bare ? 'div' : 'section'" v-else class="mw-ad-banner" :class="{ 'mw-home-banner': !bare }">
            <div :class="{ 'container-ctn': !bare }">
                <div ref="root" class="mw-ad-slider mw-ad-slider--banner" :class="{ 'is-multi': multi, 'is-paused': paused }"
                     :style="{ '--mw-ad-seconds': `${seconds}s` }" role="region" :aria-roledescription="multi ? 'carousel' : null" :aria-label="adLabel"
                     @mouseenter="hovered = true" @mouseleave="hovered = false" @focusin="focused = true" @focusout="focused = false"
                     @pointerdown="onPointerDown" @pointerup="onPointerUp">
                    <div class="mw-ad-slider__stack">
                        <component :is="ad.link_url ? 'a' : 'div'" v-for="(ad, i) in slides" :key="ad.id ?? i" v-bind="linkAttrs(ad)"
                                   class="mw-home-banner__card mw-ad-slide" :class="[slideClass(i), { 'mw-ad-banner--text': hasText(ad) }]"
                                   :aria-label="ad.title || ad.name" :aria-hidden="i === index ? null : 'true'" :tabindex="i === index ? null : -1"
                                   draggable="false" @click="onSlideClick($event, ad)">
                            <span class="mw-home-banner__label">{{ ad.sponsor ? sponsoredLabel : adLabel }}</span>
                            <picture>
                                <source v-if="ad.mobile_image_url" media="(max-width: 767px)" :srcset="ad.mobile_image_url">
                                <img :src="ad.image_url" :alt="ad.image_alt || ad.name" class="mw-home-banner__image mw-ad-slide__img" loading="lazy" draggable="false">
                            </picture>
                            <span v-if="hasText(ad)" class="mw-ad-banner__content mw-ad-slide__text">
                                <span v-if="ad.eyebrow" class="mw-property-ad__eyebrow">{{ ad.eyebrow }}</span>
                                <span v-if="ad.title" class="mw-ad-banner__title">{{ ad.title }}</span>
                                <span v-if="ad.text" class="mw-ad-banner__text">{{ ad.text }}</span>
                            </span>
                        </component>
                        <template v-if="multi">
                            <div class="mw-ad-slider__dots">
                                <button type="button" class="mw-ad-slider__arrow" aria-label="Previous" @click="prev"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" /></svg></button>
                                <button v-for="(ad, i) in slides" :key="`dot-${ad.id ?? i}`" type="button" class="mw-ad-slider__dot" :class="dotClass(i)"
                                        :aria-label="`${i + 1} / ${slides.length}`" :aria-current="i === index ? 'true' : null" @click="jump(i)">
                                    <span class="mw-ad-slider__fill" @animationend="i === index && next()"></span>
                                </button>
                                <button type="button" class="mw-ad-slider__arrow" aria-label="Next" @click="next"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 5 5-5 5" /></svg></button>
                            </div>
                        </template>
                    </div>
                </div>
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

/* ===================== Slider ===================== */
/* All slides share one grid cell, so the slider keeps one size and slides cross over each other. */
.mw-ad-slider { position: relative; touch-action: pan-y; }
.mw-ad-slider--card { margin-top: clamp(16px, 1.6vw, 24px); }
.mw-ad-slider--card .mw-property-ad { margin-top: 0; }
.mw-ad-slider__stack { position: relative; display: grid; isolation: isolate; }
.mw-ad-slide { grid-area: 1 / 1; -webkit-user-drag: none; user-select: none; }


/* Several ads: one height for every banner, the image filling it. */
.mw-ad-slider--banner.is-multi .mw-home-banner__card { height: clamp(170px, 22vw, 290px); min-height: 0; }
.mw-ad-slider--banner.is-multi .mw-home-banner__image { position: absolute; inset: 0; width: 100%; height: 100%; max-height: none; object-fit: cover; }
@media (max-width: 767.98px) {
    .mw-ad-slider--banner.is-multi .mw-home-banner__card { height: auto; aspect-ratio: 2 / 1; min-height: 170px; }
}

.mw-ad-slider.is-multi .mw-ad-slide { visibility: hidden; opacity: 0; z-index: 0; }
.mw-ad-slider.is-multi .mw-ad-slide.is-leaving { visibility: visible; opacity: 1; z-index: 1; animation: mw-ad-leave 0.9s cubic-bezier(0.65, 0, 0.35, 1) both; }
.mw-ad-slider.is-multi .mw-ad-slide.is-active { visibility: visible; opacity: 1; z-index: 2; animation: mw-ad-wipe 0.9s cubic-bezier(0.77, 0, 0.18, 1) both; }
.mw-ad-slider.is-multi .mw-ad-slide.is-active.is-back { animation-name: mw-ad-wipe-back; }
:global(html.is-rtl) .mw-ad-slider.is-multi .mw-ad-slide.is-active { animation-name: mw-ad-wipe-back; }
:global(html.is-rtl) .mw-ad-slider.is-multi .mw-ad-slide.is-active.is-back { animation-name: mw-ad-wipe; }

/* Slow zoom-out on the image while its slide is up (Ken Burns), held while paused. */
.mw-ad-slider.is-multi .mw-ad-slide.is-active .mw-ad-slide__img { animation: mw-ad-zoom calc(var(--mw-ad-seconds, 6s) + 1.5s) ease-out both; }
.mw-ad-slider.is-paused .mw-ad-slide.is-active .mw-ad-slide__img { animation-play-state: paused; }

/* Text rises in, line by line, after the wipe. */
.mw-ad-slider.is-multi .mw-ad-slide.is-active .mw-ad-slide__text > * { animation: mw-ad-rise 0.7s cubic-bezier(0.22, 1, 0.36, 1) both; }
.mw-ad-slider.is-multi .mw-ad-slide.is-active .mw-ad-slide__text > *:nth-child(1) { animation-delay: 0.35s; }
.mw-ad-slider.is-multi .mw-ad-slide.is-active .mw-ad-slide__text > *:nth-child(2) { animation-delay: 0.47s; }
.mw-ad-slider.is-multi .mw-ad-slide.is-active .mw-ad-slide__text > *:nth-child(3) { animation-delay: 0.59s; }

/* Progress bars (story style): fills over the slide's time, then moves on; click one to jump. */
.mw-ad-slider__dots {
    position: absolute; z-index: 5; right: clamp(14px, 1.4vw, 18px); bottom: clamp(12px, 1.2vw, 16px);
    display: flex; gap: 6px; padding: 3px 4px; border-radius: 999px; background: rgba(10, 15, 26, 0.45); backdrop-filter: blur(4px);
}
:global(html.is-rtl) .mw-ad-slider__dots { right: auto; left: clamp(14px, 1.4vw, 18px); }
.mw-ad-slider--card .mw-ad-slider__dots { top: clamp(10px, 1vw, 12px); bottom: auto; right: clamp(10px, 1vw, 12px); padding: 5px 7px; }
.mw-ad-slider__dot {
    position: relative; width: 26px; height: 4px; padding: 0; border: 0; border-radius: 999px; overflow: hidden;
    background: rgba(255, 255, 255, 0.35); cursor: pointer; transition: width 0.3s ease;
}
.mw-ad-slider__dot::before { content: ''; position: absolute; inset: -8px -2px; } /* bigger tap target */
.mw-ad-slider__dot.is-active { width: 40px; }
.mw-ad-slider__fill { position: absolute; inset: 0; width: 0; border-radius: inherit; background: #fff; }
.mw-ad-slider__dot.is-done .mw-ad-slider__fill { width: 100%; }
.mw-ad-slider__dot.is-active .mw-ad-slider__fill { animation: mw-ad-progress var(--mw-ad-seconds, 6s) linear both; }
.mw-ad-slider.is-paused .mw-ad-slider__dot.is-active .mw-ad-slider__fill { animation-play-state: paused; }

/* Previous / next: small chevrons at the ends of the progress-bar pill, clear of the ad's text. */
.mw-ad-slider__dots { align-items: center; }
.mw-ad-slider__arrow {
    width: 22px; height: 22px; padding: 0; border: 0; border-radius: 50%; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center; background: transparent; color: #fff; cursor: pointer;
    transition: background 0.2s ease;
}
.mw-ad-slider__arrow svg { width: 12px; height: 12px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
.mw-ad-slider__arrow:hover, .mw-ad-slider__arrow:focus-visible { background: rgba(255, 255, 255, 0.22); outline: none; }
:global(html.is-rtl) .mw-ad-slider__arrow svg { transform: scaleX(-1); }

/* Ends a little outside the card so its shadow isn't clipped. */
@keyframes mw-ad-wipe { from { clip-path: inset(-60px -60px -60px 100%); } to { clip-path: inset(-60px); } }
@keyframes mw-ad-wipe-back { from { clip-path: inset(-60px 100% -60px -60px); } to { clip-path: inset(-60px); } }
@keyframes mw-ad-leave { from { filter: brightness(1); transform: scale(1); } to { filter: brightness(0.55); transform: scale(0.97); } }
@keyframes mw-ad-zoom { from { transform: scale(1.09); } to { transform: scale(1); } }
@keyframes mw-ad-rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
@keyframes mw-ad-progress { from { width: 0; } to { width: 100%; } }

@media (prefers-reduced-motion: reduce) {
    .mw-ad-slider .mw-ad-slide,
    .mw-ad-slider .mw-ad-slide__img,
    .mw-ad-slider .mw-ad-slide__text > * { animation: none !important; }
    .mw-ad-slider.is-multi .mw-ad-slide { transition: opacity 0.4s ease, visibility 0.4s; }
}
</style>
