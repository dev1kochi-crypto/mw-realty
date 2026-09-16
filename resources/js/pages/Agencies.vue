<script setup>
const agencies = [
    { name: "Kaal Real Estate Agency", logo: "/frontend/assets/images/agencies/logo-kaal.png", logoWidth: 250, logoHeight: 89, properties: 3, delay: 0 },
    { name: "Elite Homes Realty", logo: "/frontend/assets/images/agencies/logo-elite-homes.png", logoWidth: 177, logoHeight: 177, properties: 0, delay: 1 },
    { name: "ABC Real Estate", logo: "/frontend/assets/images/agencies/logo-abc-real-estate.png", logoWidth: 207, logoHeight: 131, properties: 10, delay: 2 },
    { name: "Violet Edwards", logo: "/frontend/assets/images/agencies/logo-violet-edwards.png", logoWidth: 250, logoHeight: 24, properties: 3, delay: 3 },
    { name: "Skyline Properties", logo: "/frontend/assets/images/agencies/logo-skyline.png", logoWidth: 213, logoHeight: 148, properties: 5, delay: 0 },
    { name: "Blue Horizon Real Estate", logo: "/frontend/assets/images/agencies/logo-blue-horizon.png", logoWidth: 203, logoHeight: 100, properties: 3, delay: 1 },
    { name: "Dalton Wade", logo: "/frontend/assets/images/agencies/logo-dalton-wade.png", logoWidth: 200, logoHeight: 70, properties: 10, delay: 2 },
    { name: "DXB Dubai Properties", logo: "/frontend/assets/images/agencies/logo-dxb-dubai.png", logoWidth: 176, logoHeight: 166, properties: 3, delay: 3 },
];
</script>

<template>
    <main>
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>Our Agencies</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><span>Agencies</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-agencies-filter">
            <div class="container-ctn">
                <div class="mw-agencies-filter__bar">
                    <div class="mw-agencies-filter__field">
                        <label for="agency-location">Location</label>
                        <input type="text" id="agency-location" name="location" placeholder="Enter location" autocomplete="off">
                    </div>
                    <div class="mw-agencies-filter__field">
                        <label for="agency-name">Agency Name</label>
                        <input type="text" id="agency-name" name="agency-name" placeholder="Search agency name" autocomplete="off">
                    </div>
                    <button type="button" class="mw-agencies-filter__search">
                        <img src="/frontend/assets/images/agencies/icon-search.svg" alt="" width="18" height="18">
                        Search
                    </button>
                    <div class="mw-agencies-filter__views" role="group" aria-label="Toggle agencies view">
                        <button type="button" class="mw-agencies-filter__view-btn is-active" data-agencies-view="grid" aria-label="Grid view" aria-pressed="true">
                            <img src="/frontend/assets/images/agencies/icon-grid-view.svg" alt="" width="24" height="24">
                        </button>
                        <button type="button" class="mw-agencies-filter__view-btn" data-agencies-view="list" aria-label="List view" aria-pressed="false">
                            <img src="/frontend/assets/images/agencies/icon-list-view.svg" alt="" width="24" height="24">
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-agencies">
            <div class="container-ctn">
                <div class="mw-agencies__head">
                    <h2 class="mw-agencies__title" data-reveal>Top 10 Agencies</h2>
                    <p class="mw-agencies__text" data-reveal>Explore agency with a proven track record of high response rates and authentic listings.</p>
                </div>

                <div class="mw-agencies__grid" data-agencies-grid>
                    <article v-for="agency in agencies" :key="agency.name" class="mw-agency-card" data-reveal :style="agency.delay ? { '--reveal-delay': agency.delay } : undefined">
                        <div class="mw-agency-card__logo">
                            <img :src="agency.logo" :alt="agency.name" :width="agency.logoWidth" :height="agency.logoHeight">
                        </div>
                        <div class="mw-agency-card__body">
                            <h3 class="mw-agency-card__name">{{ agency.name }}</h3>
                            <div class="mw-agency-card__meta">
                                <span class="mw-agency-card__meta-item"><img src="/frontend/assets/images/icons/building.svg" alt="" width="18" height="18">Properties : {{ agency.properties }}</span>
                                <span class="mw-agency-card__meta-item"><img src="/frontend/assets/images/icons/location.svg" alt="" width="18" height="18">Services Area : Dubai</span>
                            </div>
                        </div>
                        <div class="mw-agency-card__foot">
                            <div class="mw-agency-card__contacts">
                                <a href="tel:+971585899990" class="mw-agency-card__icon-btn" :aria-label="`Call ${agency.name}`">
                                    <img src="/frontend/assets/images/icons/phone.svg" alt="" width="24" height="24">
                                </a>
                                <a href="mailto:info@mightywarnersrealty.com" class="mw-agency-card__icon-btn" :aria-label="`Email ${agency.name}`">
                                    <img src="/frontend/assets/images/icons/email.svg" alt="" width="24" height="24">
                                </a>
                            </div>
                            <router-link to="/agency-details" class="mw-agency-card__link">
                                View Details
                                <img src="/frontend/assets/images/icons/find-cta-arrow.svg" alt="" width="18" height="18">
                            </router-link>
                        </div>
                    </article>
                </div>

                <nav class="mw-agencies__pagination" aria-label="Agencies pagination" data-agencies-pagination>
                    <button type="button" class="mw-agencies__page mw-agencies__page--prev" data-agencies-page="prev">Previous</button>
                    <button type="button" class="mw-agencies__page is-active" data-agencies-page="1">1</button>
                    <button type="button" class="mw-agencies__page" data-agencies-page="2">2</button>
                    <button type="button" class="mw-agencies__page" data-agencies-page="3">3</button>
                    <button type="button" class="mw-agencies__page" data-agencies-page="4">4</button>
                    <span class="mw-agencies__page mw-agencies__page--ellipsis" aria-hidden="true">…</span>
                    <button type="button" class="mw-agencies__page" data-agencies-page="8">8</button>
                    <button type="button" class="mw-agencies__page mw-agencies__page--next" data-agencies-page="next">Next</button>
                </nav>
            </div>
        </section>
    </main>
</template>
