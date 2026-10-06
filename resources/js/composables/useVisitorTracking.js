// Page tracker for website-lead insights (VisitorTrackingController / VisitorTracker): one event per
// route visit, plus the seconds the page was actually visible — paused while the tab is hidden,
// sent as a beacon whenever the visitor hides / leaves the page (and every 30s as a heartbeat), so
// the latest total is kept even if the tab is simply closed. The server turns property detail pages
// into property views and filtered listing pages into searches.

// A route has to stay put this long to count — skips redirects and rapid filter tweaks.
const SETTLE_MS = 1000;
const HEARTBEAT_MS = 30000;

let current = null;
let settleTimer = null;

const clock = () => performance.now();

function visibleSeconds(entry) {
    const running = entry.visibleSince !== null ? clock() - entry.visibleSince : 0;
    return Math.round((entry.visibleMs + running) / 1000);
}

function sendTime(entry) {
    if (!entry?.id) return;
    const seconds = visibleSeconds(entry);
    if (seconds <= entry.sentSeconds) return;
    entry.sentSeconds = seconds;

    const url = `/track/page/${entry.id}/time`;
    const body = new FormData();
    body.append('seconds', String(seconds));
    if (!(navigator.sendBeacon && navigator.sendBeacon(url, body))) {
        window.axios.post(url, body).catch(() => {});
    }
}

function pause(entry) {
    if (entry.visibleSince === null) return;
    entry.visibleMs += clock() - entry.visibleSince;
    entry.visibleSince = null;
}

function begin(route) {
    if (current) {
        pause(current);
        sendTime(current);
    }
    clearTimeout(settleTimer);

    const entry = {
        id: null,
        left: false,
        visibleMs: 0,
        visibleSince: document.visibilityState === 'visible' ? clock() : null,
        sentSeconds: 0,
    };
    if (current) current.left = true;
    current = entry;

    settleTimer = setTimeout(() => {
        window.axios.post('/track/page', {
            path: route.fullPath,
            title: document.title,
            referrer: document.referrer || null,
        }).then(({ data }) => {
            entry.id = data?.id || null;
            // Left again before the id came back — still record how long it was looked at.
            if (entry.left) sendTime(entry);
        }).catch(() => {});
    }, SETTLE_MS);
}

document.addEventListener('visibilitychange', () => {
    if (!current) return;
    if (document.visibilityState === 'hidden') {
        pause(current);
        sendTime(current);
    } else {
        current.visibleSince = clock();
    }
});

window.addEventListener('pagehide', () => {
    if (!current) return;
    pause(current);
    sendTime(current);
});

setInterval(() => {
    if (current && document.visibilityState === 'visible') sendTime(current);
}, HEARTBEAT_MS);

/** Called once from app.js with the SPA router. */
export function installVisitorTracking(router) {
    router.afterEach((to, from, failure) => {
        if (failure) return;
        // Hash-only changes (in-page anchors) aren't a new page.
        if (from.name && to.path === from.path && JSON.stringify(to.query) === JSON.stringify(from.query)) return;
        begin(to);
    });
}
