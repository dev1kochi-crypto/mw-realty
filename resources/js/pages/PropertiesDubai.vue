<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useProperties } from '../composables/useProperties';
import { useLanguages } from '../composables/useLanguages';
import { useWishlist } from '../composables/useWishlist';
import { useRecaptcha } from '../composables/useRecaptcha';
import { useStaticText } from '../composables/useStaticText';
import EmptyState from '../components/EmptyState.vue';

const route = useRoute();
const { propertiesListing, fetchPropertiesListing } = useProperties();
const { isWishlisted, toggleWishlist, authenticated } = useWishlist();
const { selectedLanguage } = useLanguages();
const { getRecaptchaToken } = useRecaptcha();
const { t } = useStaticText();

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

// "Custom Request" — a buyer describes a property they can't find in the listing, submitted as
// an unassigned Lead (no property_id) so it lands on the admin's Unassigned Leads screen (see
// Crm\LeadCaptureController::storeCustomRequest).
const showCustomRequestModal = ref(false);
const customRequestSubmitting = ref(false);
const customRequestFeedback = ref(null);
const customRequestForm = reactive({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    property_category: '',
    specification: '',
    price_range: '',
    area: '',
    preferred_location: '',
    additional_details: '',
    move_in_timeline: '',
    furnishing_status: '',
    whatsapp_consent: false,
});

function openCustomRequestModal() {
    showCustomRequestModal.value = true;
    document.body.classList.add('quote-modal-open');
}

function closeCustomRequestModal() {
    showCustomRequestModal.value = false;
    document.body.classList.remove('quote-modal-open');
}

async function submitCustomRequest() {
    if (customRequestSubmitting.value) return;
    customRequestSubmitting.value = true;
    customRequestFeedback.value = null;

    try {
        const recaptcha_token = await getRecaptchaToken('custom_property_request');
        const { data } = await window.axios.post('/leads/custom-request', {
            ...customRequestForm,
            recaptcha_token,
        });
        customRequestFeedback.value = { type: 'success', text: data.message };
        Object.assign(customRequestForm, {
            first_name: '', last_name: '', email: '', phone: '',
            property_category: '', specification: '', price_range: '', area: '',
            preferred_location: '', additional_details: '',
            move_in_timeline: '', furnishing_status: '', whatsapp_consent: false,
        });
    } catch (error) {
        customRequestFeedback.value = { type: 'error', text: error.response?.data?.message || t('custom_request.generic_error') };
    } finally {
        customRequestSubmitting.value = false;
    }
}

// "Save Search" — stores the currently-applied filters so Profile.vue's Saved Searches tab can
// list them and (eventually) notify on new matches. Mirrors the same criteria keys
// CustomerController::savedSearchMeta() already reads to build that tab's summary line.
const savingSearch = ref(false);
const searchSaved = ref(false);
const searchSaveError = ref(false);
const saveSearchLabel = computed(() => {
    if (savingSearch.value) return t('properties_listing.toolbar.saving');
    if (searchSaved.value) return t('properties_listing.toolbar.search_saved');
    if (searchSaveError.value) return t('properties_listing.toolbar.save_error');
    return t('properties_listing.toolbar.save_search');
});

function buildSearchTitle() {
    const parts = [];
    if (bedroomsFilter.value) parts.push(`${bedroomsFilter.value} Bed`);
    if (propertyType.value) parts.push(propertyType.value.charAt(0).toUpperCase() + propertyType.value.slice(1));
    if (locationQuery.value.trim()) parts.push(locationQuery.value.trim());
    return parts.length ? parts.join(' ') : 'All Properties';
}

