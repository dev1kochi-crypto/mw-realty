<script setup>
/**
 * Property detail page: the listing's location on a map plus the nearby places it's tagged with
 * (PropertyPageService::nearbyGroups). Types sit in a scrollable chip strip under the map — any
 * number of types fits — and the places of the picked type are listed with their distance.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { useStaticText } from '../composables/useStaticText';
import { useLanguages } from '../composables/useLanguages';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';

const props = defineProps({
    location: { type: Object, required: true }, // { lat, lng }
    groups: { type: Array, default: () => [] }, // [{ key, label, places: [{ id, name, address, lat, lng, distance_km }] }]
    title: { type: String, default: '' },
    address: { type: String, default: '' },
});

const { t } = useStaticText();
const { selectedLanguage } = useLanguages();

// --- Type icons (stroke SVG, 24 grid), picked by keywords in the type code ---------------------
const ICONS = {
    education: '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/>',
    health: '<rect x="4" y="4" width="16" height="16" rx="4"/><path d="M12 8v8M8 12h8"/>',
    food: '<path d="M7 3v8a2 2 0 0 0 4 0V3M9 11v10M17 3c-2 0-3 2-3 5s1 4 3 4v9"/>',
    cafe: '<path d="M4 9h12v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V9Z"/><path d="M16 11h2a2 2 0 0 1 0 4h-2M8 3v3M12 3v3"/>',
    shop: '<path d="M6 7h12l-1 13H7L6 7Z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
    nature: '<path d="M12 3 7 11h3l-4 6h12l-4-6h3l-5-8Z"/><path d="M12 17v4"/>',
    beach: '<path d="M4 20h16"/><path d="M12 20 9 9"/><path d="M3 10a9 9 0 0 1 15-3L3 10Z"/>',
    transit: '<rect x="6" y="3" width="12" height="13" rx="3"/><path d="M6 10h12M9 20l-1 1M15 20l1 1M9 13h.01M15 13h.01"/>',
    airport: '<path d="M2 16 22 10 2 4l3 6-3 6Z"/>',
    mosque: '<path d="M12 3c-3 3-6 4-6 8v9h12v-9c0-4-3-5-6-8Z"/><path d="M10 20v-4a2 2 0 0 1 4 0v4"/>',
    attraction: '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"/>',
    gym: '<path d="M6 8v8M18 8v8M3 10v4M21 10v4M6 12h12"/>',
    hotel: '<path d="M3 18V7M3 14h18v4M21 14v-2a3 3 0 0 0-3-3h-7v5"/><circle cx="7" cy="11" r="1.5"/>',
    bank: '<path d="M3 10 12 4l9 6M5 10v8M9 10v8M15 10v8M19 10v8M3 20h18"/>',
    pin: '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
    home: '<path d="M4 11 12 4l8 7v9H4v-9Z"/><path d="M10 20v-5h4v5"/>',
    all: '<rect x="4" y="4" width="7" height="7" rx="2"/><rect x="13" y="4" width="7" height="7" rx="2"/><rect x="4" y="13" width="7" height="7" rx="2"/><rect x="13" y="13" width="7" height="7" rx="2"/>',
};
const ICON_RULES = [
    [/school|nursery|universit|college|academy|education/, 'education'],
    [/hospital|clinic|pharmac|medical|health/, 'health'],
    [/cafe|coffee/, 'cafe'],
    [/restaurant|food|dining/, 'food'],
    [/mall|shop|market|store/, 'shop'],
    [/beach/, 'beach'],
    [/park|garden/, 'nature'],
    [/airport/, 'airport'],
    [/metro|bus|station|tram|transit/, 'transit'],
    [/mosque|church|temple|worship/, 'mosque'],
    [/attraction|landmark|museum/, 'attraction'],
    [/gym|fitness|sport/, 'gym'],
    [/hotel|resort/, 'hotel'],
    [/bank|atm/, 'bank'],
];
const iconKey = (type) => (ICON_RULES.find(([re]) => re.test(type || '')) || [null, 'pin'])[1];
const svg = (name, size = 18) => `<svg viewBox="0 0 24 24" width="${size}" height="${size}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name] || ICONS.pin}</svg>`;

// --- Types / selection ---------------------------------------------------------------------------
const ALL = '__all';
const activeType = ref(ALL);
const activePlaceId = ref(null);
const totalPlaces = computed(() => props.groups.reduce((n, g) => n + g.places.length, 0));
const allPlaces = computed(() => props.groups
    .flatMap((g) => g.places.map((p) => ({ ...p, type: g.key, typeLabel: g.label })))
    .sort((a, b) => (a.distance_km ?? 0) - (b.distance_km ?? 0)));
const visiblePlaces = computed(() => (activeType.value === ALL ? allPlaces.value : allPlaces.value.filter((p) => p.type === activeType.value)));

function formatDistance(km) {
    if (km === null || km === undefined) return '';
    return km < 1 ? `${Math.max(50, Math.round((km * 1000) / 50) * 50)} m` : `${km.toFixed(1)} km`;
}

// Chip strip: arrow buttons only when the chips overflow.
const stripEl = ref(null);
const canScrollPrev = ref(false);
const canScrollNext = ref(false);
function updateStripArrows() {
    const el = stripEl.value;
    if (!el) return;
    const start = Math.abs(el.scrollLeft); // RTL scrolls negative
    canScrollPrev.value = start > 4;
    canScrollNext.value = start + el.clientWidth < el.scrollWidth - 4;
}
function scrollStrip(dir) {
    const el = stripEl.value;
    if (!el) return;
    const rtl = getComputedStyle(el).direction === 'rtl';
    el.scrollBy({ left: dir * (rtl ? -1 : 1) * el.clientWidth * 0.7, behavior: 'smooth' });
}
function pickType(key, evt) {
    activeType.value = key;
    activePlaceId.value = null;
    evt?.currentTarget?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
}

// --- Map -----------------------------------------------------------------------------------------
const mapEl = ref(null);
const map = shallowRef(null);
let L = null;
let placeLayer = null;
let glMap = null;
const markers = new Map();

const LABEL_EXPR = {
    en: ['coalesce', ['get', 'name:en'], ['get', 'name_en'], ['get', 'name:latin'], ['get', 'name']],
    ar: ['coalesce', ['get', 'name:ar'], ['get', 'name']],
};
function applyLabelLanguage() {
    if (!glMap || !glMap.isStyleLoaded()) return;
    const expr = LABEL_EXPR[selectedLanguage.value?.code === 'ar' ? 'ar' : 'en'];
    glMap.getStyle().layers.forEach((layer) => {
        const field = layer.type === 'symbol' && layer.layout && layer.layout['text-field'];
        if (field && JSON.stringify(field).includes('name')) glMap.setLayoutProperty(layer.id, 'text-field', expr);
    });
}

// Same basemap as the listings map (PropertyMap.vue): OpenFreeMap vector tiles, raster OSM fallback.
async function addBasemap() {
    const cfg = window.MW_MAP_TILES || {};
    try {
        const maplibregl = (await import('maplibre-gl')).default;
        await import('maplibre-gl/dist/maplibre-gl.css');
        window.maplibregl = maplibregl;
        await import('@maplibre/maplibre-gl-leaflet');
        if (!map.value) return;
        const layer = L.maplibreGL({
            style: cfg.style || 'https://tiles.openfreemap.org/styles/liberty',
            attribution: cfg.attribution || '<a href="https://openfreemap.org">OpenFreeMap</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            interactive: false,
        }).addTo(map.value);
        glMap = layer.getMaplibreMap();
        glMap.on('styledata', applyLabelLanguage);
    } catch {
        if (!map.value) return;
        L.tileLayer(cfg.fallback_url || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: cfg.fallback_attribution || '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        }).addTo(map.value);
    }
}

// Map data credits (required by the OpenStreetMap / OpenFreeMap licences) — behind a small "i" button.
const creditsOpen = ref(false);
const credits = computed(() => (window.MW_MAP_TILES || {}).attribution
    || '<a href="https://openfreemap.org" target="_blank" rel="noopener">OpenFreeMap</a> &copy; <a href="https://www.openmaptiles.org/" target="_blank" rel="noopener">OpenMapTiles</a> Data from <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>');

const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function placeIcon(place, active) {
    return L.divIcon({
        className: 'pnm-marker-wrap',
        html: `<span class="pnm-marker${active ? ' is-active' : ''}">${svg(iconKey(place.type), 15)}</span>`,
        iconSize: [32, 32],
        iconAnchor: [16, 16],
    });
}

function drawPlaces(fit = true) {
    if (!map.value || !L) return;
    placeLayer.clearLayers();
    markers.clear();
    visiblePlaces.value.forEach((place) => {
        const marker = L.marker([place.lat, place.lng], { icon: placeIcon(place, place.id === activePlaceId.value), riseOnHover: true, title: place.name })
            .bindTooltip(`<strong>${escapeHtml(place.name)}</strong><br>${escapeHtml(place.typeLabel)}${place.distance_km !== null ? ' · ' + formatDistance(place.distance_km) : ''}`, { direction: 'top', offset: [0, -14] })
            .on('click', () => focusPlace(place, false));
        marker.addTo(placeLayer);
        markers.set(place.id, marker);
    });
    if (fit) fitToVisible();
}

function fitToVisible() {
    const points = [[props.location.lat, props.location.lng], ...visiblePlaces.value.map((p) => [p.lat, p.lng])];
    if (points.length === 1) {
        map.value.setView(points[0], 15);
    } else {
        map.value.fitBounds(L.latLngBounds(points), { padding: [60, 60], maxZoom: 16 });
    }
}

const pinEl = (id) => markers.get(id)?.getElement()?.querySelector('.pnm-marker');

function focusPlace(place, pan = true) {
    // Move the highlight between pins in place — no redraw, so the other pins don't re-animate.
    const previous = activePlaceId.value;
    if (previous !== null) {
        pinEl(previous)?.classList.remove('is-active');
        markers.get(previous)?.setZIndexOffset(0);
    }
    activePlaceId.value = place.id;
    const marker = markers.get(place.id);
    if (!marker) return;
    const highlight = () => {
        pinEl(place.id)?.classList.add('is-active');
        marker.setZIndexOffset(900);
        marker.openTooltip();
    };
    // A pin hidden inside a cluster: zoom / fan the cluster out until it's visible, then highlight it.
    placeLayer.zoomToShowLayer(marker, () => {
        if (pan) map.value?.panTo(marker.getLatLng(), { animate: true });
        highlight();
    });
}

// Pins close together merge into a numbered cluster (hover lists the names; click zooms in, or fans
// them out when they're still too close) so every place can be told apart and picked.
function clusterIcon(cluster) {
    const count = cluster.getChildCount();
    return L.divIcon({ className: 'pnm-marker-wrap', html: `<span class="pnm-cluster">${count}</span>`, iconSize: [38, 38], iconAnchor: [19, 19] });
}
function clusterTooltip(e) {
    const names = e.layer.getAllChildMarkers().map((m) => `• ${escapeHtml(m.options.title)}`).join('<br>');
    e.layer.bindTooltip(names, { direction: 'top', offset: [0, -18] }).openTooltip();
}

/** Hovering a place in the list lights up its pin on the map. */
function hoverPlace(place, on) {
    pinEl(place.id)?.classList.toggle('is-hover', on);
    markers.get(place.id)?.setZIndexOffset(on ? 800 : (place.id === activePlaceId.value ? 900 : 0));
}

