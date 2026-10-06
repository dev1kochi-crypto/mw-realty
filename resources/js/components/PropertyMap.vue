<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { useStaticText } from '../composables/useStaticText';
import { useCurrency } from '../composables/useCurrency';
import { useLanguages } from '../composables/useLanguages';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';

/**
 * Full-width map view for the listing pages (/properties/map, /commercial/map,
 * /premium-properties/map). The parent passes the exact filter params it would send to its own
 * listing API; pins come from GET /api/properties/map for the visible area only, reloaded as the
 * visitor pans/zooms. One pin per address — several listings at the same address become a single
 * "count" pin that opens a list. Nearby pins cluster when zoomed out. Defaults to Dubai.
 */
const props = defineProps({
    segment: { type: String, default: 'residential' }, // residential | commercial | premium
    params: { type: Object, default: () => ({}) },
    // Full-bleed: no frame/rounded corners, fills the rest of the screen under the filter bar.
    edge: { type: Boolean, default: false },
});

const { t } = useStaticText();
const { formatPrice, formatCompact, selectedCurrency } = useCurrency();
const { selectedLanguage } = useLanguages();

const DUBAI = { center: [25.2048, 55.2708], zoom: 11 };
// Filters that narrow where listings are — when they change, fit the map to the results.
const PLACE_KEYS = ['city', 'community', 'location', 'search'];

const mapEl = ref(null);
const map = shallowRef(null);
const cluster = shallowRef(null);
let L = null;
let markerByKey = new Map();
let requestId = 0;
let moveTimer = null;
let skipNextMove = false;

const loading = ref(true);
// Map data credits (required by the OpenStreetMap / OpenFreeMap licences) — behind a small "i" button.
const creditsOpen = ref(false);
const credits = computed(() => (window.MW_MAP_TILES || {}).attribution
    || '<a href="https://openfreemap.org" target="_blank" rel="noopener">OpenFreeMap</a> &copy; <a href="https://www.openmaptiles.org/" target="_blank" rel="noopener">OpenMapTiles</a> Data from <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>');
const result = ref({ markers: [], total: 0, in_view: 0, limit: 500, bounds: null });
const selectedKey = ref(null);
const openedId = ref(null); // inside a multi-property group, the listing shown as a full card

// --- Grouping: one pin per address ---------------------------------------------------------------
const groupKey = (m) => `${m.lat.toFixed(5)},${m.lng.toFixed(5)}`;
const groups = computed(() => {
    const byKey = new Map();
    result.value.markers.forEach((m) => {
        const key = groupKey(m);
        if (!byKey.has(key)) byKey.set(key, { key, lat: m.lat, lng: m.lng, items: [] });
        byKey.get(key).items.push(m);
    });
    return byKey;
});
const selectedGroup = computed(() => (selectedKey.value ? groups.value.get(selectedKey.value) || null : null));
const openedProperty = computed(() => {
    const group = selectedGroup.value;
    if (!group) return null;
    if (group.items.length === 1) return group.items[0];
    return group.items.find((p) => p.id === openedId.value) || null;
});

const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function pinIcon(group, active) {
    const count = group.items.length;
    const first = group.items[0];
    const label = count > 1
        ? `<span class="pm-pin__count">${count}</span>${escapeHtml(t('map.properties_short', 'Properties'))}`
        : escapeHtml(first.price_value ? formatCompact(first.price_value) : t('map.on_request', 'On request'));
    return L.divIcon({
        className: 'pm-pin-wrap',
        html: `<span class="pm-pin${count > 1 ? ' pm-pin--group' : ''}${first.featured ? ' pm-pin--featured' : ''}${active ? ' is-active' : ''}">${label}</span>`,
        iconSize: null,
        iconAnchor: [0, 0],
    });
}

function clusterIcon(c) {
    const total = c.getAllChildMarkers().reduce((sum, m) => sum + (m.options.count || 1), 0);
    const size = total < 10 ? 44 : total < 50 ? 52 : total < 200 ? 60 : 68;
    return L.divIcon({
        className: 'pm-cluster-wrap',
        html: `<span class="pm-cluster" style="width:${size}px;height:${size}px">${total}</span>`,
        iconSize: [size, size],
    });
}

