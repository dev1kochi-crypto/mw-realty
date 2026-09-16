<script setup>
const heroTabs = [
    { filter: 'buy', label: 'Buy', active: true },
    { filter: 'rent', label: 'Rent' },
    { filter: 'ready', label: 'Ready' },
    { filter: 'off-plan', label: 'Off-Plan' },
];

const bedroomOptions = [
    { value: 'studio', label: 'Studio' },
    { value: '1', label: '1 Bedroom' },
    { value: '2', label: '2 Bedrooms' },
    { value: '3', label: '3 Bedrooms' },
    { value: '4', label: '4 Bedrooms' },
    { value: '5', label: '5 Bedrooms' },
    { value: '6', label: '6 Bedrooms' },
    { value: '7+', label: '7+ Bedrooms' },
];

const propertyTypeOptions = ['Apartment', 'Villa', 'Penthouse', 'Townhouse', 'Plot', 'Office'];

const pricePresets = [
    { min: 0, max: 500000, label: 'Under 500K' },
    { min: 500000, max: 1000000, label: '500K – 1M' },
    { min: 1000000, max: 2000000, label: '1M – 2M' },
    { min: 2000000, max: 5000000, label: '2M – 5M' },
    { min: 5000000, max: 10000000, label: '5M – 10M' },
    { min: 10000000, max: 30000000, label: '10M+' },
];

const partnerLogos = [
    { src: '/frontend/assets/images/partners/partner-1.png', alt: 'Developer partner' },
    { src: '/frontend/assets/images/partners/partner-2.png', alt: 'Developer partner' },
    { src: '/frontend/assets/images/partners/partner-3.png', alt: 'Developer partner' },
    { src: '/frontend/assets/images/partners/partner-4.png', alt: 'Developer partner' },
    { src: '/frontend/assets/images/partners/partner-5.png', alt: 'Nshama' },
    { src: '/frontend/assets/images/partners/partner-6.png', alt: 'Sobha' },
    { src: '/frontend/assets/images/partners/partner-7.png', alt: 'Omniyat' },
];

const projectFilters = [
    { filter: 'all', label: 'All', active: true },
    { filter: 'dubai', label: 'Dubai' },
    { filter: 'abu-dhabi', label: 'Abu Dhabi' },
    { filter: 'sharjah', label: 'Sharjah' },
    { filter: 'ajman', label: 'Ajman' },
    { filter: 'ras-al-khaimah', label: 'Ras Al Khaimah' },
    { filter: 'umm-al-quwain', label: 'Umm Al Quwain' },
];

const projectCards = [
    { category: 'dubai', images: ['project-card-1.jpg', 'project-card-2.jpg', 'luxury-card-1.jpg', 'realty-card-1.jpg'], name: 'Modern Penthouse in Downtown Dubai', location: 'Downtown Dubai', beds: 2, baths: 2, area: '2,100 sq.ft', type: 'Penthouse', price: 'AED 9,500,000' },
    { category: 'abu-dhabi', images: ['project-card-2.jpg', 'project-card-3.jpg', 'luxury-card-2.jpg', 'realty-card-2.jpg'], name: 'Skyline Residences on Saadiyat Island', location: 'Saadiyat Island, Abu Dhabi', beds: 3, baths: 3, area: '1,860 sq.ft', type: 'Apartment', price: 'AED 4,200,000' },
    { category: 'sharjah', images: ['project-card-3.jpg', 'project-card-4.jpg', 'luxury-card-3.jpg', 'realty-card-3.jpg'], name: 'Waterfront Apartment in Al Khan', location: 'Al Khan, Sharjah', beds: 3, baths: 3, area: '1,850 sq.ft', type: 'Apartment', price: 'AED 2,150,000' },
    { category: 'ajman', images: ['project-card-4.jpg', 'project-card-5.jpg', 'realty-card-4.jpg', 'luxury-card-1.jpg'], name: 'Family Villa in Al Nuaimiya', location: 'Al Nuaimiya, Ajman', beds: 4, baths: 4, area: '3,200 sq.ft', type: 'Villa', price: 'AED 3,400,000' },
    { category: 'ras-al-khaimah', images: ['project-card-5.jpg', 'project-card-6.jpg', 'luxury-card-2.jpg', 'project-card-1.jpg'], name: 'Golf Course Townhouse in Al Hamra', location: 'Al Hamra, Ras Al Khaimah', beds: 3, baths: 3, area: '2,400 sq.ft', type: 'Townhouse', price: 'AED 2,850,000' },
    { category: 'umm-al-quwain', images: ['project-card-6.jpg', 'project-card-1.jpg', 'realty-card-1.jpg', 'luxury-card-3.jpg'], name: 'Marina Residences in Umm Al Quwain', location: 'UAQ Marina, Umm Al Quwain', beds: 2, baths: 2, area: '1,420 sq.ft', type: 'Apartment', price: 'AED 1,650,000' },
    { category: 'dubai', images: ['luxury-card-1.jpg', 'project-card-1.jpg', 'realty-card-2.jpg', 'luxury-card-3.jpg'], name: 'Luxury Villa on Palm Jumeirah', location: 'Palm Jumeirah, Dubai', beds: 5, baths: 6, area: '6,800 sq.ft', type: 'Villa', price: 'AED 18,500,000' },
    { category: 'abu-dhabi', images: ['realty-card-3.jpg', 'project-card-3.jpg', 'luxury-card-2.jpg', 'project-card-6.jpg'], name: 'Beachfront Apartment on Yas Island', location: 'Yas Island, Abu Dhabi', beds: 2, baths: 2, area: '1,560 sq.ft', type: 'Apartment', price: 'AED 2,750,000' },
];

