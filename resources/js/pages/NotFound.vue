<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStaticText } from '../composables/useStaticText';

/**
 * Catch-all 404 (router `/:pathMatch(.*)*`, and server-side 404s for missing listings/agents/posts
 * via SpaController::notFound). Same visual language as ThankYou.vue — the "0" is an animated house
 * on a spinning ring — plus a property search and quick links so a dead end is never a dead end.
 */
const route = useRoute();
const router = useRouter();
const { t } = useStaticText();

const search = ref('');
const requestedPath = computed(() => route.fullPath.length > 60 ? route.fullPath.slice(0, 57) + '…' : route.fullPath);

const links = computed(() => [
    { to: { path: '/properties', query: { listing_type: 'sale' } }, label: t('nav.buy', 'Buy') },
    { to: { path: '/properties', query: { listing_type: 'rent' } }, label: t('nav.rent', 'Rent') },
    { to: '/commercial', label: t('nav.commercial', 'Commercial') },
    { to: '/agents', label: t('nav.agents', 'Agents') },
    { to: '/market-insights', label: t('nav.market_insights', 'Market Insights') },
]);

function submitSearch() {
    const location = search.value.trim();
    router.push({ path: '/properties', query: location ? { location } : {} });
}
</script>

<template>
    <main>
        <section class="nf">
            <div class="nf__orb nf__orb--red" aria-hidden="true"></div>
            <div class="nf__orb nf__orb--navy" aria-hidden="true"></div>

            <div class="container-ctn nf__inner">
                <div class="nf__code" aria-hidden="true">
                    <span class="nf__digit nf__digit--left">4</span>
                    <span class="nf__zero">
                        <span class="nf__ring"></span>
                        <span class="nf__pin">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z" /></svg>
                        </span>
                        <svg class="nf__house" viewBox="0 0 64 64" width="64" height="64">
                            <defs>
                                <linearGradient id="nfGradient" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0%" stop-color="#c92844" />
                                    <stop offset="100%" stop-color="#244373" />
                                </linearGradient>
                            </defs>
                            <path class="nf__house-line" d="M10 30 32 12l22 18" />
                            <path class="nf__house-line" d="M16 26v24h32V26" />
                            <path class="nf__house-line nf__house-door" d="M27 50V37h10v13" />
                            <circle class="nf__house-window" cx="32" cy="27" r="3" />
                        </svg>
                        <span class="nf__shadow"></span>
                    </span>
                    <span class="nf__digit nf__digit--right">4</span>
                </div>

                <span class="nf__eyebrow">{{ t('not_found.eyebrow', 'Error 404') }}</span>
                <h1 class="nf__title">{{ t('not_found.title', 'Page Not Found') }}</h1>
                <p class="nf__text">
                    {{ t('not_found.text', "Sorry, We can't find the page you're looking for.") }}
                    <span>{{ t('not_found.hint', 'It may have been moved, sold or taken off the market — but your next home is still out there.') }}</span>
                </p>
                <code v-if="route.fullPath !== '/'" class="nf__path">{{ requestedPath }}</code>

                <form class="nf__search" role="search" @submit.prevent="submitSearch">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
                    <input v-model="search" type="search" maxlength="120" :placeholder="t('not_found.search_placeholder', 'Search by city, community or building')" :aria-label="t('not_found.search_placeholder', 'Search by city, community or building')">
                    <button type="submit">{{ t('not_found.search_button', 'Search') }}</button>
                </form>

                <nav class="nf__links" :aria-label="t('not_found.popular', 'Popular pages')">
                    <span class="nf__links-label">{{ t('not_found.popular', 'Popular pages') }}:</span>
                    <router-link v-for="(link, i) in links" :key="i" :to="link.to" class="nf__chip" :style="{ '--i': i }">{{ link.label }}</router-link>
                </nav>

                <div class="nf__actions">
                    <router-link to="/" class="nf__btn nf__btn--solid">{{ t('not_found.cta', 'Back to Homepage') }}</router-link>
                    <router-link to="/contact" class="nf__btn nf__btn--ghost">{{ t('not_found.contact', 'Contact Us') }}</router-link>
                </div>
            </div>
        </section>
    </main>
</template>