function renderMarkers() {
    if (!cluster.value) return;
    cluster.value.clearLayers();
    markerByKey = new Map();
    const layers = [];
    groups.value.forEach((group) => {
        const marker = L.marker([group.lat, group.lng], {
            icon: pinIcon(group, group.key === selectedKey.value),
            count: group.items.length,
            riseOnHover: true,
            keyboard: true,
            title: group.items.length > 1 ? `${group.items.length} properties` : group.items[0].name,
        });
        marker.on('click', () => select(group.key));
        markerByKey.set(group.key, marker);
        layers.push(marker);
    });
    cluster.value.addLayers(layers);
    // The selected address can drop out of a new result set (pan away / filter change).
    if (selectedKey.value && !groups.value.has(selectedKey.value)) closeCard();
}

function refreshPinStyles(previousKey) {
    [previousKey, selectedKey.value].forEach((key) => {
        const group = key && groups.value.get(key);
        const marker = key && markerByKey.get(key);
        if (group && marker) {
            marker.setIcon(pinIcon(group, key === selectedKey.value));
            marker.setZIndexOffset(key === selectedKey.value ? 1000 : 0);
        }
    });
}

function select(key) {
    const previous = selectedKey.value;
    selectedKey.value = key;
    openedId.value = null;
    refreshPinStyles(previous);
    const marker = markerByKey.get(key);
    if (marker && map.value) {
        // Keep the pin clear of the card (top-left on desktop, bottom sheet on mobile) and the edges.
        const point = map.value.latLngToContainerPoint(marker.getLatLng());
        const size = map.value.getSize();
        const mobile = size.x < 768;
        const hidden = mobile ? point.y > size.y * 0.3 : point.x < 400;
        if (hidden || point.x > size.x - 60 || point.y < 60 || point.y > size.y - 40) {
            skipNextMove = true;
            map.value.panTo(marker.getLatLng(), { animate: true });
        }
    }
}

function closeCard() {
    const previous = selectedKey.value;
    selectedKey.value = null;
    openedId.value = null;
    refreshPinStyles(previous);
}

// --- Data -----------------------------------------------------------------------------------------
function paddedBbox() {
    const b = map.value.getBounds().pad(0.15);
    return { south: b.getSouth(), west: b.getWest(), north: b.getNorth(), east: b.getEast() };
}

async function load({ fit = false } = {}) {
    if (!map.value) return;
    const id = ++requestId;
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/properties/map', {
            params: { ...props.params, segment: props.segment, lang: selectedLanguage.value?.code, ...(fit ? {} : paddedBbox()) },
        });
        if (id !== requestId) return; // a newer request superseded this one
        result.value = data;
        renderMarkers();
        if (fit && data.bounds) fitTo(data.bounds);
    } catch {
        if (id === requestId) result.value = { ...result.value, markers: [], in_view: 0 };
    } finally {
        if (id === requestId) loading.value = false;
    }
}

function fitTo(bounds) {
    // Re-measure first: fitting while the container is still being laid out (0 × 0) makes Leaflet
    // pick the widest zoom — the "whole Middle East" view.
    map.value.invalidateSize(false);
    const size = map.value.getSize();
    if (size.x < 50 || size.y < 50) { pendingFit = bounds; return; }
    const b = L.latLngBounds([bounds.south, bounds.west], [bounds.north, bounds.east]);
    // A single address (or very tight group) shouldn't zoom in to street level.
    map.value.fitBounds(b, { padding: [60, 60], maxZoom: 15, animate: true });
}
let pendingFit = null;

function showAll() {
    if (result.value.bounds) fitTo(result.value.bounds);
}

function resetToDubai() {
    map.value?.flyTo(DUBAI.center, DUBAI.zoom, { duration: 0.8 });
}

