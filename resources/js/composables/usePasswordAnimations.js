import gsap from 'gsap';
import { prefersReducedMotion } from './useReducedMotion';

/** A single requirement row flipping between invalid/valid — small scale pop + the
 *  cross/check icon swap is handled by v-if in the template, this just animates the container. */
export function animatePasswordValidation(rowEl) {
    if (!rowEl || prefersReducedMotion()) return;
    gsap.fromTo(rowEl, { scale: 0.96 }, { scale: 1, duration: 0.25, ease: 'back.out(2)' });
}

/** The strength bar's fill width + color transition (red -> orange -> green as it grows). */
export function animatePasswordStrength(fillEl, percent, color) {
    if (!fillEl) return;
    if (prefersReducedMotion()) {
        gsap.set(fillEl, { width: `${percent}%`, backgroundColor: color });
        return;
    }
    gsap.to(fillEl, { width: `${percent}%`, backgroundColor: color, duration: 0.35, ease: 'power2.out' });
}
