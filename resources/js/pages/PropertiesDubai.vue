<script setup>
import AdBlock from '../components/AdBlock.vue';
import { computed, defineAsyncComponent, nextTick, onMounted, reactive, ref, toRef, watch } from 'vue';
import { usePageWindow } from '../composables/usePageWindow';
import { useRoute, useRouter } from 'vue-router';
import { useProperties } from '../composables/useProperties';
import { useLanguages } from '../composables/useLanguages';
import { useWishlist } from '../composables/useWishlist';
import { useStaticText } from '../composables/useStaticText';
import { useCurrency } from '../composables/useCurrency';
import { usePropertyFilters } from '../composables/usePropertyFilters';
import EmptyState from '../components/EmptyState.vue';
import LocationAutocomplete from '../components/LocationAutocomplete.vue';
import { carryFilters, takeCarriedFilters } from '../composables/useListingCarry';
// Leaflet only loads when the map view is actually opened.
const PropertyMap = defineAsyncComponent(() => import('../components/PropertyMap.vue'));

const route = useRoute();
const router = useRouter();
const { propertiesListing, fetchPropertiesListing } = useProperties();
const { isWishlisted, toggleWishlist, authenticated } = useWishlist();
const { selectedLanguage } = useLanguages();
const { t } = useStaticText();
// Prices and the price filter are in AED; they're shown in the visitor's chosen currency.
const { formatPrice, formatCompact, presetLabel } = useCurrency();

// --- Filters -------------------------------------------------------------------------------------
// Options, labels and which filters exist all come from Admin > Filters ("Show on: Listing") via
// /api/property-filters. URL/API parameters use each filter's key: select filters as ?{key}=value,
// range filters as ?min_{key}=&max_{key}= (see Api\PropertiesController).
// /premium-properties reuses this page for every premium (CRM-featured) listing — residential and
// commercial together (API ?premium=1); the filter options follow that same set of listings.
const premium = !!route.meta.premium;
// /marketing-properties: the home "Realty Property" section's view-all — Super Admin's hand-picked
// Marketing Properties list (API ?marketing=1), kept in the order it was arranged in.
const marketing = !!route.meta.marketing;
// Both lists mix residential and commercial, so each card says which it is — as does an agent's /
// agency's View all (?agent= / ?agency=), which shows every listing on their profile.
const mixedSegments = computed(() => premium || marketing || !!(applied.agent || applied.agency));
const segment = premium ? 'premium' : (marketing ? 'marketing' : 'residential');
// /properties/map (and /premium-properties/map): same page and filters, results on a map instead of cards.
const mapView = computed(() => !!route.meta.mapView);
const listPath = premium ? '/premium-properties' : (marketing ? '/marketing-properties' : '/properties');
const mapPath = listPath + '/map';
const mapParams = ref({});
const carryKey = premium ? 'premium' : (marketing ? 'marketing' : 'properties');
// List ⇄ map toggle: hand the current filters to the other view (they aren't in the URL).
function carryToOtherView() {
    carryFilters(carryKey, currentQuery());
}
// Set while we clear an incoming ?query ourselves, so the route.query watcher ignores that change.
let clearingUrl = false;
const { filters: adminFilters, loaded: filtersLoaded, byKey: adminFilter, options: adminOptions } = usePropertyFilters('listing', segment);
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
    if (['address', 'property'].includes(pickedPlace.value?.type) && pickedPlace.value.slug) {
        router.push(`/property-details/${pickedPlace.value.slug}`);
        return;
    }
    applyFilters();
}

// ?agent= / ?agency= (profile slugs) — set by the View all links on /agent-details and /agency-details.
const agentFilter = ref('');
const agencyFilter = ref('');

const minPrice = ref('');
const maxPrice = ref('');
const minSqft = ref('');
const maxSqft = ref('');
// Not admin filters: sorting, amenities (free-text on each property) and the floor-plan toggle.
const sort = ref('default');
const amenities = ref([]);
const floorPlans = ref(false);
// Listings with an upcoming open house / viewing day.
const openHouse = ref(false);
const currentPage = ref(1);

