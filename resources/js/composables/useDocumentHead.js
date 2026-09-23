function upsertTag(selector, attr, value, makeTag) {
    let el = document.head.querySelector(selector);
    if (!value) {
        if (el) el.remove();
        return;
    }
    if (!el) {
        el = makeTag();
        document.head.appendChild(el);
    }
    el.setAttribute(attr, value);
}

/**
 * Applies a resolved `seo` object (as returned by every page API endpoint — see
 * App\Support\SeoMeta::resolve() server-side) to the live document head, so client-side
 * SPA navigation (which never re-hits the server) keeps the tab title/meta tags in sync.
 * The initial load's tags are already server-rendered by SpaController — this only matters
 * for the *next* page the user navigates to without a full reload.
 */
export function useDocumentHead() {
    function setSeo(seo) {
        if (!seo) return;

        if (seo.meta_title) document.title = seo.meta_title;

        upsertTag('meta[name="description"]', 'content', seo.meta_description, () => {
            const el = document.createElement('meta');
            el.setAttribute('name', 'description');
            return el;
        });
        upsertTag('link[rel="canonical"]', 'href', seo.canonical_url, () => {
            const el = document.createElement('link');
            el.setAttribute('rel', 'canonical');
            return el;
        });
        upsertTag('meta[property="og:title"]', 'content', seo.og_title, () => {
            const el = document.createElement('meta');
            el.setAttribute('property', 'og:title');
            return el;
        });
        upsertTag('meta[property="og:description"]', 'content', seo.og_description, () => {
            const el = document.createElement('meta');
            el.setAttribute('property', 'og:description');
            return el;
        });
        upsertTag('meta[property="og:image"]', 'content', seo.og_image, () => {
            const el = document.createElement('meta');
            el.setAttribute('property', 'og:image');
            return el;
        });
    }

    return { setSeo };
}
