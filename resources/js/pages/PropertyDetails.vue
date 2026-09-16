<script setup>
const galleryImages = [
    { src: '/frontend/assets/images/property-details/gallery-2.jpg', alt: 'Elegant Apartment — bedroom' },
    { src: '/frontend/assets/images/property-details/gallery-1.jpg', alt: 'Elegant Apartment — living room' },
    { src: '/frontend/assets/images/property-details/gallery-3.jpg', alt: 'Elegant Apartment — bathroom' },
];

const pills = [
    { icon: '/frontend/assets/images/property-details/tag.svg', label: 'Rera ID', value: 'RERA70613' },
    { icon: '/frontend/assets/images/property-details/status.svg', label: 'Status', value: 'Active' },
    { icon: '/frontend/assets/images/icons/filter.svg', label: 'Type', value: 'SELL' },
    { icon: '/frontend/assets/images/icons/bed.svg', label: 'Bedrooms', value: '4' },
    { icon: '/frontend/assets/images/icons/bathroom.svg', label: 'Bathrooms', value: '3' },
    { icon: '/frontend/assets/images/icons/location.svg', label: 'Location', value: 'Mirdif' },
    { icon: '/frontend/assets/images/icons/area.svg', label: 'Size', value: '5952 sqft' },
    { icon: '/frontend/assets/images/property-details/garage.svg', label: 'Garage', value: '1' },
    { icon: '/frontend/assets/images/property-details/calendar.svg', label: 'Year Built', value: '2015' },
];

const featureChips = [
    { icon: '/frontend/assets/images/property-details/amenity-pool.svg', label: 'Swimming Pool' },
    { icon: '/frontend/assets/images/property-details/amenity-playground.svg', label: 'Playground' },
    { icon: '/frontend/assets/images/property-details/amenity-garden.svg', label: 'Garden' },
    { icon: '/frontend/assets/images/property-details/amenity-gym.svg', label: 'Gym' },
    { icon: '/frontend/assets/images/property-details/amenity-parking.svg', label: 'Parking' },
    { icon: '/frontend/assets/images/property-details/amenity-balcony.svg', label: 'Balcony or Terrace' },
    { icon: '/frontend/assets/images/property-details/amenity-security.svg', label: 'Security Staff' },
    { icon: '/frontend/assets/images/property-details/amenity-fire-alarm.svg', label: 'Fire Alarm' },
    { icon: '/frontend/assets/images/property-details/amenity-wifi.svg', label: 'WiFi' },
    { icon: '/frontend/assets/images/property-details/amenity-cctv.svg', label: 'CCTV' },
];

const nearbyChips = [
    { icon: '/frontend/assets/images/property-details/nearby-metro.svg', label: 'BurJuman Metro Station' },
    { icon: '/frontend/assets/images/property-details/nearby-school.svg', label: 'Myers-Garcia School' },
];

const floorPlans = [
    { name: '1 Bedroom Apartments', range: '672 to 869 sq. ft.', alt: 'Floor plan — 1 Bedroom Apartment' },
    { name: '2 Bedroom Apartments', range: '986 to 1,314 sq. ft', alt: 'Floor plan — 2 Bedroom Apartment' },
    { name: '3 Bedroom Apartments', range: '1,724 to 2,167 sq. ft.', alt: 'Floor plan — 3 Bedroom Apartment' },
];

const galleryPhotos = [
    { src: '/frontend/assets/images/home/realty-card-2.jpg', caption: 'Elegant Apartment — photo 2' },
    { src: '/frontend/assets/images/home/realty-card-3.jpg', caption: 'Elegant Apartment — photo 3' },
    { src: '/frontend/assets/images/home/realty-card-4.jpg', caption: 'Elegant Apartment — photo 4' },
    { src: '/frontend/assets/images/home/project-card-2.jpg', caption: 'Elegant Apartment — photo 5' },
    { src: '/frontend/assets/images/home/project-card-3.jpg', caption: 'Elegant Apartment — photo 6' },
    { src: '/frontend/assets/images/home/project-card-4.jpg', caption: 'Elegant Apartment — photo 7' },
    { src: '/frontend/assets/images/home/project-card-5.jpg', caption: 'Elegant Apartment — photo 8' },
    { src: '/frontend/assets/images/home/project-card-6.jpg', caption: 'Elegant Apartment — photo 9' },
    { src: '/frontend/assets/images/home/find-villa.jpg', caption: 'Elegant Apartment — photo 10' },
];

