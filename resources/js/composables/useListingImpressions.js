// Listing performance (portal Listing Performance panel / PropertyDailyStat):
//  - v-track-impression="property.id" on a listing card counts an impression once at least half of
//    the card is on screen — once per listing per browser per day, sent in batches.
//  - trackLeadClick(propertyId, kind) counts a click on a listing's contact buttons.
// Both endpoints live under /track/* (no CSRF needed); beacons survive the page being closed.

const STORAGE_PREFIX = 'mw-imp-';
const FLUSH_MS = 5000;
const BATCH = 60;

const today = () => new Date().toISOString().slice(0, 10);
const queue = new Set();
let seenDay = today();
let seen = loadSeen();

function loadSeen() {
    try {
        return new Set(JSON.parse(sessionStorage.getItem(STORAGE_PREFIX + seenDay) || '[]'));
    } catch {
        return new Set();
    }
}

function saveSeen() {
    try {
        sessionStorage.setItem(STORAGE_PREFIX + seenDay, JSON.stringify(Array.from(seen).slice(-500)));
    } catch {
        // Storage full / blocked — impressions still count, just not de-duplicated across pages.
    }
}

function send(url, body, preferBeacon) {
    if (preferBeacon && navigator.sendBeacon && navigator.sendBeacon(url, body)) return;
    window.axios.post(url, body).catch(() => {});
}

function flush(preferBeacon = false) {
    while (queue.size) {
        const ids = Array.from(queue).slice(0, BATCH);
        ids.forEach((id) => queue.delete(id));
        const body = new FormData();
        ids.forEach((id) => body.append('ids[]', String(id)));
        send('/track/impressions', body, preferBeacon);
    }
}

const observer = typeof IntersectionObserver !== 'undefined'
    ? new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);
            const id = Number(entry.target.dataset.impressionId);
            if (seenDay !== today()) {
                seenDay = today();
                seen = loadSeen();
            }
            if (!id || seen.has(id)) return;
            seen.add(id);
            saveSeen();
            queue.add(id);
        });
    }, { threshold: 0.5 })
    : null;

if (observer) {
    setInterval(() => flush(false), FLUSH_MS);
    window.addEventListener('pagehide', () => flush(true));
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') flush(true);
    });
}

function watch(el, id) {
    if (!observer || !id) return;
    el.dataset.impressionId = String(id);
    observer.observe(el);
}

/** Registered globally in app.js as v-track-impression. */
export const vTrackImpression = {
    mounted(el, binding) {
        watch(el, binding.value);
    },
    updated(el, binding) {
        if (binding.value && el.dataset.impressionId !== String(binding.value)) watch(el, binding.value);
    },
    unmounted(el) {
        observer?.unobserve(el);
    },
};

/** kind: call | whatsapp | email | enquiry | viewing | brochure | floor_plan */
export function trackLeadClick(propertyId, kind) {
    if (!propertyId) return;
    const body = new FormData();
    body.append('property_id', String(propertyId));
    body.append('kind', kind);
    send('/track/lead-click', body, true);
}
