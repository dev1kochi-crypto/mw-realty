<script setup>
import { computed, nextTick, onMounted, reactive, ref, toRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useProperties } from '../composables/useProperties';
import { useLanguages } from '../composables/useLanguages';
import { useWishlist } from '../composables/useWishlist';
import { useRecaptcha } from '../composables/useRecaptcha';
import { useStaticText } from '../composables/useStaticText';
import { usePropertyFilters } from '../composables/usePropertyFilters';
import EmptyState from '../components/EmptyState.vue';
import LocationAutocomplete from '../components/LocationAutocomplete.vue';

const route = useRoute();
const router = useRouter();
const { propertiesListing, fetchPropertiesListing } = useProperties();
const { isWishlisted, toggleWishlist, authenticated } = useWishlist();
const { selectedLanguage } = useLanguages();
const { getRecaptchaToken } = useRecaptcha();
const { t } = useStaticText();

// --- Filters -------------------------------------------------------------------------------------
// Options, labels and which filters exist all come from Admin > Filters ("Show on: Listing") via
// /api/property-filters. URL/API parameters use each filter's key: select filters as ?{key}=value,
// range filters as ?min_{key}=&max_{key}= (see Api\PropertiesController).
// /premium-properties reuses this page for every premium (CRM-featured) listing — residential and
// commercial together (API ?premium=1); the filter options follow that same set of listings.
const premium = !!route.meta.premium;
const { filters: adminFilters, loaded: filtersLoaded, byKey: adminFilter, options: adminOptions } = usePropertyFilters('listing', premium ? 'premium' : 'residential');
// A top-bar field shows while the filters load, then only if the admin has that filter enabled.
const showFilter = (key) => !filtersLoaded.value || !!adminFilter(key);

// Filters with their own control in the top bar / toolbar get a named ref…
const listingType = ref('');
const locationQuery = ref('');
const propertyType = ref('');
const bedroomsFilter = ref('');
const bathroomsFilter = ref('');
const completion = ref('');
// …any other admin select filter (e.g. Category: residential/commercial) lives here, keyed by filter key.
const extraSelect = reactive({});
const SELECT_BINDINGS = {
    listing_type: listingType, location: locationQuery, property_type: propertyType,
    bedrooms: bedroomsFilter, bathrooms: bathroomsFilter, completion_status: completion,
};
const selectRef = (key) => SELECT_BINDINGS[key] || toRef(extraSelect, key);
// Register extra admin select filters up front (not during render) so they're part of the state.
watch(adminFilters, (list) => {
    list.forEach((f) => {
        if (f.type === 'select' && !SELECT_BINDINGS[f.key] && !(f.key in extraSelect)) extraSelect[f.key] = '';
    });
}, { immediate: true });

// Location box: free text searches `location`; a picked suggestion filters exactly by `city` or
// `community`, and a picked address opens that property. `locationInput` is what the box shows.
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
/** Search button / Enter in the location box. */
function submitSearch() {
    if (pickedPlace.value?.type === 'address' && pickedPlace.value.slug) {
        router.push(`/property-details/${pickedPlace.value.slug}`);
        return;
    }
    applyFilters();
}

const minPrice = ref('');
const maxPrice = ref('');
const minSqft = ref('');
const maxSqft = ref('');
// Not admin filters: sorting, amenities (free-text on each property) and the floor-plan toggle.
const sort = ref('default');
const amenities = ref([]);
const floorPlans = ref(false);
const currentPage = ref(1);

// Every string filter, as [queryKey, ref] — the fixed ones plus whatever extra admin filters are in play.
function fields() {
    return [
        ...Object.entries(SELECT_BINDINGS),
        ['city', cityFilter], ['community', communityFilter],
        ['min_price', minPrice], ['max_price', maxPrice], ['min_sqft', minSqft], ['max_sqft', maxSqft],
        ...Object.keys(extraSelect).map((k) => [k, toRef(extraSelect, k)]),
    ];
}
const NON_FILTER_QUERY = ['sort', 'amenities', 'floor_plans', 'page'];

// Snapshot of the filters the current results were actually fetched with — the Clear Filters count
// and the heading read this, so half-typed input in the Location box doesn't count as "applied".
const applied = reactive({});

