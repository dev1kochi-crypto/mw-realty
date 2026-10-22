/**
 * Shared checks for the website's lead / contact forms. The server re-checks the same things
 * (App\Rules\PhoneNumber) — keep the limits in step.
 */
export const PHONE_MIN_DIGITS = 7;
export const PHONE_MAX_DIGITS = 13;

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

/** Error text for a phone number typed WITHOUT its country code, or null when fine. */
export function phoneError(value, required = false) {
    const text = String(value || '').trim();
    if (!text) return required ? 'Please enter your phone number.' : null;
    if (!/^[\d\s-]+$/.test(text)) return 'The phone number may only contain digits.';
    const digits = text.replace(/\D/g, '').length;
    if (digits < PHONE_MIN_DIGITS || digits > PHONE_MAX_DIGITS) {
        return `The phone number must be ${PHONE_MIN_DIGITS}–${PHONE_MAX_DIGITS} digits (without the country code).`;
    }
    return null;
}

export function emailError(value, required = true) {
    const text = String(value || '').trim();
    if (!text) return required ? 'Please enter your email address.' : null;
    return EMAIL_PATTERN.test(text) ? null : 'Please enter a valid email address.';
}

/** First problem with a form's email/phone, or null. */
export function contactError({ email, phone, emailRequired = true, phoneRequired = false }) {
    return emailError(email, emailRequired) || phoneError(phone, phoneRequired);
}

/** First message from a Laravel 422 (or the response message), for the form's error line. */
export function responseError(error, fallback) {
    const errors = error?.response?.data?.errors;
    return errors ? Object.values(errors).flat()[0] : (error?.response?.data?.message || fallback);
}
