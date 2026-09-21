<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useCommercial } from '../composables/useCommercial';
import { useLanguages } from '../composables/useLanguages';

const { commercialListing, fetchCommercialListing } = useCommercial();
const { selectedLanguage } = useLanguages();

const locationQuery = ref('');
const searchQuery = ref('');
const propertyType = ref('');
const listingCategory = ref('');
const currentPage = ref(1);

function load() {
    fetchCommercialListing({
        lang: selectedLanguage.value?.code,
        page: currentPage.value,
        location: locationQuery.value.trim() || undefined,
        search: searchQuery.value.trim() || undefined,
        property_type: propertyType.value || undefined,
        category: listingCategory.value || undefined,
    });
}

function applyFilters() {
    currentPage.value = 1;
    load();
}

function selectPropertyType(value) {
    propertyType.value = value;
    applyFilters();
}

function selectListingCategory(value) {
    listingCategory.value = value;
    applyFilters();
}

function goToPage(page) {
    if (page < 1 || page > (pagination.value?.last_page || 1) || page === currentPage.value) return;
    currentPage.value = page;
    load();
    document.querySelector('.mw-commercial')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const properties = computed(() => commercialListing.value?.properties || []);
const pagination = computed(() => commercialListing.value?.pagination || null);
const pageNumbers = computed(() => {
    if (!pagination.value) return [];
    return Array.from({ length: pagination.value.last_page }, (_, i) => i + 1);
});

onMounted(load);
watch(selectedLanguage, load);

// The reveal-on-scroll animation + gallery/dropdown widgets (legacy assets/js/script.js) only
// scan the DOM once — freshly rendered/filtered/paginated cards need that binding run again
// (window.MWRealty.refresh is idempotent, safe to call repeatedly).
watch(commercialListing, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});

