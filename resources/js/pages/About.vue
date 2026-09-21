<script setup>
import { computed } from 'vue';
import { useAboutPage } from '../composables/useAboutPage';

const { aboutPage } = useAboutPage();

const about = computed(() => aboutPage.value?.about || null);
const aboutEyebrow = computed(() => about.value?.eyebrow || 'Overview');
const aboutTitle = computed(() => about.value?.title || 'About Us');
const aboutImage = computed(() => about.value?.image_url || '/frontend/assets/images/about/villa.jpg');
const aboutImageAlt = computed(() => about.value?.image_alt || 'Waterfront villa in Dubai');
// Description is authored as rich text (TinyMCE) in the admin, so it's rendered as HTML here.
const aboutDescription = computed(() => about.value?.description || `
    <p>At Mighty Warner Realty, we aren't just find properties; we craft lifestyles. As Dubai's #1 property management specialists, we are your trusted partners in buying, selling, and managing real estate with unmatched expertise and care. From luxury residences to savvy investments, we deliver tailored solutions that align with your aspirations.</p>
    <p>Our professional team combines market insight with a personalized touch to make every transaction seamless and rewarding. With Mighty Warner Realty, you're not just navigating Dubai's dynamic property market, you're unlocking its finest opportunities to find your next great chapter with us.</p>
`);

const director = computed(() => aboutPage.value?.director || null);
const directorImage = computed(() => director.value?.image_url || '/frontend/assets/images/about/director.jpg');
const directorImageAlt = computed(() => director.value?.image_alt || 'Faiyaz Ahmed Khan, Founder and Director');
const directorQuote = computed(() => director.value?.quote || 'A company stands on the shoulders of its representatives. The employees, directors and the investors are the backs for any company in the group.');
const directorByline = computed(() => {
    const name = director.value?.name || 'Faiyaz Ahmed Khan';
    const designation = director.value?.designation || 'Founder & Director';
    return `${designation} | ${name}`;
});

// These 3 circular graphics are this section's fixed design — not admin-manageable art, so a
// step's icon always comes from here by position, even when its title/description are CMS-driven.
const STEP_DESIGN_ICONS = [
    '/frontend/assets/images/icons/about-step-1.png',
    '/frontend/assets/images/icons/about-step-2.png',
    '/frontend/assets/images/icons/about-step-3.png',
];

const steps = computed(() => {
    const dbSteps = aboutPage.value?.postPropertySteps;
    if (dbSteps?.length) {
        return dbSteps.map((step, i) => ({
            ...step,
            icon_url: STEP_DESIGN_ICONS[i] || STEP_DESIGN_ICONS[STEP_DESIGN_ICONS.length - 1],
        }));
    }
    return [
        { index: '01', icon_url: STEP_DESIGN_ICONS[0], title: 'Add Details Of Your Property', description: 'Begin By Telling Us The Few Basic Details About Your Property Like Your Property Type, Location, No. Of Rooms Etc.' },
        { index: '02', icon_url: STEP_DESIGN_ICONS[1], title: 'Upload Photos & Videos', description: 'Upload Photos And Videos Of Your Property Either Via Your Desktop Device Or From Your Mobile Phone.' },
        { index: '03', icon_url: STEP_DESIGN_ICONS[2], title: 'Add Pricing & Ownership', description: "Just Update Your Property's Ownership Details And Your Expected Price And Your Property Is Ready For Posting Post Property." },
    ];
});

const marketTrends = computed(() => aboutPage.value?.marketTrends || null);
const trendsTitle = computed(() => marketTrends.value?.title || 'Real Estate Market Trends & Investment Tips');
const trendsImage1 = computed(() => marketTrends.value?.image_1_url || '/frontend/assets/images/about/trends-tall.jpg');
const trendsImage1Alt = computed(() => marketTrends.value?.image_1_alt || 'City pool terrace');
const trendsImage2 = computed(() => marketTrends.value?.image_2_url || '/frontend/assets/images/about/trends-interior.jpg');
const trendsImage2Alt = computed(() => marketTrends.value?.image_2_alt || 'Luxury interior overlooking a pool');
const trendsList = computed(() => marketTrends.value?.trends?.length ? marketTrends.value.trends : [
    'Higher mortgage rates have slowed rapid price growth, leading to a more balanced market.',
    'Buyers have more negotiation power, but financing costs remain a challenge.',
    'With remote work still prevalent, suburban and mid-sized cities continue to attract investors.',
    'Lower home prices and strong rental demand make these areas attractive for buy-and-hold investors.',
    'Higher interest rates for construction loans are limiting new supply.',
    'Mortgage rates remain elevated, making affordability a key concern for buyers.',
    'More buyers are opting for adjustable-rate mortgages (ARMs) or waiting for rate cuts.',
    'Rising home prices have pushed demand toward smaller homes, condos, and suburban areas.',
]);

const whyChooseUs = computed(() => aboutPage.value?.whyChooseUsItems?.length ? aboutPage.value.whyChooseUsItems : [
    { icon_url: '/frontend/assets/images/icons/about-building.svg', label: 'Select Property' },
    { icon_url: '/frontend/assets/images/icons/about-building.svg', label: 'Buy Property' },
    { icon_url: '/frontend/assets/images/icons/about-team.svg', label: 'Meet Our Team' },
    { icon_url: '/frontend/assets/images/icons/about-loan.svg', label: 'Loan Assistance' },
    { icon_url: '/frontend/assets/images/icons/about-site.svg', label: 'Visit Site' },
    { icon_url: '/frontend/assets/images/icons/about-support.svg', label: 'Customer Support' },
]);

