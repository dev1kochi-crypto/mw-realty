<script setup>
import { computed, nextTick, watch } from 'vue';
import { useAboutPage } from '../composables/useAboutPage';
import { useStaticText } from '../composables/useStaticText';

const { t } = useStaticText();

// No lorem-ipsum / invented marketing copy on a live site (same principle as
// HomePageService's developments() section) — every section below is gated on its own real
// CMS field and simply doesn't render until an admin actually fills it in, rather than showing
// fabricated company claims, quotes, or stats as if real.
const { aboutPage } = useAboutPage();

// The reveal-on-scroll / parallax / builders slider (legacy assets/js/script.js) only scan the
// DOM once — the sections below are gated on the async /api/about fetch, so on a slower server
// they render after that scan and stay at opacity 0. Re-run it once the data arrives
// (window.MWRealty.refresh is idempotent).
watch(aboutPage, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
}, { immediate: true });

const about = computed(() => aboutPage.value?.about || null);
const aboutTitle = computed(() => about.value?.title || 'About Us');
const aboutEyebrow = computed(() => about.value?.eyebrow || 'Overview');
// Description is authored as rich text (TinyMCE) in the admin, so it's rendered as HTML here.
const aboutDescription = computed(() => about.value?.description || null);
const aboutImage = computed(() => about.value?.image_url || null);
const aboutImageAlt = computed(() => about.value?.image_alt || '');
const showAboutIntro = computed(() => !!aboutDescription.value);

const director = computed(() => aboutPage.value?.director || null);
const directorImage = computed(() => director.value?.image_url || null);
const directorImageAlt = computed(() => director.value?.image_alt || '');
const directorQuote = computed(() => director.value?.quote || null);
const directorByline = computed(() => {
    const name = director.value?.name;
    const designation = director.value?.designation;
    return [designation, name].filter(Boolean).join(' | ');
});
const showDirector = computed(() => !!directorQuote.value);

// These 3 circular graphics are this section's fixed design — not admin-manageable art, so a
// step's icon always comes from here by position.
const STEP_DESIGN_ICONS = [
    '/frontend/assets/images/icons/about-step-1.png',
    '/frontend/assets/images/icons/about-step-2.png',
    '/frontend/assets/images/icons/about-step-3.png',
];

const steps = computed(() => (aboutPage.value?.postPropertySteps || []).map((step, i) => ({
    ...step,
    icon_url: STEP_DESIGN_ICONS[i] || STEP_DESIGN_ICONS[STEP_DESIGN_ICONS.length - 1],
})));
const showSteps = computed(() => steps.value.length > 0);

const marketTrends = computed(() => aboutPage.value?.marketTrends || null);
const trendsTitle = computed(() => marketTrends.value?.title || 'Real Estate Market Trends & Investment Tips');
const trendsImage1 = computed(() => marketTrends.value?.image_1_url || null);
const trendsImage1Alt = computed(() => marketTrends.value?.image_1_alt || '');
const trendsImage2 = computed(() => marketTrends.value?.image_2_url || null);
const trendsImage2Alt = computed(() => marketTrends.value?.image_2_alt || '');
const trendsList = computed(() => marketTrends.value?.trends || []);
const showTrends = computed(() => trendsList.value.length > 0);

const whyChooseUs = computed(() => aboutPage.value?.whyChooseUsItems || []);
const showWhyChooseUs = computed(() => whyChooseUs.value.length > 0);

const connectUs = computed(() => aboutPage.value?.connectUs || null);
const connectTitle = computed(() => connectUs.value?.title || 'We value and embrace diversity');
const connectText = computed(() => connectUs.value?.text || null);
const connectButtonText = computed(() => connectUs.value?.button_text || 'Contact Our Team');
const connectButtonUrl = computed(() => connectUs.value?.button_url || '/contact');
const connectVideo = computed(() => connectUs.value?.video_url || null);
const connectPoster = computed(() => connectUs.value?.poster_url || null);
const showConnectUs = computed(() => !!(connectText.value && connectVideo.value));

const builders = computed(() => aboutPage.value?.builders || null);
const buildersTitle = computed(() => builders.value?.title || 'Our Builders');
const builderLogos = computed(() => {
    const items = (builders.value?.items || []).map((b) => ({ src: b.image_url, alt: b.image_alt || '' }));
    // Duplicated so the marquee slider has enough logos to loop seamlessly.
    return [...items, ...items];
});
const showBuilders = computed(() => (builders.value?.items || []).length > 0);
</script>

