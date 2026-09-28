<script setup>
import { computed, defineAsyncComponent, nextTick, onMounted, ref, watch } from 'vue';
import { usePageWindow } from '../composables/usePageWindow';
import { useRoute, useRouter } from 'vue-router';
import { useCommercial } from '../composables/useCommercial';
import { useLanguages } from '../composables/useLanguages';
import { useStaticText } from '../composables/useStaticText';
import { useCurrency } from '../composables/useCurrency';
import { usePropertyFilters } from '../composables/usePropertyFilters';
import { useWishlist } from '../composables/useWishlist';
import EmptyState from '../components/EmptyState.vue';
import LocationAutocomplete from '../components/LocationAutocomplete.vue';
import { carryFilters, takeCarriedFilters } from '../composables/useListingCarry';
// Leaflet only loads when the map view is actually opened.
const PropertyMap = defineAsyncComponent(() => import('../components/PropertyMap.vue'));

const route = useRoute();
const router = useRouter();
// /commercial/map: same page and filters, results on a map instead of cards.
const mapView = computed(() => !!route.meta.mapView);
const mapParams = ref({});
const { commercialListing, fetchCommercialListing } = useCommercial();
const { selectedLanguage } = useLanguages();
const { authenticated } = useWishlist();
const { t } = useStaticText();
// Prices and the price filter are in AED; they're shown in the visitor's chosen currency.
const { formatPrice, formatCompact, presetLabel } = useCurrency();

// Property type options, price/area bounds and counts come from the live Commercial-menu listings
// (GET /api/property-filters?scope=commercial), so the dropdown only offers types that exist.
const { byKey: adminFilter, options: adminOptions } = usePropertyFilters('listing', 'commercial');

// --- Filter state --------------------------------------------------------------------------------
const locationQuery = ref('');
const searchQuery = ref('');
const propertyType = ref('');
const listingCategory = ref(''); // sale | rent | off_plan
const completion = ref('');      // ready | off_plan
const bathrooms = ref('');
const minPrice = ref('');
const maxPrice = ref('');
const minSqft = ref('');
const maxSqft = ref('');
const amenities = ref([]);
const sort = ref('default');
const currentPage = ref(1);

// Location box (commercial-only suggestions): free text searches `location`; a picked city or
// community filters exactly by it; a picked address opens that property on Search.
const cityFilter = ref('');
const communityFilter = ref('');
const locationInput = ref('');
const pickedPlace = ref(null);
function onPlacePicked(place) {
    pickedPlace.value = place;
    cityFilter.value = place?.type === 'city' ? place.label : '';
    communityFilter.value = place?.type === 'community' ? place.label : '';
    locationQuery.value = place ? '' : locationInput.value;
}
function submitSearch() {
    if (['address', 'property'].includes(pickedPlace.value?.type) && pickedPlace.value.slug) {
        router.push(`/property-details/${pickedPlace.value.slug}`);
        return;
    }
    // Typed text that wasn't picked from the suggestions still filters (partial match).
    if (!pickedPlace.value) locationQuery.value = locationInput.value;
    applyFilters();
}

// [query key, ref] for every string filter — drives the API params, the URL and Clear.
const FIELDS = [
    ['location', locationQuery], ['city', cityFilter], ['community', communityFilter],
    ['search', searchQuery], ['property_type', propertyType], ['category', listingCategory],
    ['completion_status', completion], ['bathrooms', bathrooms],
    ['min_price', minPrice], ['max_price', maxPrice], ['min_sqft', minSqft], ['max_sqft', maxSqft],
];

function load() {
    const params = { lang: selectedLanguage.value?.code, page: currentPage.value };
    FIELDS.forEach(([key, r]) => { const v = String(r.value ?? '').trim(); if (v) params[key] = v; });
    if (amenities.value.length) params.amenities = [...amenities.value].sort();
    if (sort.value !== 'default') params.sort = sort.value;
    const { lang, page, sort: _sort, ...filterParams } = params;
    mapParams.value = filterParams;
    // The map view loads its own pins (PropertyMap.vue) — no page of cards needed.
    if (!mapView.value) fetchCommercialListing(params);
}

