import { computed, ref } from 'vue';

/** Auto-advance/backspace/arrow-key/paste handling for a row of single-digit OTP inputs —
 *  pure interaction logic, no animation (see useOtpAnimations.js for that). */
export function useOtpInputs(length = 4) {
    const digits = ref(Array(length).fill(''));
    const inputEls = ref([]);

    function setInputRef(el, index) {
        if (el) inputEls.value[index] = el;
    }

    function focusInput(index) {
        inputEls.value[index]?.focus();
    }

    /** @returns {boolean} whether a new digit was actually entered (vs. e.g. a no-op keypress) */
    function onInput(index, event) {
        const digit = event.target.value.replace(/[^0-9]/g, '').slice(-1);
        digits.value[index] = digit;
        event.target.value = digit;

        if (digit && index < length - 1) {
            focusInput(index + 1);
        }

        return !!digit;
    }

    function onKeydown(index, event) {
        if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
            digits.value[index - 1] = '';
            focusInput(index - 1);
        } else if (event.key === 'ArrowLeft' && index > 0) {
            event.preventDefault();
            focusInput(index - 1);
        } else if (event.key === 'ArrowRight' && index < length - 1) {
            event.preventDefault();
            focusInput(index + 1);
        }
    }

    /** @returns {boolean} whether a full code was pasted and distributed */
    function onPaste(event) {
        event.preventDefault();
        const pasted = (event.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
        if (!pasted) return false;

        for (let i = 0; i < length; i++) {
            digits.value[i] = pasted[i] || '';
        }
        const lastFilledIndex = Math.min(pasted.length, length) - 1;
        focusInput(Math.max(lastFilledIndex, 0));

        return pasted.length >= length;
    }

    function reset() {
        digits.value = Array(length).fill('');
        focusInput(0);
    }

    const code = computed(() => digits.value.join(''));
    const isComplete = computed(() => digits.value.length === length && digits.value.every((d) => d !== ''));

    return { digits, inputEls, setInputRef, onInput, onKeydown, onPaste, reset, code, isComplete };
}