function snapshot() {
    const s = {};
    fields().forEach(([key, r]) => { s[key] = String(r.value ?? '').trim(); });
    s.sort = sort.value;
    s.amenities = [...amenities.value].sort();
    s.floor_plans = floorPlans.value;
    return s;
}

function currentQuery() {
    const query = {};
    fields().forEach(([key]) => { if (applied[key]) query[key] = applied[key]; });
    if (applied.sort && applied.sort !== 'default') query.sort = applied.sort;
    if (applied.amenities?.length) query.amenities = applied.amenities.join(',');
    if (applied.floor_plans) query.floor_plans = '1';
    return query;
}

function load() {
    Object.keys(applied).forEach((k) => delete applied[k]);
    Object.assign(applied, snapshot());
    const params = { lang: selectedLanguage.value?.code, page: currentPage.value, sort: applied.sort };
    fields().forEach(([key]) => { if (applied[key]) params[key] = applied[key]; });
    if (applied.amenities.length) params.amenities = applied.amenities;
    if (applied.floor_plans) params.floor_plans = 1;
    if (premium) params.premium = 1;
    fetchPropertiesListing(params);
    // Keep the URL in step with the filters, so the view can be shared/bookmarked and Back works.
    const query = currentQuery();
    if (JSON.stringify(query) !== JSON.stringify(route.query)) router.replace({ query });
}

function applyFilters() {
    currentPage.value = 1;
    load();
}

/** Loads filter state from the URL (first visit, Home search/shortcuts, ticker links, saved searches, Back/Forward). */
function readQuery(rawQuery) {
    const query = { ...rawQuery };
    // Older links: category=sale|rent|off_plan meant purpose, completion= and min_area/max_area= were renamed.
    if (['sale', 'rent'].includes(query.category)) { query.listing_type ??= query.category; delete query.category; }
    if (query.category === 'off_plan') { query.completion_status ??= 'off_plan'; delete query.category; }
    if (query.completion) { query.completion_status ??= query.completion; delete query.completion; }
    if (query.min_area) { query.min_sqft ??= query.min_area; delete query.min_area; }
    if (query.max_area) { query.max_sqft ??= query.max_area; delete query.max_area; }

    Object.keys(extraSelect).forEach((k) => delete extraSelect[k]);
    fields().forEach(([key, r]) => { r.value = query[key] ? String(query[key]) : ''; });
    // Any other key is an admin select filter (e.g. category=commercial).
    Object.keys(query)
        .filter((k) => !NON_FILTER_QUERY.includes(k) && !fields().some(([f]) => f === k))
        .forEach((k) => { extraSelect[k] = String(query[k]); });
    sort.value = query.sort ? String(query.sort) : 'default';
    amenities.value = query.amenities ? String(query.amenities).split(',').filter(Boolean) : [];
    floorPlans.value = query.floor_plans === '1';
    locationInput.value = cityFilter.value || communityFilter.value || locationQuery.value;
    pickedPlace.value = null;
    syncPriceSlider();
}

// --- Options (all from the admin filters) ---
const humanize = (v) => String(v).replace(/[_-]+/g, ' ').replace(/^./, (c) => c.toUpperCase());
/** An admin select filter's options with an "any" entry first; keeps an unknown current value selectable. */
function optionsFor(key, anyLabel, current) {
    const options = [{ value: '', label: anyLabel }, ...adminOptions(key)];
    if (current && !options.some((o) => o.value === current)) options.push({ value: current, label: humanize(current) });
    return options;
}
const listingTypeOptions = computed(() => optionsFor('listing_type', t('properties_listing.filter_bar.any_purpose', 'Any'), listingType.value));
const completionOptions = computed(() => optionsFor('completion_status', t('properties_listing.toolbar.all'), completion.value));
const propertyTypeOptions = computed(() => optionsFor('property_type', t('properties_listing.filter_bar.all_type'), propertyType.value));
const bedroomOptions = computed(() => optionsFor('bedrooms', t('properties_listing.filter_bar.select_bedrooms'), bedroomsFilter.value));
const bathroomOptions = computed(() => optionsFor('bathrooms', t('properties_listing.filter_bar.select_bathrooms'), bathroomsFilter.value));
const labelFor = (options, value) => options.value.find((o) => o.value === value)?.label ?? value;
const listingTypeLabel = computed(() => labelFor(listingTypeOptions, listingType.value));
const propertyTypeLabel = computed(() => labelFor(propertyTypeOptions, propertyType.value));
const bedroomsLabel = computed(() => labelFor(bedroomOptions, bedroomsFilter.value));
const bathroomsLabel = computed(() => labelFor(bathroomOptions, bathroomsFilter.value));