function onMoveEnd() {
    if (skipNextMove) { skipNextMove = false; return; }
    clearTimeout(moveTimer);
    moveTimer = setTimeout(() => load(), 250);
}

const hasPlaceFilter = () => PLACE_KEYS.some((k) => props.params[k]);

// --- Basemap: labels follow the site language ---------------------------------------------------
// A vector map (config/services.php `map_tiles.style`, OpenFreeMap by default — free, no key) drawn
// by MapLibre, so every place label can be switched between English and Arabic names. If WebGL isn't
// available the plain raster fallback is used instead (local names).
let basemap = null;
let glMap = null;
const LABEL_EXPR = {
    en: ['coalesce', ['get', 'name:en'], ['get', 'name_en'], ['get', 'name:latin'], ['get', 'name']],
    ar: ['coalesce', ['get', 'name:ar'], ['get', 'name']],
};
const labelLang = () => (selectedLanguage.value?.code === 'ar' ? 'ar' : 'en');

function applyLabelLanguage() {
    if (!glMap || !glMap.isStyleLoaded()) return;
    const expr = LABEL_EXPR[labelLang()];
    glMap.getStyle().layers.forEach((layer) => {
        const field = layer.type === 'symbol' && layer.layout && layer.layout['text-field'];
        // Only place-name labels — road shields/numbers ("ref") stay as they are.
        if (field && JSON.stringify(field).includes('name')) glMap.setLayoutProperty(layer.id, 'text-field', expr);
    });
}

async function addBasemap() {
    const cfg = window.MW_MAP_TILES || {};
    try {
        const maplibregl = (await import('maplibre-gl')).default;
        await import('maplibre-gl/dist/maplibre-gl.css');
        window.maplibregl = maplibregl;
        await import('@maplibre/maplibre-gl-leaflet');
        if (!map.value) return;
        basemap = L.maplibreGL({
            style: cfg.style || 'https://tiles.openfreemap.org/styles/liberty',
            attribution: cfg.attribution || '<a href="https://openfreemap.org">OpenFreeMap</a> &copy; <a href="https://www.openmaptiles.org/">OpenMapTiles</a> Data from <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            interactive: false,
        }).addTo(map.value);
        glMap = basemap.getMaplibreMap();
        glMap.on('styledata', applyLabelLanguage);
        glMap.once('load', applyLabelLanguage);
    } catch {
        if (!map.value) return;
        basemap = L.tileLayer(cfg.fallback_url || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: cfg.fallback_attribution || '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
            className: 'pm-tiles',
        }).addTo(map.value);
    }
}

let resizeObserver = null;

onMounted(async () => {
    // markercluster is a classic plugin that extends the global `L`, so Leaflet must be global first.
    L = (await import('leaflet')).default;
    window.L = L;
    await import('leaflet.markercluster');
    if (!mapEl.value) return;

    // Credits are shown by our own compact "i" button (template) instead of Leaflet's full-width strip.
    map.value = L.map(mapEl.value, { zoomControl: false, attributionControl: false, scrollWheelZoom: true, worldCopyJump: false, minZoom: 5, maxZoom: 18 })
        .setView(DUBAI.center, DUBAI.zoom);
    L.control.zoom({ position: 'bottomright' }).addTo(map.value);
    addBasemap();

    // Keep Leaflet's idea of the map size in step with the layout (async render, window resize,
    // the mobile address bar) — and run a fit that had to wait for a real size.
    resizeObserver = new ResizeObserver(() => {
        if (!map.value) return;
        map.value.invalidateSize(false);
        if (pendingFit) { const b = pendingFit; pendingFit = null; fitTo(b); }
    });
    resizeObserver.observe(mapEl.value);

    cluster.value = L.markerClusterGroup({
        showCoverageOnHover: false,
        spiderfyOnMaxZoom: false,
        disableClusteringAtZoom: 15,
        maxClusterRadius: 55,
        iconCreateFunction: clusterIcon,
    });
    map.value.addLayer(cluster.value);
    map.value.on('moveend', onMoveEnd);
    map.value.on('click', closeCard);

    await load({ fit: hasPlaceFilter() });
});