let resizeObserver = null;
onMounted(async () => {
    L = (await import('leaflet')).default;
    window.L = L; // markercluster is a classic plugin that extends the global `L`
    await import('leaflet.markercluster');
    if (!mapEl.value) return;
    // Same look as the listings map (PropertyMap.vue): no attribution strip (credits sit behind the "i"
    // button in the template) and the zoom buttons bottom-right.
    map.value = L.map(mapEl.value, { zoomControl: false, attributionControl: false, scrollWheelZoom: false, minZoom: 5, maxZoom: 18 })
        .setView([props.location.lat, props.location.lng], 14);
    L.control.zoom({ position: 'bottomright' }).addTo(map.value);
    await addBasemap();
    L.marker([props.location.lat, props.location.lng], {
        icon: L.divIcon({ className: 'pnm-marker-wrap', html: `<span class="pnm-home"><span class="pnm-home__pulse"></span>${svg('home', 20)}</span>`, iconSize: [44, 44], iconAnchor: [22, 22] }),
        zIndexOffset: 1000,
        keyboard: false,
    }).bindTooltip(escapeHtml(props.title), { direction: 'top', offset: [0, -20] }).addTo(map.value);
    placeLayer = L.markerClusterGroup({
        maxClusterRadius: 34,
        showCoverageOnHover: false,
        spiderfyOnMaxZoom: true,
        zoomToBoundsOnClick: true,
        spiderfyDistanceMultiplier: 1.6,
        iconCreateFunction: clusterIcon,
    }).on('clustermouseover', clusterTooltip).addTo(map.value);
    drawPlaces();
    if ('ResizeObserver' in window) {
        resizeObserver = new ResizeObserver(() => { map.value?.invalidateSize(); updateStripArrows(); });
        resizeObserver.observe(mapEl.value);
    }
    nextTick(updateStripArrows);
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    map.value?.remove();
    map.value = null;
});