// List ⇄ map toggle: hand the current filters to the other view (they aren't in the URL).
function carryToOtherView() {
    carryFilters('commercial', filterQuery());
}

/** The current filters as a URL query (no page) — for switching between the list and the map. */
function filterQuery() {
    const query = {};
    FIELDS.forEach(([key, r]) => { const v = String(r.value ?? '').trim(); if (v) query[key] = v; });
    if (amenities.value.length) query.amenities = [...amenities.value].sort().join(',');
    if (sort.value !== 'default') query.sort = sort.value;
    return query;
}

/** A filter change made on this page — filters stay in memory; any incoming ?query is cleared from the URL. */
function applyFilters() {
    currentPage.value = 1;
    load();
    if (Object.keys(route.query).length) router.replace({ query: {} });
}

function readQuery(query) {
    FIELDS.forEach(([key, r]) => { r.value = query[key] ? String(query[key]) : ''; });
    amenities.value = query.amenities ? String(query.amenities).split(',').filter(Boolean) : [];
    sort.value = query.sort ? String(query.sort) : 'default';
    currentPage.value = Math.max(1, parseInt(query.page, 10) || 1);
    locationInput.value = cityFilter.value || communityFilter.value || locationQuery.value;
    pickedPlace.value = null;
    syncPriceSlider();
}

function clearFilters() {
    FIELDS.forEach(([, r]) => { r.value = ''; });
    locationInput.value = '';
    pickedPlace.value = null;
    amenities.value = [];
    sort.value = 'default';
    syncPriceSlider();
    applyFilters();
}

function setAndApply(r, value) {
    r.value = value;
    applyFilters();
}
const selectPropertyType = (v) => setAndApply(propertyType, v);
const selectListingCategory = (v) => setAndApply(listingCategory, v);

// --- Options -------------------------------------------------------------------------------------
const humanize = (v) => String(v).replace(/[_-]+/g, ' ').replace(/^./, (c) => c.toUpperCase());
const propertyTypeOptions = computed(() => {
    const options = [{ value: '', label: t('commercial.filter_bar.all_type') }, ...adminOptions('property_type')];
    if (propertyType.value && !options.some((o) => o.value === propertyType.value)) {
        options.push({ value: propertyType.value, label: humanize(propertyType.value) });
    }
    return options;
});
const categoryOptions = computed(() => [
    { value: '', label: t('commercial.filter_bar.all_categories') },
    { value: 'sale', label: t('commercial.categories.buy') },
    { value: 'rent', label: t('commercial.categories.lease') },
    { value: 'off_plan', label: t('commercial.categories.off_plan') },
]);
const completionOptions = computed(() => [
    { value: '', label: t('commercial.filter_panel.any') },
    { value: 'ready', label: t('commercial.filter_panel.completion_ready', 'Ready') },
    { value: 'off_plan', label: t('commercial.filter_panel.completion_off_plan', 'Off-Plan') },
]);
const sortOptions = computed(() => [
    { value: 'default', label: t('commercial.filter_panel.sort_recommended', 'Recommended') },
    { value: 'recent', label: t('commercial.filter_panel.sort_recent', 'Most recent') },
    { value: 'price_asc', label: t('commercial.filter_panel.sort_price_low_high', 'Price: low to high') },
    { value: 'price_desc', label: t('commercial.filter_panel.sort_price_high_low', 'Price: high to low') },
]);
const labelFor = (options, value) => options.value.find((o) => o.value === value)?.label ?? humanize(value);
const propertyTypeLabel = computed(() => labelFor(propertyTypeOptions, propertyType.value));
const categoryLabel = computed(() => labelFor(categoryOptions, listingCategory.value));

