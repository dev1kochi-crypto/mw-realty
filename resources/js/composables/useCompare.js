import { computed, ref, watch } from 'vue';

/**
 * The visitor's compare list — up to MAX listings picked with the "Compare" button on property cards
 * and the property page, shown in the floating CompareTray and opened side by side on /compare.
 * Shared app-wide (one list, like useWishlist) and kept in localStorage, so it survives reloads and
 * stays in step across tabs. Each entry keeps just what the tray shows: slug, name, image.
 */
const STORAGE_KEY = 'mw-compare';
export const COMPARE_MIN = 2;
export const COMPARE_MAX = 5;

const items = ref(read());
// Set for a moment when a 6th listing is picked, so the tray can say the list is full.
const full = ref(false);
let fullTimer = null;
let initialized = false;

function read() {
    try {
        const list = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '[]');
        return Array.isArray(list) ? list.filter((i) => i && i.slug).slice(0, COMPARE_MAX) : [];
    } catch {
        return [];
    }
}
function store(list) {
    try { window.localStorage.setItem(STORAGE_KEY, JSON.stringify(list)); } catch { /* private mode etc. */ }
}

function has(slug) {
    return items.value.some((i) => i.slug === slug);
}
function remove(slug) {
    items.value = items.value.filter((i) => i.slug !== slug);
}
/** Adds or removes a listing ({ slug, name, image }). Returns false when the list is already full. */
function toggle(property) {
    if (has(property.slug)) {
        remove(property.slug);
        return true;
    }
    if (items.value.length >= COMPARE_MAX) {
        full.value = true;
        clearTimeout(fullTimer);
        fullTimer = setTimeout(() => { full.value = false; }, 3500);
        return false;
    }
    const image = property.image || property.images?.[0] || null;
    items.value = [...items.value, { slug: property.slug, name: property.name, image }];
    return true;
}
/** Replaces the list, e.g. when /compare is opened from a shared link. */
function setSlugs(list) {
    const known = Object.fromEntries(items.value.map((i) => [i.slug, i]));
    items.value = list.slice(0, COMPARE_MAX).map((p) => ({ ...known[p.slug], ...p }));
}
function clear() {
    items.value = [];
}

const compareUrl = computed(() => `/compare?slugs=${items.value.map((i) => encodeURIComponent(i.slug)).join(',')}`);

export function useCompare() {
    if (!initialized) {
        initialized = true;
        watch(items, store, { deep: true });
        window.addEventListener('storage', (e) => {
            if (e.key === STORAGE_KEY) items.value = read();
        });
    }

    return { items, full, has, toggle, remove, setSlugs, clear, compareUrl };
}
