<script setup>
const properties = [
    {
        title: 'Elegant Apartment in Mirdif',
        images: [
            '/frontend/assets/images/home/project-card-1.jpg',
            '/frontend/assets/images/home/project-card-2.jpg',
            '/frontend/assets/images/home/luxury-card-1.jpg',
        ],
        purpose: 'Buy | Off Plan',
        type: 'Apartment',
        price: 'AED 2,292,913',
        amenity: 'Furnished',
        location: 'Mirdif, Dubai',
        beds: 4,
        baths: 3,
        area: '5,952 sq.ft',
    },
    {
        title: 'Spacious Villa in Al Barsha',
        images: [
            '/frontend/assets/images/home/project-card-3.jpg',
            '/frontend/assets/images/home/realty-card-1.jpg',
            '/frontend/assets/images/home/luxury-card-2.jpg',
        ],
        purpose: 'Buy',
        type: 'Villa',
        price: 'AED 4,850,000',
        amenity: 'Semi-furnished',
        location: 'Al Barsha, Dubai',
        beds: 5,
        baths: 6,
        area: '6,200 sq.ft',
    },
    {
        title: 'Modern Townhouse in Arabian Ranches',
        images: [
            '/frontend/assets/images/home/project-card-4.jpg',
            '/frontend/assets/images/home/realty-card-2.jpg',
            '/frontend/assets/images/home/luxury-card-3.jpg',
        ],
        purpose: 'Buy | Off Plan',
        type: 'Townhouse',
        price: 'AED 3,117,103',
        amenity: 'Furnished',
        location: 'Arabian Ranches, Dubai',
        beds: 4,
        baths: 4,
        area: '2,295 sq.ft',
    },
    {
        title: 'Sea View Penthouse in Dubai Marina',
        images: [
            '/frontend/assets/images/home/project-card-5.jpg',
            '/frontend/assets/images/home/realty-card-3.jpg',
            '/frontend/assets/images/home/luxury-card-1.jpg',
        ],
        purpose: 'Buy',
        type: 'Penthouse',
        price: 'AED 9,750,000',
        amenity: 'Furnished',
        location: 'Dubai Marina, Dubai',
        beds: 4,
        baths: 5,
        area: '3,800 sq.ft',
    },
    {
        title: 'Cozy Studio in Discovery Gardens',
        images: [
            '/frontend/assets/images/home/project-card-6.jpg',
            '/frontend/assets/images/home/realty-card-4.jpg',
            '/frontend/assets/images/home/luxury-card-2.jpg',
        ],
        purpose: 'Buy',
        type: 'Studio',
        price: 'AED 620,000',
        amenity: 'Unfurnished',
        location: 'Discovery Gardens, Dubai',
        beds: null,
        baths: 1,
        area: '520 sq.ft',
    },
    {
        title: 'Contemporary Duplex in Jumeirah Village Circle',
        images: [
            '/frontend/assets/images/home/luxury-card-3.jpg',
            '/frontend/assets/images/home/project-card-1.jpg',
            '/frontend/assets/images/home/realty-card-1.jpg',
        ],
        purpose: 'Buy | Off Plan',
        type: 'Duplex',
        price: 'AED 2,850,000',
        amenity: 'Furnished',
        location: 'JVC, Dubai',
        beds: 3,
        baths: 3,
        area: '2,050 sq.ft',
    },
    {
        title: 'Waterfront Villa in Emirates Hills',
        images: [
            '/frontend/assets/images/home/realty-card-2.jpg',
            '/frontend/assets/images/home/project-card-3.jpg',
            '/frontend/assets/images/home/luxury-card-1.jpg',
        ],
        purpose: 'Buy',
        type: 'Villa',
        price: 'AED 11,200,000',
        amenity: 'Furnished',
        location: 'Emirates Hills, Dubai',
        beds: 6,
        baths: 8,
        area: '8,500 sq.ft',
    },
    {
        title: 'Family Townhouse in Dubai Hills Estate',
        images: [
            '/frontend/assets/images/home/realty-card-3.jpg',
            '/frontend/assets/images/home/project-card-5.jpg',
            '/frontend/assets/images/home/luxury-card-3.jpg',
        ],
        purpose: 'Buy | Off Plan',
        type: 'Townhouse',
        price: 'AED 3,650,000',
        amenity: 'Furnished',
        location: 'Dubai Hills Estate, Dubai',
        beds: 4,
        baths: 5,
        area: '3,120 sq.ft',
    },
];

const bedroomLinks = [1, 2, 3, 4, 5, 6];
</script>