function amenityOverflow(amenities) {
    return Math.max(0, (amenities || []).length - 4);
}
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
                    <h1 class="mw-about-title" data-reveal>{{ commercialListing?.title || 'Commercial' }}</h1>
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
                <form class="mw-commercial-filter__bar" role="search" @submit.prevent="applyFilters">
                    <div class="mw-commercial-filter__field">
                        <label for="commercial-location">Location</label>
                        <input type="text" id="commercial-location" name="location" placeholder="Enter location" autocomplete="off" v-model="locationQuery" @keyup.enter="applyFilters">
                    </div>
                    <div class="mw-commercial-filter__field">
                        <label for="commercial-properties">Properties</label>
                        <input type="text" id="commercial-properties" name="properties" placeholder="Search by title, location" autocomplete="off" v-model="searchQuery" @keyup.enter="applyFilters">
                    </div>
                    <div class="mw-commercial-filter__field mw-commercial-filter__field--select mw-dropdown" data-dropdown>
                        <label>Property Type</label>
                        <button type="button" class="mw-commercial-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>All type</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-commercial-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option @click="selectPropertyType('')">All type</button></li>
                            <li><button type="button" data-dropdown-option @click="selectPropertyType('office')">Office</button></li>
                            <li><button type="button" data-dropdown-option @click="selectPropertyType('retail-shop')">Retail Shop</button></li>
                            <li><button type="button" data-dropdown-option @click="selectPropertyType('warehouse')">Warehouse</button></li>
                            <li><button type="button" data-dropdown-option @click="selectPropertyType('showroom')">Showroom</button></li>
                        </ul>
                    </div>
                    <div class="mw-commercial-filter__field mw-commercial-filter__field--select mw-commercial-filter__field--last mw-dropdown" data-dropdown>
                        <label>Category</label>
                        <button type="button" class="mw-commercial-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>All categories</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-commercial-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option @click="selectListingCategory('')">All categories</button></li>
                            <li><button type="button" data-dropdown-option @click="selectListingCategory('sale')">Buy</button></li>
                            <li><button type="button" data-dropdown-option @click="selectListingCategory('rent')">Lease</button></li>
                            <li><button type="button" data-dropdown-option @click="selectListingCategory('off_plan')">Off-Plan</button></li>
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

                    <article v-for="property in properties" :key="property.slug" class="mw-commercial-card" data-reveal>
                        <div class="mw-projects__media" data-card-gallery role="link" tabindex="0" @click="$router.push(`/property-details/${property.slug}`)" @keydown.enter="$router.push(`/property-details/${property.slug}`)" style="cursor: pointer;">
                            <div class="mw-projects__slides" data-gallery-track>
                                <img v-for="(image, index) in property.images" :key="image" :src="image" :alt="index === 0 ? property.name : ''" class="mw-projects__photo">
                            </div>
                            <div class="mw-commercial-card__badges">
                                <span v-if="property.verified" class="mw-badge mw-badge--success mw-commercial-card__verified">
                                    <img src="/frontend/assets/images/icons/verified.svg" alt="" width="16" height="16">
                                    MW Verified
                                </span>
                                <span class="mw-commercial-card__cta-pill" :class="{ 'mw-commercial-card__cta-pill--sell': property.cta_variant === 'sell' }">{{ property.cta }}</span>
                            </div>
                            <button type="button" class="mw-projects__nav mw-projects__nav--prev" data-gallery-prev aria-label="Previous photo" @click.stop>
                                <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                            </button>
                            <button type="button" class="mw-projects__nav mw-projects__nav--next" data-gallery-next aria-label="Next photo" @click.stop>
                                <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                            </button>
                            <div class="mw-projects__dots" data-gallery-dots></div>
                            <span v-if="property.images_count" class="mw-projects__photo-count">
                                <img src="/frontend/assets/images/icons/photo-count.svg" alt="" width="16" height="16">
                                <span data-gallery-count>{{ property.images_count }}</span>
                            </span>
                            <span class="mw-commercial-card__type">{{ property.type }}</span>
                        </div>
                        <div class="mw-commercial-card__body">
                            <div class="mw-commercial-card__price-row">
                                <span v-if="property.service" class="mw-commercial-card__service">{{ property.service }}</span>
                                <span class="mw-commercial-card__price">{{ property.price }}</span>
                            </div>
                            <h3 class="mw-commercial-card__title"><router-link :to="`/property-details/${property.slug}`">{{ property.name }}</router-link></h3>
                            <p class="mw-commercial-card__location">
                                <img src="/frontend/assets/images/icons/location.svg" alt="" width="16" height="16">
                                {{ property.location }}
                            </p>
                            <p class="mw-commercial-card__desc">{{ property.description }}</p>
                            <div class="mw-commercial-card__meta-row">
                                <span>Floor: {{ property.floor ?? '—' }}</span>
                                <span>RERA: {{ property.rera_id }}</span>
                            </div>
                            <div class="mw-commercial-card__stats">
                                <span v-if="property.beds" class="mw-commercial-card__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="16" height="16">{{ property.beds }}</span>
                                <span v-if="property.baths" class="mw-commercial-card__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="16" height="16">{{ property.baths }}</span>
                                <span class="mw-commercial-card__stat"><img src="/frontend/assets/images/icons/area.svg" alt="" width="16" height="16">{{ property.area }}</span>
                            </div>
                            <div class="mw-commercial-card__amenities">
                                <span v-for="amenity in property.amenities.slice(0, 4)" :key="amenity" class="mw-commercial-card__amenity">{{ amenity }}</span>
                                <span v-if="amenityOverflow(property.amenities)" class="mw-commercial-card__amenity mw-commercial-card__amenity--more">+{{ amenityOverflow(property.amenities) }} more</span>
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

                    <p v-if="commercialListing && !properties.length" class="mw-commercial-card__desc">No commercial properties match your search.</p>

                </div>

                <nav v-if="pagination && pagination.last_page > 1" class="mw-blog__pagination" aria-label="Commercial properties pagination">
                    <button type="button" class="mw-blog__page mw-blog__page--prev" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">Previous</button>
                    <button v-for="page in pageNumbers" :key="page" type="button" class="mw-blog__page" :class="{ 'is-active': page === currentPage }" @click="goToPage(page)">{{ page }}</button>
                    <button type="button" class="mw-blog__page mw-blog__page--next" :disabled="currentPage === pagination.last_page" @click="goToPage(currentPage + 1)">Next</button>
                </nav>
            </div>
        </section>
    </main>
</template>