const highlightCards = [
    { images: ['luxury-card-1.jpg', 'luxury-card-2.jpg', 'luxury-card-3.png', 'realty-card-1.jpg'], name: 'Luxury Beachfront Villa in Palm Jumeirah', location: 'Palm Jumeirah', beds: '5 Bed', baths: '6 Bath', area: '12,500 sq.ft', price: 'AED 17,500,000' },
    { images: ['luxury-card-2.jpg', 'realty-card-2.jpg', 'luxury-card-1.jpg', 'project-card-1.jpg'], name: 'Skyline Penthouse in Downtown Dubai', location: 'Downtown Dubai', beds: '3 Bed', baths: '4 Bath', area: '4,200 sq.ft', price: 'AED 12,800,000' },
    { images: ['luxury-card-3.png', 'luxury-card-1.jpg', 'realty-card-3.jpg', 'luxury-card-2.jpg'], name: 'Waterfront Mansion in Emirates Hills', location: 'Emirates Hills', beds: '6 Bed', baths: '7 Bath', area: '18,000 sq.ft', price: 'AED 21,000,000' },
    { images: ['realty-card-2.jpg', 'luxury-card-2.jpg', 'project-card-2.jpg', 'luxury-card-3.png'], name: 'Branded Residence in Dubai Marina', location: 'Dubai Marina', beds: '4 Bed', baths: '5 Bath', area: '6,800 sq.ft', price: 'AED 15,400,000' },
];

const postPropertySteps = [
    { index: '01', icon: 'property-add.svg', title: 'Add Details Of Your Property', text: 'Begin By Telling Us The Few Basic Details About Your Property Like Your Property Type, Location, No. Of Rooms Etc.' },
    { index: '02', icon: 'upload.svg', title: 'Upload Photos & Videos', text: 'Upload Photos And Videos Of Your Property Either Via Your Desktop Device Or From Your Mobile Phone.' },
    { index: '03', icon: 'tag-add.svg', title: 'Add Pricing & Ownership', text: "Just Update Your Property's Ownership Details And Your Expected Price And Your Property Is Ready For Posting Post Property." },
];

const popularPlaces = [
    { image: 'place-dubai.jpg', name: 'Dubai' },
    { image: 'place-dubai.jpg', name: 'Dubai' },
    { image: 'place-sharjah.jpg', name: 'Sharjah' },
    { image: 'place-ajman.jpg', name: 'Ajman' },
    { image: 'place-abudhabi.jpg', name: 'Abu Dhabi' },
];

const luxuryCards = [
    { image: 'luxury-card-1.jpg', villa: true, name: 'Nihil est aut volupt', location: 'Ut adipisci et venia', stat1: 'Natus magnam enim po', stat2: 'Tempore a labore po', area: '49 Sqft', price: 'AED 645' },
    { image: 'luxury-card-2.jpg', villa: false, name: 'Nihil est aut volupt2', location: 'Ut adipisci et venia', stat1: 'Natus magnam enim po', stat2: 'Tempore a labore po', area: '49 Sqft', price: 'AED 850' },
    { image: 'luxury-card-3.png', villa: false, name: 'Nihil est aut volupt3', location: 'Ut adipisci et venia', stat1: 'Natus magnam enim po', stat2: 'Tempore a labore po', area: '49 Sqft', price: 'AED 760' },
    { image: 'luxury-card-1.jpg', villa: true, name: 'Nihil est aut volupt', location: 'Ut adipisci et venia', stat1: 'Natus magnam enim po', stat2: 'Tempore a labore po', area: '49 Sqft', price: 'AED 645' },
];

const whyChooseUsItems = [
    { icon: 'feature-select-property.svg', title: 'Select Property' },
    { icon: 'feature-buy-property.svg', title: 'Buy Property' },
    { icon: 'feature-meet-team.svg', title: 'Meet Our Team' },
    { icon: 'feature-loan-assistance.svg', title: 'Loan Assistance' },
    { icon: 'feature-visit-site.svg', title: 'Visit Site' },
    { icon: 'feature-customer-support.svg', title: 'Customer Support' },
];

const realtyCards = [
    { image: 'realty-card-1.jpg' },
    { image: 'realty-card-2.jpg' },
    { image: 'realty-card-3.jpg' },
    { image: 'realty-card-4.jpg' },
];

const communityFilters = [
    { filter: 'for-sale', label: 'For Sale', active: true },
    { filter: 'for-rent', label: 'For Rent' },
    { filter: 'off-plan', label: 'Off Plan' },
];

const communitiesColumns = [
    [
        { icon: 'icon-community-dubai.svg', name: 'Dubai Marina' },
        { icon: 'icon-community-building-vector.svg', name: 'Palm Jumeirah' },
        { icon: 'icon-community-creek.svg', name: 'Dubai Creek Harbour' },
        { icon: 'icon-community-building-vector.svg', name: 'Jumeirah Village Circle' },
        { icon: 'icon-community-water-polo.svg', name: 'Al Habtoor Polo Resort and Club' },
    ],
    [
        { icon: 'icon-community-dubai.svg', name: 'Downtown Dubai' },
        { icon: 'icon-community-real-estate.svg', name: 'Dubai Hills Estate' },
        { icon: 'icon-community-beach.svg', name: 'Emaar Beachfront' },
        { icon: 'icon-community-golf.svg', name: 'Jumeirah Golf Estates' },
    ],
    [
        { icon: 'icon-community-building-2.svg', name: 'Jumeirah Beach Residence' },
        { icon: 'icon-community-building-6.svg', name: 'Damac Lagoons' },
        { icon: 'icon-community-mountain.svg', name: 'DAMAC Hills' },
        { icon: 'icon-community-building-vector.svg', name: 'Jumeirah' },
    ],
    [
        { icon: 'icon-community-building-outline.svg', name: 'Sobha Hartland' },
        { icon: 'icon-community-hot-springs-a.svg', name: 'The Springs' },
        { icon: 'icon-community-holiday-village.svg', name: 'Jumeirah Village Triangle' },
        { icon: 'icon-community-building-04.svg', name: 'Al Habtoor City' },
    ],
];

const findPropertiesCards = [
    { modifier: 'penthouse-1', image: 'find-penthouse-1.jpg', type: 'Pent House', count: '20 properties' },
    { modifier: 'villa', image: 'find-villa.jpg', type: 'Villa', count: '20 properties' },
    { modifier: 'penthouse-2', image: 'find-penthouse-2.jpg', type: 'Pent House', count: '20 properties' },
    { modifier: 'plot', image: 'find-plot.jpg', type: 'Plot', count: '20 properties' },
];

