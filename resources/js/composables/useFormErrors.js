// Login.vue and Signup.vue post as real native <form> submissions (session redirect flow,
// not axios), so a failed submission comes back as a fresh page load with the error and the
// submitted values flashed to the session by Laravel — welcome.blade.php injects them as
// window.MW_FORM_ERRORS / window.MW_OLD_INPUT once per load. Read once and consumed by the
// page that needed them; a later navigation away and back is a fresh server render anyway.
export function useFormErrors() {
    const errors = window.MW_FORM_ERRORS || null;
    const old = window.MW_OLD_INPUT || {};
    const messages = errors ? Object.values(errors).flat() : [];

    return { messages, old };
}
