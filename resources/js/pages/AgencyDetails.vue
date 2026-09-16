<script setup>
const properties = [
    {
        images: [
            { src: '/frontend/assets/images/home/project-card-1.jpg', alt: 'Modern Penthouse in Downtown Dubai' },
            { src: '/frontend/assets/images/home/project-card-2.jpg', alt: '' },
            { src: '/frontend/assets/images/home/realty-card-1.jpg', alt: '' },
            { src: '/frontend/assets/images/home/luxury-card-1.jpg', alt: '' },
        ],
        type: 'Penthouse',
        price: 'AED 9,500,000',
        name: 'Modern Penthouse in Downtown Dubai...',
        location: 'Downtown Dubai',
        bed: 'N/A',
        bath: 'N/A',
        area: '4200 sq.ft',
    },
    {
        images: [
            { src: '/frontend/assets/images/home/luxury-card-1.jpg', alt: 'Luxury Beachfront Villa in Palm Jumeirah' },
            { src: '/frontend/assets/images/home/luxury-card-2.jpg', alt: '' },
            { src: '/frontend/assets/images/home/project-card-3.jpg', alt: '' },
            { src: '/frontend/assets/images/home/realty-card-2.jpg', alt: '' },
        ],
        type: 'Penthouse',
        price: 'AED 17,500,000',
        name: 'Luxury Beachfront Villa in Palm Jumeirah',
        location: 'Downtown Dubai',
        bed: 'N/A',
        bath: 'N/A',
        area: '7000 sq.ft',
    },
    {
        images: [
            { src: '/frontend/assets/images/home/project-card-3.jpg', alt: 'Modern Penthouse in Downtown Dubai' },
            { src: '/frontend/assets/images/home/realty-card-3.jpg', alt: '' },
            { src: '/frontend/assets/images/home/luxury-card-3.png', alt: '' },
            { src: '/frontend/assets/images/home/project-card-1.jpg', alt: '' },
        ],
        type: 'Penthouse',
        price: 'AED 9,500,000',
        name: 'Modern Penthouse in Downtown Dubai...',
        location: 'Downtown Dubai',
        bed: '2 Bed',
        bath: '2 Bath',
        area: '4200 sq.ft',
    },
    {
        images: [
            { src: '/frontend/assets/images/home/realty-card-4.jpg', alt: 'Luxury Beachfront Villa in Palm Jumeirah' },
            { src: '/frontend/assets/images/home/luxury-card-2.jpg', alt: '' },
            { src: '/frontend/assets/images/home/project-card-4.jpg', alt: '' },
            { src: '/frontend/assets/images/home/luxury-card-1.jpg', alt: '' },
        ],
        type: 'Penthouse',
        price: 'AED 17,500,000',
        name: 'Luxury Beachfront Villa in Palm Jumeirah',
        location: 'Downtown Dubai',
        bed: '3 Bed',
        bath: '3 Bath',
        area: '7000 sq.ft',
    },
];

const agents = [
    {
        avatar: '/frontend/assets/images/agents/ahmed.png',
        name: 'Ahmed Hassan Al Mansoori',
        years: 7,
        areas: 'Downtown Dubai, Dubai Marina, Business Bay',
        to: null,
    },
    {
        avatar: '/frontend/assets/images/agents/jayme.png',
        name: 'Jayme Craig',
        years: 5,
        areas: 'Jumeirah, Business Bay',
        to: '/agent-details',
    },
    {
        avatar: '/frontend/assets/images/agents/abhishek.png',
        name: 'Abhishek Mohan',
        years: 7,
        areas: 'Downtown Dubai, Dubai Marina, Business Bay',
        to: null,
    },
];
</script>