// "More filters" panel: every select filter the admin shows on the listing page, in admin order.
const panelSelectFilters = computed(() => adminFilters.value.filter((f) => f.type === 'select' && f.options?.length));

// How each admin select filter appears in the "More filters" panel:
// - already in the top bar → shown in the panel on phones only (the bar hides its fields ≤767px);
// - covered at every size (Location box, toolbar Completion tabs) → never repeated in the panel;
// - short fixed choices → compact buttons; longer lists (Property Type, Category, …) → a dropdown.
const TOP_BAR_KEYS = ['listing_type', 'property_type', 'bedrooms', 'bathrooms'];
const PANEL_SKIP_KEYS = ['location', 'completion_status'];
const PANEL_PILL_KEYS = ['listing_type', 'bedrooms', 'bathrooms'];
const panelFilter = (f) => ({
    show: f.type === 'select' && f.options?.length > 0 && !PANEL_SKIP_KEYS.includes(f.key),
    mobileOnly: TOP_BAR_KEYS.includes(f.key),
    pills: PANEL_PILL_KEYS.includes(f.key),
});
// Panel order (the original panel design): Purpose, Property Type (phones), Price, Bedrooms,
// Bathrooms (phones), Area, then any other admin filter in the admin's order (e.g. Category).
const PANEL_ORDER = ['listing_type', 'property_type', 'price', 'bedrooms', 'bathrooms', 'sqft'];
const panelFilters = computed(() => {
    const rank = (f) => { const i = PANEL_ORDER.indexOf(f.key); return i === -1 ? PANEL_ORDER.length : i; };
    return adminFilters.value.map((f, i) => ({ f, i })).sort((a, b) => rank(a.f) - rank(b.f) || a.i - b.i).map(({ f }) => f);
});
const sortOptions = computed(() => [
    { value: 'default', label: t('properties_listing.toolbar.sort_recommended', 'Recommended') },
    { value: 'recent', label: t('properties_listing.toolbar.sort_most_recent') },
    { value: 'price_asc', label: t('properties_listing.toolbar.sort_price_low_high') },
    { value: 'price_desc', label: t('properties_listing.toolbar.sort_price_high_low') },
    { value: 'popular', label: t('properties_listing.toolbar.sort_most_popular') },
]);
const sortLabel = computed(() => labelFor(sortOptions, sort.value));
const AMENITY_OPTIONS = ['furnished', 'community-pool', 'parking', 'gym', 'security', 'balcony', 'concierge', 'private-pool'];
const amenityLabel = (key) => t(`properties_listing.filter_panel.amenity_${key.replace(/-/g, '_')}`, humanize(key));

// --- Price slider (range bounds from the admin "price" filter = the live listings' max) ---
const priceFilter = computed(() => adminFilter('price'));
const sqftFilter = computed(() => adminFilter('sqft'));
const priceCeiling = computed(() => priceFilter.value?.max || 30000000);
const priceStep = computed(() => priceFilter.value?.step || 50000);
// Panel slider draft (applied on "Apply"); the thumbs can't cross each other.
const priceLo = ref(0);
const priceHi = ref(30000000);
const pricePresets = computed(() => [
    [0, 500000, 'under_500k'], [500000, 1000000, '500k_1m'], [1000000, 2000000, '1m_2m'],
    [2000000, 5000000, '2m_5m'], [5000000, 10000000, '5m_10m'], [10000000, Infinity, '10m_plus'],
]
    .filter(([lo]) => lo < priceCeiling.value)
    .map(([lo, hi, key]) => [lo, Math.min(hi, priceCeiling.value), key]));

/** Puts the panel's price slider back in line with the applied price filter. */
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

const formatAed = (v) => {
    const n = Number(v);
    return 'AED ' + (n >= 1e6 ? `${+(n / 1e6).toFixed(2)}M` : n >= 1e3 ? `${+(n / 1e3).toFixed(0)}K` : n);
};

/** "Apply" in the More filters panel. */
function applyPanel() {
    minPrice.value = priceLo.value > 0 ? String(priceLo.value) : '';
    maxPrice.value = priceHi.value < priceCeiling.value ? String(priceHi.value) : '';
    submitSearch();
}

