<script setup>
const otherProperties = [
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
</script>

<template>
    <main>
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>Jayme Craig</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><router-link to="/agents">Agent</router-link><span class="mw-about-crumb__sep"> / </span><span>Jayme Craig</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-agent-detail">
            <div class="container-ctn">
                <article class="mw-agent-profile" data-reveal>
                    <div class="mw-agent-profile__top">
                        <div class="mw-agent-profile__avatar">
                            <img src="/frontend/assets/images/agents/jayme.png" alt="Jayme Craig" width="80" height="80">
                        </div>
                        <div class="mw-agent-profile__meta">
                            <div class="mw-agent-profile__row">
                                <h2 class="mw-agent-profile__name">Jayme Craig</h2>
                                <span class="mw-agent-profile__badge mw-agent-profile__badge--broker">MW Brocker</span>
                                <span class="mw-agent-profile__badge mw-agent-profile__badge--quality">Quality Lister</span>
                                <span class="mw-agent-profile__badge mw-agent-profile__badge--responsive">Responsive Brocker</span>
                            </div>
                            <div class="mw-agent-profile__row">
                                <span class="mw-agent-profile__badge mw-agent-profile__badge--area">Serves in Dubai</span>
                                <div class="mw-agent-profile__share">
                                    Share
                                    <a href="#" aria-label="Share on X"><img src="/frontend/assets/images/icons/contact-twitter.svg" alt="" width="18" height="18"></a>
                                    <a href="#" aria-label="Share on Facebook"><img src="/frontend/assets/images/icons/contact-facebook.svg" alt="" width="18" height="18"></a>
                                    <a href="#" aria-label="Share on Instagram"><img src="/frontend/assets/images/icons/contact-instagram.svg" alt="" width="18" height="18"></a>
                                    <a href="#" aria-label="Share on LinkedIn"><img src="/frontend/assets/images/icons/contact-linkedin.svg" alt="" width="18" height="18"></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mw-agent-profile__bar">
                        <span class="mw-agent-profile__chip">Years of Experience: 5</span>
                        <span class="mw-agent-profile__chip">Preferred Areas: Jumeirah, Business Bay</span>
                        <span class="mw-agent-profile__chip mw-agent-profile__chip--stat">Rent : 0</span>
                        <span class="mw-agent-profile__chip mw-agent-profile__chip--stat">Sell : 0</span>
                        <div class="mw-agent-profile__actions">
                            <a class="mw-agent-cta mw-agent-cta--email" href="mailto:info@mightywarnersrealty.com">
                                <img src="/frontend/assets/images/icons/email.svg" alt="" width="18" height="18">
                                Email
                            </a>
                            <a class="mw-agent-cta mw-agent-cta--call" href="tel:+971585899990">
                                <img src="/frontend/assets/images/icons/phone.svg" alt="" width="18" height="18">
                                Call Us
                            </a>
                            <a class="mw-agent-cta mw-agent-cta--whatsapp" href="https://wa.me/971585899990" target="_blank" rel="noopener">
                                <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="18" height="18">
                                WhatsApp
                            </a>
                        </div>
                    </div>
                </article>

                <div class="mw-agent-detail__tabs" data-agent-tabs>
                    <button type="button" class="mw-agent-detail__tab is-active" data-agent-tab="about">About Me</button>
                    <button type="button" class="mw-agent-detail__tab" data-agent-tab="listings">My Listing Properties</button>
                </div>

                <div class="mw-agent-detail__bio" data-agent-panel="about">
                    <p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.</p>
                    <p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged. It was popularised in the 1960s with the release of Letraset sheets containing Lorem Ipsum passages, and more recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.</p>
                </div>
            </div>
        </section>

        <section class="mw-agent-more">
            <div class="container-ctn">
                <div class="mw-agent-more__head">
                    <h2 class="mw-agent-more__title">Other Properties</h2>
                    <div class="mw-agent-more__nav">
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--prev mw-agent-more__nav-btn" data-agent-listings-prev aria-label="Previous properties">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--next mw-agent-more__nav-btn" data-agent-listings-next aria-label="Next properties">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                    </div>
                </div>

                <div class="mw-agent-more__track" data-agent-listings-slider>
                    <div v-for="(property, index) in otherProperties" :key="index" class="mw-agent-more__slide"><article class="mw-agent-prop">
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
        </section>
    </main>
</template>
