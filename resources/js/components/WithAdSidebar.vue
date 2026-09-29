<script setup>
import { computed, ref } from 'vue';
import { useAd } from '../composables/useAd';
import AdBlock from './AdBlock.vue';

/**
 * Wraps a page's list with an Admin › Ads card in a right-hand column. The column only exists
 * while an ad runs for `placement`, so a page with no ad keeps its full width.
 *   - variant "card"  (agents, agencies, blogs, insights): a ~300px card; stacks under the list < 992px.
 *   - variant "tower" (property listings): a slim ~220px tall ad beside the cards on wide screens
 *     (≥ 1200px), leaving the card grid itself untouched; on smaller screens it becomes a banner
 *     above the list instead.
 */
const props = defineProps({
    placement: { type: String, required: true },
    variant: { type: String, default: 'card' },
});

const { ad } = useAd(props.placement);
const hasAd = computed(() => !!(ad.value && ad.value.image_url));

// Small screens: the floating strip can be closed (for this visit).
const dismissKey = `mw-ad-strip-closed:${props.placement}`;
const readDismissed = () => { try { return sessionStorage.getItem(dismissKey) === '1'; } catch { return false; } };
const stripClosed = ref(readDismissed());
const closeStrip = () => { stripClosed.value = true; try { sessionStorage.setItem(dismissKey, '1'); } catch { /* storage blocked */ } };
</script>

<template>
    <div :class="{ 'mw-with-ad': hasAd, [`mw-with-ad--${variant}`]: hasAd }">
        <div class="mw-with-ad__main">
            <slot />
        </div>
        <aside v-if="hasAd" class="mw-with-ad__side">
            <AdBlock variant="card" :preloaded="ad" />
        </aside>
        <!-- Tower, small screens: a compact strip that floats at the bottom while the list is on screen. -->
        <div v-if="hasAd && variant === 'tower' && !stripClosed" class="mw-with-ad__float">
            <AdBlock :preloaded="ad" bare />
            <button type="button" class="mw-with-ad__close" aria-label="Close advertisement" @click="closeStrip">&times;</button>
        </div>
    </div>
</template>

<style>
/* Not scoped: the list grids inside are styled by the site stylesheet. */

/* The site sets overflow-x: hidden on body and main, which makes each a (never-scrolling) scroll
   container, so a sticky child sticks to that box instead of the window and just scrolls away.
   `clip` still hides sideways overflow without creating a scroll container. */
body:has(.mw-with-ad),
main:has(.mw-with-ad) { overflow-x: clip; }

/* --- card: agents, agencies, blogs, insights --- */
@media (min-width: 992px) {
    .mw-with-ad--card { display: grid; grid-template-columns: minmax(0, 1fr) clamp(260px, 24vw, 320px); gap: clamp(20px, 2vw, 32px); align-items: start; }
    .mw-with-ad--card .mw-with-ad__side { position: sticky; top: 110px; }
    .mw-with-ad--card .mw-with-ad__side .mw-property-ad { margin-top: 0; aspect-ratio: 4 / 5; }
    /* One column less while the ad takes the side. */
    .mw-with-ad--card .mw-agencies__grid:not(.is-list-view) { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .mw-with-ad--card .mw-blog__grid,
    .mw-with-ad--card .mw-insights-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (min-width: 992px) and (max-width: 1199.98px) {
    .mw-with-ad--card .mw-agents__grid,
    .mw-with-ad--card .mw-agencies__grid:not(.is-list-view) { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 991.98px) {
    .mw-with-ad--card .mw-with-ad__side { margin-top: 24px; max-width: 480px; }
}

/* --- tower: property listings --- */
/* ≥ 992px: slim column beside the card grid that stays in view while the list scrolls. */
.mw-with-ad--tower .mw-with-ad__side { display: none; }
@media (min-width: 992px) {
    .mw-with-ad--tower { display: grid; grid-template-columns: minmax(0, 1fr) clamp(170px, 15vw, 240px); gap: clamp(16px, 1.6vw, 28px); align-items: start; }
    .mw-with-ad--tower .mw-with-ad__side { display: block; position: sticky; top: 110px; }
    .mw-with-ad--tower .mw-with-ad__side .mw-property-ad { margin-top: 0; aspect-ratio: 1 / 2.2; max-height: calc(100vh - 140px); }
    .mw-with-ad--tower .mw-property-ad__title { font-size: clamp(15px, 1.3vw, 19px); }
    .mw-with-ad--tower .mw-property-ad__text { font-size: 12px; }
    .mw-with-ad--tower .mw-with-ad__float { display: none; }
}
/* Phones: sit above the site's fixed bottom navigation bar (shown < 768px). */
@media (max-width: 767.98px) {
    .mw-with-ad__float { bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important; }
}
/* < 992px: a compact strip floating at the bottom of the screen while the list is visible. */
@media (max-width: 991.98px) {
    .mw-with-ad__float { position: sticky; bottom: 12px; z-index: 30; margin-top: 20px; }
    .mw-with-ad__float .mw-home-banner__card { height: 96px; min-height: 0; box-shadow: 0 10px 28px rgba(10, 15, 26, 0.28); }
    .mw-with-ad__float .mw-home-banner__image { position: absolute; inset: 0; width: 100%; height: 100%; max-height: none; object-fit: cover; }
    .mw-with-ad__float .mw-ad-banner__content { min-height: 0; height: 100%; max-width: 78%; padding: 22px 16px 10px; gap: 2px; justify-content: center; }
    .mw-with-ad__float .mw-ad-banner__title { font-size: 17px; line-height: 1.2; }
    .mw-with-ad__float .mw-ad-banner__text,
    .mw-with-ad__float .mw-property-ad__eyebrow { display: none; }
    .mw-with-ad__float .mw-home-banner__label { top: 8px; left: 10px; font-size: 9px; padding: 2px 8px; }
    .mw-with-ad__close,
    .mw-with-ad__close:hover { color: #fff; }
    .mw-with-ad__close {
        position: absolute; top: 8px; right: 8px; z-index: 3; width: 26px; height: 26px; border-radius: 50%; border: 0;
        background: rgba(0, 0, 0, 0.55); color: #fff; font-size: 18px; line-height: 1; display: flex; align-items: center; justify-content: center;
    }
}
</style>