<template>
    <div class="offcanvas offcanvas-end mw-filter-panel" tabindex="-1" id="dubai-filter-panel" aria-labelledby="dubaiFilterLabel">
        <div class="offcanvas-header mw-filter-panel__top">
            <h2 class="mw-filter-panel__title" id="dubaiFilterLabel">Filters</h2>
            <button type="button" class="mw-filter-panel__close" data-bs-dismiss="offcanvas" aria-label="Close filters">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="offcanvas-body mw-filter-panel__body">

            <section class="mw-filter-panel__section mw-filter-panel__section--basics">
                <div class="mw-filter-panel__field mw-filter-panel__field--select mw-dropdown" data-dropdown>
                    <span>Purpose</span>
                    <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                        <span data-dropdown-label>Buy</span>
                        <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                    </button>
                    <ul class="mw-dropdown__menu" data-dropdown-menu>
                        <li><button type="button" class="is-selected" data-dropdown-option>Buy</button></li>
                        <li><button type="button" data-dropdown-option>Rent</button></li>
                        <li><button type="button" data-dropdown-option>Off-Plan</button></li>
                    </ul>
                </div>
                <label class="mw-filter-panel__field">
                    <span>Location</span>
                    <input type="text" id="dubai-location-mobile" name="location" placeholder="Enter location" autocomplete="off" value="Dubai">
                </label>
                <div class="mw-filter-panel__field mw-filter-panel__field--select mw-dropdown" data-dropdown>
                    <span>Property Type</span>
                    <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                        <span data-dropdown-label>All type</span>
                        <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                    </button>
                    <ul class="mw-dropdown__menu" data-dropdown-menu>
                        <li><button type="button" class="is-selected" data-dropdown-option>All type</button></li>
                        <li><button type="button" data-dropdown-option>Apartment</button></li>
                        <li><button type="button" data-dropdown-option>Villa</button></li>
                        <li><button type="button" data-dropdown-option>Townhouse</button></li>
                        <li><button type="button" data-dropdown-option>Penthouse</button></li>
                        <li><button type="button" data-dropdown-option>Duplex</button></li>
                        <li><button type="button" data-dropdown-option>Studio</button></li>
                    </ul>
                </div>
            </section>

            <section class="mw-filter-panel__section">
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
                    <button type="button" data-tab>Studio</button>
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
                <h3 class="mw-filter-panel__section-title">Property Features</h3>
                <div class="mw-filter-panel__checks">
                    <label class="mw-filter-panel__check"><input type="checkbox" name="property-feature" value="verified"><span>MW Verified Property</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="property-feature" value="floor-plans"><span>Properties with floor plans</span></label>
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
                    <h1 class="mw-about-title" data-reveal>Properties in Dubai</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><span>Properties in Dubai</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-dubai-filter">
            <div class="container-ctn">
                <form class="mw-dubai-filter__bar" role="search" @submit.prevent>
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dropdown" data-dropdown>
                        <label>Purpose</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>Buy</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option>Buy</button></li>
                            <li><button type="button" data-dropdown-option>Rent</button></li>
                            <li><button type="button" data-dropdown-option>Off-Plan</button></li>
                        </ul>
                    </div>
                    <div class="mw-dubai-filter__field">
                        <label for="dubai-location">Location</label>
                        <input type="text" id="dubai-location" name="location" placeholder="Enter location" autocomplete="off" value="Dubai">
                    </div>
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dropdown" data-dropdown>
                        <label>Property Type</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>All type</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option>All type</button></li>
                            <li><button type="button" data-dropdown-option>Apartment</button></li>
                            <li><button type="button" data-dropdown-option>Villa</button></li>
                            <li><button type="button" data-dropdown-option>Townhouse</button></li>
                            <li><button type="button" data-dropdown-option>Penthouse</button></li>
                            <li><button type="button" data-dropdown-option>Duplex</button></li>
                            <li><button type="button" data-dropdown-option>Studio</button></li>
                        </ul>
                    </div>
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dropdown" data-dropdown>
                        <label>Bedrooms</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>Select bedrooms</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option>Select bedrooms</button></li>
                            <li><button type="button" data-dropdown-option>Studio</button></li>
                            <li><button type="button" data-dropdown-option>1</button></li>
                            <li><button type="button" data-dropdown-option>2</button></li>
                            <li><button type="button" data-dropdown-option>3</button></li>
                            <li><button type="button" data-dropdown-option>4</button></li>
                            <li><button type="button" data-dropdown-option>5+</button></li>
                        </ul>
                    </div>
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dubai-filter__field--last mw-dropdown" data-dropdown>
                        <label>Bathrooms</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>Select bathrooms</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option>Select bathrooms</button></li>
                            <li><button type="button" data-dropdown-option>1</button></li>
                            <li><button type="button" data-dropdown-option>2</button></li>
                            <li><button type="button" data-dropdown-option>3</button></li>
                            <li><button type="button" data-dropdown-option>4</button></li>
                            <li><button type="button" data-dropdown-option>5+</button></li>
                        </ul>
                    </div>
                    <button type="button" class="mw-dubai-filter__filter-btn" aria-label="More filters" data-bs-toggle="offcanvas" data-bs-target="#dubai-filter-panel" aria-controls="dubai-filter-panel">
                        <img src="/frontend/assets/images/icons/filter.svg" alt="" width="24" height="24">
                    </button>
                    <button type="submit" class="mw-dubai-filter__search">
                        <img src="/frontend/assets/images/icons/search.svg" alt="" width="18" height="18">
                        Search
                    </button>
                </form>
            </div>
        </section>

        <section class="mw-dubai-toolbar">
            <div class="container-ctn">
                <div class="mw-dubai-toolbar__row">
                    <div class="mw-pill-tabs" data-tab-group>
                        <button type="button" class="is-active" data-tab>All</button>
                        <button type="button" data-tab>Ready</button>
                        <button type="button" data-tab>Off Plan</button>
                    </div>
                    <div class="mw-dubai-toolbar__right">
                        <div class="mw-dropdown mw-dubai-toolbar__sort" data-dropdown>
                            <button type="button" class="mw-dubai-toolbar__sort-trigger" data-dropdown-trigger aria-haspopup="listbox">
                                <span data-dropdown-label>Most Recent</span>
                                <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                            </button>
                            <ul class="mw-dropdown__menu" data-dropdown-menu>
                                <li><button type="button" class="is-selected" data-dropdown-option>Most Recent</button></li>
                                <li><button type="button" data-dropdown-option>Price: Low to High</button></li>
                                <li><button type="button" data-dropdown-option>Price: High to Low</button></li>
                                <li><button type="button" data-dropdown-option>Most Popular</button></li>
                            </ul>
                        </div>
                        <div class="mw-dubai-toolbar__views" role="group" aria-label="Toggle properties view">
                            <button type="button" class="mw-dubai-toolbar__view-btn is-active" data-properties-view="grid" aria-label="Grid view" aria-pressed="true">
                                <img src="/frontend/assets/images/agencies/icon-grid-view.svg" alt="" width="24" height="24">
                            </button>
                            <button type="button" class="mw-dubai-toolbar__view-btn" data-properties-view="list" aria-label="List view" aria-pressed="false">
                                <img src="/frontend/assets/images/agencies/icon-list-view.svg" alt="" width="24" height="24">
                            </button>
                        </div>
                        <div class="mw-dubai-toolbar__actions">
                            <button type="button" class="quote-modal__btn quote-modal__btn--primary mw-dubai-toolbar__custom-request" data-crm-open="custom-request-modal">Custom Request</button>
                            <button type="button" class="mw-dubai-toolbar__clear">Clear Filters</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-dubai-ticker" aria-label="Recommended searches and off-plan investment links">
            <div class="mw-dubai-ticker__viewport">
                <div class="mw-dubai-ticker__track" data-ticker-track>
                    <div class="mw-dubai-ticker__group">
                        <span class="mw-dubai-ticker__label">Recommended Searches</span>
                        <router-link v-for="n in bedroomLinks" :key="'a-' + n" :to="{ path: '/properties-dubai', query: { bedrooms: n } }">{{ n }} Bedroom Properties for sale in dubai</router-link>
                        <span class="mw-dubai-ticker__label">Invest in Off Plan Properties</span>
                        <a href="#">1 Off Plan Properties in Abu Dhabi</a>
                        <a href="#">2 Off Plan Properties in Abu Dhabi</a>
                        <a href="#">3 Off Plan Properties in Abu Dhabi</a>
                        <a href="#">4 Off Plan Properties in Abu Dhabi</a>
                    </div>
                    <div class="mw-dubai-ticker__group" aria-hidden="true">
                        <span class="mw-dubai-ticker__label">Recommended Searches</span>
                        <router-link v-for="n in bedroomLinks" :key="'b-' + n" :to="{ path: '/properties-dubai', query: { bedrooms: n } }" tabindex="-1">{{ n }} Bedroom Properties for sale in dubai</router-link>
                        <span class="mw-dubai-ticker__label">Invest in Off Plan Properties</span>
                        <a href="#" tabindex="-1">1 Off Plan Properties in Abu Dhabi</a>
                        <a href="#" tabindex="-1">2 Off Plan Properties in Abu Dhabi</a>
                        <a href="#" tabindex="-1">3 Off Plan Properties in Abu Dhabi</a>
                        <a href="#" tabindex="-1">4 Off Plan Properties in Abu Dhabi</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-dubai-listing">
            <div class="container-ctn">
                <div class="mw-dubai-listing__head">
                    <h2 class="mw-dubai-listing__title" data-reveal>Properties for sale in dubai</h2>
                    <p class="mw-dubai-listing__count" data-reveal>1,379 listings</p>
                </div>

                <div class="mw-dubai-listing__grid" data-properties-grid>

                    <article v-for="property in properties" :key="property.title" class="mw-dubai-card" data-reveal>
                        <div class="mw-projects__media" data-card-gallery>
                            <div class="mw-projects__slides" data-gallery-track>
                                <router-link v-for="(image, index) in property.images" :key="image" to="/property-details">
                                    <img :src="image" :alt="index === 0 ? property.title : ''" class="mw-projects__photo">
                                </router-link>
                            </div>
                            <span class="mw-dubai-card__purpose">{{ property.purpose }}</span>
                            <button type="button" class="mw-dubai-card__fav" aria-label="Save property">
                                <img src="/frontend/assets/images/icons/heart.svg" alt="" width="18" height="18">
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
                                <span data-gallery-count>{{ property.images.length }}</span>
                            </span>
                            <span class="mw-dubai-card__type">{{ property.type }}</span>
                        </div>
                        <div class="mw-dubai-card__body">
                            <div class="mw-dubai-card__price-row">
                                <span class="mw-dubai-card__price">{{ property.price }}</span>
                                <span class="mw-dubai-card__amenity">{{ property.amenity }}</span>
                            </div>
                            <h3 class="mw-dubai-card__title"><router-link to="/property-details">{{ property.title }}</router-link></h3>
                            <p class="mw-dubai-card__location">
                                <img src="/frontend/assets/images/icons/location.svg" alt="" width="16" height="16">
                                {{ property.location }}
                            </p>
                            <div class="mw-dubai-card__stats">
                                <span v-if="property.beds" class="mw-dubai-card__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="16" height="16">{{ property.beds }}</span>
                                <span v-if="property.baths" class="mw-dubai-card__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="16" height="16">{{ property.baths }}</span>
                                <span class="mw-dubai-card__stat"><img src="/frontend/assets/images/icons/area.svg" alt="" width="16" height="16">{{ property.area }}</span>
                            </div>
                            <div class="mw-dubai-card__divider"></div>
                            <div class="mw-dubai-card__contacts">
                                <a href="mailto:info@mightywarnersrealty.com" class="mw-dubai-card__contact-btn">
                                    <img src="/frontend/assets/images/icons/email.svg" alt="" width="16" height="16">
                                    Email
                                </a>
                                <a href="tel:+971585899990" class="mw-dubai-card__contact-btn">
                                    <img src="/frontend/assets/images/icons/phone.svg" alt="" width="16" height="16">
                                    Call Us
                                </a>
                                <a href="https://wa.me/971585899990" class="mw-dubai-card__contact-btn">
                                    <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="16" height="16">
                                    WhatsApp
                                </a>
                            </div>
                        </div>
                    </article>

                </div>

                <nav class="mw-dubai-listing__pagination" aria-label="Properties pagination">
                    <button type="button" class="mw-dubai-listing__page mw-dubai-listing__page--prev" data-dubai-page="prev">Previous</button>
                    <button type="button" class="mw-dubai-listing__page is-active" data-dubai-page="1">1</button>
                    <button type="button" class="mw-dubai-listing__page" data-dubai-page="2">2</button>
                    <button type="button" class="mw-dubai-listing__page" data-dubai-page="3">3</button>
                    <button type="button" class="mw-dubai-listing__page" data-dubai-page="4">4</button>
                    <span class="mw-dubai-listing__page mw-dubai-listing__page--ellipsis" aria-hidden="true">…</span>
                    <button type="button" class="mw-dubai-listing__page" data-dubai-page="8">8</button>
                    <button type="button" class="mw-dubai-listing__page mw-dubai-listing__page--next" data-dubai-page="next">Next</button>
                </nav>
            </div>
        </section>
    </main>
</template>