// --- Applied-filter summary (Clear Filters count + tooltip) ---
const activeFilters = computed(() => {
    const chips = [];
    const add = (key, label, text) => chips.push({ key, label, text });
    adminFilters.value.filter((f) => f.type === 'select').forEach((f) => {
        const value = applied[f.key];
        if (value) add(f.key, f.label, (f.options || []).find((o) => o.value === value)?.label ?? humanize(value));
    });
    // Free-text location (or a filter the admin has since switched off) still counts.
    [...Object.keys(SELECT_BINDINGS), 'city', 'community', ...Object.keys(extraSelect)].forEach((key) => {
        if (applied[key] && !chips.some((c) => c.key === key)) add(key, humanize(key), applied[key]);
    });
    if (applied.min_price || applied.max_price) {
        add('price', priceFilter.value?.label || t('properties_listing.filter_panel.price_title'), applied.min_price && applied.max_price
            ? `${formatAed(applied.min_price)} – ${formatAed(applied.max_price)}`
            : applied.min_price ? `${formatAed(applied.min_price)}+` : `≤ ${formatAed(applied.max_price)}`);
    }
    if (applied.min_sqft || applied.max_sqft) {
        add('sqft', sqftFilter.value?.label || 'Area', applied.min_sqft && applied.max_sqft
            ? `${applied.min_sqft} – ${applied.max_sqft} sq.ft` : applied.min_sqft ? `${applied.min_sqft}+ sq.ft` : `≤ ${applied.max_sqft} sq.ft`);
    }
    (applied.amenities || []).forEach((a) => add(`amenity:${a}`, t('properties_listing.filter_panel.amenities_title', 'Amenity'), amenityLabel(a)));
    if (applied.floor_plans) add('floor_plans', t('properties_listing.filter_panel.features_title', 'Feature'), t('properties_listing.filter_panel.feature_floor_plans', 'Floor plans'));
    return chips;
});
const filterCount = computed(() => activeFilters.value.length);

// Hero title + breadcrumb.
const pageTitle = computed(() => (premium ? t('premium_listing.hero_title', 'Premium Properties') : t('properties_listing.hero.title')));

// "Penthouse Properties for Rent in Dubai" — the heading says what is actually being shown.
const listingTitle = computed(() => {
    const type = applied.property_type ? `${labelFor(propertyTypeOptions, applied.property_type)} ` : '';
    const purpose = { sale: ' for Sale', rent: ' for Rent' }[applied.listing_type] ?? '';
    const offPlan = applied.completion_status === 'off_plan' ? 'Off-Plan ' : '';
    if (premium) {
        return (!type && !purpose && !offPlan)
            ? t('premium_listing.all_title', 'All Premium Properties')
            : `Premium ${offPlan}${type}Properties${purpose}`;
    }
    if (!type && !purpose && !offPlan) return t('properties_listing.listing.all_title', 'All Properties in Dubai');
    if (!type && !offPlan && applied.listing_type === 'sale') return t('properties_listing.listing.title');
    return `${offPlan}${type}Properties${purpose} in Dubai`;
});

// Top-bar dropdowns and toolbar controls apply straight away; the panel applies on "Apply".
function setAndApply(r, value) {
    r.value = value;
    applyFilters();
}
const selectListingType = (v) => setAndApply(listingType, v);
const selectPropertyType = (v) => setAndApply(propertyType, v);
const selectBedrooms = (v) => setAndApply(bedroomsFilter, v);
const selectBathrooms = (v) => setAndApply(bathroomsFilter, v);
const selectCompletion = (v) => setAndApply(completion, v);
const selectSort = (v) => setAndApply(sort, v);