<style scoped>
.nf {
    --nf-red: #c92844;
    --nf-navy: #244373;
    position: relative;
    overflow: hidden;
    /* Clears the fixed site header (same offset the grey title banner uses on other pages). */
    margin-top: clamp(64px, 4.85vw, 74px);
    padding: clamp(40px, 5vw, 96px) 0 clamp(64px, 7vw, 130px);
    background:
        radial-gradient(circle at 1px 1px, rgba(36, 67, 115, 0.07) 1px, transparent 0) 0 0 / 26px 26px,
        #fff;
    text-align: center;
}
.nf__inner { position: relative; z-index: 1; }
.nf__orb { position: absolute; width: clamp(260px, 32vw, 520px); aspect-ratio: 1; border-radius: 50%; filter: blur(70px); opacity: 0.16; pointer-events: none; }
.nf__orb--red { background: var(--nf-red); top: -14%; inset-inline-end: -8%; animation: nf-drift 15s ease-in-out infinite alternate; }
.nf__orb--navy { background: var(--nf-navy); bottom: -20%; inset-inline-start: -10%; animation: nf-drift 18s ease-in-out infinite alternate-reverse; }

/* 4 [house] 4 */
.nf__code { display: flex; align-items: center; justify-content: center; gap: clamp(8px, 1.4vw, 26px); direction: ltr; }
.nf__digit { font-family: "Playfair Display", Georgia, serif; font-size: clamp(110px, 13vw, 230px); font-weight: 700; line-height: 1; background: linear-gradient(135deg, var(--nf-red), var(--nf-navy)); -webkit-background-clip: text; background-clip: text; color: transparent; }
.nf__digit--left { animation: nf-drop 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.1s both; }
.nf__digit--right { animation: nf-drop 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.3s both; }
.nf__zero { position: relative; display: flex; align-items: center; justify-content: center; width: clamp(96px, 11vw, 190px); aspect-ratio: 1; animation: nf-zoom 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) 0.45s both; }
.nf__ring { position: absolute; inset: 0; border-radius: 50%; padding: clamp(6px, 0.6vw, 10px); background: conic-gradient(from 0deg, var(--nf-red), var(--nf-navy), rgba(36, 67, 115, 0.08) 70%, var(--nf-red)); -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0); -webkit-mask-composite: xor; mask-composite: exclude; animation: nf-spin 6s linear infinite; }
.nf__house { position: relative; width: 48%; height: auto; overflow: visible; animation: nf-float 3.2s ease-in-out 1.4s infinite; }
.nf__house-line { fill: none; stroke: url(#nfGradient); stroke-width: 4; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 120; stroke-dashoffset: 120; animation: nf-draw 1s ease-out 0.8s forwards; }
.nf__house-door { animation-delay: 1.2s; }
.nf__house-window { fill: var(--nf-red); opacity: 0; animation: nf-blink 2.4s ease-in-out 1.8s infinite; }
.nf__pin { position: absolute; top: 4%; inset-inline-end: 6%; display: flex; align-items: center; justify-content: center; width: clamp(30px, 2.6vw, 44px); aspect-ratio: 1; border-radius: 50%; background: #fff; color: var(--nf-red); box-shadow: 0 8px 20px rgba(201, 40, 68, 0.25); animation: nf-bounce 1.8s ease-in-out 1.3s infinite; }
.nf__shadow { position: absolute; bottom: -10%; left: 50%; width: 46%; height: 8%; border-radius: 50%; background: rgba(36, 67, 115, 0.14); transform: translateX(-50%); filter: blur(3px); animation: nf-shadow 3.2s ease-in-out 1.4s infinite; }

/* Text */
.nf__eyebrow { display: inline-flex; margin-top: clamp(18px, 1.8vw, 30px); padding: 6px 14px; border-radius: 50px; background: rgba(201, 40, 68, 0.08); color: var(--nf-red); font-family: "Plus Jakarta Sans", sans-serif; font-size: 12px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; animation: nf-rise 0.6s ease-out 0.9s both; }
.nf__title { margin: 14px 0 0; color: var(--nf-navy); font-family: "Playfair Display", Georgia, serif; font-size: clamp(30px, 3.2vw, 60px); font-weight: 600; line-height: 1.15; animation: nf-rise 0.7s ease-out 1s both; }
.nf__text { max-width: 600px; margin: clamp(10px, 0.9vw, 16px) auto 0; color: #35373c; font-family: "Plus Jakarta Sans", sans-serif; font-size: clamp(15px, 1vw, 18px); line-height: 1.65; animation: nf-rise 0.7s ease-out 1.1s both; }
.nf__text span { display: block; color: #6b7080; }
.nf__path { display: inline-block; max-width: 100%; margin-top: 12px; padding: 4px 10px; border-radius: 6px; background: #f3f5fa; color: #6b7080; font-size: 13px; overflow-wrap: anywhere; animation: nf-rise 0.6s ease-out 1.15s both; }

/* Search + quick links */
.nf__search { display: flex; align-items: center; gap: 10px; max-width: 560px; margin: clamp(26px, 2.4vw, 40px) auto 0; padding: 6px 6px 6px 18px; border: 1px solid #dfe3ee; border-radius: 10px; background: #fff; color: #8a90a2; box-shadow: 0 14px 34px rgba(36, 67, 115, 0.1); animation: nf-rise 0.6s ease-out 1.25s both; transition: border-color 0.2s ease, box-shadow 0.2s ease; }
.nf__search:focus-within { border-color: var(--nf-navy); box-shadow: 0 16px 40px rgba(36, 67, 115, 0.16); }
.nf__search input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; color: #1f2230; font-family: "Plus Jakarta Sans", sans-serif; font-size: 15px; }
.nf__search button { padding: 12px 22px; border: 0; border-radius: 7px; background: linear-gradient(90deg, var(--nf-red), var(--nf-navy)); color: #fff; font-family: "Plus Jakarta Sans", sans-serif; font-size: 15px; font-weight: 700; cursor: pointer; transition: opacity 0.2s ease; }
.nf__search button:hover { opacity: 0.9; }
.nf__links { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 8px; margin-top: 18px; font-family: "Plus Jakarta Sans", sans-serif; }
.nf__links-label { color: #6b7080; font-size: 14px; animation: nf-rise 0.5s ease-out 1.35s both; }
.nf__chip { padding: 7px 14px; border: 1px solid rgba(36, 67, 115, 0.16); border-radius: 50px; color: var(--nf-navy); font-size: 14px; font-weight: 600; text-decoration: none; animation: nf-rise 0.5s ease-out calc(1.4s + var(--i) * 0.07s) both; transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease; }
.nf__chip:hover { background: var(--nf-navy); color: #fff; transform: translateY(-2px); }

.nf__actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; margin-top: clamp(28px, 2.4vw, 42px); animation: nf-rise 0.6s ease-out 1.8s both; }
.nf__btn { display: inline-flex; align-items: center; justify-content: center; padding: clamp(14px, 1vw, 18px) clamp(26px, 1.9vw, 36px); border-radius: 5px; font-family: "Plus Jakarta Sans", sans-serif; font-size: 16px; font-weight: 700; text-decoration: none; white-space: nowrap; transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease; }
.nf__btn:hover { transform: translateY(-2px); }
.nf__btn--solid { background: linear-gradient(90deg, var(--nf-red) 0%, var(--nf-navy) 100%); color: #fff; box-shadow: 0 12px 26px rgba(201, 40, 68, 0.25); }
.nf__btn--ghost { border: 1.5px solid var(--nf-navy); color: var(--nf-navy); background: #fff; }
.nf__btn--ghost:hover { background: var(--nf-navy); color: #fff; }

@keyframes nf-drop { from { opacity: 0; transform: translateY(-60px) rotate(-8deg); } to { opacity: 1; transform: none; } }
@keyframes nf-zoom { from { opacity: 0; transform: scale(0.4) rotate(-90deg); } to { opacity: 1; transform: none; } }
@keyframes nf-spin { to { transform: rotate(360deg); } }
@keyframes nf-draw { to { stroke-dashoffset: 0; } }
@keyframes nf-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-7px); } }
@keyframes nf-shadow { 0%, 100% { transform: translateX(-50%) scale(1); opacity: 1; } 50% { transform: translateX(-50%) scale(0.75); opacity: 0.6; } }
@keyframes nf-bounce { 0%, 100% { transform: translateY(0); } 40% { transform: translateY(-9px); } 60% { transform: translateY(-4px); } }
@keyframes nf-blink { 0%, 100% { opacity: 0.25; } 50% { opacity: 1; } }
@keyframes nf-rise { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
@keyframes nf-drift { from { transform: translate(0, 0) scale(1); } to { transform: translate(-6%, 8%) scale(1.12); } }

@media (max-width: 575.98px) {
    .nf__search { padding-inline-start: 14px; }
    .nf__search button { padding: 11px 16px; }
    .nf__btn { flex: 1 1 100%; }
}
@media (prefers-reduced-motion: reduce) {
    .nf *, .nf *::before, .nf *::after { animation-duration: 0.01ms !important; animation-delay: 0s !important; animation-iteration-count: 1 !important; }
}
</style>