onBeforeUnmount(() => {
    clearTimeout(moveTimer);
    requestId++;
    resizeObserver?.disconnect();
    map.value?.remove();
    map.value = null;
});

// Filters changed → reload. Dubai stays the default view: the map only moves by itself for a place
// search (city / community / location text); otherwise "Show all results" is one click away.
watch(() => JSON.stringify(props.params), async () => {
    closeCard();
    await load({ fit: hasPlaceFilter() });
});
watch(() => selectedLanguage.value?.code, () => { applyLabelLanguage(); load(); });
// Pin prices are in the visitor's currency.
watch(() => selectedCurrency.value?.code, renderMarkers);

function onKey(e) {
    if (e.key === 'Escape' && selectedKey.value) closeCard();
}
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

const truncated = computed(() => result.value.in_view > result.value.markers.length);
const detailUrl = (p) => `/property-details/${p.slug}`;
const specs = (p) => [
    p.beds ? { icon: 'bed', text: p.beds } : null,
    p.baths ? { icon: 'bath', text: p.baths } : null,
    p.area && p.area !== '—' ? { icon: 'area', text: p.area } : null,
].filter(Boolean);

watch(openedId, () => nextTick(() => document.querySelector('.pm-card__scroll')?.scrollTo({ top: 0 })));
</script>