watch(activeType, () => drawPlaces());
watch(() => props.groups, () => { activeType.value = ALL; drawPlaces(); nextTick(updateStripArrows); });
watch(selectedLanguage, applyLabelLanguage);
</script>

<template>
    <div class="pnm">
        <div class="pnm-map-wrap">
            <div ref="mapEl" class="pnm-map" :aria-label="t('property_details.map_aria', 'Map of the property and nearby places')"></div>
            <div class="pnm-credits" :class="{ 'is-open': creditsOpen }">
                <button type="button" class="pnm-credits-btn" :aria-expanded="creditsOpen" :aria-label="t('map.credits', 'Map credits')" :title="t('map.credits', 'Map credits')" @click="creditsOpen = !creditsOpen">i</button>
                <span v-if="creditsOpen" class="pnm-credits-text" v-html="credits"></span>
            </div>
        </div>

        <template v-if="groups.length">
            <div class="pnm-strip-wrap">
                <button v-show="canScrollPrev" type="button" class="pnm-strip-arrow pnm-strip-arrow--prev" :aria-label="t('common.previous', 'Previous')" @click="scrollStrip(-1)">‹</button>
                <div ref="stripEl" class="pnm-strip" role="tablist" @scroll.passive="updateStripArrows">
                    <button type="button" role="tab" class="pnm-chip" :class="{ 'is-active': activeType === ALL }" :aria-selected="activeType === ALL" @click="pickType(ALL, $event)">
                        <span class="pnm-chip-icon" v-html="svg('all', 16)"></span>{{ t('property_details.nearby_all', 'All') }}<span class="pnm-chip-count">{{ totalPlaces }}</span>
                    </button>
                    <button v-for="group in groups" :key="group.key" type="button" role="tab" class="pnm-chip" :class="{ 'is-active': activeType === group.key }" :aria-selected="activeType === group.key" @click="pickType(group.key, $event)">
                        <span class="pnm-chip-icon" v-html="svg(iconKey(group.key), 16)"></span>{{ group.label }}<span class="pnm-chip-count">{{ group.places.length }}</span>
                    </button>
                </div>
                <button v-show="canScrollNext" type="button" class="pnm-strip-arrow pnm-strip-arrow--next" :aria-label="t('common.next', 'Next')" @click="scrollStrip(1)">›</button>
            </div>

            <ul class="pnm-list">
                <li v-for="place in visiblePlaces" :key="place.id">
                    <button type="button" class="pnm-item" :class="{ 'is-active': place.id === activePlaceId }" @click="focusPlace(place)" @mouseenter="hoverPlace(place, true)" @mouseleave="hoverPlace(place, false)" @focus="hoverPlace(place, true)" @blur="hoverPlace(place, false)">
                        <span class="pnm-item-icon" v-html="svg(iconKey(place.type), 18)"></span>
                        <span class="pnm-item-text">
                            <span class="pnm-item-name">{{ place.name }}</span>
                            <span class="pnm-item-meta">{{ place.typeLabel }}<template v-if="place.address"> · {{ place.address }}</template></span>
                        </span>
                        <span v-if="place.distance_km !== null" class="pnm-item-distance">{{ formatDistance(place.distance_km) }}</span>
                    </button>
                </li>
            </ul>
        </template>
        <p v-else-if="address" class="pnm-address">{{ address }}</p>
    </div>