function saveSearch() {
    if (!authenticated.value) {
        window.location.href = '/login';
        return;
    }
    if (savingSearch.value) return;

    savingSearch.value = true;
    searchSaveError.value = false;
    window.axios.post('/customer/saved-searches', {
        title: buildSearchTitle(),
        criteria: {
            location: locationQuery.value.trim() || undefined,
            property_type: propertyType.value || undefined,
            bedrooms: bedroomsFilter.value || undefined,
            bathrooms: bathroomsFilter.value || undefined,
            category: category.value || undefined,
        },
    }).then(() => {
        searchSaved.value = true;
        setTimeout(() => { searchSaved.value = false; }, 2500);
    }).catch(() => {
        searchSaveError.value = true;
        setTimeout(() => { searchSaveError.value = false; }, 2500);
    }).finally(() => {
        savingSearch.value = false;
    });
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
            <h2 class="mw-filter-panel__title" id="dubaiFilterLabel">{{ t('properties_listing.filter_panel.title') }}</h2>
            <button type="button" class="mw-filter-panel__close" data-bs-dismiss="offcanvas" :aria-label="t('properties_listing.filter_panel.close_aria')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="offcanvas-body mw-filter-panel__body">

            <section class="mw-filter-panel__section mw-filter-panel__section--basics">
                <div class="mw-filter-panel__field mw-filter-panel__field--select mw-dropdown" data-dropdown>
                    <span>{{ t('properties_listing.filter_panel.purpose_label') }}</span>
                    <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                        <span data-dropdown-label>{{ t('properties_listing.filter_bar.buy') }}</span>
                        <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                    </button>
                    <ul class="mw-dropdown__menu" data-dropdown-menu>
                        <li><button type="button" class="is-selected" data-dropdown-option>{{ t('properties_listing.filter_bar.buy') }}</button></li>
                        <li><button type="button" data-dropdown-option>{{ t('properties_listing.filter_bar.rent') }}</button></li>
                        <li><button type="button" data-dropdown-option>{{ t('properties_listing.filter_bar.off_plan') }}</button></li>
                    </ul>
                </div>
                <label class="mw-filter-panel__field">
                    <span>{{ t('properties_listing.filter_panel.location_label') }}</span>
                    <input type="text" id="dubai-location-mobile" name="location" :placeholder="t('properties_listing.filter_panel.location_placeholder')" autocomplete="off" value="Dubai">
                </label>
                <div class="mw-filter-panel__field mw-filter-panel__field--select mw-dropdown" data-dropdown>
                    <span>{{ t('properties_listing.filter_panel.property_type_label') }}</span>
                    <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                        <span data-dropdown-label>{{ t('properties_listing.filter_panel.all_type') }}</span>
                        <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                    </button>
                    <ul class="mw-dropdown__menu" data-dropdown-menu>
                        <li><button type="button" class="is-selected" data-dropdown-option>{{ t('properties_listing.filter_panel.all_type') }}</button></li>
                        <li><button type="button" data-dropdown-option>{{ t('properties_listing.property_types.apartment') }}</button></li>
                        <li><button type="button" data-dropdown-option>{{ t('properties_listing.property_types.villa') }}</button></li>
                        <li><button type="button" data-dropdown-option>{{ t('properties_listing.property_types.townhouse') }}</button></li>
                        <li><button type="button" data-dropdown-option>{{ t('properties_listing.property_types.penthouse') }}</button></li>
                        <li><button type="button" data-dropdown-option>{{ t('properties_listing.property_types.duplex') }}</button></li>
                        <li><button type="button" data-dropdown-option>{{ t('properties_listing.property_types.studio') }}</button></li>
                    </ul>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">{{ t('properties_listing.filter_panel.price_title') }}</h3>
                <div class="mw-hero__price-readout">
                    <label class="mw-hero__price-input">
                        <span>{{ t('properties_listing.filter_panel.min_label') }}</span>
                        <input type="text" inputmode="decimal" data-price-min-input :aria-label="t('properties_listing.filter_panel.min_aria')" value="AED 0">
                    </label>
                    <label class="mw-hero__price-input">
                        <span>{{ t('properties_listing.filter_panel.max_label') }}</span>
                        <input type="text" inputmode="decimal" data-price-max-input :aria-label="t('properties_listing.filter_panel.max_aria')" value="AED 30M">
                    </label>
                </div>
                <div class="mw-hero__price-slider" data-price-slider>
                    <div class="mw-hero__price-track"></div>
                    <div class="mw-hero__price-fill" data-price-range></div>
                    <input type="range" name="min_price" min="0" max="30000000" step="50000" value="0" data-price-min :aria-label="t('properties_listing.filter_panel.min_aria')">
                    <input type="range" name="max_price" min="0" max="30000000" step="50000" value="30000000" data-price-max :aria-label="t('properties_listing.filter_panel.max_aria')">
                </div>
                <p class="mw-hero__price-presets-title">{{ t('properties_listing.filter_panel.quick_select') }}</p>
                <div class="mw-hero__price-presets">
                    <button type="button" data-price-preset data-min="0" data-max="500000">{{ t('properties_listing.price_presets.under_500k') }}</button>
                    <button type="button" data-price-preset data-min="500000" data-max="1000000">{{ t('properties_listing.price_presets.500k_1m') }}</button>
                    <button type="button" data-price-preset data-min="1000000" data-max="2000000">{{ t('properties_listing.price_presets.1m_2m') }}</button>
                    <button type="button" data-price-preset data-min="2000000" data-max="5000000">{{ t('properties_listing.price_presets.2m_5m') }}</button>
                    <button type="button" data-price-preset data-min="5000000" data-max="10000000">{{ t('properties_listing.price_presets.5m_10m') }}</button>
                    <button type="button" data-price-preset data-min="10000000" data-max="30000000">{{ t('properties_listing.price_presets.10m_plus') }}</button>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">{{ t('properties_listing.filter_panel.bedrooms_title') }}</h3>
                <div class="mw-pill-tabs" data-tab-group>
                    <button type="button" class="is-active" data-tab>{{ t('properties_listing.filter_panel.any') }}</button>
                    <button type="button" data-tab>{{ t('properties_listing.bedroom_options.studio') }}</button>
                    <button type="button" data-tab>{{ t('properties_listing.bedroom_options.one') }}</button>
                    <button type="button" data-tab>{{ t('properties_listing.bedroom_options.two') }}</button>
                    <button type="button" data-tab>{{ t('properties_listing.bedroom_options.three') }}</button>
                    <button type="button" data-tab>{{ t('properties_listing.bedroom_options.four') }}</button>
                    <button type="button" data-tab>{{ t('properties_listing.bedroom_options.five_plus') }}</button>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">{{ t('properties_listing.filter_panel.bathrooms_title') }}</h3>
                <div class="mw-pill-tabs" data-tab-group>
                    <button type="button" class="is-active" data-tab>{{ t('properties_listing.filter_panel.any') }}</button>
                    <button type="button" data-tab>{{ t('properties_listing.bathroom_options.one') }}</button>
                    <button type="button" data-tab>{{ t('properties_listing.bathroom_options.two') }}</button>
                    <button type="button" data-tab>{{ t('properties_listing.bathroom_options.three') }}</button>
                    <button type="button" data-tab>{{ t('properties_listing.bathroom_options.four') }}</button>
                    <button type="button" data-tab>{{ t('properties_listing.bathroom_options.five_plus') }}</button>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">{{ t('properties_listing.filter_panel.area_title') }}</h3>
                <div class="mw-filter-panel__range-inputs">
                    <label>
                        <span>{{ t('properties_listing.filter_panel.min_label') }}</span>
                        <input type="number" inputmode="numeric" :placeholder="t('properties_listing.filter_panel.area_min_placeholder')" :aria-label="t('properties_listing.filter_panel.area_min_aria')">
                    </label>
                    <label>
                        <span>{{ t('properties_listing.filter_panel.max_label') }}</span>
                        <input type="number" inputmode="numeric" :placeholder="t('properties_listing.filter_panel.area_max_placeholder')" :aria-label="t('properties_listing.filter_panel.area_max_aria')">
                    </label>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">{{ t('properties_listing.filter_panel.features_title') }}</h3>
                <div class="mw-filter-panel__checks">
                    <label class="mw-filter-panel__check"><input type="checkbox" name="property-feature" value="verified"><span>{{ t('properties_listing.filter_panel.feature_verified') }}</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="property-feature" value="floor-plans"><span>{{ t('properties_listing.filter_panel.feature_floor_plans') }}</span></label>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">{{ t('properties_listing.filter_panel.amenities_title') }}</h3>
                <div class="mw-filter-panel__checks">
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="furnished"><span>{{ t('properties_listing.filter_panel.amenity_furnished') }}</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="community-pool"><span>{{ t('properties_listing.filter_panel.amenity_community_pool') }}</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="parking"><span>{{ t('properties_listing.filter_panel.amenity_parking') }}</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="gym"><span>{{ t('properties_listing.filter_panel.amenity_gym') }}</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="security"><span>{{ t('properties_listing.filter_panel.amenity_security') }}</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="balcony"><span>{{ t('properties_listing.filter_panel.amenity_balcony') }}</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="concierge"><span>{{ t('properties_listing.filter_panel.amenity_concierge') }}</span></label>
                    <label class="mw-filter-panel__check"><input type="checkbox" name="amenity" value="private-pool"><span>{{ t('properties_listing.filter_panel.amenity_private_pool') }}</span></label>
                </div>
            </section>

        </div>
        <div class="mw-filter-panel__foot">
            <button type="button" class="mw-filter-panel__reset">{{ t('properties_listing.filter_panel.reset') }}</button>
            <button type="button" class="mw-filter-panel__apply" data-bs-dismiss="offcanvas">{{ t('properties_listing.filter_panel.apply') }}</button>
        </div>
    </div>

    <main>
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ t('properties_listing.hero.title') }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" :aria-label="t('properties_listing.hero.breadcrumb_aria')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('properties_listing.hero.breadcrumb_home') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ t('properties_listing.hero.title') }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-dubai-filter">
            <div class="container-ctn">
                <form class="mw-dubai-filter__bar" role="search" @submit.prevent="applyFilters">
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dropdown" data-dropdown>
                        <label>{{ t('properties_listing.filter_bar.purpose_label') }}</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>{{ t('properties_listing.filter_bar.buy') }}</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option @click="selectCategory('sale')">{{ t('properties_listing.filter_bar.buy') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectCategory('rent')">{{ t('properties_listing.filter_bar.rent') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectCategory('off_plan')">{{ t('properties_listing.filter_bar.off_plan') }}</button></li>
                        </ul>
                    </div>
                    <div class="mw-dubai-filter__field">
                        <label for="dubai-location">{{ t('properties_listing.filter_bar.location_label') }}</label>
                        <input type="text" id="dubai-location" name="location" :placeholder="t('properties_listing.filter_bar.location_placeholder')" autocomplete="off" v-model="locationQuery" @keyup.enter="applyFilters">
                    </div>
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dropdown" data-dropdown>
                        <label>{{ t('properties_listing.filter_bar.property_type_label') }}</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>{{ t('properties_listing.filter_bar.all_type') }}</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option @click="selectPropertyType('')">{{ t('properties_listing.filter_bar.all_type') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectPropertyType('apartment')">{{ t('properties_listing.property_types.apartment') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectPropertyType('villa')">{{ t('properties_listing.property_types.villa') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectPropertyType('townhouse')">{{ t('properties_listing.property_types.townhouse') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectPropertyType('penthouse')">{{ t('properties_listing.property_types.penthouse') }}</button></li>
                        </ul>
                    </div>
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dropdown" data-dropdown>
                        <label>{{ t('properties_listing.filter_bar.bedrooms_label') }}</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>{{ t('properties_listing.filter_bar.select_bedrooms') }}</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option @click="selectBedrooms('')">{{ t('properties_listing.filter_bar.select_bedrooms') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBedrooms('studio')">{{ t('properties_listing.bedroom_options.studio') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBedrooms('1')">{{ t('properties_listing.bedroom_options.one') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBedrooms('2')">{{ t('properties_listing.bedroom_options.two') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBedrooms('3')">{{ t('properties_listing.bedroom_options.three') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBedrooms('4')">{{ t('properties_listing.bedroom_options.four') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBedrooms('5+')">{{ t('properties_listing.bedroom_options.five_plus') }}</button></li>
                        </ul>
                    </div>
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dubai-filter__field--last mw-dropdown" data-dropdown>
                        <label>{{ t('properties_listing.filter_bar.bathrooms_label') }}</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label>{{ t('properties_listing.filter_bar.select_bathrooms') }}</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option @click="selectBathrooms('')">{{ t('properties_listing.filter_bar.select_bathrooms') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBathrooms('1')">{{ t('properties_listing.bathroom_options.one') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBathrooms('2')">{{ t('properties_listing.bathroom_options.two') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBathrooms('3')">{{ t('properties_listing.bathroom_options.three') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBathrooms('4')">{{ t('properties_listing.bathroom_options.four') }}</button></li>
                            <li><button type="button" data-dropdown-option @click="selectBathrooms('5+')">{{ t('properties_listing.bathroom_options.five_plus') }}</button></li>
                        </ul>
                    </div>
                    <button type="button" class="mw-dubai-filter__filter-btn" :aria-label="t('properties_listing.filter_bar.more_filters_aria')" data-bs-toggle="offcanvas" data-bs-target="#dubai-filter-panel" aria-controls="dubai-filter-panel">
                        <img src="/frontend/assets/images/icons/filter.svg" alt="" width="24" height="24">
                    </button>
                    <button type="submit" class="mw-dubai-filter__search">
                        <img src="/frontend/assets/images/icons/search.svg" alt="" width="18" height="18">
                        {{ t('properties_listing.filter_bar.search') }}
                    </button>
                </form>
            </div>
        </section>

        <section class="mw-dubai-toolbar">
            <div class="container-ctn">
                <div class="mw-dubai-toolbar__row">
                    <div class="mw-pill-tabs" data-tab-group>
                        <button type="button" class="is-active" data-tab>{{ t('properties_listing.toolbar.all') }}</button>
                        <button type="button" data-tab>{{ t('properties_listing.toolbar.ready') }}</button>
                        <button type="button" data-tab>{{ t('properties_listing.toolbar.off_plan') }}</button>
                    </div>
                    <div class="mw-dubai-toolbar__right">
                        <div class="mw-dropdown mw-dubai-toolbar__sort" data-dropdown>
                            <button type="button" class="mw-dubai-toolbar__sort-trigger" data-dropdown-trigger aria-haspopup="listbox">
                                <span data-dropdown-label>{{ t('properties_listing.toolbar.sort_most_recent') }}</span>
                                <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                            </button>
                            <ul class="mw-dropdown__menu" data-dropdown-menu>
                                <li><button type="button" class="is-selected" data-dropdown-option>{{ t('properties_listing.toolbar.sort_most_recent') }}</button></li>
                                <li><button type="button" data-dropdown-option>{{ t('properties_listing.toolbar.sort_price_low_high') }}</button></li>
                                <li><button type="button" data-dropdown-option>{{ t('properties_listing.toolbar.sort_price_high_low') }}</button></li>
                                <li><button type="button" data-dropdown-option>{{ t('properties_listing.toolbar.sort_most_popular') }}</button></li>
                            </ul>
                        </div>
                        <div class="mw-dubai-toolbar__views" role="group" :aria-label="t('properties_listing.toolbar.views_aria')">
                            <button type="button" class="mw-dubai-toolbar__view-btn is-active" data-properties-view="grid" :aria-label="t('properties_listing.toolbar.grid_view_aria')" aria-pressed="true">
                                <img src="/frontend/assets/images/agencies/icon-grid-view.svg" alt="" width="24" height="24">
                            </button>
                            <button type="button" class="mw-dubai-toolbar__view-btn" data-properties-view="list" :aria-label="t('properties_listing.toolbar.list_view_aria')" aria-pressed="false">
                                <img src="/frontend/assets/images/agencies/icon-list-view.svg" alt="" width="24" height="24">
                            </button>
                        </div>
                        <div class="mw-dubai-toolbar__actions">
                            <button type="button" class="quote-modal__btn quote-modal__btn--primary mw-dubai-toolbar__custom-request" @click="openCustomRequestModal">{{ t('properties_listing.toolbar.custom_request') }}</button>
                            <button type="button" class="mw-dubai-toolbar__save" :disabled="savingSearch" @click="saveSearch">{{ saveSearchLabel }}</button>
                            <button type="button" class="mw-dubai-toolbar__clear" @click="clearFilters">{{ t('properties_listing.toolbar.clear_filters') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-dubai-ticker" :aria-label="t('properties_listing.ticker.aria_label')">
            <div class="mw-dubai-ticker__viewport">
                <div class="mw-dubai-ticker__track" data-ticker-track>
                    <div class="mw-dubai-ticker__group">
                        <span class="mw-dubai-ticker__label">{{ t('properties_listing.ticker.recommended_searches') }}</span>
                        <router-link v-for="n in bedroomLinks" :key="'a-' + n" :to="{ path: '/properties', query: { bedrooms: n } }">{{ n }} {{ t('properties_listing.ticker.bedroom_link_suffix') }}</router-link>
                        <span class="mw-dubai-ticker__label">{{ t('properties_listing.ticker.invest_off_plan') }}</span>
                        <a href="#">1 {{ t('properties_listing.ticker.off_plan_link_suffix') }}</a>
                        <a href="#">2 {{ t('properties_listing.ticker.off_plan_link_suffix') }}</a>
                        <a href="#">3 {{ t('properties_listing.ticker.off_plan_link_suffix') }}</a>
                        <a href="#">4 {{ t('properties_listing.ticker.off_plan_link_suffix') }}</a>
                    </div>
                    <div class="mw-dubai-ticker__group" aria-hidden="true">
                        <span class="mw-dubai-ticker__label">{{ t('properties_listing.ticker.recommended_searches') }}</span>
                        <router-link v-for="n in bedroomLinks" :key="'b-' + n" :to="{ path: '/properties', query: { bedrooms: n } }" tabindex="-1">{{ n }} {{ t('properties_listing.ticker.bedroom_link_suffix') }}</router-link>
                        <span class="mw-dubai-ticker__label">{{ t('properties_listing.ticker.invest_off_plan') }}</span>
                        <a href="#" tabindex="-1">1 {{ t('properties_listing.ticker.off_plan_link_suffix') }}</a>
                        <a href="#" tabindex="-1">2 {{ t('properties_listing.ticker.off_plan_link_suffix') }}</a>
                        <a href="#" tabindex="-1">3 {{ t('properties_listing.ticker.off_plan_link_suffix') }}</a>
                        <a href="#" tabindex="-1">4 {{ t('properties_listing.ticker.off_plan_link_suffix') }}</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-dubai-listing">
            <div class="container-ctn">
                <div class="mw-dubai-listing__head">
                    <h2 class="mw-dubai-listing__title" data-reveal>{{ t('properties_listing.listing.title') }}</h2>
                    <p class="mw-dubai-listing__count" data-reveal>{{ pagination?.total ?? 0 }} {{ t('properties_listing.listing.count_suffix') }}</p>
                </div>

                <div class="mw-dubai-listing__grid" data-properties-grid>

                    <article v-for="property in properties" :key="property.slug" class="mw-dubai-card" data-reveal>
                        <div class="mw-projects__media" data-card-gallery role="link" tabindex="0" @click="$router.push(`/property-details/${property.slug}`)" @keydown.enter="$router.push(`/property-details/${property.slug}`)" style="cursor: pointer;">
                            <div class="mw-projects__slides" data-gallery-track>
                                <img v-for="(image, index) in property.images" :key="image" :src="image" :alt="index === 0 ? property.name : ''" class="mw-projects__photo" loading="lazy">
                            </div>
                            <button type="button" class="mw-dubai-card__fav" :class="{ 'is-saved': isWishlisted(property.id) }" :aria-label="t('properties_listing.listing.save_property_aria')" :aria-pressed="isWishlisted(property.id)" @click.stop="toggleWishlist(property.id)">
                                <img src="/frontend/assets/images/icons/heart.svg" alt="" width="18" height="18">
                            </button>
                            <button type="button" class="mw-projects__nav mw-projects__nav--prev" data-gallery-prev :aria-label="t('properties_listing.listing.previous_photo_aria')" @click.stop>
                                <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                            </button>
                            <button type="button" class="mw-projects__nav mw-projects__nav--next" data-gallery-next :aria-label="t('properties_listing.listing.next_photo_aria')" @click.stop>
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
                                    {{ t('properties_listing.listing.email') }}
                                </a>
                                <a href="tel:+971585899990" class="mw-dubai-card__contact-btn" @click.stop>
                                    <img src="/frontend/assets/images/icons/phone.svg" alt="" width="16" height="16">
                                    {{ t('properties_listing.listing.call_us') }}
                                </a>
                                <a href="https://wa.me/971585899990" class="mw-dubai-card__contact-btn" @click.stop>
                                    <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="16" height="16">
                                    {{ t('properties_listing.listing.whatsapp') }}
                                </a>
                            </div>
                        </div>
                    </article>

                    <EmptyState
                        v-if="propertiesListing && !properties.length"
                        :title="t('properties_listing.empty_state.title')"
                        :message="t('properties_listing.empty_state.message')"
                    >
                        <button type="button" class="mw-dubai-toolbar__clear" @click="clearFilters">{{ t('properties_listing.toolbar.clear_filters') }}</button>
                    </EmptyState>

                </div>

                <nav v-if="pagination && pagination.last_page > 1" class="mw-dubai-listing__pagination" :aria-label="t('properties_listing.listing.pagination_aria')">
                    <button type="button" class="mw-dubai-listing__page mw-dubai-listing__page--prev" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">{{ t('properties_listing.listing.previous') }}</button>
                    <button v-for="page in pageNumbers" :key="page" type="button" class="mw-dubai-listing__page" :class="{ 'is-active': page === currentPage }" @click="goToPage(page)">{{ page }}</button>
                    <button type="button" class="mw-dubai-listing__page mw-dubai-listing__page--next" :disabled="currentPage === pagination.last_page" @click="goToPage(currentPage + 1)">{{ t('properties_listing.listing.next') }}</button>
                </nav>
            </div>
        </section>

        <div v-if="showCustomRequestModal" class="quote-modal" role="dialog" aria-modal="true" :aria-label="t('custom_request.dialog_aria_label')">
            <div class="quote-modal__backdrop" @click="closeCustomRequestModal"></div>
            <div class="quote-modal__dialog">
                <div class="quote-modal__panel">
                    <div class="quote-modal__head">
                        <div class="crm-modal__heading">
                            <h3 class="quote-modal__title">{{ t('custom_request.title') }}</h3>
                            <p class="crm-modal__subtitle">{{ t('custom_request.subtitle') }}</p>
                        </div>
                        <button type="button" class="crm-modal__close" :aria-label="t('custom_request.close_aria')" @click="closeCustomRequestModal">
                            <svg viewBox="0 0 16 16" fill="none"><path d="M1 1l14 14M15 1L1 15" stroke="#414A66" stroke-width="1.5" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                    <form class="quote-modal__form crm-modal__form" @submit.prevent="submitCustomRequest">
                        <div class="quote-modal__fields">
                            <p v-if="customRequestFeedback" class="crm-modal__feedback" :class="customRequestFeedback.type === 'success' ? 'is-success' : 'is-error'">{{ customRequestFeedback.text }}</p>

                            <h4 class="crm-modal__section-title">{{ t('custom_request.section_personal_information') }}</h4>
                            <div class="crm-modal__row">
                                <label class="crm-modal__field">
                                    <span class="crm-modal__label">{{ t('custom_request.first_name_label') }}</span>
                                    <input type="text" v-model="customRequestForm.first_name" :placeholder="t('custom_request.first_name_placeholder')" required>
                                </label>
                                <label class="crm-modal__field">
                                    <span class="crm-modal__label">{{ t('custom_request.last_name_label') }}</span>
                                    <input type="text" v-model="customRequestForm.last_name" :placeholder="t('custom_request.last_name_placeholder')">
                                </label>
                            </div>
                            <div class="crm-modal__row">
                                <label class="crm-modal__field">
                                    <span class="crm-modal__label">{{ t('custom_request.email_label') }}</span>
                                    <input type="email" v-model="customRequestForm.email" :placeholder="t('custom_request.email_placeholder')" required>
                                </label>
                                <label class="crm-modal__field">
                                    <span class="crm-modal__label">{{ t('custom_request.phone_label') }}</span>
                                    <input type="tel" v-model="customRequestForm.phone" :placeholder="t('custom_request.phone_placeholder')">
                                </label>
                            </div>

                            <h4 class="crm-modal__section-title">{{ t('custom_request.section_property_requirements') }}</h4>
                            <div class="crm-modal__row">
                                <label class="crm-modal__field">
                                    <span class="crm-modal__label">{{ t('custom_request.property_category_label') }}</span>
                                    <select v-model="customRequestForm.property_category">
                                        <option value="">{{ t('custom_request.select_category') }}</option>
                                        <option value="apartment">{{ t('custom_request.category_apartment') }}</option>
                                        <option value="villa">{{ t('custom_request.category_villa') }}</option>
                                        <option value="townhouse">{{ t('custom_request.category_townhouse') }}</option>
                                        <option value="penthouse">{{ t('custom_request.category_penthouse') }}</option>
                                        <option value="commercial">{{ t('custom_request.category_commercial') }}</option>
                                    </select>
                                </label>
                                <label class="crm-modal__field">
                                    <span class="crm-modal__label">{{ t('custom_request.specification_label') }}</span>
                                    <input type="text" v-model="customRequestForm.specification" :placeholder="t('custom_request.specification_placeholder')">
                                </label>
                            </div>
                            <div class="crm-modal__row">
                                <label class="crm-modal__field">
                                    <span class="crm-modal__label">{{ t('custom_request.price_range_label') }}</span>
                                    <input type="text" v-model="customRequestForm.price_range" :placeholder="t('custom_request.price_range_placeholder')">
                                </label>
                                <label class="crm-modal__field">
                                    <span class="crm-modal__label">{{ t('custom_request.area_label') }}</span>
                                    <input type="text" v-model="customRequestForm.area" :placeholder="t('custom_request.area_placeholder')">
                                </label>
                            </div>
                            <label class="crm-modal__field crm-modal__field--full">
                                <span class="crm-modal__label">{{ t('custom_request.preferred_location_label') }}</span>
                                <input type="text" v-model="customRequestForm.preferred_location" :placeholder="t('custom_request.preferred_location_placeholder')">
                            </label>
                            <label class="crm-modal__field crm-modal__field--full">
                                <span class="crm-modal__label">{{ t('custom_request.additional_details_label') }}</span>
                                <textarea v-model="customRequestForm.additional_details" :placeholder="t('custom_request.additional_details_placeholder')"></textarea>
                            </label>

                            <h4 class="crm-modal__section-title">{{ t('custom_request.section_preferences') }}</h4>
                            <div class="crm-modal__row">
                                <label class="crm-modal__field">
                                    <span class="crm-modal__label">{{ t('custom_request.move_in_timeline_label') }}</span>
                                    <select v-model="customRequestForm.move_in_timeline">
                                        <option value="">{{ t('custom_request.select_timeline') }}</option>
                                        <option value="immediate">{{ t('custom_request.timeline_immediate') }}</option>
                                        <option value="1-3-months">{{ t('custom_request.timeline_1_3_months') }}</option>
                                        <option value="3-6-months">{{ t('custom_request.timeline_3_6_months') }}</option>
                                        <option value="6-12-months">{{ t('custom_request.timeline_6_12_months') }}</option>
                                        <option value="flexible">{{ t('custom_request.timeline_flexible') }}</option>
                                    </select>
                                </label>
                                <label class="crm-modal__field">
                                    <span class="crm-modal__label">{{ t('custom_request.furnishing_status_label') }}</span>
                                    <select v-model="customRequestForm.furnishing_status">
                                        <option value="">{{ t('custom_request.select_status') }}</option>
                                        <option value="furnished">{{ t('custom_request.furnishing_furnished') }}</option>
                                        <option value="unfurnished">{{ t('custom_request.furnishing_unfurnished') }}</option>
                                        <option value="semi-furnished">{{ t('custom_request.furnishing_semi_furnished') }}</option>
                                        <option value="no-preference">{{ t('custom_request.furnishing_no_preference') }}</option>
                                    </select>
                                </label>
                            </div>

                            <label class="crm-modal__checkbox">
                                <input type="checkbox" v-model="customRequestForm.whatsapp_consent">
                                <span class="crm-modal__checkbox-box"></span>
                                <span class="crm-modal__checkbox-text">
                                    <strong>{{ t('custom_request.whatsapp_consent_label') }}</strong>
                                    <small>{{ t('custom_request.whatsapp_consent_text') }}</small>
                                </span>
                            </label>
                        </div>
                        <div class="crm-modal__footer">
                            <button type="submit" class="quote-modal__btn crm-modal__submit" :disabled="customRequestSubmitting">
                                {{ customRequestSubmitting ? t('custom_request.submitting') : t('custom_request.submit') }}
                            </button>
                            <p class="crm-modal__terms">
                                {{ t('custom_request.terms_prefix') }}
                                <router-link to="/terms-and-conditions" @click="closeCustomRequestModal">{{ t('custom_request.terms_of_service') }}</router-link>
                                {{ t('custom_request.terms_and') }}
                                <router-link to="/privacy-policy" @click="closeCustomRequestModal">{{ t('custom_request.privacy_policy') }}</router-link>.
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</template>