<template>
    <section class="pm" :class="{ 'pm--edge': edge }" :aria-label="t('map.aria_label', 'Properties on a map')">
        <div ref="mapEl" class="pm__map"></div>

        <!-- Status chip -->
        <div class="pm__status" role="status" aria-live="polite">
            <span v-if="loading" class="pm__spinner" aria-hidden="true"></span>
            <template v-if="!loading && result.in_view === 0">
                {{ result.total ? t('map.none_here', 'No properties in this area') : t('map.none', 'No properties match your filters') }}
                <button v-if="result.total" type="button" class="pm__status-btn" @click="showAll">{{ t('map.show_all', 'Show all results') }}</button>
            </template>
            <template v-else-if="!loading">
                <strong>{{ result.in_view.toLocaleString() }}</strong>&nbsp;{{ t('map.in_area', 'properties in this area') }}
                <span v-if="truncated" class="pm__status-note">&middot; {{ t('map.zoom_in', 'zoom in to see all') }}</span>
            </template>
            <template v-else>{{ t('map.loading', 'Loading properties…') }}</template>
        </div>

        <!-- Map credits: required by the map data licence, kept to a small "i" button. -->
        <div class="pm__credits" :class="{ 'is-open': creditsOpen }">
            <button type="button" class="pm__credits-btn" :aria-expanded="creditsOpen" :aria-label="t('map.credits', 'Map credits')" :title="t('map.credits', 'Map credits')" @click="creditsOpen = !creditsOpen">i</button>
            <span v-if="creditsOpen" class="pm__credits-text" v-html="credits"></span>
        </div>

        <!-- Map controls -->
        <div class="pm__controls">
            <button type="button" class="pm__control" :title="t('map.reset_dubai', 'Back to Dubai')" :aria-label="t('map.reset_dubai', 'Back to Dubai')" @click="resetToDubai">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /></svg>
            </button>
            <button v-if="result.total" type="button" class="pm__control" :title="t('map.show_all', 'Show all results')" :aria-label="t('map.show_all', 'Show all results')" @click="showAll">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9V3h6M21 9V3h-6M3 15v6h6M21 15v6h-6" /></svg>
            </button>
        </div>

        <!-- Selected address card -->
        <transition name="pm-card">
            <aside v-if="selectedGroup" class="pm-card" :class="{ 'pm-card--list': !openedProperty }" @click.stop>
                <button type="button" class="pm-card__close" :aria-label="t('map.close', 'Close')" @click="closeCard">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
                </button>

                <!-- Several listings at this address: the list -->
                <div v-if="!openedProperty" class="pm-card__scroll">
                    <div class="pm-card__group-head">
                        <span class="pm-card__group-count">{{ selectedGroup.items.length }}</span>
                        <div>
                            <strong>{{ t('map.at_address', 'properties at this address') }}</strong>
                            <span>{{ selectedGroup.items[0].location }}</span>
                        </div>
                    </div>
                    <button v-for="(p, i) in selectedGroup.items" :key="p.id" type="button" class="pm-row" v-track-impression="p.id" :style="{ '--i': i }" @click="openedId = p.id">
                        <img :src="p.image" :alt="p.name" class="pm-row__img" loading="lazy">
                        <span class="pm-row__body">
                            <span class="pm-row__price">{{ formatPrice(p.price_value, p.price) }}</span>
                            <span class="pm-row__name">{{ p.name }}</span>
                            <span class="pm-row__meta">{{ [p.type, p.beds ? p.beds + ' ' + t('map.bed', 'Bed') : null, p.area !== '—' ? p.area : null].filter(Boolean).join(' · ') }}</span>
                        </span>
                        <svg class="pm-row__chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6" /></svg>
                    </button>
                </div>

                <!-- One listing: the small card -->
                <div v-else class="pm-card__scroll">
                    <button v-if="selectedGroup.items.length > 1" type="button" class="pm-card__back" @click="openedId = null">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m15 18-6-6 6-6" /></svg>
                        {{ t('map.all_at_address', 'All properties here') }} ({{ selectedGroup.items.length }})
                    </button>
                    <router-link :to="detailUrl(openedProperty)" class="pm-card__media">
                        <img :src="openedProperty.image" :alt="openedProperty.name">
                        <span v-if="openedProperty.purpose_badge" class="pm-card__badge">{{ openedProperty.purpose_badge }}</span>
                        <span v-if="openedProperty.featured" class="pm-card__badge pm-card__badge--premium">{{ t('map.premium', 'Premium') }}</span>
                        <span v-if="openedProperty.images_count > 1" class="pm-card__photos">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2" /><circle cx="12" cy="12" r="3" /></svg>
                            {{ openedProperty.images_count }}
                        </span>
                    </router-link>
                    <div class="pm-card__body">
                        <div class="pm-card__price">{{ formatPrice(openedProperty.price_value, openedProperty.price) }}</div>
                        <router-link :to="detailUrl(openedProperty)" class="pm-card__name">{{ openedProperty.name }}</router-link>
                        <div class="pm-card__location">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-7.6-7-12a7 7 0 1 1 14 0c0 4.4-7 12-7 12z" /><circle cx="12" cy="9" r="2.5" /></svg>
                            {{ openedProperty.location }}
                        </div>
                        <div class="pm-card__specs">
                            <span class="pm-card__type">{{ openedProperty.type }}</span>
                            <span v-for="s in specs(openedProperty)" :key="s.icon" class="pm-card__spec">
                                <svg v-if="s.icon === 'bed'" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 18V7M21 18v-5a3 3 0 0 0-3-3H10v8M3 14h18M6 11a1.5 1.5 0 1 0 0-.01" /></svg>
                                <svg v-else-if="s.icon === 'bath'" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12h16v3a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4zM6 12V6a2 2 0 0 1 3.5-1.3M6 21l1-2M18 21l-1-2" /></svg>
                                <svg v-else viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20 20 4M4 14v6h6M20 10V4h-6" /></svg>
                                {{ s.text }}
                            </span>
                        </div>
                        <router-link :to="detailUrl(openedProperty)" class="pm-card__cta">
                            {{ t('map.view_details', 'View Details') }}
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                        </router-link>
                    </div>
                </div>
            </aside>
        </transition>
    </section>
</template>

