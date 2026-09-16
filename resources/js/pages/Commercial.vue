<script setup>
const properties = [
    {
        title: 'Luxury Hotel Apartment in Business Bay',
        images: [
            '/frontend/assets/images/home/project-card-1.jpg',
            '/frontend/assets/images/home/project-card-2.jpg',
            '/frontend/assets/images/home/luxury-card-1.jpg',
        ],
        verified: true,
        cta: 'Buy Now',
        ctaVariant: null,
        type: 'Hotel Apartment',
        service: '+20,000 service',
        price: 'AED 1,850,000',
        location: 'Business Bay, Dubai',
        desc: 'Spacious 1-bedroom hotel apartment with full rental',
        floor: 12,
        rera: 'DUB987654',
        beds: 1,
        baths: 1,
        area: '950 sq.ft',
        amenities: ['Furnished', 'Hotel Service', 'House Keeping', 'Room Services'],
        extraMore: null,
    },
    {
        title: 'Loft Apartment in Al Quoz',
        images: [
            '/frontend/assets/images/home/project-card-3.jpg',
            '/frontend/assets/images/home/realty-card-1.jpg',
            '/frontend/assets/images/home/luxury-card-2.jpg',
        ],
        verified: false,
        cta: 'Buy Now',
        ctaVariant: null,
        type: 'Loft',
        service: '+10,000 service',
        price: 'AED 1,200,000',
        location: 'Al Quoz, Dubai',
        desc: 'Industrial-style loft with high ceilings',
        floor: 4,
        rera: 'DUB765432',
        beds: 2,
        baths: 2,
        area: '1800 sq.ft',
        amenities: ['Unfurnished', 'Open Plan', 'Exposed Bricks', 'Artist Studio'],
        extraMore: null,
    },
    {
        title: 'Waterfront Villa in Emirates Hills',
        images: [
            '/frontend/assets/images/home/project-card-4.jpg',
            '/frontend/assets/images/home/realty-card-2.jpg',
            '/frontend/assets/images/home/luxury-card-3.jpg',
            '/frontend/assets/images/home/project-card-5.jpg',
        ],
        verified: true,
        cta: 'Buy Now',
        ctaVariant: null,
        type: 'Villa',
        service: '+60,000 service',
        price: 'AED 1,200,000',
        location: 'Emirates Hills, Dubai',
        desc: 'Exclusive 6-bedroom mansion with lake views',
        floor: 3,
        rera: 'DUB876543',
        beds: 6,
        baths: 8,
        area: '8500 sq.ft',
        amenities: ['Furnished', 'Private Cinema', 'Wine Cellar', 'Home Spa'],
        extraMore: 1,
    },
    {
        title: 'Affordable Apartment in Discovery Gardens',
        images: [
            '/frontend/assets/images/home/project-card-6.jpg',
            '/frontend/assets/images/home/realty-card-3.jpg',
            '/frontend/assets/images/home/luxury-card-1.jpg',
        ],
        verified: false,
        cta: 'Buy Now',
        ctaVariant: null,
        type: 'Apartment',
        service: '+7,000 service',
        price: 'AED 550,000',
        location: 'Discovery Gardens, Dubai',
        desc: '1-bedroom apartment perfect for first-time buyers',
        floor: 5,
        rera: 'DUB234567',
        beds: 1,
        baths: 1,
        area: '800 sq.ft',
        amenities: ['Semifurnished', 'Community Pool', 'Parking', '24/7 Security'],
        extraMore: null,
    },
    {
        title: 'Penthouse in Jumeirah Beach Residence',
        images: [
            '/frontend/assets/images/home/luxury-card-2.jpg',
            '/frontend/assets/images/home/project-card-1.jpg',
            '/frontend/assets/images/home/realty-card-4.jpg',
        ],
        verified: true,
        cta: 'Buy Now',
        ctaVariant: null,
        type: 'Penthouse',
        service: '+35,000 service',
        price: 'AED 9,500,000',
        location: 'JBR, Dubai',
        desc: 'Luxury 4-bedroom penthouse with panoramic sea views',
        floor: 42,
        rera: 'DUB567890',
        beds: 4,
        baths: 5,
        area: '3800 sq.ft',
        amenities: ['Furnished', 'Private Pool', 'Jacuzzi', 'Smart Home'],
        extraMore: 1,
    },
    {
        title: 'Townhouse in Arabian Ranches',
        images: [
            '/frontend/assets/images/home/project-card-2.jpg',
            '/frontend/assets/images/home/project-card-5.jpg',
            '/frontend/assets/images/home/realty-card-1.jpg',
        ],
        verified: true,
        cta: 'Buy Now',
        ctaVariant: null,
        type: 'Town House',
        service: '+15,000 service',
        price: 'AED 2,800,000',
        location: 'Arabian Ranches, Dubai',
        desc: 'Family-friendly 3-bedroom townhouse in gated community',
        floor: 1,
        rera: 'DUB901234',
        beds: 3,
        baths: 5,
        area: '2200 sq.ft',
        amenities: ['Furnished', 'Community Pool', 'Tennis Court', 'Kids Play Area'],
        extraMore: null,
    },
    {
        title: 'Updated Commercial Space for Lease',
        images: [
            '/frontend/assets/images/home/realty-card-2.jpg',
            '/frontend/assets/images/home/project-card-3.jpg',
            '/frontend/assets/images/home/luxury-card-3.jpg',
        ],
        verified: true,
        cta: 'Sell',
        ctaVariant: 'sell',
        type: 'Commercial',
        service: '+2,500 service',
        price: 'AED 500,000',
        location: 'Business Bay, Dubai',
        desc: 'Prime commercial space available in business district',
        floor: 10,
        rera: 'RERA-COMM-7890',
        beds: null,
        baths: null,
        area: '2500 sq.ft',
        amenities: ['Furnished', 'High-Speed Internet', 'Conference Rooms', 'Reception'],
        extraMore: null,
    },
    {
        title: 'Studio Apartment in Dubai Marina',
        images: [
            '/frontend/assets/images/home/project-card-5.jpg',
            '/frontend/assets/images/home/realty-card-3.jpg',
            '/frontend/assets/images/home/luxury-card-1.jpg',
        ],
        verified: false,
        cta: 'Buy Now',
        ctaVariant: null,
        type: 'Apartment',
        service: '+8,000 service',
        price: 'AED 750,000',
        location: 'Dubai Marina, Dubai',
        desc: 'Modern studio with marina views, ideal for investment',
        floor: 15,
        rera: 'DUB789012',
        beds: null,
        baths: 1,
        area: '550 sq.ft',
        amenities: ['Unfurnished', 'Balcony', 'Gym', 'Concierge'],
        extraMore: 1,
    },
];
</script>

