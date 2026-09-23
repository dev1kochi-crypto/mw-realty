/** Same convention public/frontend/assets/js/script.js already uses before running its own
 *  reveal-on-scroll animations — checked once per call site, not reactive (a user's OS-level
 *  preference doesn't change mid-session in any way that matters here). */
export function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
