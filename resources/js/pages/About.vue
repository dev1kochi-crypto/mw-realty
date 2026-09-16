<script setup>
const builderLogos = [
    { src: '/frontend/assets/images/about/builder-1.png', alt: 'Tata Housing' },
    { src: '/frontend/assets/images/about/builder-2.png', alt: 'Godrej Properties' },
    { src: '/frontend/assets/images/about/builder-3.png', alt: 'Lodha Dynasty' },
    { src: '/frontend/assets/images/about/builder-4.png', alt: 'BCC Builders' },
    { src: '/frontend/assets/images/about/builder-5.png', alt: 'Mahagun' },
    { src: '/frontend/assets/images/about/builder-6.png', alt: 'Gaurs' },
    { src: '/frontend/assets/images/about/builder-1.png', alt: '' },
    { src: '/frontend/assets/images/about/builder-2.png', alt: '' },
    { src: '/frontend/assets/images/about/builder-3.png', alt: '' },
    { src: '/frontend/assets/images/about/builder-4.png', alt: '' },
    { src: '/frontend/assets/images/about/builder-5.png', alt: '' },
    { src: '/frontend/assets/images/about/builder-6.png', alt: '' },
];

const marketTrends = [
    'Higher mortgage rates have slowed rapid price growth, leading to a more balanced market.',
    'Buyers have more negotiation power, but financing costs remain a challenge.',
    'With remote work still prevalent, suburban and mid-sized cities continue to attract investors.',
    'Lower home prices and strong rental demand make these areas attractive for buy-and-hold investors.',
    'Higher interest rates for construction loans are limiting new supply.',
    'Mortgage rates remain elevated, making affordability a key concern for buyers.',
    'More buyers are opting for adjustable-rate mortgages (ARMs) or waiting for rate cuts.',
    'Rising home prices have pushed demand toward smaller homes, condos, and suburban areas.',
];

