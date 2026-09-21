// Loads the Google reCAPTCHA v3 script once (site key injected into window.MW_RECAPTCHA_SITE_KEY
// by welcome.blade.php) and issues per-submit tokens. Resolves to null when no site key is
// configured yet, so forms keep working uninterrupted before real keys are added — the backend
// (App\Rules\RecaptchaRule) mirrors this by skipping verification when no secret key is set.
let scriptPromise = null;

function loadScript(siteKey) {
    if (!scriptPromise) {
        scriptPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = `https://www.google.com/recaptcha/api.js?render=${siteKey}`;
            script.async = true;
            script.defer = true;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Failed to load reCAPTCHA script'));
            document.head.appendChild(script);
        });
    }
    return scriptPromise;
}

export function useRecaptcha() {
    async function getRecaptchaToken(action) {
        const siteKey = window.MW_RECAPTCHA_SITE_KEY;
        if (!siteKey) {
            return null;
        }

        try {
            await loadScript(siteKey);
            return await new Promise((resolve) => {
                window.grecaptcha.ready(() => {
                    window.grecaptcha.execute(siteKey, { action }).then(resolve).catch(() => resolve(null));
                });
            });
        } catch {
            return null;
        }
    }

    return { getRecaptchaToken };
}