const BATHROOM_OPTIONS = ['1', '2', '3', '4', '5+'];
const AMENITY_OPTIONS = ['furnished', 'parking', 'pantry', 'meeting-rooms', 'metro', 'security', 'central-ac', 'loading-bay', 'gym', 'concierge'];
const amenityLabel = (key) => t(`commercial.filter_panel.amenity_${key.replace(/-/g, '_')}`, humanize(key));

// --- Price slider (bounds = the most expensive live commercial listing) ---------------------------
const priceCeiling = computed(() => adminFilter('price')?.max || 30000000);
const priceStep = computed(() => adminFilter('price')?.step || 50000);
const priceLo = ref(0);
const priceHi = ref(30000000);
const pricePresets = computed(() => [
    [0, 500000, 'under_500k'], [500000, 1000000, '500k_1m'], [1000000, 2000000, '1m_2m'],
    [2000000, 5000000, '2m_5m'], [5000000, 10000000, '5m_10m'], [10000000, Infinity, '10m_plus'],
]
    .filter(([lo]) => lo < priceCeiling.value)
    .map(([lo, hi, key]) => [lo, Math.min(hi, priceCeiling.value), key, hi === Infinity]));

function syncPriceSlider() {
    priceLo.value = Number(minPrice.value) || 0;
    priceHi.value = Number(maxPrice.value) || priceCeiling.value;
}
watch(priceCeiling, syncPriceSlider);

function onPriceInput(which) {
    if (which === 'lo' && priceLo.value > priceHi.value) priceLo.value = priceHi.value;
    if (which === 'hi' && priceHi.value < priceLo.value) priceHi.value = priceLo.value;
}
function pickPricePreset([lo, hi]) {
    priceLo.value = lo;
    priceHi.value = hi;
}
const priceFillStyle = computed(() => ({
    left: `${(priceLo.value / priceCeiling.value) * 100}%`,
    width: `${((priceHi.value - priceLo.value) / priceCeiling.value) * 100}%`,
}));
// Filter values stay in AED; only the text is in the chosen currency.
const formatAed = (v) => formatCompact(v);

/** "Apply Filters" in the panel. */
function applyPanel() {
    minPrice.value = priceLo.value > 0 ? String(priceLo.value) : '';
    maxPrice.value = priceHi.value < priceCeiling.value ? String(priceHi.value) : '';
    submitSearch();
}

// How many of the panel-only filters are applied — shown as a badge on the filter button.
const panelFilterCount = computed(() => [completion, bathrooms, minPrice, maxPrice, minSqft, maxSqft]
    .filter((r) => r.value).length
    - (minPrice.value && maxPrice.value ? 1 : 0)
    - (minSqft.value && maxSqft.value ? 1 : 0)
    + amenities.value.length
    + (sort.value !== 'default' ? 1 : 0));
const activeFilterCount = computed(() => {
    let count = 0;
    if (locationQuery.value || cityFilter.value || communityFilter.value) count++;
    if (searchQuery.value) count++;
    if (propertyType.value) count++;
    if (listingCategory.value) count++;
    if (completion.value) count++;
    if (bathrooms.value) count++;
    if (minPrice.value || maxPrice.value) count++;
    if (minSqft.value || maxSqft.value) count++;
    count += amenities.value.length;
    if (sort.value !== 'default') count++;
    return count;
});
const hasAnyFilter = computed(() => activeFilterCount.value > 0);

const savingSearch = ref(false);
const searchSaved = ref(false);
const searchSaveError = ref(false);
const saveSearchLabel = computed(() => {
    if (savingSearch.value) return t('properties_listing.toolbar.saving');
    if (searchSaved.value) return t('properties_listing.toolbar.search_saved');
    if (searchSaveError.value) return t('properties_listing.toolbar.save_error');
    return t('properties_listing.toolbar.save_search');
});

