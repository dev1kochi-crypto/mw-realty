import { computed } from 'vue';

const SPECIAL_CHARS = /[!@#$%^&*(),.?":{}|<>_\-+=[\];'`~\\/]/;

/**
 * Pure reactive validation logic for a password (+ optional confirm) field — no DOM/animation
 * here, so it's easy to reuse across Signup's three account-type variants. The component's
 * template/GSAP code reacts to these computed values (see animatePasswordValidation/
 * animatePasswordStrength in useAuthEntrance.js) rather than this file touching the DOM.
 *
 * @param {import('vue').Ref<string>} passwordRef
 * @param {import('vue').Ref<string>} [confirmRef]
 */
export function usePasswordValidation(passwordRef, confirmRef) {
    const requirements = computed(() => {
        const value = passwordRef.value || '';
        return {
            length: value.length >= 8,
            lowercase: /[a-z]/.test(value),
            uppercase: /[A-Z]/.test(value),
            number: /[0-9]/.test(value),
            special: SPECIAL_CHARS.test(value),
        };
    });

    const passedCount = computed(() => Object.values(requirements.value).filter(Boolean).length);

    // WEAK: <3 requirements satisfied. MEDIUM: 3-4. STRONG: all 5.
    const strength = computed(() => {
        if (passedCount.value >= 5) return 'strong';
        if (passedCount.value >= 3) return 'medium';
        return 'weak';
    });

    const strengthPercent = computed(() => (passedCount.value / 5) * 100);
    const isValid = computed(() => passedCount.value === 5);

    // Only meaningful once the user has actually started typing a confirmation — showing
    // "doesn't match" before they've typed anything would be a false-negative flash.
    const passwordsMatch = computed(() => {
        if (!confirmRef || !confirmRef.value) return null;
        return confirmRef.value === passwordRef.value;
    });

    return { requirements, strength, strengthPercent, isValid, passwordsMatch };
}
