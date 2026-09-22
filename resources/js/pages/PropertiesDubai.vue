<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useProperties } from '../composables/useProperties';
import { useLanguages } from '../composables/useLanguages';
import { useWishlist } from '../composables/useWishlist';

const route = useRoute();
const { propertiesListing, fetchPropertiesListing } = useProperties();
const { isWishlisted, toggleWishlist } = useWishlist();
const { selectedLanguage } = useLanguages();

const locationQuery = ref('');
const propertyType = ref('');
const category = ref('sale');
const bedroomsFilter = ref('');
const bathroomsFilter = ref('');
const currentPage = ref(1);

function load() {
    fetchPropertiesListing({
        lang: selectedLanguage.value?.code,
        page: currentPage.value,
        location: locationQuery.value.trim() || undefined,
        property_type: propertyType.value || undefined,
        category: category.value || undefined,
        bedrooms: bedroomsFilter.value || undefined,
        bathrooms: bathroomsFilter.value || undefined,
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

function selectCategory(value) {
    category.value = value;
    applyFilters();
}

function selectBedrooms(value) {
    bedroomsFilter.value = value;
    applyFilters();
}

function selectBathrooms(value) {
    bathroomsFilter.value = value;
    applyFilters();
}

function clearFilters() {
    locationQuery.value = '';
    propertyType.value = '';
    category.value = '';
    bedroomsFilter.value = '';
    bathroomsFilter.value = '';
    applyFilters();
}

function goToPage(page) {
    if (page < 1 || page > (pagination.value?.last_page || 1) || page === currentPage.value) return;
    currentPage.value = page;
    load();
    document.querySelector('.mw-dubai-listing')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const properties = computed(() => propertiesListing.value?.properties || []);
const pagination = computed(() => propertiesListing.value?.pagination || null);
const pageNumbers = computed(() => {
    if (!pagination.value) return [];
    return Array.from({ length: pagination.value.last_page }, (_, i) => i + 1);
});

onMounted(() => {
    if (route.query.bedrooms) bedroomsFilter.value = String(route.query.bedrooms);
    load();
});
watch(selectedLanguage, load);

// See Blogs.vue for why this re-run is needed after the async fetch populates the page.
watch(propertiesListing, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});

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
                <form class="mw-dubai-filter__bar" role="search" @submit.prevent="applyFilters">
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dropdown" data-dropdown>
                        <label>Purpose</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>Buy</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option @click="selectCategory('sale')">Buy</button></li>
                            <li><button type="button" data-dropdown-option @click="selectCategory('rent')">Rent</button></li>
                            <li><button type="button" data-dropdown-option @click="selectCategory('off_plan')">Off-Plan</button></li>
                        </ul>
                    </div>
                    <div class="mw-dubai-filter__field">
                        <label for="dubai-location">Location</label>
                        <input type="text" id="dubai-location" name="location" placeholder="Enter location" autocomplete="off" v-model="locationQuery" @keyup.enter="applyFilters">
                    </div>
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dropdown" data-dropdown>
                        <label>Property Type</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>All type</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option @click="selectPropertyType('')">All type</button></li>
                            <li><button type="button" data-dropdown-option @click="selectPropertyType('apartment')">Apartment</button></li>
                            <li><button type="button" data-dropdown-option @click="selectPropertyType('villa')">Villa</button></li>
                            <li><button type="button" data-dropdown-option @click="selectPropertyType('townhouse')">Townhouse</button></li>
                            <li><button type="button" data-dropdown-option @click="selectPropertyType('penthouse')">Penthouse</button></li>
                        </ul>
                    </div>
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dropdown" data-dropdown>
                        <label>Bedrooms</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>Select bedrooms</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option @click="selectBedrooms('')">Select bedrooms</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBedrooms('studio')">Studio</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBedrooms('1')">1</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBedrooms('2')">2</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBedrooms('3')">3</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBedrooms('4')">4</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBedrooms('5+')">5+</button></li>
                        </ul>
                    </div>
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dubai-filter__field--last mw-dropdown" data-dropdown>
                        <label>Bathrooms</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>Select bathrooms</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option @click="selectBathrooms('')">Select bathrooms</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBathrooms('1')">1</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBathrooms('2')">2</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBathrooms('3')">3</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBathrooms('4')">4</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBathrooms('5+')">5+</button></li>
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
                            <button type="button" class="mw-dubai-toolbar__clear" @click="clearFilters">Clear Filters</button>
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
                        <router-link v-for="n in bedroomLinks" :key="'a-' + n" :to="{ path: '/properties', query: { bedrooms: n } }">{{ n }} Bedroom Properties for sale in dubai</router-link>
                        <span class="mw-dubai-ticker__label">Invest in Off Plan Properties</span>
                        <a href="#">1 Off Plan Properties in Abu Dhabi</a>
                        <a href="#">2 Off Plan Properties in Abu Dhabi</a>
                        <a href="#">3 Off Plan Properties in Abu Dhabi</a>
                        <a href="#">4 Off Plan Properties in Abu Dhabi</a>
                    </div>
                    <div class="mw-dubai-ticker__group" aria-hidden="true">
                        <span class="mw-dubai-ticker__label">Recommended Searches</span>
                        <router-link v-for="n in bedroomLinks" :key="'b-' + n" :to="{ path: '/properties', query: { bedrooms: n } }" tabindex="-1">{{ n }} Bedroom Properties for sale in dubai</router-link>
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
                    <p class="mw-dubai-listing__count" data-reveal>{{ pagination?.total ?? 0 }} listings</p>
                </div>

                <div class="mw-dubai-listing__grid" data-properties-grid>

                    <article v-for="property in properties" :key="property.slug" class="mw-dubai-card" data-reveal>
                        <div class="mw-projects__media" data-card-gallery role="link" tabindex="0" @click="$router.push(`/property-details/${property.slug}`)" @keydown.enter="$router.push(`/property-details/${property.slug}`)" style="cursor: pointer;">
                            <div class="mw-projects__slides" data-gallery-track>
                                <img v-for="(image, index) in property.images" :key="image" :src="image" :alt="index === 0 ? property.name : ''" class="mw-projects__photo">
                            </div>
                            <button type="button" class="mw-dubai-card__fav" :class="{ 'is-saved': isWishlisted(property.id) }" aria-label="Save property" :aria-pressed="isWishlisted(property.id)" @click.stop="toggleWishlist(property.id)">
                                <img src="/frontend/assets/images/icons/heart.svg" alt="" width="18" height="18">
                            </button>
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
                            <span class="mw-dubai-card__type">{{ property.type }}</span>
                        </div>
                        <div class="mw-dubai-card__body">
                            <div class="mw-dubai-card__price-row">
                                <span class="mw-dubai-card__price">{{ property.price }}</span>
                            </div>
                            <h3 class="mw-dubai-card__title"><router-link :to="`/property-details/${property.slug}`">{{ property.name }}</router-link></h3>
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
                                <a href="mailto:info@mightywarnersrealty.com" class="mw-dubai-card__contact-btn" @click.stop>
                                    <img src="/frontend/assets/images/icons/email.svg" alt="" width="16" height="16">
                                    Email
                                </a>
                                <a href="tel:+971585899990" class="mw-dubai-card__contact-btn" @click.stop>
                                    <img src="/frontend/assets/images/icons/phone.svg" alt="" width="16" height="16">
                                    Call Us
                                </a>
                                <a href="https://wa.me/971585899990" class="mw-dubai-card__contact-btn" @click.stop>
                                    <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="16" height="16">
                                    WhatsApp
                                </a>
                            </div>
                        </div>
                    </article>

                    <p v-if="propertiesListing && !properties.length" class="mw-dubai-listing__count">No properties match your search.</p>

                </div>

                <nav v-if="pagination && pagination.last_page > 1" class="mw-dubai-listing__pagination" aria-label="Properties pagination">
                    <button type="button" class="mw-dubai-listing__page mw-dubai-listing__page--prev" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">Previous</button>
                    <button v-for="page in pageNumbers" :key="page" type="button" class="mw-dubai-listing__page" :class="{ 'is-active': page === currentPage }" @click="goToPage(page)">{{ page }}</button>
                    <button type="button" class="mw-dubai-listing__page mw-dubai-listing__page--next" :disabled="currentPage === pagination.last_page" @click="goToPage(currentPage + 1)">Next</button>
                </nav>
            </div>
        </section>
    </main>
</template>