const similarProperties = [
    {
        photos: [
            { src: '/frontend/assets/images/home/project-card-3.jpg', alt: 'Spacious Villa in Al Barsha' },
            { src: '/frontend/assets/images/home/realty-card-1.jpg', alt: '' },
            { src: '/frontend/assets/images/home/luxury-card-2.jpg', alt: '' },
        ],
        type: 'Villa', price: 'AED 4,850,000', name: 'Spacious Villa in Al Barsha', location: 'Al Barsha, Dubai',
        beds: '5 Bed', baths: '6 Bath', area: '6,200 sq.ft',
    },
    {
        photos: [
            { src: '/frontend/assets/images/home/project-card-4.jpg', alt: 'Modern Townhouse in Arabian Ranches' },
            { src: '/frontend/assets/images/home/realty-card-2.jpg', alt: '' },
            { src: '/frontend/assets/images/home/luxury-card-3.png', alt: '' },
        ],
        type: 'Townhouse', price: 'AED 3,117,103', name: 'Modern Townhouse in Arabian Ranches', location: 'Arabian Ranches, Dubai',
        beds: '4 Bed', baths: '4 Bath', area: '2,295 sq.ft',
    },
    {
        photos: [
            { src: '/frontend/assets/images/home/project-card-5.jpg', alt: 'Sea View Penthouse in Dubai Marina' },
            { src: '/frontend/assets/images/home/realty-card-3.jpg', alt: '' },
            { src: '/frontend/assets/images/home/luxury-card-1.jpg', alt: '' },
        ],
        type: 'Penthouse', price: 'AED 9,750,000', name: 'Sea View Penthouse in Dubai Marina', location: 'Dubai Marina, Dubai',
        beds: '4 Bed', baths: '5 Bath', area: '3,800 sq.ft',
    },
    {
        photos: [
            { src: '/frontend/assets/images/home/project-card-6.jpg', alt: 'Cozy Studio in Discovery Gardens' },
            { src: '/frontend/assets/images/home/realty-card-4.jpg', alt: '' },
            { src: '/frontend/assets/images/home/luxury-card-2.jpg', alt: '' },
        ],
        type: 'Studio', price: 'AED 650,000', name: 'Cozy Studio in Discovery Gardens', location: 'Discovery Gardens, Dubai',
        beds: 'Studio', baths: '1 Bath', area: '450 sq.ft',
    },
    {
        photos: [
            { src: '/frontend/assets/images/home/luxury-card-3.png', alt: 'Contemporary Duplex in Jumeirah Village Circle' },
            { src: '/frontend/assets/images/home/project-card-1.jpg', alt: '' },
            { src: '/frontend/assets/images/home/realty-card-1.jpg', alt: '' },
        ],
        type: 'Duplex', price: 'AED 2,850,000', name: 'Contemporary Duplex in Jumeirah Village Circle', location: 'Jumeirah Village Circle, Dubai',
        beds: '3 Bed', baths: '3 Bath', area: '1,950 sq.ft',
    },
    {
        photos: [
            { src: '/frontend/assets/images/home/luxury-card-1.jpg', alt: 'Luxury Beachfront Villa in Palm Jumeirah' },
            { src: '/frontend/assets/images/home/luxury-card-2.jpg', alt: '' },
            { src: '/frontend/assets/images/home/project-card-2.jpg', alt: '' },
        ],
        type: 'Villa', price: 'AED 17,500,000', name: 'Luxury Beachfront Villa in Palm Jumeirah', location: 'Palm Jumeirah, Dubai',
        beds: '5 Bed', baths: '6 Bath', area: '7,000 sq.ft',
    },
];
</script>

