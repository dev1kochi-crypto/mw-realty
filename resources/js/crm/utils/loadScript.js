/** Loads a CDN script once (e.g. TinyMCE for the property form) — resolves when it's ready. */
const loaded = {};

export function loadScript(src) {
    if (!loaded[src]) {
        loaded[src] = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = resolve;
            script.onerror = () => {
                delete loaded[src];
                reject(new Error(`Could not load ${src}`));
            };
            document.head.appendChild(script);
        });
    }
    return loaded[src];
}