<template>
    <main>
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>DXB Dubai Properties</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><router-link to="/agencies">Agencies</router-link><span class="mw-about-crumb__sep"> / </span><span>DXB Dubai Properties</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-agency-detail">
            <div class="container-ctn">

                <article class="mw-agency-profile" data-reveal>
                    <div class="mw-agency-profile__logo">
                        <img src="/frontend/assets/images/agencies/logo-dxb-dubai.png" alt="DXB Dubai Properties" width="176" height="166">
                    </div>

                    <div class="mw-agency-profile__info">
                        <span class="mw-agency-profile__since">Since 2025</span>

                        <div class="mw-agency-profile__head">
                            <h2 class="mw-agency-profile__name">DXB Dubai Properties</h2>
                            <div class="mw-agency-profile__contacts">
                                <a href="tel:+971585899990" class="mw-agency-profile__icon-btn" aria-label="Call DXB Dubai Properties">
                                    <img src="/frontend/assets/images/icons/phone.svg" alt="" width="24" height="24">
                                </a>
                                <a href="mailto:info@mightywarnersrealty.com" class="mw-agency-profile__icon-btn" aria-label="Email DXB Dubai Properties">
                                    <img src="/frontend/assets/images/icons/email.svg" alt="" width="24" height="24">
                                </a>
                                <a href="https://wa.me/971585899990" target="_blank" rel="noopener" class="mw-agency-profile__icon-btn" aria-label="WhatsApp DXB Dubai Properties">
                                    <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="24" height="24">
                                </a>
                            </div>
                        </div>

                        <p class="mw-agency-profile__area">
                            <img src="/frontend/assets/images/icons/location.svg" alt="" width="18" height="18">
                            Services Area : Dubai
                        </p>

                        <div class="mw-agency-profile__stats">
                            <span class="mw-agency-profile__stat"><img src="/frontend/assets/images/agency-details/icon-user-tie.svg" alt="" width="24" height="24">3 Total Agent</span>
                            <span class="mw-agency-profile__stat"><img src="/frontend/assets/images/agency-details/icon-user-tie.svg" alt="" width="24" height="24">3 Total Properties</span>
                            <span class="mw-agency-profile__stat"><img src="/frontend/assets/images/agency-details/icon-user-tie.svg" alt="" width="24" height="24">3 For Sale</span>
                            <span class="mw-agency-profile__stat"><img src="/frontend/assets/images/agency-details/icon-user-tie.svg" alt="" width="24" height="24">0 For Rent</span>
                        </div>

                        <p class="mw-agency-profile__desc">Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.</p>
                    </div>

                    <div class="mw-agency-profile__map">
                        <iframe src="https://www.google.com/maps?q=Dubai&output=embed" title="Services Area — Dubai" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                    </div>
                </article>

                <div class="mw-agency-panel" data-reveal>
                    <div class="mw-agency-panel__head">
                        <h2 class="mw-agency-panel__title">Properties</h2>
                        <div class="mw-agency-panel__actions">
                            <a href="#" class="mw-btn mw-btn--outline mw-agency-panel__view-all">View All</a>
                            <div class="mw-agent-more__nav">
                                <button type="button" class="mw-arrow-btn mw-arrow-btn--prev mw-agent-more__nav-btn" data-agency-properties-prev aria-label="Previous properties">
                                    <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                                </button>
                                <button type="button" class="mw-arrow-btn mw-arrow-btn--next mw-agent-more__nav-btn" data-agency-properties-next aria-label="Next properties">
                                    <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mw-agent-more__track" data-agency-properties-slider>
                        <div v-for="(property, index) in properties" :key="index" class="mw-agent-more__slide"><article class="mw-agent-prop">
                            <div class="mw-agent-prop__media" data-card-gallery>
                                <div class="mw-agent-prop__slides" data-gallery-track>
                                    <img v-for="(image, imgIndex) in property.images" :key="imgIndex" :src="image.src" :alt="image.alt" class="mw-agent-prop__photo">
                                </div>
                                <span class="mw-badge mw-badge--success mw-agent-prop__verified">
                                    <img src="/frontend/assets/images/icons/verified.svg" alt="" width="18" height="18">
                                    Verified
                                </span>
                                <button type="button" class="mw-agent-prop__save" aria-label="Save property" aria-pressed="false">
                                    <img src="/frontend/assets/images/icons/heart.svg" alt="" width="24" height="24">
                                </button>
                                <button type="button" class="mw-agent-prop__nav mw-agent-prop__nav--prev" data-gallery-prev aria-label="Previous photo">
                                    <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                                </button>
                                <button type="button" class="mw-agent-prop__nav mw-agent-prop__nav--next" data-gallery-next aria-label="Next photo">
                                    <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                                </button>
                                <span class="mw-agent-prop__count">
                                    <img src="/frontend/assets/images/icons/photo-count.svg" alt="" width="16" height="16">
                                    <span data-gallery-count>{{ property.images.length }}</span>
                                </span>
                                <div class="mw-agent-prop__overlay">
                                    <span class="mw-agent-prop__type">{{ property.type }}</span>
                                    <span class="mw-agent-prop__price">{{ property.price }}</span>
                                </div>
                            </div>
                            <div class="mw-agent-prop__body">
                                <h3 class="mw-agent-prop__name">{{ property.name }}</h3>
                                <p class="mw-agent-prop__location">
                                    <img src="/frontend/assets/images/icons/location.svg" alt="" width="18" height="18">
                                    {{ property.location }}
                                </p>
                                <div class="mw-agent-prop__stats">
                                    <span class="mw-agent-prop__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="18" height="18">{{ property.bed }}</span>
                                    <span class="mw-agent-prop__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="18" height="18">{{ property.bath }}</span>
                                    <span class="mw-agent-prop__stat"><img src="/frontend/assets/images/icons/area.svg" alt="" width="18" height="18">{{ property.area }}</span>
                                </div>
                                <div class="mw-agent-prop__actions">
                                    <a class="mw-agent-cta mw-agent-cta--email mw-agent-cta--soft" href="mailto:info@mightywarnersrealty.com">
                                        <img src="/frontend/assets/images/icons/email.svg" alt="" width="18" height="18">Email
                                    </a>
                                    <a class="mw-agent-cta mw-agent-cta--call" href="tel:+971585899990">
                                        <img src="/frontend/assets/images/icons/phone.svg" alt="" width="18" height="18">Call Us
                                    </a>
                                    <a class="mw-agent-cta mw-agent-cta--whatsapp" href="https://wa.me/971585899990" target="_blank" rel="noopener">
                                        <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="18" height="18">WhatsApp
                                    </a>
                                </div>
                            </div>
                        </article></div>
                    </div>
                </div>

                <div class="mw-agency-panel" data-reveal>
                    <div class="mw-agency-panel__head">
                        <h2 class="mw-agency-panel__title">Agent</h2>
                        <div class="mw-agency-panel__actions">
                            <router-link to="/agents" class="mw-btn mw-btn--outline mw-agency-panel__view-all">View All</router-link>
                            <div class="mw-agent-more__nav">
                                <button type="button" class="mw-arrow-btn mw-arrow-btn--prev mw-agent-more__nav-btn" data-agency-agents-prev aria-label="Previous agents">
                                    <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                                </button>
                                <button type="button" class="mw-arrow-btn mw-arrow-btn--next mw-agent-more__nav-btn" data-agency-agents-next aria-label="Next agents">
                                    <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mw-agent-more__track" data-agency-agents-slider>
                        <div v-for="(agent, index) in agents" :key="index" class="mw-agent-more__slide"><article class="mw-agent-card">
                            <div class="mw-agent-card__head">
                                <div class="mw-agent-card__avatar">
                                    <img :src="agent.avatar" :alt="agent.name" width="80" height="80">
                                </div>
                                <div class="mw-agent-card__intro">
                                    <h3 class="mw-agent-card__name">{{ agent.name }}</h3>
                                    <div class="mw-agent-card__tags">
                                        <span class="mw-agent-card__tag mw-agent-card__tag--fill">Serves in Dubai</span>
                                        <span class="mw-agent-card__tag">Rent : 0</span>
                                        <span class="mw-agent-card__tag">Sell : 0</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mw-agent-card__body">
                                <p>Years of Experience: {{ agent.years }}</p>
                                <p>Preferred Areas: {{ agent.areas }}</p>
                            </div>
                            <div class="mw-agent-card__foot">
                                <img class="mw-agent-card__logo" src="/frontend/assets/images/logo-dark.png" alt="MW Realty" width="50" height="28">
                                <span class="mw-agent-card__rule" aria-hidden="true"></span>
                                <router-link v-if="agent.to" :to="agent.to" class="mw-agent-card__link">
                                    View Details
                                    <img src="/frontend/assets/images/icons/find-cta-arrow.svg" alt="" width="18" height="18">
                                </router-link>
                                <a v-else href="#" class="mw-agent-card__link">
                                    View Details
                                    <img src="/frontend/assets/images/icons/find-cta-arrow.svg" alt="" width="18" height="18">
                                </a>
                            </div>
                        </article></div>
                    </div>
                </div>

                <div class="mw-agency-panel" data-reveal>
                    <div class="mw-agency-panel__head">
                        <h2 class="mw-agency-panel__title">Contact Info</h2>
                    </div>

                    <div class="mw-agency-contact">
                        <div class="mw-agency-contact__media">
                            <img src="/frontend/assets/images/agencies/logo-dxb-dubai.png" alt="DXB Dubai Properties" width="176" height="166">
                        </div>

                        <div class="mw-agency-contact__panel">
                            <div class="mw-agency-contact__col">
                                <p class="mw-agency-contact__row">
                                    <img src="/frontend/assets/images/icons/phone.svg" alt="" width="24" height="24">
                                    +971 58 589 9990
                                </p>
                                <p class="mw-agency-contact__row">
                                    <img src="/frontend/assets/images/icons/email.svg" alt="" width="24" height="24">
                                    info@mightywarnersrealty.com
                                </p>
                                <p class="mw-agency-contact__row">
                                    <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="24" height="24">
                                    +971 58 589 9990
                                </p>
                            </div>

                            <div class="mw-agency-contact__col">
                                <h3 class="mw-agency-contact__subtitle">Agency Details</h3>
                                <p class="mw-agency-contact__row">
                                    <img src="/frontend/assets/images/agency-details/icon-info.svg" alt="" width="24" height="24">
                                    ORN No: ORN762349134
                                </p>
                                <p class="mw-agency-contact__row">
                                    <img src="/frontend/assets/images/agency-details/icon-globe.svg" alt="" width="24" height="24">
                                    www.dxbdubaiproperties.com
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </main>
</template>