<template>
    <main>
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>Elegant Apartment</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><router-link to="/properties-dubai">Properties in Dubai</router-link><span class="mw-about-crumb__sep"> / </span><span>Eelegant Apartment</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-property">
            <div class="mw-property__gallery-bleed" data-reveal>
                <div class="mw-property__gallery">
                    <div class="mw-agent-more__track" data-property-gallery-slider>
                        <div v-for="(photo, index) in galleryImages" :key="index" class="mw-agent-more__slide">
                            <div class="mw-property__gallery-item">
                                <img :src="photo.src" :alt="photo.alt">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="mw-property__gallery-arrow mw-property__gallery-arrow--prev" data-property-gallery-prev aria-label="Previous photo">
                        <img src="/frontend/assets/images/icons/chevron.svg" alt="" width="18" height="18">
                    </button>
                    <button type="button" class="mw-property__gallery-arrow mw-property__gallery-arrow--next" data-property-gallery-next aria-label="Next photo">
                        <img src="/frontend/assets/images/icons/chevron.svg" alt="" width="18" height="18">
                    </button>
                </div>
            </div>

            <div class="container-ctn">

                <div class="mw-property__head" data-reveal>
                    <h2 class="sr-only">Elegant Apartment</h2>
                    <div class="mw-property__head-main">
                        <span class="mw-property__status-badge">For Sale</span>
                        <span class="mw-property__meta-item"><img src="/frontend/assets/images/icons/location.svg" alt="" width="16" height="16">Mirdif, Abu Dhabi</span>
                        <span class="mw-property__meta-item"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="16" height="16">4 Bedrooms</span>
                        <span class="mw-property__meta-item"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="16" height="16">3 Bathrooms</span>
                        <span class="mw-property__meta-item"><img src="/frontend/assets/images/property-details/calendar.svg" alt="" width="16" height="16">Updated: February 24</span>
                        <span class="mw-property__meta-item"><img src="/frontend/assets/images/property-details/eye.svg" alt="" width="16" height="16">5,584<img class="mw-property__meta-info" src="/frontend/assets/images/property-details/info.svg" alt="" width="16" height="16"></span>
                        <span class="mw-property__meta-item"><img src="/frontend/assets/images/icons/heart.svg" alt="" width="16" height="16">408</span>
                    </div>
                    <div class="mw-property__price">
                        <span class="mw-property__price-label">Starting Price</span>
                        <strong class="mw-property__price-value">
                            <span class="mw-property__price-currency">AED</span>
                            <span class="mw-property__price-amount">2,292,913</span>
                        </strong>
                    </div>
                </div>

                <div class="mw-property__tabs-bar" data-property-tabs-bar>
                    <nav class="mw-property__tabs" role="tablist" aria-label="Property sections">
                        <a href="#overview" class="mw-property__tab is-active" role="tab" aria-selected="true" data-property-tab="overview">Overview</a>
                        <a href="#features" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="features">Features &amp; Amenities</a>
                        <a href="#floor-plans" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="floor-plans">Floor Plans</a>
                        <a href="#near-by" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="near-by">Near By</a>
                        <a href="#gallery" class="mw-property__tab" role="tab" aria-selected="false" data-property-tab="gallery">Gallery</a>
                    </nav>

                    <select class="mw-property__tabs-select" aria-label="Property sections" data-property-tabs-select>
                        <option value="overview">Overview</option>
                        <option value="features">Features &amp; Amenities</option>
                        <option value="floor-plans">Floor Plans</option>
                        <option value="near-by">Near By</option>
                        <option value="gallery">Gallery</option>
                    </select>
                </div>

                <div class="mw-property__body">
                    <div class="mw-property__main">

                        <section id="overview" class="mw-property__section" data-reveal data-property-panel="overview">
                            <h3 class="mw-property__section-title">About the project</h3>
                            <div class="mw-property__about-text">
                                <p>Your ultimate destination for luxurious living at &quot;The Resident Tower Sector 150, Noida&quot;!</p>
                                <p>At The Resident Tower, we understand that choosing a home is more than just selecting four walls and a roof. It's about embracing a lifestyle that resonates with your aspirations. Our meticulously designed spaces combine comfort, elegance, and functionality to create homes that inspire and delight.</p>
                                <p>Each apartment is thoughtfully crafted with premium finishes and superior amenities, ensuring that every corner reflects quality and sophistication. From the grand entrance lobby to the private sanctuaries of our residences, every detail has been carefully considered to enhance your living experience.</p>
                            </div>

                            <div class="mw-property__pills">
                                <div v-for="pill in pills" :key="pill.label" class="mw-property__pill">
                                    <span class="mw-property__pill-icon"><span class="mw-property__pill-icon-glyph" :style="{ '--icon': `url('${pill.icon}')` }" aria-hidden="true"></span></span>
                                    <span class="mw-property__pill-text"><span class="mw-property__pill-label">{{ pill.label }}</span><span class="mw-property__pill-value">{{ pill.value }}</span></span>
                                </div>
                            </div>
                        </section>

                        <section id="features" class="mw-property__section" data-reveal data-property-panel="features">
                            <h3 class="mw-property__section-title">Features &amp; Amenities</h3>
                            <div class="mw-property__chips">
                                <span v-for="chip in featureChips" :key="chip.label" class="mw-property__chip"><img :src="chip.icon" alt="" width="24" height="24">{{ chip.label }}</span>
                            </div>
                        </section>

                        <section id="floor-plans" class="mw-property__section" data-reveal data-property-panel="floor-plans">
                            <h3 class="mw-property__section-title">Floor Plans</h3>
                            <div class="mw-property__floorplan">
                                <div class="mw-property__floorplan-list" role="tablist" aria-label="Floor plan types" data-floorplan-tablist>
                                    <div v-for="(plan, index) in floorPlans" :key="plan.name" class="mw-property__floorplan-row" :class="{ 'is-active': index === 0 }" role="tab" tabindex="0" :aria-selected="index === 0 ? 'true' : 'false'" data-floorplan-tab data-floorplan-img="/frontend/assets/images/property-details/floorplan.png" :data-floorplan-alt="plan.alt">
                                        <div class="mw-property__floorplan-row-head">
                                            <div>
                                                <p class="mw-property__floorplan-name">{{ plan.name }}</p>
                                                <p class="mw-property__floorplan-range">{{ plan.range }}</p>
                                            </div>
                                            <span class="mw-property__floorplan-arrow" aria-hidden="true">
                                                <img src="/frontend/assets/images/icons/chevron.svg" alt="" width="24" height="24">
                                            </span>
                                        </div>
                                    </div>
                                    <a href="#" class="mw-property__floorplan-download">Download Floor Plan</a>
                                </div>
                                <div class="mw-property__floorplan-media">
                                    <a href="/frontend/assets/images/property-details/floorplan.png" data-fancybox="floorplan" data-caption="Floor plan — 1 Bedroom Apartment" class="mw-property__floorplan-zoom" data-floorplan-link>
                                        <img src="/frontend/assets/images/property-details/floorplan.png" alt="Floor plan — 1 Bedroom Apartment" data-floorplan-image>
                                    </a>
                                    <div class="mw-property__floorplan-caption">
                                        <span class="mw-property__floorplan-caption-name" data-floorplan-caption-name>1 Bedroom Apartments</span>
                                        <span class="mw-property__floorplan-caption-range" data-floorplan-caption-range>672 to 869 sq. ft.</span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section id="near-by" class="mw-property__section" data-reveal data-property-panel="near-by">
                            <h3 class="mw-property__section-title">Near By</h3>
                            <div class="mw-property__chips">
                                <span v-for="chip in nearbyChips" :key="chip.label" class="mw-property__chip"><img :src="chip.icon" alt="" width="24" height="24">{{ chip.label }}</span>
                            </div>
                        </section>

                        <section id="gallery" class="mw-property__section" data-reveal data-property-panel="gallery">
                            <h3 class="mw-property__section-title">Photo Gallery</h3>
                            <div class="mw-property__photo-gallery">
                                <div class="mw-property__photo-gallery-main">
                                    <a href="/frontend/assets/images/home/realty-card-1.jpg" data-fancybox="property-gallery" data-caption="Elegant Apartment — photo 1">
                                        <img src="/frontend/assets/images/home/realty-card-1.jpg" alt="Elegant Apartment photo gallery — main view">
                                    </a>
                                </div>
                                <div class="mw-property__photo-gallery-grid">
                                    <a v-for="(photo, index) in galleryPhotos" :key="index" :href="photo.src" data-fancybox="property-gallery" :data-caption="photo.caption"><img :src="photo.src" alt="Elegant Apartment photo"></a>
                                </div>
                            </div>
                        </section>

                    </div>

                    <aside class="mw-property__agent-wrap" data-reveal>
                        <article class="mw-agent-card mw-property-agent">
                            <div class="mw-agent-card__head">
                                <div class="mw-agent-card__avatar">
                                    <img src="/frontend/assets/images/agents/ahmed.png" alt="Ahmed Hassan Al Mansoori" width="80" height="80">
                                </div>
                                <div class="mw-agent-card__intro">
                                    <h4 class="mw-agent-card__name">Ahmed Hassan Al Mansoori</h4>
                                    <div class="mw-agent-card__tags">
                                        <span class="mw-agent-card__tag mw-agent-card__tag--fill">Serves in Dubai</span>
                                        <span class="mw-agent-card__tag">Rent : 0</span>
                                        <span class="mw-agent-card__tag">Sell : 0</span>
                                        <span class="mw-agent-card__tag">Quality Lister</span>
                                        <span class="mw-agent-card__tag">Responsive Brocker</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mw-agent-card__body">
                                <p class="mw-property-agent__stat">Years of Experience: 7</p>
                                <p class="mw-property-agent__stat">Preferred Areas: Downtown Dubai, Dubai Marina, Business Bay</p>
                                <div class="mw-property-agent__cta">
                                    <a href="tel:+971585899990" class="mw-property-agent__cta-btn mw-property-agent__cta-btn--call" aria-label="Call Ahmed Hassan Al Mansoori">
                                        <img src="/frontend/assets/images/icons/phone.svg" alt="" width="18" height="18">
                                    </a>
                                    <a href="https://wa.me/971585899990" target="_blank" rel="noopener" class="mw-property-agent__cta-btn mw-property-agent__cta-btn--whatsapp" aria-label="WhatsApp Ahmed Hassan Al Mansoori">
                                        <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="18" height="18">
                                    </a>
                                    <a href="mailto:info@mightywarnersrealty.com" class="mw-property-agent__cta-btn mw-property-agent__cta-btn--email" aria-label="Email Ahmed Hassan Al Mansoori">
                                        <img src="/frontend/assets/images/icons/email.svg" alt="" width="18" height="18">
                                    </a>
                                    <span class="mw-agent-profile__badge mw-agent-profile__badge--broker mw-property-agent__broker-badge">MW Brocker</span>
                                </div>
                            </div>
                            <div class="mw-agent-card__foot">
                                <img class="mw-agent-card__logo" src="/frontend/assets/images/logo-dark.png" alt="MW Realty" width="50" height="28">
                                <span class="mw-agent-card__rule" aria-hidden="true"></span>
                                <router-link to="/agent-details" class="mw-agent-card__link">
                                    View Details
                                    <img src="/frontend/assets/images/icons/find-cta-arrow.svg" alt="" width="18" height="18">
                                </router-link>
                            </div>
                        </article>

                        <div class="mw-property-enquiry">
                            <h4 class="mw-property-enquiry__title">Enquire About This Property</h4>
                            <form class="mw-property-enquiry__form" novalidate @submit.prevent>
                                <div class="mw-property-enquiry__field">
                                    <label for="enquiry-name">Name*</label>
                                    <input type="text" id="enquiry-name" name="name" placeholder="Your name" required>
                                </div>
                                <div class="mw-property-enquiry__field">
                                    <label for="enquiry-email">Email*</label>
                                    <input type="email" id="enquiry-email" name="email" placeholder="Your email" required>
                                </div>
                                <div class="mw-property-enquiry__field">
                                    <label for="enquiry-phone">Phone</label>
                                    <input type="tel" id="enquiry-phone" name="phone" placeholder="Your phone">
                                </div>
                                <div class="mw-property-enquiry__field">
                                    <label for="enquiry-property">Property</label>
                                    <input type="text" id="enquiry-property" name="property" value="Elegant Apartment" disabled>
                                </div>
                                <div class="mw-property-enquiry__field">
                                    <label for="enquiry-message">Message</label>
                                    <textarea id="enquiry-message" name="message" rows="4" placeholder="I'm interested in this property...">I'm interested in "Elegant Apartment" — please share more details.</textarea>
                                </div>

                                <button type="submit" class="mw-btn mw-btn--gradient mw-property-enquiry__submit">Send Enquiry</button>
                            </form>
                        </div>

                        <router-link to="/#post-property" class="mw-property-ad">
                            <span class="mw-property-ad__label">Advertisement</span>
                            <img src="/frontend/assets/images/home/post-property.jpg" alt="" class="mw-property-ad__bg">
                            <span class="mw-property-ad__content">
                                <span class="mw-property-ad__eyebrow">MW Realty</span>
                                <span class="mw-property-ad__title">Sell Your Property Faster</span>
                                <span class="mw-property-ad__text">List with MW Realty and reach thousands of verified buyers across the UAE.</span>
                                <span class="mw-property-ad__cta">
                                    Post Your Property
                                    <img src="/frontend/assets/images/icons/find-cta-arrow.svg" alt="" width="16" height="16">
                                </span>
                            </span>
                        </router-link>

                        <a href="#" class="mw-property-ad mw-property-ad--image">
                            <span class="mw-property-ad__label">Advertisement</span>
                            <img src="/frontend/assets/images/property-details/ad-mortgage.svg" alt="Home Finance — Own It Sooner. Mortgage pre-approval in 24 hours, rates from 3.49% with our partner banks. Get Pre-Approved." class="mw-property-ad__image">
                        </a>
                    </aside>
                </div>

            </div>
        </section>

        <section class="mw-agent-more">
            <div class="container-ctn">
                <div class="mw-agent-more__head">
                    <h2 class="mw-agent-more__title">Similar Properties</h2>
                    <div class="mw-agent-more__nav">
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--prev mw-agent-more__nav-btn" data-similar-properties-prev aria-label="Previous properties">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--next mw-agent-more__nav-btn" data-similar-properties-next aria-label="Next properties">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                    </div>
                </div>

                <div class="mw-agent-more__track" data-similar-properties-slider>

                    <div v-for="(property, index) in similarProperties" :key="index" class="mw-agent-more__slide"><article class="mw-agent-prop">
                        <div class="mw-agent-prop__media" data-card-gallery>
                            <div class="mw-agent-prop__slides" data-gallery-track>
                                <img v-for="(photo, pIndex) in property.photos" :key="pIndex" :src="photo.src" :alt="photo.alt" class="mw-agent-prop__photo">
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
                                <span data-gallery-count>3</span>
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
                                <span class="mw-agent-prop__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="18" height="18">{{ property.beds }}</span>
                                <span class="mw-agent-prop__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="18" height="18">{{ property.baths }}</span>
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