const whyChooseUs = [
    { icon: '/frontend/assets/images/icons/about-building.svg', label: 'Select Property' },
    { icon: '/frontend/assets/images/icons/about-building.svg', label: 'Buy Property' },
    { icon: '/frontend/assets/images/icons/about-team.svg', label: 'Meet Our Team' },
    { icon: '/frontend/assets/images/icons/about-loan.svg', label: 'Loan Assistance' },
    { icon: '/frontend/assets/images/icons/about-site.svg', label: 'Visit Site' },
    { icon: '/frontend/assets/images/icons/about-support.svg', label: 'Customer Support' },
];
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
                        <p class="mw-about-intro__eyebrow" data-reveal>Overview</p>
                        <h2 class="mw-about-title" data-reveal>About Us</h2>
                        <div class="mw-about-intro__photo" data-reveal>
                            <img src="/frontend/assets/images/about/villa.jpg" alt="Waterfront villa in Dubai" data-parallax="0.18">
                        </div>
                    </div>
                    <div class="mw-about-intro__copy" data-reveal="right">
                        <p>At Mighty Warner Realty, we aren't just find properties; we craft lifestyles. As Dubai's #1 property management specialists, we are your trusted partners in buying, selling, and managing real estate with unmatched expertise and care. From luxury residences to savvy investments, we deliver tailored solutions that align with your aspirations.</p>
                        <p>Our professional team combines market insight with a personalized touch to make every transaction seamless and rewarding. With Mighty Warner Realty, you're not just navigating Dubai's dynamic property market, you're unlocking its finest opportunities to find your next great chapter with us.</p>
                        <p>At Mighty Warner Realty, we aren't just find properties; we craft lifestyles. As Dubai's #1 property management specialists, we are your trusted partners in buying, selling, and managing real estate with unmatched expertise and care. From luxury residences to savvy investments, we deliver tailored solutions that align with your aspirations.</p>
                        <p>Our professional team combines market insight with a personalized touch to make every transaction seamless and rewarding. With Mighty Warner Realty, you're not just navigating Dubai's dynamic property market, you're unlocking its finest opportunities to find your next great chapter with us.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-about-stack">
            <div class="mw-about-director mw-about-pin">
                <div class="container-ctn">
                    <div class="mw-about-director__media">
                        <img src="/frontend/assets/images/about/director.jpg" alt="Faiyaz Ahmed Khan, Founder and Director" data-parallax="0.22">
                        <div class="mw-about-director__card" data-reveal="up">
                            <p class="mw-about-director__quote">A company stands on the shoulders of its representatives. The employees, directors and the investors are the backs for any company in the group.</p>
                            <p class="mw-about-director__byline">Founder &amp; Director | Faiyaz Ahmed Khan</p>
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
                        <article class="mw-about-steps__card" data-reveal style="--reveal-delay: 1">
                            <span class="mw-about-steps__num">01</span>
                            <div class="mw-about-steps__icon mw-about-steps__icon--1">
                                <img src="/frontend/assets/images/icons/about-step-1.png" alt="" width="171" height="114">
                            </div>
                            <h3 class="mw-about-steps__name">Add Details Of Your Property</h3>
                            <p class="mw-about-steps__text">Begin By Telling Us The Few Basic Details About Your Property Like Your Property Type, Location, No. Of Rooms Etc.</p>
                        </article>
                        <article class="mw-about-steps__card" data-reveal style="--reveal-delay: 2">
                            <span class="mw-about-steps__num">02</span>
                            <div class="mw-about-steps__icon mw-about-steps__icon--2">
                                <img src="/frontend/assets/images/icons/about-step-2.png" alt="" width="155" height="114">
                            </div>
                            <h3 class="mw-about-steps__name">Upload Photos &amp; Videos</h3>
                            <p class="mw-about-steps__text">Upload Photos And Videos Of Your Property Either Via Your Desktop Device Or From Your Mobile Phone.</p>
                        </article>
                        <article class="mw-about-steps__card" data-reveal style="--reveal-delay: 3">
                            <span class="mw-about-steps__num">03</span>
                            <div class="mw-about-steps__icon mw-about-steps__icon--3">
                                <img src="/frontend/assets/images/icons/about-step-3.png" alt="" width="114" height="114">
                            </div>
                            <h3 class="mw-about-steps__name">Add Pricing &amp; Ownership</h3>
                            <p class="mw-about-steps__text">Just Update Your Property's Ownership Details And Your Expected Price And Your Property Is Ready For Posting Post Property.</p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-about-trends">
            <div class="container-ctn">
                <div class="mw-about-trends__grid">
                    <div class="mw-about-trends__media">
                        <img class="mw-about-trends__photo mw-about-trends__photo--tall" src="/frontend/assets/images/about/trends-tall.jpg" alt="City pool terrace" data-parallax="0.16">
                        <img class="mw-about-trends__photo mw-about-trends__photo--inset" src="/frontend/assets/images/about/trends-interior.jpg" alt="Luxury interior overlooking a pool" data-parallax="0.28">
                        <img class="mw-about-trends__overlay" src="/frontend/assets/images/about/overlay.png" alt="">
                    </div>
                    <div class="mw-about-trends__copy" data-reveal="right">
                        <h2 class="mw-about-title">Real Estate Market<br>Trends &amp; Investment Tips</h2>
                        <ul class="mw-about-trends__list">
                            <li v-for="(trend, index) in marketTrends" :key="index" data-reveal :style="{ '--reveal-delay': index + 1 }">
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
                        <template v-for="(item, index) in whyChooseUs" :key="item.label">
                            <div class="mw-about-why__card" data-reveal :style="{ '--reveal-delay': index + 1 }">
                                <img :src="item.icon" alt="">
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
                    <h2 class="mw-about-title" data-reveal>We value and embrace diversity</h2>
                    <p class="mw-about-diversity__text" data-reveal style="--reveal-delay: 1">At MW Realty, we offer end-to-end real estate services under one roof.</p>
                    <a href="#" class="mw-btn mw-btn--gradient mw-about-diversity__cta" data-reveal style="--reveal-delay: 2">
                        Contact Our Team
                        <img src="/frontend/assets/images/icons/about-phone.svg" alt="" width="24" height="24">
                    </a>
                    <div class="mw-about-diversity__media is-playing">
                        <video class="mw-about-diversity__video" poster="/frontend/assets/images/about/diversity.jpg" autoplay muted loop playsinline preload="auto">
                            <source src="/frontend/assets/video/about.mp4" type="video/mp4">
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
                        <h2 class="mw-about-title" data-reveal>Our Builders</h2>
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