function saveSearch() {
    if (!authenticated.value) {
        window.location.href = '/login';
        return;
    }
    if (savingSearch.value) return;

    const criteria = {};
    FIELDS.forEach(([key, field]) => {
        const value = String(field.value ?? '').trim();
        if (value) criteria[key] = value;
    });
    if (amenities.value.length) criteria.amenities = [...amenities.value].sort().join(',');
    if (sort.value !== 'default') criteria.sort = sort.value;

    savingSearch.value = true;
    searchSaveError.value = false;
    window.axios.post('/customer/saved-searches', {
        title: commercialListing.value?.title || t('commercial.hero.default_title'),
        criteria,
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
    document.querySelector('.mw-commercial')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const properties = computed(() => commercialListing.value?.properties || []);
const pagination = computed(() => commercialListing.value?.pagination || null);
const pageNumbers = usePageWindow(currentPage, computed(() => pagination.value?.last_page));
const resultsText = computed(() => {
    const total = pagination.value?.total ?? 0;
    return t('commercial.results_count', '{count} commercial properties').replace('{count}', total.toLocaleString());
});

onMounted(() => {
    // Filters arrive either in the URL (links such as /commercial?city=…&property_type=office) or
    // carried over from the list/map toggle; after that they live in memory only.
    const incoming = Object.keys(route.query).length ? route.query : (takeCarriedFilters('commercial') || {});
    readQuery(incoming);
    load();
});
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
            <h2 class="mw-filter-panel__title" id="commercialFilterLabel">{{ t('commercial.filter_panel.title') }}</h2>
            <button type="button" class="mw-filter-panel__close" data-bs-dismiss="offcanvas" :aria-label="t('commercial.filter_panel.close_aria')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="offcanvas-body mw-filter-panel__body">

            <!-- Location, Properties, Property Type and Category are in the top bar; on phones (where the
                 bar hides its fields) they live here instead. -->
            <section class="mw-filter-panel__section mw-filter-panel__section--basics mw-filter-panel__section--mobile-only">
                <label class="mw-filter-panel__field">
                    <span>{{ t('commercial.filter_panel.location_label') }}</span>
                    <LocationAutocomplete id="commercial-location-mobile" name="location" scope="commercial" v-model="locationInput" :placeholder="t('commercial.filter_panel.location_placeholder')" @select="onPlacePicked" />
                </label>
                <label class="mw-filter-panel__field">
                    <span>{{ t('commercial.filter_panel.properties_label') }}</span>
                    <input type="text" id="commercial-properties-mobile" name="properties" v-model="searchQuery" :placeholder="t('commercial.filter_panel.properties_placeholder')" autocomplete="off">
                </label>
            </section>

            <section class="mw-filter-panel__section mw-filter-panel__section--mobile-only">
                <h3 class="mw-filter-panel__section-title">{{ t('commercial.filter_panel.property_type_label') }}</h3>
                <div class="mw-filter-panel__select-wrap">
                    <select class="mw-filter-panel__select" :aria-label="t('commercial.filter_panel.property_type_label')" v-model="propertyType">
                        <option v-for="opt in propertyTypeOptions" :key="opt.value || 'all'" :value="opt.value">{{ opt.label }}{{ opt.count ? ` (${opt.count})` : '' }}</option>
                    </select>
                </div>
            </section>

            <section class="mw-filter-panel__section mw-filter-panel__section--mobile-only">
                <h3 class="mw-filter-panel__section-title">{{ t('commercial.filter_panel.category_label') }}</h3>
                <div class="mw-pill-tabs mw-pill-tabs--compact">
                    <button v-for="opt in categoryOptions" :key="opt.value || 'all'" type="button" data-tab :class="{ 'is-active': listingCategory === opt.value }" @click="listingCategory = opt.value">{{ opt.label }}</button>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">{{ t('commercial.filter_panel.price_title') }}</h3>
                <div class="mw-hero__price-readout">
                    <label class="mw-hero__price-input">
                        <span>{{ t('commercial.filter_panel.min_label') }}</span>
                        <input type="text" readonly :aria-label="t('commercial.filter_panel.min_aria')" :value="formatAed(priceLo)">
                    </label>
                    <label class="mw-hero__price-input">
                        <span>{{ t('commercial.filter_panel.max_label') }}</span>
                        <input type="text" readonly :aria-label="t('commercial.filter_panel.max_aria')" :value="priceHi >= priceCeiling ? `${formatAed(priceCeiling)}+` : formatAed(priceHi)">
                    </label>
                </div>
                <div class="mw-hero__price-slider">
                    <div class="mw-hero__price-track"></div>
                    <div class="mw-hero__price-fill" :style="priceFillStyle"></div>
                    <input type="range" name="min_price" min="0" :max="priceCeiling" :step="priceStep" v-model.number="priceLo" @input="onPriceInput('lo')" :aria-label="t('commercial.filter_panel.min_aria')">
                    <input type="range" name="max_price" min="0" :max="priceCeiling" :step="priceStep" v-model.number="priceHi" @input="onPriceInput('hi')" :aria-label="t('commercial.filter_panel.max_aria')">
                </div>
                <p class="mw-hero__price-presets-title">{{ t('commercial.filter_panel.quick_select') }}</p>
                <div class="mw-hero__price-presets">
                    <button v-for="p in pricePresets" :key="p[2]" type="button" :class="{ 'is-active': priceLo === p[0] && priceHi === p[1] }" @click="pickPricePreset(p)">{{ presetLabel(p[0], p[1], { top: p[3], baseLabel: t(`commercial.price_presets.${p[2]}`), under: t('commercial.price_presets.under_word', 'Under') }) }}</button>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">{{ t('commercial.filter_panel.bathrooms_title') }}</h3>
                <div class="mw-pill-tabs mw-pill-tabs--compact">
                    <button type="button" data-tab :class="{ 'is-active': !bathrooms }" @click="bathrooms = ''">{{ t('commercial.filter_panel.any') }}</button>
                    <button v-for="b in BATHROOM_OPTIONS" :key="b" type="button" data-tab :class="{ 'is-active': bathrooms === b }" @click="bathrooms = b">{{ b }}</button>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">{{ t('commercial.filter_panel.area_title') }}</h3>
                <div class="mw-filter-panel__range-inputs">
                    <label>
                        <span>{{ t('commercial.filter_panel.min_label') }}</span>
                        <input type="number" inputmode="numeric" min="0" step="50" v-model="minSqft" :placeholder="t('commercial.filter_panel.area_min_placeholder')" :aria-label="t('commercial.filter_panel.area_min_aria')">
                    </label>
                    <label>
                        <span>{{ t('commercial.filter_panel.max_label') }}</span>
                        <input type="number" inputmode="numeric" min="0" step="50" v-model="maxSqft" :placeholder="adminFilter('sqft')?.max ? String(adminFilter('sqft').max) : t('commercial.filter_panel.area_max_placeholder')" :aria-label="t('commercial.filter_panel.area_max_aria')">
                    </label>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">{{ t('commercial.filter_panel.completion_title', 'Completion') }}</h3>
                <div class="mw-pill-tabs mw-pill-tabs--compact">
                    <button v-for="opt in completionOptions" :key="opt.value || 'any'" type="button" data-tab :class="{ 'is-active': completion === opt.value }" @click="completion = opt.value">{{ opt.label }}</button>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">{{ t('commercial.filter_panel.sort_title', 'Sort by') }}</h3>
                <div class="mw-filter-panel__select-wrap">
                    <select class="mw-filter-panel__select" :aria-label="t('commercial.filter_panel.sort_title', 'Sort by')" v-model="sort">
                        <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">{{ t('commercial.filter_panel.amenities_title') }}</h3>
                <div class="mw-filter-panel__checks">
                    <label v-for="a in AMENITY_OPTIONS" :key="a" class="mw-filter-panel__check"><input type="checkbox" name="amenity" :value="a" v-model="amenities"><span>{{ amenityLabel(a) }}</span></label>
                </div>
            </section>

        </div>
        <div class="mw-filter-panel__foot">
            <button type="button" class="mw-filter-panel__reset" @click="clearFilters">{{ t('commercial.filter_panel.reset') }}</button>
            <button type="button" class="mw-filter-panel__apply" data-bs-dismiss="offcanvas" @click="applyPanel">{{ t('commercial.filter_panel.apply') }}</button>
        </div>
    </div>

    <main>
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ commercialListing?.title || t('commercial.hero.default_title') }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" :aria-label="t('commercial.hero.breadcrumb_aria')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('commercial.hero.breadcrumb_home') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ t('commercial.hero.breadcrumb_current') }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-commercial-filter">
            <div class="container-ctn">
                <form class="mw-commercial-filter__bar" role="search" @submit.prevent="submitSearch">
                    <div class="mw-commercial-filter__field" :class="{ 'is-filled': locationQuery || cityFilter || communityFilter }">
                        <label for="commercial-location">{{ t('commercial.filter_bar.location_label') }}</label>
                        <LocationAutocomplete id="commercial-location" name="location" scope="commercial" v-model="locationInput" :placeholder="t('commercial.filter_bar.location_placeholder')" @select="onPlacePicked" @enter="submitSearch" />
                    </div>
                    <div class="mw-commercial-filter__field" :class="{ 'is-filled': searchQuery }">
                        <label for="commercial-properties">{{ t('commercial.filter_bar.properties_label') }}</label>
                        <input type="text" id="commercial-properties" name="properties" :placeholder="t('commercial.filter_bar.properties_placeholder')" autocomplete="off" v-model="searchQuery" @keyup.enter="applyFilters">
                    </div>
                    <div class="mw-commercial-filter__field mw-commercial-filter__field--select mw-dropdown" :class="{ 'is-filled': propertyType }" data-dropdown>
                        <label>{{ t('commercial.filter_bar.property_type_label') }}</label>
                        <button type="button" class="mw-commercial-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label data-dropdown-label-reactive>{{ propertyTypeLabel }}</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-commercial-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li v-for="opt in propertyTypeOptions" :key="opt.value || 'all'">
                                <button type="button" :class="{ 'is-selected': opt.value === propertyType }" data-dropdown-option @click="selectPropertyType(opt.value)">
                                    {{ opt.label }}<span v-if="opt.count" class="mw-commercial-filter__count"> ({{ opt.count }})</span>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="mw-commercial-filter__field mw-commercial-filter__field--select mw-commercial-filter__field--last mw-dropdown" :class="{ 'is-filled': listingCategory }" data-dropdown>
                        <label>{{ t('commercial.filter_bar.category_label') }}</label>
                        <button type="button" class="mw-commercial-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label data-dropdown-label-reactive>{{ categoryLabel }}</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-commercial-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li v-for="opt in categoryOptions" :key="opt.value || 'all'"><button type="button" :class="{ 'is-selected': opt.value === listingCategory }" data-dropdown-option @click="selectListingCategory(opt.value)">{{ opt.label }}</button></li>
                        </ul>
                    </div>
                    <button type="button" class="mw-commercial-filter__filter-btn" :aria-label="t('commercial.filter_bar.more_filters_aria')" data-bs-toggle="offcanvas" data-bs-target="#commercial-filter-panel" aria-controls="commercial-filter-panel">
                        <img src="/frontend/assets/images/icons/filter.svg" alt="" width="24" height="24">
                        <span v-if="panelFilterCount > 0" class="mw-commercial-filter__badge">{{ panelFilterCount }}</span>
                    </button>
                    <button type="submit" class="mw-commercial-filter__search">
                        <img src="/frontend/assets/images/icons/search.svg" alt="" width="18" height="18">
                        {{ t('commercial.filter_bar.search') }}
                    </button>
                </form>
            </div>
        </section>

        <div v-if="pagination || mapView" class="mw-commercial-toolbar">
            <div class="container-ctn">
                <div class="mw-commercial-filter__actions">
                    <div class="mw-dubai-toolbar__views" role="group" :aria-label="t('properties_listing.toolbar.views_aria', 'View')">
                        <router-link to="/commercial" @click="carryToOtherView" class="mw-dubai-toolbar__view-btn" :class="{ 'is-active': !mapView }" :aria-label="t('properties_listing.toolbar.grid_view_aria', 'Grid view')" :aria-pressed="!mapView">
                            <img src="/frontend/assets/images/agencies/icon-grid-view.svg" alt="" width="24" height="24">
                        </router-link>
                        <router-link to="/commercial/map" @click="carryToOtherView" class="mw-dubai-toolbar__view-btn mw-map-toggle" :class="{ 'is-active': mapView }" :aria-label="t('map.map_view_aria', 'Map view')" :aria-pressed="mapView" :title="t('map.map_view_aria', 'Map view')">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4 3 6.5v13L9 17l6 2.5 6-2.5V4l-6 2.5z" /><path d="M9 4v13M15 6.5v13" /></svg>
                        </router-link>
                    </div>
                    <button type="button" class="mw-dubai-toolbar__save" :disabled="savingSearch" @click="saveSearch">{{ saveSearchLabel }}</button>
                    <button type="button" class="mw-dubai-toolbar__clear" :class="{ 'has-filters': hasAnyFilter }" @click="clearFilters">
                        {{ t('properties_listing.toolbar.clear_filters') }}<span v-if="activeFilterCount" class="mw-dubai-toolbar__clear-count">{{ activeFilterCount }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Map view: edge to edge, straight under the filter bar (no title / container). -->
        <section v-if="mapView" class="mw-map-section">
            <PropertyMap edge segment="commercial" :params="mapParams" />
        </section>

        <section v-else class="mw-commercial">
            <div class="container-ctn">
                <div class="mw-commercial__head">
                    <h2 class="mw-commercial__title">{{ commercialListing?.title || t('commercial.hero.default_title') }}</h2>
                    <p class="mw-commercial__count">{{ resultsText }}</p>
                </div>
                <div class="mw-commercial__grid">

                    <article v-for="property in properties" :key="property.slug" class="mw-commercial-card" data-reveal>
                        <div class="mw-projects__media" data-card-gallery role="link" tabindex="0" @click="$router.push(`/property-details/${property.slug}`)" @keydown.enter="$router.push(`/property-details/${property.slug}`)" style="cursor: pointer;">
                            <div class="mw-projects__slides" data-gallery-track>
                                <img v-for="(image, index) in property.images" :key="image" :src="image" :alt="index === 0 ? property.name : ''" class="mw-projects__photo" loading="lazy">
                            </div>
                            <div class="mw-commercial-card__badges">
                                <span v-if="property.verified" class="mw-badge mw-badge--success mw-commercial-card__verified">
                                    <img src="/frontend/assets/images/icons/verified.svg" alt="" width="16" height="16">
                                    {{ t('commercial.card.verified') }}
                                </span>
                                <span class="mw-commercial-card__cta-pill" :class="{ 'mw-commercial-card__cta-pill--sell': property.cta_variant === 'sell' }">{{ property.cta }}</span>
                            </div>
                            <button type="button" class="mw-projects__nav mw-projects__nav--prev" data-gallery-prev :aria-label="t('commercial.card.previous_photo_aria')" @click.stop>
                                <img src="/frontend/assets/images/icons/chevron.svg" alt="">
                            </button>
                            <button type="button" class="mw-projects__nav mw-projects__nav--next" data-gallery-next :aria-label="t('commercial.card.next_photo_aria')" @click.stop>
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
                                <span v-if="property.service" class="mw-commercial-card__service">{{ property.service_value ? `+${formatPrice(property.service_value)} service` : property.service }}</span>
                                <span class="mw-commercial-card__price">{{ formatPrice(property.price_value, property.price) }}</span>
                            </div>
                            <h3 class="mw-commercial-card__title"><router-link :to="`/property-details/${property.slug}`">{{ property.name }}</router-link></h3>
                            <p class="mw-commercial-card__location">
                                <img src="/frontend/assets/images/icons/location.svg" alt="" width="16" height="16">
                                {{ property.location }}
                            </p>
                            <p class="mw-commercial-card__desc">{{ property.description }}</p>
                            <div class="mw-commercial-card__meta-row">
                                <span>{{ t('commercial.card.floor_label') }}: {{ property.floor ?? '—' }}</span>
                                <span>{{ t('commercial.card.rera_label') }}: {{ property.rera_id }}</span>
                            </div>
                            <div class="mw-commercial-card__stats">
                                <span v-if="property.beds" class="mw-commercial-card__stat"><img src="/frontend/assets/images/icons/bed.svg" alt="" width="16" height="16">{{ property.beds }}</span>
                                <span v-if="property.baths" class="mw-commercial-card__stat"><img src="/frontend/assets/images/icons/bathroom.svg" alt="" width="16" height="16">{{ property.baths }}</span>
                                <span class="mw-commercial-card__stat"><img src="/frontend/assets/images/icons/area.svg" alt="" width="16" height="16">{{ property.area }}</span>
                            </div>
                            <div class="mw-commercial-card__amenities">
                                <span v-for="amenity in property.amenities.slice(0, 4)" :key="amenity" class="mw-commercial-card__amenity">{{ amenity }}</span>
                                <span v-if="amenityOverflow(property.amenities)" class="mw-commercial-card__amenity mw-commercial-card__amenity--more">+{{ amenityOverflow(property.amenities) }} {{ t('commercial.card.more_amenities_suffix') }}</span>
                            </div>
                            <div class="mw-commercial-card__divider"></div>
                            <div class="mw-commercial-card__contacts">
                                <a href="tel:+971585899990" class="mw-commercial-card__contact-btn">
                                    <img src="/frontend/assets/images/icons/phone.svg" alt="" width="16" height="16">
                                    {{ t('commercial.card.call_us') }}
                                </a>
                                <a href="https://wa.me/971585899990" class="mw-commercial-card__contact-btn">
                                    <img src="/frontend/assets/images/icons/whatsapp.svg" alt="" width="16" height="16">
                                    {{ t('commercial.card.whatsapp') }}
                                </a>
                                <a href="mailto:info@mightywarnersrealty.com" class="mw-commercial-card__contact-btn">
                                    <img src="/frontend/assets/images/icons/email.svg" alt="" width="16" height="16">
                                    {{ t('commercial.card.email') }}
                                </a>
                            </div>
                        </div>
                    </article>

                    <EmptyState
                        v-if="commercialListing && !properties.length"
                        :title="t('commercial.empty_state.title')"
                        :message="t('commercial.empty_state.message')"
                    >
                        <button type="button" class="mw-dubai-toolbar__clear" @click="clearFilters">{{ t('commercial.clear_filters') }}</button>
                    </EmptyState>

                </div>

                <nav v-if="pagination && pagination.last_page > 1" class="mw-blog__pagination" :aria-label="t('commercial.pagination_aria')">
                    <button type="button" class="mw-blog__page mw-blog__page--prev" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">{{ t('commercial.previous') }}</button>
                    <button v-for="page in pageNumbers" :key="page" type="button" class="mw-blog__page" :class="{ 'is-active': page === currentPage }" @click="goToPage(page)">{{ page }}</button>
                    <button type="button" class="mw-blog__page mw-blog__page--next" :disabled="currentPage === pagination.last_page" @click="goToPage(currentPage + 1)">{{ t('commercial.next') }}</button>
                </nav>
            </div>
        </section>
    </main>
</template>