</template>

<style scoped>
.pnm { --pnm-navy: #244373; --pnm-red: #c92844; --pnm-border: #e6e9f0; display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; }
.pnm-map-wrap { position: relative; border-radius: 16px; overflow: hidden; border: 1px solid var(--pnm-border); }
.pnm-map { height: 380px; width: 100%; background: #f2f4f7; z-index: 0; }
@media (max-width: 767px) { .pnm-map { height: 300px; } }

.pnm :deep(.pnm-marker-wrap) { background: none; border: 0; }
/* Place pins: solid brand navy with a white ring so they stand out on the busy basemap. */
.pnm :deep(.pnm-marker) { position: relative; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--pnm-navy); color: #fff; border: 2.5px solid #fff; box-shadow: 0 0 0 4px rgba(36, 67, 115, 0.18), 0 6px 14px rgba(20, 30, 60, 0.35); cursor: pointer; transition: transform 0.18s ease, background 0.18s ease, box-shadow 0.18s ease; animation: pnm-pin-in 0.35s ease-out backwards; }
/* Explicit white glyph — Leaflet/site styles otherwise leave the icon's currentColor dark on the navy pin. */
.pnm :deep(.pnm-marker svg), .pnm :deep(.pnm-home svg) { display: block; color: #fff !important; stroke: #fff !important; fill: none !important; }
.pnm :deep(.pnm-marker svg *), .pnm :deep(.pnm-home svg *) { stroke: #fff !important; }
.pnm :deep(.pnm-marker:hover), .pnm :deep(.pnm-marker.is-hover) { transform: translateY(-3px) scale(1.12); box-shadow: 0 0 0 6px rgba(36, 67, 115, 0.22), 0 10px 20px rgba(20, 30, 60, 0.38); }
.pnm :deep(.pnm-marker.is-active) { background: linear-gradient(135deg, var(--pnm-red), var(--pnm-navy)); transform: scale(1.3); box-shadow: 0 0 0 6px rgba(201, 40, 68, 0.25), 0 10px 22px rgba(20, 30, 60, 0.4); z-index: 2; }
.pnm :deep(.pnm-marker.is-active::after) { content: ''; position: absolute; inset: -4px; border-radius: 50%; border: 2px solid var(--pnm-red); animation: pnm-ring 1.4s ease-out infinite; pointer-events: none; }
.pnm :deep(.pnm-home) { position: relative; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--pnm-red), #a51d36); color: #fff; border: 3px solid #fff; box-shadow: 0 8px 20px rgba(201, 40, 68, 0.45); }
.pnm :deep(.pnm-home__pulse) { position: absolute; inset: -3px; border-radius: 50%; background: rgba(201, 40, 68, 0.35); animation: pnm-ring 2s ease-out infinite; z-index: -1; }
.pnm :deep(.pnm-cluster) { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--pnm-red), var(--pnm-navy)); color: #fff; font-weight: 800; font-size: 14px; border: 2.5px solid #fff; box-shadow: 0 0 0 5px rgba(201, 40, 68, 0.2), 0 8px 18px rgba(20, 30, 60, 0.35); cursor: pointer; transition: transform 0.18s ease; }
.pnm :deep(.pnm-cluster:hover) { transform: scale(1.1); }
@keyframes pnm-ring { from { transform: scale(1); opacity: 0.9; } to { transform: scale(2); opacity: 0; } }
@keyframes pnm-pin-in { from { transform: translateY(-8px) scale(0.6); opacity: 0; } to { transform: none; opacity: 1; } }
@media (prefers-reduced-motion: reduce) { .pnm :deep(.pnm-marker), .pnm :deep(.pnm-marker.is-active::after), .pnm :deep(.pnm-home__pulse) { animation: none; } }

.pnm :deep(.leaflet-control-zoom) { border: 0; box-shadow: 0 6px 16px rgba(36, 67, 115, 0.16); border-radius: 10px; overflow: hidden; margin: 0 12px 12px 0; }
.pnm :deep(.leaflet-control-zoom a) { width: 36px; height: 36px; line-height: 36px; color: var(--pnm-navy); font-size: 18px; }
.pnm-credits { position: absolute; z-index: 500; left: 12px; bottom: 12px; display: flex; align-items: center; gap: 6px; max-width: calc(100% - 80px); }
.pnm-credits-btn { flex-shrink: 0; width: 22px; height: 22px; padding: 0; border: 0; border-radius: 50%; background: rgba(255, 255, 255, 0.92); color: #6b7080; font: italic 700 12px/22px Georgia, serif; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.18); cursor: pointer; }
.pnm-credits-btn:hover, .pnm-credits.is-open .pnm-credits-btn { color: var(--pnm-navy); }
.pnm-credits-text { padding: 4px 10px; border-radius: 50px; background: rgba(255, 255, 255, 0.95); color: #6b7080; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12); }
.pnm-credits-text :deep(a) { color: var(--pnm-navy); text-decoration: none; }

/* Grid with a minmax(0) track so the scrollable strip never widens the page column (flex/intrinsic sizing). */
.pnm-strip-wrap { position: relative; display: grid; grid-template-columns: minmax(0, 1fr); align-items: center; }
.pnm-strip { display: flex; gap: 8px; overflow-x: auto; scroll-behavior: smooth; scrollbar-width: none; padding: 2px; min-width: 0; }
.pnm-strip::-webkit-scrollbar { display: none; }
.pnm-chip { flex: 0 0 auto; display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; border-radius: 999px; border: 1px solid var(--pnm-border); background: #fff; color: #3b4660; font-weight: 600; font-size: 0.92rem; white-space: nowrap; cursor: pointer; transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease; }
.pnm-chip:hover { border-color: var(--pnm-navy); color: var(--pnm-navy); }
.pnm-chip.is-active { background: var(--pnm-navy); border-color: var(--pnm-navy); color: #fff; }
.pnm-chip-icon { display: inline-flex; }
.pnm-chip-count { min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; background: rgba(32, 63, 104, 0.08); }
.pnm-chip-count { color: var(--pnm-navy); }
.pnm-chip.is-active .pnm-chip-count { background: #fff; color: var(--pnm-navy); }
.pnm-strip-arrow { position: absolute; top: 50%; transform: translateY(-50%); z-index: 2; width: 34px; height: 34px; border-radius: 50%; border: 1px solid var(--pnm-border); background: #fff; color: var(--pnm-navy); font-size: 1.3rem; line-height: 1; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 14px rgba(20, 30, 60, 0.15); cursor: pointer; }
.pnm-strip-arrow--prev { inset-inline-start: -6px; }
.pnm-strip-arrow--next { inset-inline-end: -6px; }
[dir='rtl'] .pnm-strip-arrow { transform: translateY(-50%) scaleX(-1); }

.pnm-list { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; max-height: 330px; overflow-y: auto; }
@media (max-width: 767px) { .pnm-list { grid-template-columns: 1fr; } }
.pnm-list > li { min-width: 0; }
.pnm-item { box-sizing: border-box; width: 100%; min-width: 0; display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 12px; border: 1px solid var(--pnm-border); background: #fff; text-align: start; cursor: pointer; transition: border-color 0.15s ease, box-shadow 0.15s ease; }
.pnm-item:hover, .pnm-item.is-active { border-color: var(--pnm-navy); box-shadow: 0 6px 16px rgba(20, 30, 60, 0.08); }
.pnm-item-icon { flex: 0 0 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: rgba(32, 63, 104, 0.08); color: var(--pnm-navy); }
.pnm-item-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.pnm-item-name { font-weight: 600; color: #1d2433; font-size: 0.93rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pnm-item-meta { color: #6b7488; font-size: 0.8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pnm-item-distance { flex: 0 0 auto; font-weight: 700; font-size: 0.82rem; color: var(--pnm-navy); background: rgba(32, 63, 104, 0.08); padding: 4px 10px; border-radius: 999px; }
.pnm-address { margin: 0; color: #6b7488; }
</style>
