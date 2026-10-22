/*
 * Country-code phone pickers for the portal (intl-tel-input, loaded in portal/layouts/app).
 * Markup — the visible field holds the number only, a hidden field carries the "+code":
 *   <input type="hidden" name="phone_country_code" id="x" value="+971">
 *   <input type="tel" name="phone" data-phone-input data-code-input="#x">
 * Limits match App\Rules\PhoneNumber (7–13 digits, without the country code).
 */
(function () {
    const MIN_DIGITS = 7;
    const MAX_DIGITS = 13;

    function codeInputFor(input) {
        return (input.dataset.codeInput && document.querySelector(input.dataset.codeInput))
            || input.closest('form')?.querySelector('[name="phone_country_code"]');
    }

    function sync(input) {
        const codeInput = codeInputFor(input);
        const data = input._iti.getSelectedCountryData();
        if (codeInput && data.dialCode) codeInput.value = '+' + data.dialCode;
    }

    /** Select the country for a "+971" dial code (the main country when several share it, e.g. +1 → US). */
    function setCode(input, code) {
        const iti = init(input);
        const dial = String(code || '').replace(/\D/g, '');
        const match = window.intlTelInput.getCountryData()
            .filter((c) => c.dialCode === dial)
            .sort((a, b) => (a.priority || 0) - (b.priority || 0))[0];
        iti.setCountry(match ? match.iso2 : 'ae');
        sync(input);
    }

    function check(input) {
        const digits = input.value.replace(/\D/g, '').length;
        input.setCustomValidity(digits && (digits < MIN_DIGITS || digits > MAX_DIGITS)
            ? `Enter ${MIN_DIGITS}–${MAX_DIGITS} digits, without the country code.` : '');
    }

    function init(input) {
        if (input._iti) return input._iti;
        input._iti = window.intlTelInput(input, {
            initialCountry: 'ae',
            separateDialCode: true,
            countryOrder: ['ae', 'sa', 'kw', 'bh', 'qa', 'om', 'in', 'gb', 'us'],
        });
        input.setAttribute('inputmode', 'tel');
        input.addEventListener('countrychange', () => sync(input));
        input.addEventListener('input', () => {
            // Number only: digits with optional spaces/dashes, capped at the max digit count.
            let value = input.value.replace(/[^\d\s-]/g, '');
            let seen = 0;
            value = value.replace(/\d/g, (d) => (++seen <= MAX_DIGITS ? d : ''));
            if (value !== input.value) input.value = value;
            check(input);
        });
        const codeInput = codeInputFor(input);
        if (codeInput && codeInput.value) setCode(input, codeInput.value); else sync(input);
        return input._iti;
    }

    window.portalPhone = { init, setCode };
    const initAll = () => document.querySelectorAll('[data-phone-input]').forEach(init);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll); else initAll();
})();
