/**
 * Media is stored as full-size Cloudinary originals (often 1–6 MB JPEGs, an 8 MB hero video).
 * Cloudinary resizes/re-encodes on the fly when the URL carries a transformation, so the public
 * site asks for a sized, auto-format (WebP/AVIF), auto-quality copy instead of the original.
 */
const UPLOAD = /^(https:\/\/res\.cloudinary\.com\/[^/]+\/(image|video)\/upload\/)(?:(?:[a-z]{1,3}_[^/,]+,?)+\/)?(?=(?:v\d+\/)?[^/]+\/)/;

/** Adds (or replaces this helper's own) transformation on a Cloudinary image/video URL; other URLs pass through. */
export function cloudinaryUrl(url, width) {
    if (typeof url !== 'string' || !url.includes('res.cloudinary.com/')) return url;
    const match = url.match(UPLOAD);
    if (!match) return url;
    const [whole, base, type] = match;
    const rest = url.slice(whole.length);
    // PDFs/SVGs live under image/upload too — leave them untouched.
    if (/\.(pdf|svg)(\?|$)/i.test(rest)) return url;
    const transform = type === 'video'
        ? `q_auto,vc_auto,c_limit,w_${width}`
        : `f_auto,q_auto,c_limit,w_${width}`;

    return `${base}${transform}/${rest}`;
}

const isPhone = () => typeof window !== 'undefined' && window.innerWidth < 768;

/** Default size for anything coming from the API — big enough for full-width banners and galleries. */
function defaultWidth(type) {
    if (type === 'video') return isPhone() ? 720 : 1280;
    return isPhone() ? 900 : 1600;
}

function rewrite(value) {
    if (typeof value === 'string') {
        if (!value.includes('res.cloudinary.com/')) return value;
        const type = value.includes('/video/upload/') ? 'video' : 'image';
        return cloudinaryUrl(value, defaultWidth(type));
    }
    if (Array.isArray(value)) {
        for (let i = 0; i < value.length; i++) value[i] = rewrite(value[i]);
        return value;
    }
    if (value && typeof value === 'object') {
        for (const key of Object.keys(value)) value[key] = rewrite(value[key]);
    }
    return value;
}

/** Rewrites Cloudinary URLs in every GET reply (the public site never sends media URLs back). */
export function installCloudinaryOptimizer(instance) {
    instance.interceptors.response.use((response) => {
        if (String(response.config?.method ?? 'get').toLowerCase() === 'get') {
            response.data = rewrite(response.data);
        }
        return response;
    });
}