// Every string filter, as [queryKey, ref] — the fixed ones plus whatever extra admin filters are in play.
function fields() {
    return [
        ...Object.entries(SELECT_BINDINGS),
        ['city', cityFilter], ['community', communityFilter],
        ['agent', agentFilter], ['agency', agencyFilter],
        ['min_price', minPrice], ['max_price', maxPrice], ['min_sqft', minSqft], ['max_sqft', maxSqft],
        ...Object.keys(extraSelect).map((k) => [k, toRef(extraSelect, k)]),
    ];
}
const NON_FILTER_QUERY = ['sort', 'amenities', 'floor_plans', 'open_house', 'page'];

// Snapshot of the filters the current results were actually fetched with — the Clear Filters count
// and the heading read this, so half-typed input in the Location box doesn't count as "applied".
const applied = reactive({});

function snapshot() {
    const s = {};
    fields().forEach(([key, r]) => { s[key] = String(r.value ?? '').trim(); });
    s.sort = sort.value;
    s.amenities = [...amenities.value].sort();
    s.floor_plans = floorPlans.value;
    s.open_house = openHouse.value;
    return s;
}

function currentQuery() {
    const query = {};
    fields().forEach(([key]) => { if (applied[key]) query[key] = applied[key]; });
    if (applied.sort && applied.sort !== 'default') query.sort = applied.sort;
    if (applied.amenities?.length) query.amenities = applied.amenities.join(',');
    if (applied.floor_plans) query.floor_plans = '1';
    if (applied.open_house) query.open_house = '1';
    return query;
}

function load() {
    Object.keys(applied).forEach((k) => delete applied[k]);
    Object.assign(applied, snapshot());
    const filterParams = {};
    fields().forEach(([key]) => { if (applied[key]) filterParams[key] = applied[key]; });
    if (applied.amenities.length) filterParams.amenities = applied.amenities;
    if (applied.floor_plans) filterParams.floor_plans = 1;
    if (applied.open_house) filterParams.open_house = 1;
    mapParams.value = filterParams;
    // The map view loads its own pins (PropertyMap.vue) — no page of cards needed.
    if (!mapView.value) {
        const params = { ...filterParams, lang: selectedLanguage.value?.code, page: currentPage.value, sort: applied.sort };
        if (premium) params.premium = 1;
        if (marketing) params.marketing = 1;
        fetchPropertiesListing(params);
    }
}

/** A filter change made on this page — filters stay in memory; any incoming ?query is cleared from the URL. */
function applyFilters() {
    currentPage.value = 1;
    load();
    if (Object.keys(route.query).length) {
        clearingUrl = true;
        router.replace({ query: {} });
    }
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
    openHouse.value = query.open_house === '1';
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
const completionPillsKey = computed(() => completionOptions.value.map((o) => `${o.value}:${o.label}`).join('|'));
watch(completionPillsKey, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});
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
    .map(([lo, hi, key]) => [lo, Math.min(hi, priceCeiling.value), key, hi === Infinity]));

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

// Filter values stay in AED; only the text is in the chosen currency.
const formatAed = (v) => formatCompact(v);

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
    if (applied.open_house) add('open_house', t('properties_listing.filter_panel.features_title', 'Feature'), t('properties_listing.filter_panel.feature_open_house', 'Open house'));
    if (applied.floor_plans) add('floor_plans', t('properties_listing.filter_panel.features_title', 'Feature'), t('properties_listing.filter_panel.feature_floor_plans', 'Floor plans'));
    if (applied.agent) add('agent', t('properties_listing.listing.agent_label', 'Agent'), ownerName.value || humanize(applied.agent));
    if (applied.agency) add('agency', t('properties_listing.listing.agency_label', 'Agency'), ownerName.value || humanize(applied.agency));
    return chips;
});
const filterCount = computed(() => activeFilters.value.length);

// Whose listings ?agent= / ?agency= is showing (name from the API).
const ownerName = computed(() => propertiesListing.value?.owner?.name || '');
function clearOwnerFilter() {
    agentFilter.value = '';
    agencyFilter.value = '';
    applyFilters();
}

// Hero title + breadcrumb.
// /marketing-properties text comes from the CMS section heading the home block (Common Titles ›
// Realty Property): Title 1 → eyebrow, Title 2 → heading, Description → intro.
const marketingSection = computed(() => propertiesListing.value?.section || null);
const pageTitle = computed(() => (premium
    ? t('premium_listing.hero_title', 'Premium Properties')
    : (marketing
        ? (marketingSection.value?.title || t('marketing_listing.hero_title', 'Realty Properties'))
        : t('properties_listing.hero.title'))));