const connectUs = computed(() => aboutPage.value?.connectUs || null);
const connectTitle = computed(() => connectUs.value?.title || 'We value and embrace diversity');
const connectText = computed(() => connectUs.value?.text || 'At MW Realty, we offer end-to-end real estate services under one roof.');
const connectButtonText = computed(() => connectUs.value?.button_text || 'Contact Our Team');
const connectButtonUrl = computed(() => connectUs.value?.button_url || '/contact');
const connectVideo = computed(() => connectUs.value?.video_url || '/frontend/assets/video/about.mp4');
const connectPoster = computed(() => connectUs.value?.poster_url || '/frontend/assets/images/about/diversity.jpg');

const builders = computed(() => aboutPage.value?.builders || null);
const buildersTitle = computed(() => builders.value?.title || 'Our Builders');
const builderLogos = computed(() => {
    const items = builders.value?.items?.length ? builders.value.items.map((b) => ({ src: b.image_url, alt: b.image_alt || '' })) : [
        { src: '/frontend/assets/images/about/builder-1.png', alt: 'Tata Housing' },
        { src: '/frontend/assets/images/about/builder-2.png', alt: 'Godrej Properties' },
        { src: '/frontend/assets/images/about/builder-3.png', alt: 'Lodha Dynasty' },
        { src: '/frontend/assets/images/about/builder-4.png', alt: 'BCC Builders' },
        { src: '/frontend/assets/images/about/builder-5.png', alt: 'Mahagun' },
        { src: '/frontend/assets/images/about/builder-6.png', alt: 'Gaurs' },
    ];
    // Duplicated so the marquee slider has enough logos to loop seamlessly.
    return [...items, ...items];
});
</script>

<template>
    <div class="mw-about-progress" aria-hidden="true"><span></span></div>

    <main>
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>Inside Our World</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><span>About Us</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-about-intro">
            <div class="container-ctn">
                <div class="mw-about-intro__grid">
                    <div class="mw-about-intro__sticky">
                        <p class="mw-about-intro__eyebrow" data-reveal>{{ aboutEyebrow }}</p>
                        <h2 class="mw-about-title" data-reveal>{{ aboutTitle }}</h2>
                        <div class="mw-about-intro__photo" data-reveal>
                            <img :src="aboutImage" :alt="aboutImageAlt" data-parallax="0.18">
                        </div>
                    </div>
                    <div class="mw-about-intro__copy" data-reveal="right" v-html="aboutDescription"></div>
                </div>
            </div>
        </section>

        <section class="mw-about-stack">
            <div class="mw-about-director mw-about-pin">
                <div class="container-ctn">
                    <div class="mw-about-director__media">
                        <img :src="directorImage" :alt="directorImageAlt" data-parallax="0.22">
                        <div class="mw-about-director__card" data-reveal="up">
                            <p class="mw-about-director__quote">{{ directorQuote }}</p>
                            <p class="mw-about-director__byline">{{ directorByline }}</p>
                            <a href="#" class="mw-btn mw-btn--gradient">Read Director Message</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mw-about-steps mw-about-pin">
                <div class="mw-about-steps__band" aria-hidden="true"></div>
                <div class="container-ctn">
                    <h2 class="mw-about-title" data-reveal>Post Your property in 3 simple steps</h2>
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

        <section class="mw-about-trends">
            <div class="container-ctn">
                <div class="mw-about-trends__grid">
                    <div class="mw-about-trends__media">
                        <img class="mw-about-trends__photo mw-about-trends__photo--tall" :src="trendsImage1" :alt="trendsImage1Alt" data-parallax="0.16">
                        <img class="mw-about-trends__photo mw-about-trends__photo--inset" :src="trendsImage2" :alt="trendsImage2Alt" data-parallax="0.28">
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
            <section class="mw-about-why mw-about-pin">
                <div class="container-ctn">
                    <h2 class="mw-about-title" data-reveal>Why Choose Us</h2>
                    <div class="mw-about-why__layout">
                        <template v-for="(item, index) in whyChooseUs" :key="index">
                            <div class="mw-about-why__card" data-reveal :style="{ '--reveal-delay': index + 1 }">
                                <img :src="item.icon_url" alt="">
                                <span>{{ item.label }}</span>
                            </div>
                            <div v-if="index === 0" class="mw-about-why__photo">
                                <img src="/frontend/assets/images/about/why-choose.jpg" alt="Residential tower at dusk" data-parallax="0.2">
                                <img class="mw-about-why__overlay" src="/frontend/assets/images/about/overlay.png" alt="">
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            <section class="mw-about-diversity" data-video-expand>
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
                        <button type="button" class="mw-about-diversity__play" aria-label="Pause video">
                            <img class="mw-about-diversity__icon-play" src="/frontend/assets/images/icons/about-play.svg" alt="">
                            <img class="mw-about-diversity__icon-pause" src="/frontend/assets/images/icons/pause.svg" alt="">
                        </button>
                    </div>
                </div>
            </section>

            <section class="mw-about-builders">
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
                            <h2 class="mw-about-title">Newsletter</h2>
                            <p class="mw-about-news__text">Sign up to receive the latest articles</p>
                        </div>
                        <form class="mw-about-news__form" data-reveal style="--reveal-delay: 1" @submit.prevent>
                            <input type="email" name="email" placeholder="Enter your email address" required>
                            <button type="submit">
                                Subscribe Now
                                <img src="/frontend/assets/images/icons/about-newsletter-arrow.svg" alt="">
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