const testimonialQuoteText = "Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.";

const testimonials = [
    { type: 'video', modifier: 'max', image: 'testimonial-max.jpg', name: 'Max Patrick', role: 'CEO Of Company' },
    { type: 'video', modifier: 'anna', image: 'testimonial-anna.jpg', name: 'Anna James', role: 'Managing Director Of Company' },
    { type: 'quote', name: 'Jenifer Jackson', role: 'Managing Director Of Company' },
    { type: 'video', modifier: 'johnson', image: 'testimonial-johnson.jpg', name: 'Johnson Mathew', role: 'CEO Of Company' },
    { type: 'quote', name: 'Jenifer Jackson', role: 'Managing Director Of Company' },
    { type: 'video', modifier: 'max', image: 'testimonial-max.jpg', name: 'Max Patrick', role: 'CEO Of Company' },
];
</script>

<template>
    <main>
        <section class="mw-hero">
            <video class="mw-hero__video" autoplay muted loop playsinline poster="/frontend/assets/images/home/hero-bg.jpg" aria-hidden="true">
                <source src="/frontend/assets/video/banner.mp4" type="video/mp4">
            </video>
            <div class="container-ctn mw-hero__inner">
                <div class="mw-hero__panel">
                    <h1 class="mw-hero__title">Your Trusted Partner in</h1>
                    <p class="mw-hero__subtitle">Finding the Best Properties</p>

                    <div class="mw-hero__tabs" role="tablist" aria-label="Listing type" data-tab-group>
                        <button v-for="tab in heroTabs" :key="tab.filter" type="button" class="mw-hero__tab" :class="{ 'is-active': tab.active }" data-tab :data-filter="tab.filter">{{ tab.label }}</button>
                    </div>

                    <form class="mw-hero__search" id="search" role="search" @submit.prevent>
                        <label class="mw-hero__field">
                            <img src="/frontend/assets/images/icons/location.svg" alt="">
                            <input type="text" name="location" placeholder="Enter location">
                        </label>
                        <div class="mw-hero__field mw-hero__field--select mw-dropdown" data-dropdown data-dropdown-trigger tabindex="0" role="button" aria-haspopup="listbox">
                            <img src="/frontend/assets/images/icons/bed.svg" alt="">
                            <span data-dropdown-label>Bedroom</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-hero__field-chevron">
                            <ul class="mw-dropdown__menu" data-dropdown-menu>
                                <li v-for="opt in bedroomOptions" :key="opt.value"><button type="button" data-dropdown-option :data-value="opt.value">{{ opt.label }}</button></li>
                            </ul>
                        </div>
                        <div class="mw-hero__field mw-hero__field--select mw-hero__price mw-dropdown" data-dropdown>
                            <button type="button" class="mw-hero__price-trigger" data-dropdown-trigger aria-haspopup="dialog">
                                <img src="/frontend/assets/images/icons/tag-price.svg" alt="">
                                <span data-dropdown-label>Price Range</span>
                                <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-hero__field-chevron">
                            </button>
                            <div class="mw-dropdown__menu mw-hero__price-panel" data-dropdown-menu>
                                <div class="mw-hero__price-readout">
                                    <label class="mw-hero__price-input">
                                        <span>Min</span>
                                        <input type="text" inputmode="decimal" data-price-min-input aria-label="Minimum price" value="AED 0">
                                    </label>
                                    <label class="mw-hero__price-input">
                                        <span>Max</span>
                                        <input type="text" inputmode="decimal" data-price-max-input aria-label="Maximum price" value="AED 30M">
                                    </label>
                                </div>
                                <div class="mw-hero__price-slider" data-price-slider>
                                    <div class="mw-hero__price-track"></div>
                                    <div class="mw-hero__price-fill" data-price-range></div>
                                    <input type="range" name="min_price" min="0" max="30000000" step="50000" value="0" data-price-min aria-label="Minimum price">
                                    <input type="range" name="max_price" min="0" max="30000000" step="50000" value="30000000" data-price-max aria-label="Maximum price">
                                </div>
                                <p class="mw-hero__price-presets-title">Quick select</p>
                                <div class="mw-hero__price-presets">
                                    <button v-for="preset in pricePresets" :key="preset.label" type="button" data-price-preset :data-min="preset.min" :data-max="preset.max">{{ preset.label }}</button>
                                </div>
                            </div>
                        </div>
                        <div class="mw-hero__field mw-hero__field--select mw-dropdown" data-dropdown data-dropdown-trigger tabindex="0" role="button" aria-haspopup="listbox">
                            <img src="/frontend/assets/images/icons/building.svg" alt="">
                            <span data-dropdown-label>Property Type</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-hero__field-chevron">
                            <ul class="mw-dropdown__menu" data-dropdown-menu>
                                <li v-for="opt in propertyTypeOptions" :key="opt"><button type="button" data-dropdown-option>{{ opt }}</button></li>
                            </ul>
                        </div>
                        <button type="submit" class="mw-hero__submit">Search</button>
                    </form>

                    <div class="mw-hero__foot">
                        <p class="mw-hero__ai-note">Want to find out more about UAE real estate using AI? <strong>Comming Soon</strong></p>
                        <a href="#" class="mw-hero__guide">
                            <span>
                                <img src="/frontend/assets/images/icons/play.svg" alt="" width="24" height="24">
                                Let Us Guide Your Home
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-partners">
            <div class="mw-partners__slider">
                <div class="mw-partners__track">
                    <div class="mw-partners__group">
                        <div v-for="(logo, index) in partnerLogos" :key="'a' + index" class="mw-partners__logo"><img :src="logo.src" :alt="logo.alt"></div>
                    </div>
                    <div class="mw-partners__group" aria-hidden="true">
                        <div v-for="(logo, index) in partnerLogos" :key="'b' + index" class="mw-partners__logo"><img :src="logo.src" alt=""></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-projects mw-section" id="projects">
            <div class="container-ctn">
                <div class="mw-section-head">
                    <p class="mw-eyebrow">UAE developments</p>
                    <h2 class="mw-section-title">Browse New Projects in the UAE</h2>
                </div>

                <div class="mw-projects__filters">
                    <div class="mw-pill-tabs" data-tab-group data-filter-target="#projects-grid">
                        <button v-for="f in projectFilters" :key="f.filter" type="button" :class="{ 'is-active': f.active }" data-tab :data-filter="f.filter">{{ f.label }}</button>
                    </div>
                </div>

                <div class="mw-projects__grid" id="projects-grid" data-projects-slider>
                    <article v-for="(card, index) in projectCards" :key="index" class="mw-projects__card" :data-category="card.category">
                        <div class="mw-projects__media" data-card-gallery>
                            <div class="mw-projects__slides" data-gallery-track>
                                <img v-for="(img, i) in card.images" :key="i" :src="`/frontend/assets/images/home/${img}`" :alt="i === 0 ? card.name : ''" class="mw-projects__photo">
                            </div>
                            <span class="mw-badge mw-badge--success mw-projects__verified">
                                <img src="/frontend/assets/images/icons/verified.svg" alt="" width="18" height="18">
                                Verified
                            </span>
                            <button type="button" class="mw-projects__save" aria-label="Save property" aria-pressed="false">
                                <img src="/frontend/assets/images/icons/heart.svg" alt="" width="24" height="24">
                            </button>
                            <button type="button" class="mw-projects__nav mw-projects__nav--prev" data-gallery-prev aria-label="Previous photo">
                                <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                            </button>
                            <button type="button" class="mw-projects__nav mw-projects__nav--next" data-gallery-next aria-label="Next photo">
                                <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                            </button>
                            <div class="mw-projects__dots" data-gallery-dots></div>
                            <span class="mw-projects__photo-count">
                                <img src="/frontend/assets/images/icons/photo-count.svg" alt="" width="16" height="16">
                                <span data-gallery-count>4</span>
                            </span>
                            <div class="mw-projects__contact">
                                <a href="mailto:info@mightywarnersrealty.com" class="mw-projects__contact-btn mw-projects__contact-btn--email" aria-label="Mail">
                                    <img src="/frontend/assets/images/icons/email.svg" alt="">
                                    <span>Mail</span>
                                </a>
                                <a href="tel:+971585899990" class="mw-projects__contact-btn mw-projects__contact-btn--call" aria-label="Call us">
                                    <img src="/frontend/assets/images/icons/phone.svg" alt="">
                                    <span>Call us</span>
                                </a>
                                <a href="https://wa.me/971585899990" class="mw-projects__contact-btn mw-projects__contact-btn--whatsapp" aria-label="Chat with us">
                                    <img src="/frontend/assets/images/icons/whatsapp.svg" alt="">
                                    <span>Chat with us</span>
                                </a>
                            </div>
                        </div>
                        <div class="mw-projects__body">
                            <h3 class="mw-projects__name">{{ card.name }}</h3>
                            <p class="mw-projects__location">
                                <img src="/frontend/assets/images/icons/location.svg" alt="">
                                {{ card.location }}
                            </p>
                            <div class="mw-projects__divider"></div>
                            <div class="mw-projects__stats">
                                <span class="mw-projects__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="">{{ card.beds }}</span>
                                <span class="mw-projects__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="">{{ card.baths }}</span>
                                <span class="mw-projects__stat"><img src="/frontend/assets/images/icons/area.svg" alt="">{{ card.area }}</span>
                            </div>
                            <div class="mw-projects__divider"></div>
                            <div class="mw-projects__foot-row">
                                <span class="mw-projects__type">{{ card.type }}</span>
                                <span class="mw-projects__price">{{ card.price }}</span>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="mw-projects__actions">
                    <router-link to="/properties-dubai" class="mw-btn mw-btn--solid">
                        View All Properties
                        <img src="/frontend/assets/images/icons/arrow-up-right.svg" alt="" class="mw-projects__view-all-icon">
                    </router-link>
                </div>
            </div>
        </section>

        <section class="mw-highlight mw-section" id="premium-properties">
            <div class="container-ctn">
                <div class="mw-highlight__head">
                    <p class="mw-eyebrow">Exclusive selection</p>
                    <h2 class="mw-section-title">Premium Properties</h2>
                    <div class="mw-highlight__head-row">
                        <p class="mw-highlight__text">A highlight of exclusive listings from our premium customers — featured homes selected for exceptional location, quality, and investment value.</p>
                        <a href="#" class="mw-btn mw-highlight__cta">
                            View More Details
                            <img src="/frontend/assets/images/icons/luxury-cta-arrow.svg" alt="" width="18" height="18">
                        </a>
                    </div>
                </div>

                <div class="mw-highlight__carousel">
                    <div class="mw-highlight__track" data-highlight-slider>
                        <article v-for="(card, index) in highlightCards" :key="index" class="mw-highlight__card">
                            <div class="mw-highlight__media" data-card-gallery>
                                <div class="mw-highlight__slides" data-gallery-track>
                                    <img v-for="(img, i) in card.images" :key="i" :src="`/frontend/assets/images/home/${img}`" :alt="i === 0 ? card.name : ''" class="mw-highlight__photo">
                                </div>
                                <span class="mw-highlight__badge">
                                    <img src="/frontend/assets/images/icons/star.svg" alt="" width="18" height="18">
                                    Premium
                                </span>
                                <button type="button" class="mw-highlight__save" aria-label="Save property" aria-pressed="false">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M16.696 3C14.652 3 12.887 4.197 12 5.943C11.113 4.197 9.348 3 7.304 3C4.374 3 2 5.457 2 8.481C2 11.505 3.817 14.277 6.165 16.554C8.513 18.831 12 21 12 21C12 21 15.374 18.867 17.835 16.554C20.46 14.088 22 11.514 22 8.481C22 5.448 19.626 3 16.696 3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                                <button type="button" class="mw-highlight__shot mw-highlight__shot--prev" data-gallery-prev aria-label="Previous photo">
                                    <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                                </button>
                                <button type="button" class="mw-highlight__shot mw-highlight__shot--next" data-gallery-next aria-label="Next photo">
                                    <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                                </button>
                                <div class="mw-highlight__dots" data-gallery-dots></div>
                                <span class="mw-highlight__count">
                                    <img src="/frontend/assets/images/icons/photo-count.svg" alt="" width="16" height="16">
                                    <span data-gallery-count>4</span>
                                </span>
                            </div>
                            <div class="mw-highlight__body">
                                <span class="mw-highlight__price">{{ card.price }}</span>
                                <h3 class="mw-highlight__name">{{ card.name }}</h3>
                                <p class="mw-highlight__location">
                                    <img src="/frontend/assets/images/icons/location.svg" alt="" width="18" height="18">
                                    {{ card.location }}
                                </p>
                                <div class="mw-highlight__stats">
                                    <span class="mw-highlight__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="18" height="18">{{ card.beds }}</span>
                                    <span class="mw-highlight__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="18" height="18">{{ card.baths }}</span>
                                    <span class="mw-highlight__stat"><img src="/frontend/assets/images/icons/area.svg" alt="" width="18" height="18">{{ card.area }}</span>
                                </div>
                                <div class="mw-highlight__contacts">
                                    <a href="mailto:info@mightywarnersrealty.com" aria-label="Email"><img src="/frontend/assets/images/icons/realty-email.svg" alt="" width="40" height="40"></a>
                                    <a href="tel:+971585899990" aria-label="Call"><img src="/frontend/assets/images/icons/realty-phone.svg" alt="" width="40" height="40"></a>
                                    <a href="https://wa.me/971585899990" aria-label="WhatsApp"><img src="/frontend/assets/images/icons/realty-whatsapp.svg" alt="" width="40" height="40"></a>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div class="mw-highlight__nav">
                        <button type="button" data-highlight-prev class="mw-arrow-btn mw-arrow-btn--prev mw-highlight__nav-btn" aria-label="Previous premium property">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                        <button type="button" data-highlight-next class="mw-arrow-btn mw-arrow-btn--next mw-highlight__nav-btn" aria-label="Next premium property">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-home-banner">
            <div class="container-ctn">
                <a href="#" class="mw-home-banner__card">
                    <span class="mw-home-banner__label">Advertisement</span>
                    <picture>
                        <source media="(max-width: 767px)" srcset="/frontend/assets/images/home/ad-home-banner-mobile.svg">
                        <img src="/frontend/assets/images/home/ad-home-banner.svg" alt="Home Finance — Own It Sooner. Mortgage pre-approval in 24 hours, rates from 3.49% with our partner banks. Get Pre-Approved." class="mw-home-banner__image">
                    </picture>
                </a>
            </div>
        </section>

        <section class="mw-post-property mw-section mw-section--alt" id="post-property">
            <div class="container-ctn">
                <div class="mw-post-property__inner">
                    <div class="mw-post-property__intro">
                        <p class="mw-eyebrow">List with MW Realty</p>
                        <h2 class="mw-section-title">Post your property in 3 simple steps</h2>
                        <p class="mw-post-property__text">Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book.</p>
                        <a href="#contact" class="mw-btn mw-btn--solid mw-post-property__cta">
                            Start listing
                            <img src="/frontend/assets/images/icons/arrow-up-right.svg" alt="" class="mw-post-property__cta-icon">
                        </a>
                    </div>

                    <div class="mw-post-property__media">
                        <img src="/frontend/assets/images/home/post-property.jpg" alt="Aerial view of a UAE waterfront residential community" class="mw-post-property__photo">
                        <span class="mw-post-property__media-tag">3 easy steps</span>
                    </div>

                    <ol class="mw-post-property__steps">
                        <li v-for="step in postPropertySteps" :key="step.index" class="mw-post-property__step">
                            <div class="mw-post-property__card">
                                <span class="mw-post-property__step-index" aria-hidden="true">{{ step.index }}</span>
                                <span class="mw-post-property__step-icon-wrap">
                                    <img :src="`/frontend/assets/images/icons/${step.icon}`" alt="" class="mw-post-property__step-icon">
                                </span>
                                <div class="mw-post-property__step-body">
                                    <p class="mw-post-property__step-label">Step {{ step.index }}</p>
                                    <h3 class="mw-post-property__step-title">{{ step.title }}</h3>
                                    <p class="mw-post-property__step-text">{{ step.text }}</p>
                                </div>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </section>

        <section class="mw-popular-places mw-section" id="popular-places">
            <div class="container-ctn">
                <div class="mw-popular-places__head">
                    <div class="mw-popular-places__heading">
                        <p class="mw-eyebrow">In-demand cities</p>
                        <h2 class="mw-section-title">Most Popular Properties Places</h2>
                    </div>
                    <div class="mw-popular-places__nav">
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--prev mw-popular-places__nav-btn" data-places-prev aria-label="Previous places">
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" width="18" height="18">
                        </button>
                        <button type="button" class="mw-arrow-btn mw-arrow-btn--next mw-popular-places__nav-btn" data-places-next aria-label="Next places">
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" width="18" height="18">
                        </button>
                    </div>
                </div>

                <div class="mw-popular-places__slider" data-places-slider>
                    <a v-for="(place, index) in popularPlaces" :key="index" href="#" class="mw-popular-places__card">
                        <div class="mw-popular-places__media">
                            <img :src="`/frontend/assets/images/home/${place.image}`" :alt="place.name">
                            <div class="mw-popular-places__foot">
                                <span class="mw-popular-places__name">{{ place.name }}</span>
                                <span class="mw-popular-places__arrow">
                                    <img src="/frontend/assets/images/icons/icon-arrow-right.svg" alt="">
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <section class="mw-luxury mw-section" id="luxury">
            <div class="mw-luxury__mark" aria-hidden="true">
                <img src="/frontend/assets/images/home/luxury-logo.png" alt="">
            </div>
            <div class="container-aside">
                <div class="mw-luxury__layout">
                    <div class="mw-luxury__intro">
                        <p class="mw-eyebrow">Signature collection</p>
                        <h2 class="mw-section-title">Luxury Project</h2>
                        <p class="mw-luxury__text">Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book.</p>
                        <div class="mw-luxury__actions">
                            <a href="#" class="mw-btn mw-luxury__cta">
                                View More Details
                                <img src="/frontend/assets/images/icons/luxury-cta-arrow.svg" alt="" width="18" height="18">
                            </a>
                            <div class="mw-luxury__nav">
                                <button type="button" class="mw-arrow-btn mw-arrow-btn--prev mw-luxury__nav-btn" data-luxury-prev aria-label="Previous luxury project">
                                    <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                                </button>
                                <button type="button" class="mw-arrow-btn mw-arrow-btn--next mw-luxury__nav-btn" data-luxury-next aria-label="Next luxury project">
                                    <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mw-luxury__stage">
                        <div class="mw-luxury__slider" data-luxury-slider>
                            <article v-for="(card, index) in luxuryCards" :key="index" class="mw-luxury__card">
                                <img :src="`/frontend/assets/images/home/${card.image}`" :alt="card.name" class="mw-luxury__photo" :class="{ 'mw-luxury__photo--villa': card.villa }">
                                <span class="mw-luxury__tag">Buy</span>
                                <button type="button" class="mw-luxury__save" aria-label="Save property" aria-pressed="false">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M16.696 3C14.652 3 12.887 4.197 12 5.943C11.113 4.197 9.348 3 7.304 3C4.374 3 2 5.457 2 8.481C2 11.505 3.817 14.277 6.165 16.554C8.513 18.831 12 21 12 21C12 21 15.374 18.867 17.835 16.554C20.46 14.088 22 11.514 22 8.481C22 5.448 19.626 3 16.696 3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                                <div class="mw-luxury__panel">
                                    <h3 class="mw-luxury__name">{{ card.name }}</h3>
                                    <p class="mw-luxury__location">
                                        <img src="/frontend/assets/images/icons/luxury-location.svg" alt="" width="18" height="18">
                                        {{ card.location }}
                                    </p>
                                    <div class="mw-luxury__stats">
                                        <span class="mw-luxury__stat">
                                            <img src="/frontend/assets/images/icons/luxury-home.svg" alt="" width="18" height="18">
                                            {{ card.stat1 }}
                                        </span>
                                        <span class="mw-luxury__stat">
                                            <img src="/frontend/assets/images/icons/luxury-building.svg" alt="" width="18" height="18">
                                            {{ card.stat2 }}
                                        </span>
                                        <span class="mw-luxury__stat">
                                            <img src="/frontend/assets/images/icons/luxury-area.svg" alt="" width="18" height="18">
                                            {{ card.area }}
                                        </span>
                                    </div>
                                    <div class="mw-luxury__foot">
                                        <span class="mw-luxury__price">{{ card.price }}</span>
                                        <div class="mw-luxury__contacts">
                                            <a href="mailto:info@mightywarnersrealty.com" class="mw-luxury__contact" aria-label="Mail">
                                                <img src="/frontend/assets/images/icons/luxury-email.svg" alt="" width="40" height="40">
                                            </a>
                                            <a href="tel:+971585899990" class="mw-luxury__contact" aria-label="Call">
                                                <img src="/frontend/assets/images/icons/luxury-phone.svg" alt="" width="40" height="40">
                                            </a>
                                            <a href="https://wa.me/971585899990" class="mw-luxury__contact" aria-label="WhatsApp">
                                                <img src="/frontend/assets/images/icons/luxury-whatsapp.svg" alt="" width="40" height="40">
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-why-choose-us mw-section" id="why-choose-us">
            <div class="container-ctn">
                <div class="mw-why-choose-us__inner">
                    <div class="mw-why-choose-us__visual">
                        <p class="mw-eyebrow">The MW advantage</p>
                        <h2 class="mw-section-title">Why Choose Us</h2>
                        <div class="mw-why-choose-us__media">
                            <div class="mw-why-choose-us__media-bg">
                                <img src="/frontend/assets/images/home/why-choose-us-bg.png" alt="">
                            </div>
                            <img class="mw-why-choose-us__mark" src="/frontend/assets/images/home/luxury-logo.png" alt="" aria-hidden="true">
                            <div class="mw-why-choose-us__media-photo">
                                <img src="/frontend/assets/images/home/why-choose-us.png" alt="Luxury residential tower developed by MW Realty">
                            </div>
                        </div>
                    </div>

                    <div class="mw-why-choose-us__content">
                        <div class="mw-why-choose-us__grid">
                            <div v-for="item in whyChooseUsItems" :key="item.title" class="mw-why-choose-us__item">
                                <div class="mw-why-choose-us__item-head">
                                    <img :src="`/frontend/assets/images/icons/${item.icon}`" alt="" class="mw-why-choose-us__icon">
                                    <h3 class="mw-why-choose-us__title">{{ item.title }}</h3>
                                </div>
                                <p class="mw-why-choose-us__text">Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-realty mw-section" id="realty-property">
            <div class="container-ctn">
                <div class="mw-realty__head">
                    <p class="mw-eyebrow">On-market homes</p>
                    <h2 class="mw-section-title">Realty Property</h2>
                    <div class="mw-realty__head-row">
                        <p class="mw-realty__text">Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book.</p>
                        <a href="#" class="mw-btn mw-realty__cta">
                            View More Details
                            <img src="/frontend/assets/images/icons/luxury-cta-arrow.svg" alt="" width="18" height="18">
                        </a>
                    </div>
                </div>

                <div class="mw-realty__carousel">
                    <div class="mw-realty__track" data-realty-slider>
                        <article v-for="(card, index) in realtyCards" :key="index" class="mw-realty__card">
                            <div class="mw-realty__media">
                                <img :src="`/frontend/assets/images/home/${card.image}`" alt="Modern Penthouse in Downtown Dubai" class="mw-realty__photo">
                                <span class="mw-badge mw-badge--success mw-realty__verified">
                                    <img src="/frontend/assets/images/icons/verified.svg" alt="" width="18" height="18">
                                    Verified
                                </span>
                                <button type="button" class="mw-realty__save" aria-label="Save property" aria-pressed="false">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M16.696 3C14.652 3 12.887 4.197 12 5.943C11.113 4.197 9.348 3 7.304 3C4.374 3 2 5.457 2 8.481C2 11.505 3.817 14.277 6.165 16.554C8.513 18.831 12 21 12 21C12 21 15.374 18.867 17.835 16.554C20.46 14.088 22 11.514 22 8.481C22 5.448 19.626 3 16.696 3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="mw-realty__body">
                                <h3 class="mw-realty__name">Modern Penthouse in Downtown....</h3>
                                <p class="mw-realty__location">
                                    <img src="/frontend/assets/images/icons/location.svg" alt="" width="18" height="18">
                                    Downtown Dubai
                                </p>
                                <div class="mw-realty__divider"></div>
                                <div class="mw-realty__stats">
                                    <span class="mw-realty__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="18" height="18">2 Bed</span>
                                    <span class="mw-realty__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="18" height="18">2 Bath</span>
                                    <span class="mw-realty__stat"><img src="/frontend/assets/images/icons/area.svg" alt="" width="18" height="18">4200 sq.ft</span>
                                </div>
                                <div class="mw-realty__divider"></div>
                                <div class="mw-realty__foot">
                                    <span class="mw-realty__price">AED 9,500,000</span>
                                    <div class="mw-realty__actions">
                                        <a href="mailto:info@mightywarnersrealty.com" aria-label="Email"><img src="/frontend/assets/images/icons/realty-email.svg" alt="" width="40" height="40"></a>
                                        <a href="tel:+971585899990" aria-label="Call"><img src="/frontend/assets/images/icons/realty-phone.svg" alt="" width="40" height="40"></a>
                                        <a href="https://wa.me/971585899990" aria-label="WhatsApp"><img src="/frontend/assets/images/icons/realty-whatsapp.svg" alt="" width="40" height="40"></a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div class="mw-realty__nav">
                        <button type="button" data-realty-prev class="mw-arrow-btn mw-arrow-btn--prev mw-realty__nav-btn" aria-label="Previous realty property">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                        <button type="button" data-realty-next class="mw-arrow-btn mw-arrow-btn--next mw-realty__nav-btn" aria-label="Next realty property">
                            <img src="/frontend/assets/images/icons/luxury-nav-arrow.svg" alt="" width="24" height="24">
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-communities mw-section" id="communities">
            <div class="container-ctn">
                <div class="mw-section-head">
                    <p class="mw-eyebrow">Neighbourhood living</p>
                    <h2 class="mw-section-title">Popular Properties in Dubai Communities</h2>
                </div>

                <div class="mw-communities__tabs">
                    <div class="mw-pill-tabs" data-tab-group data-filter-target="#communities-panels">
                        <button v-for="f in communityFilters" :key="f.filter" type="button" :class="{ 'is-active': f.active }" data-tab :data-filter="f.filter">{{ f.label }}</button>
                    </div>
                </div>

                <div id="communities-panels">
                    <div class="mw-communities__grid" data-category="for-sale">
                        <div v-for="(col, ci) in communitiesColumns" :key="ci" class="mw-communities__col">
                            <a v-for="(row, ri) in col" :key="ri" href="#" class="mw-communities__row" :class="{ 'mw-communities__row--last': ri === col.length - 1 }">
                                <img class="mw-communities__icon" :src="`/frontend/assets/images/icons/${row.icon}`" alt="" width="24" height="24">
                                <span class="mw-communities__name">{{ row.name }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-find-properties mw-section mw-section--alt" id="find-properties">
            <div class="container-ctn">
                <div class="mw-section-head">
                    <p class="mw-eyebrow">Browse by type</p>
                    <h2 class="mw-section-title">Find Properties</h2>
                </div>

                <div class="mw-find-properties__grid">
                    <a v-for="card in findPropertiesCards" :key="card.modifier" href="#" class="mw-find-properties__card" :class="`mw-find-properties__card--${card.modifier}`">
                        <img :src="`/frontend/assets/images/home/${card.image}`" :alt="`${card.type} properties`">
                        <span class="mw-find-properties__label">
                            <span class="mw-find-properties__label-type">{{ card.type }}</span>
                            <span class="mw-find-properties__label-count">{{ card.count }}</span>
                        </span>
                    </a>

                    <div class="mw-find-properties__card mw-find-properties__card--tall">
                        <img src="/frontend/assets/images/home/find-tall.jpg" alt="All properties">
                        <img class="mw-find-properties__mark" src="/frontend/assets/images/home/find-properties-mark.svg" alt="" aria-hidden="true">
                        <router-link to="/properties-dubai" class="mw-find-properties__cta">
                            View All Properties
                            <img src="/frontend/assets/images/icons/find-cta-arrow.svg" alt="" width="18" height="18">
                        </router-link>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-testimonials mw-section" id="testimonials">
            <div class="container-ctn">
                <div class="mw-testimonials__header">
                    <div class="mw-section-head">
                        <p class="mw-eyebrow">From our clients</p>
                        <h2 class="mw-section-title">Why Our Clients Trust Us</h2>
                        <p class="mw-section-text">Discover what our customers are saying about their experiences.</p>
                    </div>
                    <div class="mw-testimonials__arrows">
                        <button type="button" data-testimonials-prev class="mw-testimonials__arrow mw-testimonials__arrow--prev" aria-label="Previous testimonial">
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" width="24" height="24">
                        </button>
                        <button type="button" data-testimonials-next class="mw-testimonials__arrow mw-testimonials__arrow--next" aria-label="Next testimonial">
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" width="24" height="24">
                        </button>
                    </div>
                </div>

                <div class="mw-testimonials__slider" data-testimonials-slider>
                    <template v-for="(item, index) in testimonials" :key="index">
                        <article v-if="item.type === 'video'" class="mw-testimonials__card mw-testimonials__card--video" :class="`mw-testimonials__card--${item.modifier}`">
                            <div class="mw-testimonials__media">
                                <img class="mw-testimonials__photo" :src="`/frontend/assets/images/home/${item.image}`" :alt="item.name">
                                <video class="mw-testimonials__video" :poster="`/frontend/assets/images/home/${item.image}`" preload="metadata" playsinline>
                                    <source src="/frontend/video/testimonial.webm" type="video/webm">
                                </video>
                                <button type="button" class="mw-testimonials__play" :aria-label="`Play ${item.name}'s video testimonial`">
                                    <img class="mw-testimonials__icon-play" src="/frontend/assets/images/icons/play.svg" alt="" width="24" height="24">
                                    <img class="mw-testimonials__icon-pause" src="/frontend/assets/images/icons/pause.svg" alt="" width="24" height="24">
                                </button>
                            </div>
                            <div class="mw-testimonials__info">
                                <p class="mw-testimonials__name">{{ item.name }}</p>
                                <p class="mw-testimonials__role">{{ item.role }}</p>
                            </div>
                        </article>

                        <article v-else class="mw-testimonials__card mw-testimonials__card--quote">
                            <div class="mw-testimonials__body">
                                <div class="mw-testimonials__stars" aria-label="5 star rating">
                                    <img src="/frontend/assets/images/icons/star.svg" alt="" width="24" height="24">
                                    <img src="/frontend/assets/images/icons/star.svg" alt="" width="24" height="24">
                                    <img src="/frontend/assets/images/icons/star.svg" alt="" width="24" height="24">
                                    <img src="/frontend/assets/images/icons/star.svg" alt="" width="24" height="24">
                                    <img src="/frontend/assets/images/icons/star.svg" alt="" width="24" height="24">
                                </div>
                                <img class="mw-testimonials__quote-icon" src="/frontend/assets/images/icons/quote-mark.svg" alt="" width="48" height="48">
                                <p class="mw-testimonials__quote-text">{{ testimonialQuoteText }}</p>
                                <div class="mw-testimonials__author">
                                    <img class="mw-testimonials__avatar" src="/frontend/assets/images/home/testimonial-jenifer-avatar.jpg" alt="">
                                    <div class="mw-testimonials__author-info">
                                        <p class="mw-testimonials__name mw-testimonials__name--light">{{ item.name }}</p>
                                        <p class="mw-testimonials__role mw-testimonials__role--light">{{ item.role }}</p>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>

                <div class="mw-testimonials__nav">
                    <div data-testimonials-dots class="mw-testimonials__dots"></div>
                </div>
            </div>
        </section>

        <section class="mw-contact" id="contact">
            <div class="mw-contact__inner">
                <div class="mw-contact__visual">
                    <div class="mw-contact__photo">
                        <img src="/frontend/assets/images/home/contact-photo.jpg" alt="Modern living room managed by MW Realty">
                    </div>

                    <div class="mw-contact__form-card">
                        <form class="mw-contact__form" novalidate @submit.prevent>
                            <div class="mw-contact__field">
                                <label for="contact-name">Name*</label>
                                <input type="text" id="contact-name" name="name" placeholder="Your name" required>
                            </div>

                            <div class="mw-contact__field">
                                <label for="contact-email">Email*</label>
                                <input type="email" id="contact-email" name="email" placeholder="Your email" required>
                            </div>

                            <div class="mw-contact__field">
                                <label for="contact-phone">phone*</label>
                                <input type="tel" id="contact-phone" name="phone" placeholder="Your phone" required>
                            </div>

                            <div class="mw-contact__field mw-contact__field--select">
                                <label for="contact-interest">what are you interested in?</label>
                                <div class="mw-contact__select-wrap">
                                    <select id="contact-interest" name="interest" required>
                                        <option value="" selected disabled hidden>Select</option>
                                        <option value="buy">Buy a property</option>
                                        <option value="rent">Rent a property</option>
                                        <option value="sell">Sell / list a property</option>
                                        <option value="management">Property management</option>
                                    </select>
                                    <img class="mw-contact__select-chevron" src="/frontend/assets/images/icons/chevron-down.svg" alt="" width="24" height="24">
                                </div>
                            </div>

                            <div class="mw-contact__field">
                                <label for="contact-message">Message</label>
                                <textarea id="contact-message" name="message" rows="5" placeholder="How can we help you?"></textarea>
                            </div>

                            <button type="submit" class="mw-contact__submit">Contact Our Expert</button>
                        </form>
                    </div>
                </div>

                <div class="mw-contact__info">
                    <p class="mw-eyebrow">We're here to help</p>
                    <h2 class="mw-contact__heading">Connect with the experts who make property management simple.</h2>
                    <p class="mw-contact__text">Experience hassle-free property management, expertly handled by professionals who understand your unique requirements.</p>

                    <div class="mw-contact__rows">
                        <div class="mw-contact__row">
                            <span class="mw-contact__icon"><img src="/frontend/assets/images/icons/location.svg" alt="" width="24" height="24"></span>
                            <div class="mw-contact__row-body">
                                <p class="mw-contact__row-label">Office Address</p>
                                <p class="mw-contact__row-value">Office No: 703 - Churchill Tower Business Bay - Dubai, UAE</p>
                            </div>
                        </div>

                        <div class="mw-contact__row">
                            <span class="mw-contact__icon"><img src="/frontend/assets/images/icons/phone.svg" alt="" width="24" height="24"></span>
                            <div class="mw-contact__row-body">
                                <p class="mw-contact__row-label">Toll Free</p>
                                <a class="mw-contact__row-value mw-contact__row-value--link mw-contact__row-value--lg" href="tel:800644489">800644489</a>
                            </div>
                        </div>

                        <div class="mw-contact__row">
                            <span class="mw-contact__icon"><img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="24" height="24"></span>
                            <div class="mw-contact__row-body">
                                <p class="mw-contact__row-label">Request A Call Back</p>
                                <a class="mw-contact__row-value mw-contact__row-value--link mw-contact__row-value--lg" href="https://wa.me/971585899990" target="_blank" rel="noopener">+971 58 589 9990</a>
                            </div>
                        </div>

                        <div class="mw-contact__row">
                            <span class="mw-contact__icon"><img src="/frontend/assets/images/icons/email.svg" alt="" width="24" height="24"></span>
                            <div class="mw-contact__row-body">
                                <p class="mw-contact__row-label">Email Us</p>
                                <a class="mw-contact__row-value mw-contact__row-value--link" href="mailto:info@mightywarnersrealty.com">info@mightywarnersrealty.com</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