// "Penthouse Properties for Rent in Dubai" — the heading says what is actually being shown.
const listingTitle = computed(() => {
    const type = applied.property_type ? `${labelFor(propertyTypeOptions, applied.property_type)} ` : '';
    const purpose = { sale: ' for Sale', rent: ' for Rent' }[applied.listing_type] ?? '';
    const offPlan = applied.completion_status === 'off_plan' ? 'Off-Plan ' : '';
    if (marketing) {
        return (!type && !purpose && !offPlan)
            ? (marketingSection.value?.title ? `All ${marketingSection.value.title}` : t('marketing_listing.all_title', 'All Realty Properties'))
            : `Realty ${offPlan}${type}Properties${purpose}`;
    }
    if (premium) {
        return (!type && !purpose && !offPlan)
            ? t('premium_listing.all_title', 'All Premium Properties')
            : `Premium ${offPlan}${type}Properties${purpose}`;
    }
    if ((applied.agent || applied.agency) && ownerName.value) {
        return t('properties_listing.listing.by_owner_title', 'Properties by {name}').replace('{name}', ownerName.value);
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
    openHouse.value = false;
    syncPriceSlider();
    applyFilters();
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
const pageNumbers = usePageWindow(currentPage, computed(() => pagination.value?.last_page));

onMounted(() => {
    // Filters arrive either in the URL (Home search, header Buy/Rent, ticker, saved searches) or
    // carried over from the list/map toggle; after that they live in memory only.
    const incoming = Object.keys(route.query).length ? route.query : (takeCarriedFilters(carryKey) || {});
    readQuery(incoming);
    load();
});
// Links that land on this same page with a new ?query (header Buy/Rent, ticker "N Bedroom",
// Back/Forward) don't remount it — pick those filters up. Our own URL clearing is ignored.
watch(() => route.query, (query) => {
    if (clearingUrl) { clearingUrl = false; return; }
    if (!Object.keys(query).length || JSON.stringify(query) === JSON.stringify(currentQuery())) return;
    readQuery(query);
    currentPage.value = 1;
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
                        <button v-for="p in pricePresets" :key="p[2]" type="button" :class="{ 'is-active': priceLo === p[0] && priceHi === p[1] }" @click="pickPricePreset(p)">{{ presetLabel(p[0], p[1], { top: p[3], baseLabel: t(`properties_listing.price_presets.${p[2]}`), under: t('properties_listing.price_presets.under_word', 'Under') }) }}</button>
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
                    <label class="mw-filter-panel__check"><input type="checkbox" name="property-feature" value="open-house" v-model="openHouse"><span>{{ t('properties_listing.filter_panel.feature_open_house_properties', 'Open house properties') }}</span></label>
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
                    <p v-if="marketing && marketingSection?.eyebrow" class="mw-eyebrow mw-marketing-eyebrow">{{ marketingSection.eyebrow }}</p>
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
                    <!-- Keyed by its option list: assets/js/script.js moves these buttons into its own
                         mobile-dropdown wrapper on first scan, so options that arrive later (from the
                         filters API) would be inserted after "All" instead of before it. A new key
                         rebuilds the group in the right order, and the watcher below re-scans it. -->
                    <div v-if="showFilter('completion_status')" :key="completionPillsKey" class="mw-pill-tabs" role="group">
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
                            <template v-if="!mapView">
                                <button type="button" class="mw-dubai-toolbar__view-btn is-active" data-properties-view="grid" :aria-label="t('properties_listing.toolbar.grid_view_aria')" aria-pressed="true">
                                    <img src="/frontend/assets/images/agencies/icon-grid-view.svg" alt="" width="24" height="24">
                                </button>
                                <button type="button" class="mw-dubai-toolbar__view-btn" data-properties-view="list" :aria-label="t('properties_listing.toolbar.list_view_aria')" aria-pressed="false">
                                    <img src="/frontend/assets/images/agencies/icon-list-view.svg" alt="" width="24" height="24">
                                </button>
                            </template>
                            <template v-else>
                                <router-link :to="listPath" class="mw-dubai-toolbar__view-btn" :aria-label="t('properties_listing.toolbar.grid_view_aria')" @click="carryToOtherView">
                                    <img src="/frontend/assets/images/agencies/icon-grid-view.svg" alt="" width="24" height="24">
                                </router-link>
                                <router-link :to="listPath" class="mw-dubai-toolbar__view-btn" :aria-label="t('properties_listing.toolbar.list_view_aria')" @click="carryToOtherView">
                                    <img src="/frontend/assets/images/agencies/icon-list-view.svg" alt="" width="24" height="24">
                                </router-link>
                            </template>
                            <router-link :to="mapPath" @click="carryToOtherView" class="mw-dubai-toolbar__view-btn mw-map-toggle" :class="{ 'is-active': mapView }" :aria-label="t('map.map_view_aria', 'Map view')" :aria-pressed="mapView" :title="t('map.map_view_aria', 'Map view')">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4 3 6.5v13L9 17l6 2.5 6-2.5V4l-6 2.5z" /><path d="M9 4v13M15 6.5v13" /></svg>
                            </router-link>
                        </div>
                        <div class="mw-dubai-toolbar__actions">
                            <button type="button" class="mw-dubai-toolbar__save" :disabled="savingSearch" @click="saveSearch">{{ saveSearchLabel }}</button>
                            <button type="button" class="mw-dubai-toolbar__clear" :class="{ 'has-filters': filterCount }" :title="activeFilters.map((f) => `${f.label}: ${f.text}`).join(' · ')" @click="clearFilters">
                                {{ t('properties_listing.toolbar.clear_filters') }}<span v-if="filterCount" class="mw-dubai-toolbar__clear-count">{{ filterCount }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="!mapView" class="mw-dubai-ticker" :aria-label="t('properties_listing.ticker.aria_label')">
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

        <!-- Map view: edge to edge, straight under the filter bar (no title / container). -->
        <section v-if="mapView" class="mw-map-section">
            <PropertyMap edge :segment="segment" :params="mapParams" />
        </section>

        <section v-else class="mw-dubai-listing">
            <div class="container-ctn">
                <div class="mw-dubai-listing__head">
                    <h2 class="mw-dubai-listing__title">{{ listingTitle }}</h2>
                    <p class="mw-dubai-listing__count">{{ pagination?.total ?? 0 }} {{ t('properties_listing.listing.count_suffix') }}</p>
                </div>
                <p v-if="(applied.agent || applied.agency) && ownerName" class="mw-dubai-listing__owner">
                    <span class="mw-dubai-listing__owner-chip">
                        {{ applied.agent ? t('properties_listing.listing.agent_label', 'Agent') : t('properties_listing.listing.agency_label', 'Agency') }}: {{ ownerName }}
                        <button type="button" :aria-label="t('properties_listing.listing.clear_owner_aria', 'Show all properties')" @click="clearOwnerFilter">&times;</button>
                    </span>
                </p>
                <p v-if="marketing && marketingSection?.description" class="mw-marketing-intro">{{ marketingSection.description }}</p>

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
                            <span v-if="mixedSegments" class="mw-dubai-card__segment" :class="`mw-dubai-card__segment--${property.segment}`">{{ property.segment === 'commercial' ? t('premium_listing.tag_commercial', 'Commercial') : t('premium_listing.tag_residential', 'Residential') }}</span>
                        </div>
                        <div class="mw-dubai-card__body">
                            <div class="mw-dubai-card__price-row">
                                <span class="mw-dubai-card__price">{{ formatPrice(property.price_value, property.price) }}</span>
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


        <!-- Admin › Ads — wide banner below the listings, before the footer (not on map view). -->
        <AdBlock v-if="!mapView" :placement="premium ? 'premium-properties' : (marketing ? 'marketing-properties' : 'properties-dubai')" />
    </main>
</template>

<style scoped>
/* /marketing-properties: CMS eyebrow + intro (Common Titles › Realty Property). */
.mw-marketing-eyebrow { margin: 0 0 0.35rem; }
.mw-marketing-intro { max-width: 860px; margin: -0.5rem 0 1.5rem; color: #5b6078; font-size: 0.98rem; line-height: 1.7; }
</style>