<template>
    <div class="mw-about-progress" aria-hidden="true"><span></span></div>

    <main>
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ aboutTitle }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" :aria-label="t('about.breadcrumb_aria')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('about.breadcrumb_home') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ t('about.breadcrumb_current') }}</span></p>
                </div>
            </nav>
        </section>

        <section v-if="showAboutIntro" class="mw-about-intro">
            <div class="container-ctn">
                <div class="mw-about-intro__grid">
                    <div class="mw-about-intro__sticky">
                        <p class="mw-about-intro__eyebrow" data-reveal>{{ aboutEyebrow }}</p>
                        <h2 class="mw-about-title" data-reveal>{{ aboutTitle }}</h2>
                        <div v-if="aboutImage" class="mw-about-intro__photo" data-reveal>
                            <img :src="aboutImage" :alt="aboutImageAlt" data-parallax="0.18">
                        </div>
                    </div>
                    <div class="mw-about-intro__copy" data-reveal="right" v-html="aboutDescription"></div>
                </div>
            </div>
        </section>

        <section v-if="showDirector || showSteps" class="mw-about-stack">
            <div v-if="showDirector" class="mw-about-director mw-about-pin">
                <div class="container-ctn">
                    <div class="mw-about-director__media">
                        <img v-if="directorImage" :src="directorImage" :alt="directorImageAlt" data-parallax="0.22">
                        <div class="mw-about-director__card" data-reveal="up">
                            <p class="mw-about-director__quote">{{ directorQuote }}</p>
                            <p v-if="directorByline" class="mw-about-director__byline">{{ directorByline }}</p>
                            <a href="#" class="mw-btn mw-btn--gradient">{{ t('about.director.read_message') }}</a>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="showSteps" class="mw-about-steps mw-about-pin">
                <div class="mw-about-steps__band" aria-hidden="true"></div>
                <div class="container-ctn">
                    <h2 class="mw-about-title" data-reveal>{{ t('about.steps.heading') }}</h2>
                    <div class="mw-about-steps__grid">
                        <article v-for="(step, index) in steps" :key="index" class="mw-about-steps__card" data-reveal :style="{ '--reveal-delay': index + 1 }">
                            <span class="mw-about-steps__num">{{ step.index }}</span>
                            <div :class="['mw-about-steps__icon', `mw-about-steps__icon--${index + 1}`]">
                                <img :src="step.icon_url" alt="" width="171" height="114">
                            </div>
                            <h3 class="mw-about-steps__name">{{ step.title }}</h3>
                            <p class="mw-about-steps__text">{{ step.description }}</p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="showTrends" class="mw-about-trends">
            <div class="container-ctn">
                <div class="mw-about-trends__grid">
                    <div class="mw-about-trends__media">
                        <img v-if="trendsImage1" class="mw-about-trends__photo mw-about-trends__photo--tall" :src="trendsImage1" :alt="trendsImage1Alt" data-parallax="0.16">
                        <img v-if="trendsImage2" class="mw-about-trends__photo mw-about-trends__photo--inset" :src="trendsImage2" :alt="trendsImage2Alt" data-parallax="0.28">
                        <img class="mw-about-trends__overlay" src="/frontend/assets/images/about/overlay.png" alt="">
                    </div>
                    <div class="mw-about-trends__copy" data-reveal="right">
                        <h2 class="mw-about-title">{{ trendsTitle }}</h2>
                        <ul class="mw-about-trends__list">
                            <li v-for="(trend, index) in trendsList" :key="index" data-reveal :style="{ '--reveal-delay': index + 1 }">
                                <img src="/frontend/assets/images/icons/about-arrow.svg" alt=""><span>{{ trend }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <div class="mw-about-stack">
            <section v-if="showWhyChooseUs" class="mw-about-why mw-about-pin">
                <div class="container-ctn">
                    <h2 class="mw-about-title" data-reveal>{{ t('about.why_choose_us.heading') }}</h2>
                    <div class="mw-about-why__layout">
                        <template v-for="(item, index) in whyChooseUs" :key="index">
                            <div class="mw-about-why__card" data-reveal :style="{ '--reveal-delay': index + 1 }">
                                <img :src="item.icon_url" alt="">
                                <span>{{ item.label }}</span>
                            </div>
                            <div v-if="index === 0" class="mw-about-why__photo">
                                <img src="/frontend/assets/images/about/why-choose.jpg" :alt="t('about.why_choose_us.photo_alt')" data-parallax="0.2">
                                <img class="mw-about-why__overlay" src="/frontend/assets/images/about/overlay.png" alt="">
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            <section v-if="showConnectUs" class="mw-about-diversity" data-video-expand>
                <div class="container-ctn">
                    <h2 class="mw-about-title" data-reveal>{{ connectTitle }}</h2>
                    <p class="mw-about-diversity__text" data-reveal style="--reveal-delay: 1">{{ connectText }}</p>
                    <router-link :to="connectButtonUrl" class="mw-btn mw-btn--gradient mw-about-diversity__cta" data-reveal style="--reveal-delay: 2">
                        {{ connectButtonText }}
                        <img src="/frontend/assets/images/icons/about-phone.svg" alt="" width="24" height="24">
                    </router-link>
                    <div class="mw-about-diversity__media is-playing">
                        <video class="mw-about-diversity__video" :poster="connectPoster" autoplay muted loop playsinline preload="auto">
                            <source :src="connectVideo" type="video/mp4">
                        </video>
                        <button type="button" class="mw-about-diversity__play" :aria-label="t('about.diversity.pause_video_aria')">
                            <img class="mw-about-diversity__icon-play" src="/frontend/assets/images/icons/about-play.svg" alt="">
                            <img class="mw-about-diversity__icon-pause" src="/frontend/assets/images/icons/pause.svg" alt="">
                        </button>
                    </div>
                </div>
            </section>

            <section v-if="showBuilders" class="mw-about-builders">
                <div class="container-ctn">
                    <div class="mw-about-builders__row">
                        <h2 class="mw-about-title" data-reveal>{{ buildersTitle }}</h2>
                        <div class="mw-about-builders__logos" data-builders-slider>
                            <div v-for="(logo, index) in builderLogos" :key="index" class="mw-about-builders__logo">
                                <img :src="logo.src" :alt="logo.alt">
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mw-about-news">
                <div class="container-ctn">
                    <div class="mw-about-news__row">
                        <div data-reveal>
                            <h2 class="mw-about-title">{{ t('about.newsletter.title') }}</h2>
                            <p class="mw-about-news__text">{{ t('about.newsletter.text') }}</p>
                        </div>
                        <form class="mw-about-news__form" data-reveal style="--reveal-delay: 1" @submit.prevent>
                            <input type="email" name="email" :placeholder="t('about.newsletter.placeholder')" required>
                            <button type="submit">
                                {{ t('about.newsletter.submit') }}
                                <img src="/frontend/assets/images/icons/about-newsletter-arrow.svg" alt="">
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