function clearFilters() {
    fields().forEach(([, r]) => { r.value = ''; });
    locationInput.value = '';
    pickedPlace.value = null;
    sort.value = 'default';
    amenities.value = [];
    floorPlans.value = false;
    syncPriceSlider();
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
    const extras = [applied.bedrooms && `${applied.bedrooms} Bed`, applied.location].filter(Boolean);
    return [listingTitle.value, ...extras].join(' · ');
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
        // Exactly the listing's query string, so "View results" in the Profile reopens this search.
        criteria: currentQuery(),
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
    readQuery(route.query);
    load();
});
// Same-page navigation (the ticker's "N Bedroom" links, Back/Forward) doesn't remount the page,
// so re-read the URL whenever it no longer matches the filters on screen.
watch(() => route.query, (query) => {
    if (JSON.stringify(query) === JSON.stringify(currentQuery())) return;
    readQuery(query);
    applyFilters();
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

            <!-- The top bar has its own Location box; on phones (where the bar's fields are hidden) it lives here. -->
            <section class="mw-filter-panel__section mw-filter-panel__section--mobile-only">
                <label class="mw-filter-panel__field">
                    <span>{{ t('properties_listing.filter_panel.location_label') }}</span>
                    <LocationAutocomplete id="dubai-location-mobile" name="location" v-model="locationInput" :placeholder="t('properties_listing.filter_panel.location_placeholder')" @select="onPlacePicked" />
                </label>
            </section>

            <!-- Admin filters (Admin > Filters, "Show on: Listing") in the admin's order — minus the ones
                 the top bar / toolbar already has (see panelFilter()). -->
            <template v-for="f in panelFilters" :key="f.key">
                <section v-if="panelFilter(f).show" class="mw-filter-panel__section" :class="{ 'mw-filter-panel__section--mobile-only': panelFilter(f).mobileOnly }">
                    <h3 class="mw-filter-panel__section-title">{{ f.label }}</h3>
                    <div v-if="panelFilter(f).pills" class="mw-pill-tabs mw-pill-tabs--compact">
                        <button type="button" data-tab :class="{ 'is-active': !selectRef(f.key).value }" @click="selectRef(f.key).value = ''">{{ t('properties_listing.filter_panel.any') }}</button>
                        <button v-for="opt in f.options" :key="opt.value" type="button" data-tab :class="{ 'is-active': selectRef(f.key).value === opt.value }" @click="selectRef(f.key).value = opt.value">{{ opt.label }}</button>
                    </div>
                    <div v-else class="mw-filter-panel__select-wrap">
                        <select class="mw-filter-panel__select" :aria-label="f.label" :value="selectRef(f.key).value" @change="selectRef(f.key).value = $event.target.value">
                            <option value="">{{ t('properties_listing.filter_panel.any') }}</option>
                            <option v-for="opt in f.options" :key="opt.value" :value="opt.value">{{ opt.label }}{{ opt.count ? ` (${opt.count})` : '' }}</option>
                        </select>
                    </div>
                </section>

                <section v-else-if="f.key === 'price'" class="mw-filter-panel__section">
                    <h3 class="mw-filter-panel__section-title">{{ f.label }}</h3>
                    <div class="mw-hero__price-readout">
                        <label class="mw-hero__price-input">
                            <span>{{ t('properties_listing.filter_panel.min_label') }}</span>
                            <input type="text" readonly :aria-label="t('properties_listing.filter_panel.min_aria')" :value="formatAed(priceLo)">
                        </label>
                        <label class="mw-hero__price-input">
                            <span>{{ t('properties_listing.filter_panel.max_label') }}</span>
                            <input type="text" readonly :aria-label="t('properties_listing.filter_panel.max_aria')" :value="priceHi >= priceCeiling ? `${formatAed(priceCeiling)}+` : formatAed(priceHi)">
                        </label>
                    </div>
                    <div class="mw-hero__price-slider">
                        <div class="mw-hero__price-track"></div>
                        <div class="mw-hero__price-fill" :style="priceFillStyle"></div>
                        <input type="range" name="min_price" min="0" :max="priceCeiling" :step="priceStep" v-model.number="priceLo" @input="onPriceInput('lo')" :aria-label="t('properties_listing.filter_panel.min_aria')">
                        <input type="range" name="max_price" min="0" :max="priceCeiling" :step="priceStep" v-model.number="priceHi" @input="onPriceInput('hi')" :aria-label="t('properties_listing.filter_panel.max_aria')">
                    </div>
                    <p class="mw-hero__price-presets-title">{{ t('properties_listing.filter_panel.quick_select') }}</p>
                    <div class="mw-hero__price-presets">
                        <button v-for="p in pricePresets" :key="p[2]" type="button" :class="{ 'is-active': priceLo === p[0] && priceHi === p[1] }" @click="pickPricePreset(p)">{{ t(`properties_listing.price_presets.${p[2]}`) }}</button>
                    </div>
                </section>

                <section v-else-if="f.key === 'sqft'" class="mw-filter-panel__section">
                    <h3 class="mw-filter-panel__section-title">{{ f.label }}</h3>
                    <div class="mw-filter-panel__range-inputs">
                        <label>
                            <span>{{ t('properties_listing.filter_panel.min_label') }}</span>
                            <input type="number" inputmode="numeric" min="0" :max="f.max" :step="f.step" v-model="minSqft" :placeholder="t('properties_listing.filter_panel.area_min_placeholder')" :aria-label="t('properties_listing.filter_panel.area_min_aria')">
                        </label>
                        <label>
                            <span>{{ t('properties_listing.filter_panel.max_label') }}</span>
                            <input type="number" inputmode="numeric" min="0" :max="f.max" :step="f.step" v-model="maxSqft" :placeholder="t('properties_listing.filter_panel.area_max_placeholder')" :aria-label="t('properties_listing.filter_panel.area_max_aria')">
                        </label>
                    </div>
                </section>
            </template>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">{{ t('properties_listing.filter_panel.features_title') }}</h3>
                <div class="mw-filter-panel__checks">
                    <label class="mw-filter-panel__check"><input type="checkbox" name="property-feature" value="floor-plans" v-model="floorPlans"><span>{{ t('properties_listing.filter_panel.feature_floor_plans') }}</span></label>
                </div>
            </section>

            <section class="mw-filter-panel__section">
                <h3 class="mw-filter-panel__section-title">{{ t('properties_listing.filter_panel.amenities_title') }}</h3>
                <div class="mw-filter-panel__checks">
                    <label v-for="a in AMENITY_OPTIONS" :key="a" class="mw-filter-panel__check"><input type="checkbox" name="amenity" :value="a" v-model="amenities"><span>{{ amenityLabel(a) }}</span></label>
                </div>
            </section>

        </div>
        <div class="mw-filter-panel__foot">
            <button type="button" class="mw-filter-panel__reset" @click="clearFilters">{{ t('properties_listing.filter_panel.reset') }}</button>
            <button type="button" class="mw-filter-panel__apply" data-bs-dismiss="offcanvas" @click="applyPanel">{{ t('properties_listing.filter_panel.apply') }}</button>
        </div>
    </div>

    <main>
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ pageTitle }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" :aria-label="t('properties_listing.hero.breadcrumb_aria')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('properties_listing.hero.breadcrumb_home') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ pageTitle }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-dubai-filter">
            <div class="container-ctn">
                <form class="mw-dubai-filter__bar" role="search" @submit.prevent="submitSearch">
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dropdown" v-if="showFilter('listing_type')" :class="{ 'is-filled': listingType }" data-dropdown>
                        <label>{{ t('properties_listing.filter_bar.purpose_label') }}</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label data-dropdown-label-reactive>{{ listingTypeLabel }}</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li v-for="opt in listingTypeOptions" :key="opt.value"><button type="button" :class="{ 'is-selected': opt.value === listingType }" data-dropdown-option @click="selectListingType(opt.value)">{{ opt.label }}</button></li>
                        </ul>
                    </div>
                    <div class="mw-dubai-filter__field" :class="{ 'is-filled': applied.location || applied.city || applied.community }">
                        <label for="dubai-location">{{ t('properties_listing.filter_bar.location_label') }}</label>
                        <LocationAutocomplete id="dubai-location" name="location" v-model="locationInput" :placeholder="t('properties_listing.filter_bar.location_placeholder')" @select="onPlacePicked" @enter="submitSearch" />
                    </div>
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dropdown" v-if="showFilter('property_type')" :class="{ 'is-filled': propertyType }" data-dropdown>
                        <label>{{ t('properties_listing.filter_bar.property_type_label') }}</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label data-dropdown-label-reactive>{{ propertyTypeLabel }}</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li v-for="opt in propertyTypeOptions" :key="opt.value"><button type="button" :class="{ 'is-selected': opt.value === propertyType }" data-dropdown-option @click="selectPropertyType(opt.value)">{{ opt.label }}</button></li>
                        </ul>
                    </div>
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dropdown" v-if="showFilter('bedrooms')" :class="{ 'is-filled': bedroomsFilter }" data-dropdown>
                        <label>{{ t('properties_listing.filter_bar.bedrooms_label') }}</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label data-dropdown-label-reactive>{{ bedroomsLabel }}</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li v-for="opt in bedroomOptions" :key="opt.value"><button type="button" :class="{ 'is-selected': opt.value === bedroomsFilter }" data-dropdown-option @click="selectBedrooms(opt.value)">{{ opt.label }}</button></li>
                        </ul>
                    </div>
                    <div class="mw-dubai-filter__field mw-dubai-filter__field--select mw-dubai-filter__field--last mw-dropdown" v-if="showFilter('bathrooms')" :class="{ 'is-filled': bathroomsFilter }" data-dropdown>
                        <label>{{ t('properties_listing.filter_bar.bathrooms_label') }}</label>
                        <button type="button" class="mw-dubai-filter__select-trigger" data-dropdown-trigger aria-haspopup="listbox">
                            <span data-dropdown-label data-dropdown-label-reactive>{{ bathroomsLabel }}</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li v-for="opt in bathroomOptions" :key="opt.value"><button type="button" :class="{ 'is-selected': opt.value === bathroomsFilter }" data-dropdown-option @click="selectBathrooms(opt.value)">{{ opt.label }}</button></li>
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
                    <div v-if="showFilter('completion_status')" class="mw-pill-tabs" role="group">
                        <button v-for="opt in completionOptions" :key="opt.value" type="button" data-tab :class="{ 'is-active': opt.value === completion }" :aria-pressed="opt.value === completion" @click="selectCompletion(opt.value)">{{ opt.label }}</button>
                    </div>
                    <div class="mw-dubai-toolbar__right">
                        <div class="mw-dropdown mw-dubai-toolbar__sort" data-dropdown>
                            <button type="button" class="mw-dubai-toolbar__sort-trigger" data-dropdown-trigger aria-haspopup="listbox">
                                <span data-dropdown-label data-dropdown-label-reactive>{{ sortLabel }}</span>
                                <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" class="mw-dubai-filter__chevron">
                            </button>
                            <ul class="mw-dropdown__menu" data-dropdown-menu>
                                <li v-for="opt in sortOptions" :key="opt.value"><button type="button" :class="{ 'is-selected': opt.value === sort }" data-dropdown-option @click="selectSort(opt.value)">{{ opt.label }}</button></li>
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
                            <button type="button" class="mw-dubai-toolbar__clear" :class="{ 'has-filters': filterCount }" :title="activeFilters.map((f) => `${f.label}: ${f.text}`).join(' · ')" @click="clearFilters">
                                {{ t('properties_listing.toolbar.clear_filters') }}<span v-if="filterCount" class="mw-dubai-toolbar__clear-count">{{ filterCount }}</span>
                            </button>
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
                    <h2 class="mw-dubai-listing__title">{{ listingTitle }}</h2>
                    <p class="mw-dubai-listing__count">{{ pagination?.total ?? 0 }} {{ t('properties_listing.listing.count_suffix') }}</p>
                </div>

                <div class="mw-dubai-listing__grid" data-properties-grid>

                    <article v-for="property in properties" :key="property.slug" class="mw-dubai-card" data-reveal>
                        <div class="mw-projects__media" data-card-gallery role="link" tabindex="0" @click="$router.push(`/property-details/${property.slug}`)" @keydown.enter="$router.push(`/property-details/${property.slug}`)" style="cursor: pointer;">
                            <div class="mw-projects__slides" data-gallery-track>
                                <img v-for="(image, index) in property.images" :key="image" :src="image" :alt="index === 0 ? property.name : ''" class="mw-projects__photo" loading="lazy">
                            </div>
                            <button type="button" class="mw-dubai-card__fav" :class="{ 'is-saved': isWishlisted(property.id) }" :aria-label="t('properties_listing.listing.save_property_aria')" :aria-pressed="isWishlisted(property.id)" @click.stop="toggleWishlist(property.id)">
                                <img :src="isWishlisted(property.id) ? '/frontend/assets/images/icons/heart-filled.svg' : '/frontend/assets/images/icons/heart.svg'" alt="" width="18" height="18">
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
                            <!-- Premium page mixes both menus, so each card says which it is. -->
                            <span v-if="premium" class="mw-dubai-card__segment" :class="`mw-dubai-card__segment--${property.segment}`">{{ property.segment === 'commercial' ? t('premium_listing.tag_commercial', 'Commercial') : t('premium_listing.tag_residential', 'Residential') }}</span>
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
