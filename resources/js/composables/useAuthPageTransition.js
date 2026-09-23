import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import gsap from 'gsap';
import { prefersReducedMotion } from './useReducedMotion';
import { authTransitionState } from './authTransitionState';

/**
 * The login<->signup orb transition, entirely self-contained to whichever page uses it — no
 * global router-view/<transition> wrapper involved (that approach crashed Vue's internal
 * afterLeave hook and broke navigation site-wide; this doesn't touch anything outside these two
 * pages). The leaving page plays its own exit animation on click, then pushes the route itself;
 * the entering page checks `authTransitionState.active` on mount to know whether to play the
 * matching "orb contracts back down" animation, or do nothing (a direct visit, a reload, or
 * this page reached from anywhere other than that other page's link).
 *
 * @param {import('vue').Ref<HTMLElement|null>} panelEl  the panel/card containing this page's
 *        own .mw-auth-glass / .mw-auth-glass__orb / .mw-login-form (see AuthGlassAmbient.vue)
 */
export function useAuthPageTransition(panelEl) {
    const router = useRouter();

    function navigateWithEffect(to) {
        return function handleClick(e) {
            e.preventDefault();

            if (prefersReducedMotion() || !panelEl.value) {
                router.push(to);
                return;
            }

            const glass = panelEl.value.querySelector('.mw-auth-glass');
            const orb = panelEl.value.querySelector('.mw-auth-glass__orb');
            const form = panelEl.value.querySelector('.mw-login-form');

            if (!glass && !orb && !form) {
                router.push(to);
                return;
            }

            authTransitionState.active = true;
            const tl = gsap.timeline({ onComplete: () => router.push(to) });
            if (form) tl.to(form, { opacity: 0, scale: 0.97, y: -8, duration: 0.35, ease: 'power2.in' }, 0);
            if (glass) tl.to(glass, { opacity: 1, duration: 0.25, ease: 'power2.out' }, 0);
            if (orb) tl.to(orb, { scale: 2.2, duration: 0.45, ease: 'power2.inOut' }, 0);
        };
    }

    onMounted(() => {
        if (!authTransitionState.active) return;
        authTransitionState.active = false;

        if (prefersReducedMotion() || !panelEl.value) return;

        const glass = panelEl.value.querySelector('.mw-auth-glass');
        const orb = panelEl.value.querySelector('.mw-auth-glass__orb');

        if (orb) {
            gsap.set(orb, { scale: 2.2 });
            gsap.to(orb, { scale: 1, duration: 0.5, ease: 'power2.out' });
        }
        // Fades back to fully invisible once the contract settles — only ever seen during the
        // click transition itself, never lingering as ambient page decoration afterward.
        if (glass) {
            gsap.set(glass, { opacity: 1 });
            gsap.to(glass, { opacity: 0, duration: 0.4, delay: 0.5, ease: 'power2.out' });
        }
    });

    return { navigateWithEffect };
}
