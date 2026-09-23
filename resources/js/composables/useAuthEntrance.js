import gsap from 'gsap';
import { prefersReducedMotion } from './useReducedMotion';

/**
 * Staggered entrance for everything inside an auth card/panel — the head block (badge/title/
 * subtitle), any error message, and the form's own fields/links/buttons — in their existing DOM
 * order. No markup reordering or new data-* attributes needed; this just animates what's already
 * there. `containerEl` is the panel/card that directly contains (a) a <form> and (b) whatever
 * else sits alongside it (pill tabs, a head block, an error paragraph) — see Login.vue/Signup.vue.
 */
export function playAuthEntrance(containerEl) {
    if (!containerEl) return;

    // :scope > *:not(form) — sibling content alongside the form (pill tabs, head block, errors).
    // :scope > form > * — the form's own direct children, exploded so each animates separately.
    // Hidden inputs (_token, login_type, ...) aren't visible, so excluded from both.
    const elements = Array.from(
        containerEl.querySelectorAll(':scope > *:not(.mw-auth-glass):not(form), :scope > form > *'),
    ).filter((el) => !(el.tagName === 'INPUT' && el.type === 'hidden'));

    if (prefersReducedMotion()) {
        gsap.set(elements, { clearProps: 'all' });
        return;
    }

    gsap.set(elements, { opacity: 0, y: 16 });
    gsap.to(elements, {
        opacity: 1,
        y: 0,
        duration: 0.5,
        stagger: 0.06,
        ease: 'power3.out',
        delay: 0.15,
        clearProps: 'transform',
    });
}