<template>
    <div class="offcanvas offcanvas-end mw-filter-panel" tabindex="-1" id="commercial-filter-panel" aria-labelledby="commercialFilterLabel">
        <div class="offcanvas-header mw-filter-panel__top">
            <h2 class="mw-filter-panel__title" id="commercialFilterLabel">Filters</h2>
            <button type="button" class="mw-filter-panel__close" data-bs-dismiss="offcanvas" aria-label="Close filters">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="offcanvas-body mw-filter-panel__body">

            <section class="mw-filter-panel__section mw-filter-panel__section--basics">
                <label class="mw-filter-panel__field">
                    <span>Location</span>
                    <input type="text" id="commercial-location-mobile" name="location" placeholder="Enter location" autocomplete="off">
                </label>
                <label class="mw-filter-panel__field">
                    <span>Properties</span>
                    <input type="text" id="commercial-properties-mobile" name="properties" placeholder="Search by title, location" autocomplete="off">
                </label>
                <div class="mw-filter-panel__field mw-filter-panel__field--select mw-dropdown" data-dropdown>
                    <span>Property Type</span>
                    <button type="button" class="mw-commercial-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                        <span data-dropdown-label>All type</span>
                        <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-commercial-filter__chevron">
                    </button>
                    <ul class="mw-dropdown__menu" data-dropdown-menu>
                        <li><button type="button" class="is-selected" data-dropdown-option>All type</button></li>
                        <li><button type="button" data-dropdown-option>Hotel Apartment</button></li>
                        <li><button type="button" data-dropdown-option>Loft</button></li>
                        <li><button type="button" data-dropdown-option>Villa</button></li>
                        <li><button type="button" data-dropdown-option>Apartment</button></li>
                        <li><button type="button" data-dropdown-option>Penthouse</button></li>
                        <li><button type="button" data-dropdown-option>Town House</button></li>
                        <li><button type="button" data-dropdown-option>Commercial</button></li>
                    </ul>
                </div>
                <div class="mw-filter-panel__field mw-filter-panel__field--select mw-dropdown" data-dropdown>
                    <span>Category</span>
                    <button type="button" class="mw-commercial-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                        <span data-dropdown-label>All categories</span>
                        <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-commercial-filter__chevron">
                    </button>
                    <ul class="mw-dropdown__menu" data-dropdown-menu>
                        <li><button type="button" class="is-selected" data-dropdown-option>All categories</button></li>
                        <li><button type="button" data-dropdown-option>Buy</button></li>
                        <li><button type="button" data-dropdown-option>Lease</button></li>
                        <li><button type="button" data-dropdown-option>Off-Plan</button></li>
                    </ul>
                </div>
            </section>

            <section class="mw-filter-panel__section" data-dropdown>
                <h3 class="mw-filter-panel__section-title">Price Range</h3>
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
                    <button type="button" data-price-preset data-min="0" data-max="500000">Under 500K</button>
                    <button type="button" data-price-preset data-min="500000" data-max="1000000">500K – 1M</button>
                    <button type="button" data-price-preset data-min="1000000" data-max="2000000">1M – 2M</button>
                    <button type="button" data-price-preset data-min="2000000" data-max="5000000">2M – 5M</button>
                    <button type="button" data-price-preset data-min="5000000" data-max="10000000">5M – 10M</button>
                    <button type="button" data-price-preset data-min="10000000" data-max="30000000">10M+</button>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">Bedrooms</h3>
                <div class="mw-pill-tabs" data-tab-group>
                    <button type="button" class="is-active" data-tab>Any</button>
                    <button type="button" data-tab>1</button>
                    <button type="button" data-tab>2</button>
                    <button type="button" data-tab>3</button>
                    <button type="button" data-tab>4</button>
                    <button type="button" data-tab>5+</button>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">Bathrooms</h3>
                <div class="mw-pill-tabs" data-tab-group>
                    <button type="button" class="is-active" data-tab>Any</button>
                    <button type="button" data-tab>1</button>
                    <button type="button" data-tab>2</button>
                    <button type="button" data-tab>3</button>
                    <button type="button" data-tab>4</button>
                    <button type="button" data-tab>5+</button>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">Area (sq.ft)</h3>
                <div class="mw-filter-panel__range-inputs">
                    <label>
                        <span>Min</span>
                        <input type="number" inputmode="numeric" placeholder="0" aria-label="Minimum area">
                    </label>
                    <label>
                        <span>Max</span>
                        <input type="number" inputmode="numeric" placeholder="10,000" aria-label="Maximum area">
                    </label>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">Amenities</h3>
                <div class="mw-filter-panel__checks">
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="furnished"><span>Furnished</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="community-pool"><span>Community Pool</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="parking"><span>Parking</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="gym"><span>Gym</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="security"><span>24/7 Security</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="balcony"><span>Balcony</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="concierge"><span>Concierge</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="private-pool"><span>Private Pool</span></label>
                </div>
            </section>

        </div>
        <div class="mw-filter-panel__foot">
            <button type="button" class="mw-filter-panel__reset">Reset</button>
            <button type="button" class="mw-filter-panel__apply" data-bs-dismiss="offcanvas">Apply Filters</button>
        </div>
    </div>

    <main>
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>Commercial</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><span>Commercial</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-commercial-filter">
            <div class="container-ctn">
                <form class="mw-commercial-filter__bar" role="search" @submit.prevent>
                    <div class="mw-commercial-filter__field">
                        <label for="commercial-location">Location</label>
                        <input type="text" id="commercial-location" name="location" placeholder="Enter location" autocomplete="off">
                    </div>
                    <div class="mw-commercial-filter__field">
                        <label for="commercial-properties">Properties</label>
                        <input type="text" id="commercial-properties" name="properties" placeholder="Search by title, location" autocomplete="off">
                    </div>
                    <div class="mw-commercial-filter__field mw-commercial-filter__field--select mw-dropdown" data-dropdown>
                        <label>Property Type</label>
                        <button type="button" class="mw-commercial-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>All type</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-commercial-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option>All type</button></li>
                            <li><button type="button" data-dropdown-option>Hotel Apartment</button></li>
                            <li><button type="button" data-dropdown-option>Loft</button></li>
                            <li><button type="button" data-dropdown-option>Villa</button></li>
                            <li><button type="button" data-dropdown-option>Apartment</button></li>
                            <li><button type="button" data-dropdown-option>Penthouse</button></li>
                            <li><button type="button" data-dropdown-option>Town House</button></li>
                            <li><button type="button" data-dropdown-option>Commercial</button></li>
                        </ul>
                    </div>
                    <div class="mw-commercial-filter__field mw-commercial-filter__field--select mw-commercial-filter__field--last mw-dropdown" data-dropdown>
                        <label>Category</label>
                        <button type="button" class="mw-commercial-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>All categories</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-commercial-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option>All categories</button></li>
                            <li><button type="button" data-dropdown-option>Buy</button></li>
                            <li><button type="button" data-dropdown-option>Lease</button></li>
                            <li><button type="button" data-dropdown-option>Off-Plan</button></li>
                        </ul>
                    </div>
                    <button type="button" class="mw-commercial-filter__filter-btn" aria-label="More filters" data-bs-toggle="offcanvas" data-bs-target="#commercial-filter-panel" aria-controls="commercial-filter-panel">
                        <img src="/frontend/assets/images/icons/filter.svg" alt="" width="24" height="24">
                    </button>
                    <button type="submit" class="mw-commercial-filter__search">
                        <img src="/frontend/assets/images/icons/search.svg" alt="" width="18" height="18">
                        Search
                    </button>
                </form>
            </div>
        </section>

        <section class="mw-commercial">
            <div class="container-ctn">
                <div class="mw-commercial__grid">

                    <article v-for="property in properties" :key="property.title" class="mw-commercial-card" data-reveal>
                        <div class="mw-projects__media" data-card-gallery>
                            <div class="mw-projects__slides" data-gallery-track>
                                <img v-for="(image, index) in property.images" :key="image" :src="image" :alt="index === 0 ? property.title : ''" class="mw-projects__photo">
                            </div>
                            <div class="mw-commercial-card__badges">
                                <span v-if="property.verified" class="mw-badge mw-badge--success mw-commercial-card__verified">
                                    <img src="/frontend/assets/images/icons/verified.svg" alt="" width="16" height="16">
                                    MW Verified
                                </span>
                                <span class="mw-commercial-card__cta-pill" :class="{ 'mw-commercial-card__cta-pill--sell': property.ctaVariant === 'sell' }">{{ property.cta }}</span>
                            </div>
                            <button type="button" class="mw-projects__nav mw-projects__nav--prev" data-gallery-prev aria-label="Previous photo">
                                <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                            </button>
                            <button type="button" class="mw-projects__nav mw-projects__nav--next" data-gallery-next aria-label="Next photo">
                                <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                            </button>
                            <div class="mw-projects__dots" data-gallery-dots></div>
                            <span class="mw-projects__photo-count">
                                <img src="/frontend/assets/images/icons/photo-count.svg" alt="" width="16" height="16">
                                <span data-gallery-count>{{ property.images.length }}</span>
                            </span>
                            <span class="mw-commercial-card__type">{{ property.type }}</span>
                        </div>
                        <div class="mw-commercial-card__body">
                            <div class="mw-commercial-card__price-row">
                                <span class="mw-commercial-card__service">{{ property.service }}</span>
                                <span class="mw-commercial-card__price">{{ property.price }}</span>
                            </div>
                            <h3 class="mw-commercial-card__title">{{ property.title }}</h3>
                            <p class="mw-commercial-card__location">
                                <img src="/frontend/assets/images/icons/location.svg" alt="" width="16" height="16">
                                {{ property.location }}
                            </p>
                            <p class="mw-commercial-card__desc">{{ property.desc }}</p>
                            <div class="mw-commercial-card__meta-row">
                                <span>Floor: {{ property.floor }}</span>
                                <span>RERA: {{ property.rera }}</span>
                            </div>
                            <div class="mw-commercial-card__stats">
                                <span v-if="property.beds" class="mw-commercial-card__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="16" height="16">{{ property.beds }}</span>
                                <span v-if="property.baths" class="mw-commercial-card__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="16" height="16">{{ property.baths }}</span>
                                <span class="mw-commercial-card__stat"><img src="/frontend/assets/images/icons/area.svg" alt="" width="16" height="16">{{ property.area }}</span>
                            </div>
                            <div class="mw-commercial-card__amenities">
                                <span v-for="amenity in property.amenities" :key="amenity" class="mw-commercial-card__amenity">{{ amenity }}</span>
                                <span v-if="property.extraMore" class="mw-commercial-card__amenity mw-commercial-card__amenity--more">+{{ property.extraMore }} more</span>
                            </div>
                            <div class="mw-commercial-card__divider"></div>
                            <div class="mw-commercial-card__contacts">
                                <a href="tel:+971585899990" class="mw-commercial-card__contact-btn">
                                    <img src="/frontend/assets/images/icons/phone.svg" alt="" width="16" height="16">
                                    Call Us
                                </a>
                                <a href="https://wa.me/971585899990" class="mw-commercial-card__contact-btn">
                                    <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="16" height="16">
                                    WhatsApp
                                </a>
                                <a href="mailto:info@mightywarnersrealty.com" class="mw-commercial-card__contact-btn">
                                    <img src="/frontend/assets/images/icons/email.svg" alt="" width="16" height="16">
                                    Email
                                </a>
                            </div>
                        </div>
                    </article>

                </div>
            </div>
        </section>
    </main>
</template>
