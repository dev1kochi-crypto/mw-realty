import { reactive, ref } from 'vue';
import { useRecaptcha } from '../../composables/useRecaptcha';
import { useStaticText } from '../../composables/useStaticText';
import { contactError, responseError } from '../../composables/useContactValidation';

/**
 * Contact details + sending a calculator lead (POST /api/contact, source=calculator) with the
 * figures the visitor worked out. Shared by the enquiry form under each calculator and the
 * eligibility "unlock your results" popup. `send(summary)` resolves true once it is saved.
 */
export function useCalculatorLead() {
    const { t } = useStaticText();
    const { getRecaptchaToken } = useRecaptcha();

    const form = reactive({ name: '', email: '', phone: '', phone_country_code: '+971', message: '' });
    const submitting = ref(false);
    const feedback = ref(null);

    async function send(summary) {
        if (submitting.value) return false;
        const problem = (!form.name.trim() && t('calculators.enquiry.error_name', 'Please enter your name.')) || contactError(form);
        if (problem) {
            feedback.value = { type: 'error', text: problem };
            return false;
        }
        submitting.value = true;
        feedback.value = null;

        try {
            const calculation = Object.fromEntries(Object.entries(summary || {}).map(([label, value]) => [label, String(value)]));
            const recaptcha_token = await getRecaptchaToken('calculator');
            await window.axios.post('/api/contact', { ...form, source: 'calculator', calculation, recaptcha_token });
            return true;
        } catch (error) {
            feedback.value = { type: 'error', text: responseError(error, t('contact_page.generic_error', 'Something went wrong. Please try again.')) };
            return false;
        } finally {
            submitting.value = false;
        }
    }

    return { form, submitting, feedback, send };
}
