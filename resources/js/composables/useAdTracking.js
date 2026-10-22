// Ad performance (CRM › Advertisements report / AdStat):
//  - recordAdImpression(id): a slide was on screen (AdBlock decides when — at least half visible
//    for a second, once per slide per page visit). Sent in batches every few seconds.
//  - trackAdClick(id): the visitor opened an ad.
// Both endpoints live under /track/* (no CSRF needed); beacons survive the page being closed.

const FLUSH_MS = 4000;
const BATCH = 30;
const queue = new Set();

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
        send('/track/ad-impressions', body, preferBeacon);
    }
}

let started = false;
function start() {
    if (started || typeof window === 'undefined') return;
    started = true;
    setInterval(() => flush(false), FLUSH_MS);
    window.addEventListener('pagehide', () => flush(true));
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') flush(true);
    });
}

export function recordAdImpression(id) {
    if (!id) return;
    start();
    queue.add(id);
}

export function trackAdClick(id) {
    if (!id) return;
    const body = new FormData();
    body.append('id', String(id));
    send('/track/ad-click', body, true);
}