<style scoped>
.pm {
    --pm-red: #c92844;
    --pm-navy: #244373;
    position: relative;
    height: clamp(520px, calc(100vh - 150px), 900px);
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e3e7f0;
    box-shadow: 0 18px 40px rgba(36, 67, 115, 0.1);
    background: #eef1f6;
    isolation: isolate;
}
.pm--edge { height: max(520px, calc(100svh - 78px)); border: 0; border-radius: 0; box-shadow: none; }
.pm__map { position: absolute; inset: 0; z-index: 0; font-family: "Plus Jakarta Sans", sans-serif; }
/* Calm the standard map colours so the brand-coloured pins stand out. */
.pm :deep(.pm-tiles) { filter: saturate(0.55) contrast(0.95) brightness(1.04); }

/* Status chip + controls */
.pm__status { position: absolute; top: 16px; left: 50%; z-index: 500; display: inline-flex; align-items: center; gap: 8px; max-width: calc(100% - 32px); padding: 9px 16px; border-radius: 50px; background: #fff; color: #35373c; box-shadow: 0 8px 22px rgba(36, 67, 115, 0.16); font-family: "Plus Jakarta Sans", sans-serif; font-size: 14px; white-space: nowrap; transform: translateX(-50%); }
.pm__status strong { color: var(--pm-navy); }
.pm__status-note { color: #8a90a2; }
.pm__status-btn { border: 0; background: none; color: var(--pm-red); font-weight: 700; cursor: pointer; padding: 0; }
.pm__spinner { width: 14px; height: 14px; border-radius: 50%; border: 2px solid rgba(36, 67, 115, 0.2); border-top-color: var(--pm-red); animation: pm-spin 0.7s linear infinite; }
.pm__controls { position: absolute; top: 16px; inset-inline-end: 16px; z-index: 500; display: flex; flex-direction: column; gap: 8px; }
.pm__control { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border: 0; border-radius: 10px; background: #fff; color: var(--pm-navy); box-shadow: 0 6px 16px rgba(36, 67, 115, 0.16); cursor: pointer; transition: background 0.2s ease, color 0.2s ease; }
.pm__control:hover { background: var(--pm-navy); color: #fff; }

/* Pins (Leaflet renders them outside Vue's scope → :deep) */
.pm :deep(.pm-pin-wrap) { background: none; border: 0; }
.pm :deep(.pm-pin) { position: absolute; left: 0; top: 0; display: inline-flex; align-items: center; gap: 5px; padding: 6px 11px; border-radius: 50px; background: #fff; color: var(--pm-navy); border: 1.5px solid rgba(36, 67, 115, 0.18); box-shadow: 0 6px 16px rgba(36, 67, 115, 0.22); font-family: "Plus Jakarta Sans", sans-serif; font-size: 13px; font-weight: 800; white-space: nowrap; cursor: pointer; transform: translate(-50%, calc(-100% - 7px)); transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease; animation: pm-pin-in 0.35s ease-out both; }
.pm :deep(.pm-pin::after) { content: ''; position: absolute; left: 50%; bottom: -6px; width: 10px; height: 10px; background: inherit; border-right: inherit; border-bottom: inherit; transform: translateX(-50%) rotate(45deg); border-radius: 0 0 2px 0; }
.pm :deep(.pm-pin:hover) { transform: translate(-50%, calc(-100% - 10px)); }
.pm :deep(.pm-pin--featured) { border-color: rgba(201, 40, 68, 0.45); }
.pm :deep(.pm-pin--group) { color: var(--pm-red); }
.pm :deep(.pm-pin__count) { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 50px; background: var(--pm-red); color: #fff; font-size: 11px; }
.pm :deep(.pm-pin.is-active) { background: linear-gradient(90deg, var(--pm-red), var(--pm-navy)); color: #fff; border-color: transparent; transform: translate(-50%, calc(-100% - 10px)) scale(1.08); }
.pm :deep(.pm-pin.is-active::after) { background: var(--pm-navy); }
.pm :deep(.pm-pin.is-active .pm-pin__count) { background: #fff; color: var(--pm-red); }
.pm :deep(.pm-cluster-wrap) { background: none; border: 0; }
.pm :deep(.pm-cluster) { display: flex; align-items: center; justify-content: center; border-radius: 50%; background: linear-gradient(135deg, var(--pm-red), var(--pm-navy)); color: #fff; font-family: "Plus Jakarta Sans", sans-serif; font-size: 14px; font-weight: 800; box-shadow: 0 0 0 6px rgba(201, 40, 68, 0.18), 0 10px 22px rgba(36, 67, 115, 0.3); transition: transform 0.18s ease; }
.pm :deep(.pm-cluster:hover) { transform: scale(1.08); }
.pm :deep(.leaflet-control-zoom) { border: 0; box-shadow: 0 6px 16px rgba(36, 67, 115, 0.16); border-radius: 10px; overflow: hidden; margin: 0 16px 16px 0; }
.pm :deep(.leaflet-control-zoom a) { width: 40px; height: 40px; line-height: 40px; color: var(--pm-navy); font-size: 18px; }
.pm__credits { position: absolute; z-index: 500; left: 12px; bottom: 12px; display: flex; align-items: center; gap: 6px; max-width: calc(100% - 24px); font-family: "Plus Jakarta Sans", sans-serif; }
.pm__credits-btn { flex-shrink: 0; width: 22px; height: 22px; padding: 0; border: 0; border-radius: 50%; background: rgba(255, 255, 255, 0.92); color: #6b7080; font: italic 700 12px/22px Georgia, serif; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.18); cursor: pointer; }
.pm__credits-btn:hover, .pm__credits.is-open .pm__credits-btn { color: var(--pm-navy); }
.pm__credits-text { padding: 4px 10px; border-radius: 50px; background: rgba(255, 255, 255, 0.95); color: #6b7080; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12); }
.pm__credits-text :deep(a) { color: var(--pm-navy); text-decoration: none; }

/* Card */
.pm-card { position: absolute; z-index: 600; left: 16px; top: 16px; bottom: auto; width: 360px; max-height: calc(100% - 32px); display: flex; flex-direction: column; border-radius: 16px; background: #fff; box-shadow: 0 22px 50px rgba(20, 30, 60, 0.28); overflow: hidden; font-family: "Plus Jakarta Sans", sans-serif; }
.pm-card__scroll { overflow-y: auto; overscroll-behavior: contain; }
.pm-card__close { position: absolute; top: 10px; right: 10px; z-index: 2; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border: 0; border-radius: 50%; background: rgba(255, 255, 255, 0.95); color: #35373c; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); cursor: pointer; }
.pm-card__close:hover { color: var(--pm-red); }
.pm-card__back { display: flex; align-items: center; gap: 6px; width: 100%; padding: 12px 52px 12px 16px; border: 0; border-bottom: 1px solid #eef0f5; background: #fff; color: var(--pm-navy); font-size: 13px; font-weight: 700; text-align: start; cursor: pointer; }
.pm-card__back:hover { color: var(--pm-red); }
.pm-card__media { position: relative; display: block; aspect-ratio: 16 / 10; background: #eef1f6; overflow: hidden; }
.pm-card__media img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
.pm-card__media:hover img { transform: scale(1.05); }
.pm-card__badge { position: absolute; top: 12px; left: 12px; padding: 4px 10px; border-radius: 50px; background: rgba(255, 255, 255, 0.95); color: var(--pm-navy); font-size: 12px; font-weight: 700; }
.pm-card__badge--premium { top: 42px; background: linear-gradient(90deg, var(--pm-red), var(--pm-navy)); color: #fff; }
.pm-card__photos { position: absolute; bottom: 10px; right: 10px; display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px; background: rgba(0, 0, 0, 0.55); color: #fff; font-size: 12px; font-weight: 600; }
.pm-card__body { padding: 16px 18px 18px; }
.pm-card__price { color: var(--pm-red); font-size: 20px; font-weight: 800; }
.pm-card__name { display: block; margin-top: 4px; color: var(--pm-navy); font-family: "Playfair Display", Georgia, serif; font-size: 18px; font-weight: 600; line-height: 1.3; text-decoration: none; }
.pm-card__name:hover { color: var(--pm-red); }
.pm-card__location { display: flex; align-items: center; gap: 5px; margin-top: 6px; color: #6b7080; font-size: 13px; }
.pm-card__specs { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #eef0f5; }
.pm-card__type, .pm-card__spec { display: inline-flex; align-items: center; gap: 5px; padding: 4px 9px; border-radius: 6px; background: #f3f5fa; color: #35373c; font-size: 12.5px; font-weight: 600; }
.pm-card__type { background: rgba(36, 67, 115, 0.08); color: var(--pm-navy); }
.pm-card__cta { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 16px; padding: 13px 18px; border-radius: 6px; background: linear-gradient(90deg, var(--pm-red), var(--pm-navy)); color: #fff; font-size: 15px; font-weight: 700; text-decoration: none; transition: opacity 0.2s ease, gap 0.2s ease; }
.pm-card__cta:hover { color: #fff; opacity: 0.92; gap: 12px; }

/* Group list */
.pm-card__group-head { display: flex; align-items: center; gap: 12px; padding: 16px 52px 14px 16px; border-bottom: 1px solid #eef0f5; }
.pm-card__group-head strong { display: block; color: var(--pm-navy); font-size: 15px; }
.pm-card__group-head span:not(.pm-card__group-count) { display: block; color: #6b7080; font-size: 12.5px; }
.pm-card__group-count { display: flex; align-items: center; justify-content: center; flex-shrink: 0; width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, var(--pm-red), var(--pm-navy)); color: #fff; font-size: 17px; font-weight: 800; }
.pm-row { display: flex; align-items: center; gap: 12px; width: 100%; padding: 10px 14px; border: 0; border-bottom: 1px solid #f2f4f8; background: #fff; text-align: start; cursor: pointer; animation: pm-row-in 0.3s ease-out calc(var(--i) * 0.04s) both; transition: background 0.15s ease; }
.pm-row:hover { background: #f7f8fc; }
.pm-row__img { width: 72px; height: 56px; flex-shrink: 0; border-radius: 8px; object-fit: cover; background: #eef1f6; }
.pm-row__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.pm-row__price { color: var(--pm-red); font-size: 14px; font-weight: 800; }
.pm-row__name { overflow: hidden; color: var(--pm-navy); font-size: 13.5px; font-weight: 700; white-space: nowrap; text-overflow: ellipsis; }
.pm-row__meta { overflow: hidden; color: #8a90a2; font-size: 12px; white-space: nowrap; text-overflow: ellipsis; }
.pm-row__chevron { flex-shrink: 0; color: #b6bccb; }
.pm-row:hover .pm-row__chevron { color: var(--pm-red); }

.pm-card-enter-active, .pm-card-leave-active { transition: opacity 0.22s ease, transform 0.22s ease; }
.pm-card-enter-from, .pm-card-leave-to { opacity: 0; transform: translateY(14px) scale(0.98); }

@keyframes pm-spin { to { transform: rotate(360deg); } }
@keyframes pm-pin-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes pm-row-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

@media (max-width: 767.98px) {
    /* Short enough to sit above the site's fixed mobile bottom bar, so the card's button stays tappable. */
    .pm { height: calc(100svh - 240px); min-height: 440px; border-radius: 12px; }
    /* Edge view on phones: screen minus the top header and the fixed bottom bar. */
    .pm--edge { height: max(440px, calc(100svh - 140px)); border-radius: 0; }
    .pm__status { top: 12px; font-size: 13px; padding: 8px 13px; }
    .pm__status-note { display: none; }
    .pm__controls { top: 60px; inset-inline-end: 12px; }
    .pm-card { left: 8px; right: 8px; top: auto; bottom: 8px; width: auto; max-height: 72%; border-radius: 16px 16px 12px 12px; }
    .pm-card__media { aspect-ratio: 16 / 8; }
}
@media (prefers-reduced-motion: reduce) {
    .pm :deep(.pm-pin), .pm-row { animation: none; }
}
</style>
