<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useFormErrors } from '../composables/useFormErrors';
import { playAuthEntrance } from '../composables/useAuthEntrance';
import { useAuthPageTransition } from '../composables/useAuthPageTransition';
import { usePasswordValidation } from '../composables/usePasswordValidation';
import { animatePasswordValidation, animatePasswordStrength } from '../composables/usePasswordAnimations';
import AuthGlassAmbient from '../components/auth/AuthGlassAmbient.vue';
import PasswordField from '../components/auth/PasswordField.vue';

const router = useRouter();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const { messages: errorMessages, old: oldInput } = useFormErrors();
const accountType = ref(oldInput.signup_type || 'user');
const cardEl = ref(null);
const { navigateWithEffect } = useAuthPageTransition(cardEl);
const goToLogin = navigateWithEffect('/login');

const formAction = computed(() => (accountType.value === 'user' ? '/customer/register' : '/portal/register'));
const portalType = computed(() => (accountType.value === 'agency' ? 'company' : 'agent'));

const typeConfig = {
    user: {
        badge: '/frontend/assets/images/icons/user.svg',
        subtitle: 'Sign up as a user to save favorites and enquire on properties.',
        loginText: 'Already have an account?',
    },
    agent: {
        badge: '/frontend/assets/images/icons/verified.svg',
        subtitle: 'Sign up as an agent to list properties and manage your clients.',
        loginText: 'Already have an agent account?',
    },
    agency: {
        badge: '/frontend/assets/images/icons/building.svg',
        subtitle: 'Register your agency to list properties and manage your agents.',
        loginText: 'Already have an agency account?',
    },
};

const config = computed(() => typeConfig[accountType.value]);

const PASSWORD_RULES = [
    ['length', 'At least 8 characters'],
    ['lowercase', 'One lowercase letter'],
    ['uppercase', 'One uppercase letter'],
    ['number', 'One number'],
    ['special', 'One special character'],
];
const showPasswordHelp = ref(false);
function closePasswordHelpOnOutsideClick(e) {
    if (showPasswordHelp.value && !e.target.closest('.mw-help-wrap')) {
        showPasswordHelp.value = false;
    }
}
onMounted(() => document.addEventListener('click', closePasswordHelpOnOutsideClick));
onBeforeUnmount(() => document.removeEventListener('click', closePasswordHelpOnOutsideClick));

// --- 'user' branch only: this is the one account type that registers via axios (see
// handleSubmit below) and gets the email-OTP step — agent/agency keep the existing native
// form-post + pending-approval flow untouched. v-model (not :value) here matters: a plain
// :value binding gets forcibly reset on any unrelated re-render, which would silently wipe
// whatever the user typed the moment errorMessage/submitting changes (same bug fixed on
// Login.vue earlier).
const userName = ref(oldInput.name || '');
const userEmail = ref(oldInput.email || '');
const userPhone = ref(oldInput.phone || '');
const userPassword = ref('');
const userConfirmPassword = ref('');
const agreeTerms = ref(false);

// Agent/agency password fields stay native-form-post, but still get v-model purely so the
// eye-icon PasswordField component has something to bind to — no other behavior change.
const agentPassword = ref('');
const agentConfirmPassword = ref('');
const agencyPassword = ref('');
const agencyConfirmPassword = ref('');

const { requirements, strength, strengthPercent, isValid: passwordValid, passwordsMatch } =
    usePasswordValidation(userPassword, userConfirmPassword);

const STRENGTH_COLORS = { weak: '#c92844', medium: '#e08a1e', strong: '#1f9d55' };
const STRENGTH_LABELS = { weak: 'Weak', medium: 'Medium', strong: 'Strong' };
const strengthColor = computed(() => STRENGTH_COLORS[strength.value]);
const strengthLabel = computed(() => STRENGTH_LABELS[strength.value]);

const canSubmitUser = computed(() => (
    userName.value.trim() !== ''
    && userEmail.value.trim() !== ''
    && passwordValid.value
    && passwordsMatch.value === true
    && agreeTerms.value
));

const errorMessage = ref(errorMessages.length ? errorMessages[0] : null);
const submitting = ref(false);

async function handleSubmit(e) {
    if (accountType.value !== 'user') return; // agent/agency: let the native form post proceed as before

    e.preventDefault();
    if (submitting.value || !canSubmitUser.value) return;
    submitting.value = true;
    errorMessage.value = null;

    try {
        const { data } = await window.axios.post('/customer/register', new FormData(e.target));
        router.push({ path: '/verify-email', query: { user_id: data.user_id, email_sent: data.email_sent ? '1' : '0' } });
    } catch (error) {
        submitting.value = false;
        const fieldErrors = error.response?.data?.errors;
        errorMessage.value = fieldErrors
            ? Object.values(fieldErrors).flat()[0]
            : (error.response?.data?.message || 'Something went wrong — please try again.');
    }
}

// Requirement rows + strength bar pop/fill animation on change.
const requirementRefs = ref({});
function setRequirementRef(el, key) {
    if (el) requirementRefs.value[key] = el;
}
watch(requirements, (current, previous) => {
    if (!previous) return;
    for (const key of Object.keys(current)) {
        if (current[key] !== previous[key]) {
            animatePasswordValidation(requirementRefs.value[key]);
        }
    }
});

const strengthFillEl = ref(null);
watch(strengthPercent, (percent) => animatePasswordStrength(strengthFillEl.value, percent, strengthColor.value));

onMounted(() => playAuthEntrance(cardEl.value));
</script>

<template>
    <main>
        <section class="mw-login-band">
            <div class="container-ctn">
                <div class="mw-login-panel mw-login-panel--signup">
                    <div class="mw-login-panel__photo">
                        <img src="/frontend/assets/images/login/panel-photo.png" alt="Cozy Dubai apartment interior">
                    </div>
                    <div class="mw-login-panel__form" ref="cardEl">
                        <AuthGlassAmbient />
                        <div class="mw-signup-card__head">
                            <span class="mw-signup-card__badge">
                                <img :src="config.badge" alt="">
                            </span>
                            <h1 class="mw-login-form__title">Create your account</h1>
                            <p class="mw-login-form__subtitle">{{ config.subtitle }}</p>
                        </div>

                        <p v-if="errorMessage" class="mw-form-feedback mw-form-feedback--error" role="alert" style="margin: 0 0 16px;">{{ errorMessage }}</p>

                        <form class="mw-login-form mw-signup-form" :action="formAction" method="post" enctype="multipart/form-data" @submit="handleSubmit">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <input type="hidden" name="signup_type" :value="accountType">
                        <input v-if="accountType !== 'user'" type="hidden" name="type" :value="portalType">
                        <div class="mw-login-form__grid">
                            <div class="mw-login-form__field mw-login-form__field--full">
                                <label>Account Type</label>
                                <div class="mw-role-switch">
                                    <button type="button" :class="{ 'is-active': accountType === 'user' }" @click="accountType = 'user'">Customer</button>
                                    <button type="button" :class="{ 'is-active': accountType === 'agent' }" @click="accountType = 'agent'">Agent</button>
                                    <button type="button" :class="{ 'is-active': accountType === 'agency' }" @click="accountType = 'agency'">Agency</button>
                                </div>
                            </div>

                            <template v-if="accountType === 'user'">
                                <div class="mw-login-form__field">
                                    <label for="signup-user-name">Full Name</label>
                                    <input type="text" id="signup-user-name" name="name" v-model="userName" placeholder="Enter your full name" autocomplete="name" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-user-email">Email Address</label>
                                    <input type="email" id="signup-user-email" name="email" v-model="userEmail" placeholder="Enter your email" autocomplete="email" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-user-phone">Phone Number</label>
                                    <input type="tel" id="signup-user-phone" name="phone" v-model="userPhone" placeholder="Enter phone number" autocomplete="tel">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-user-password">
                                        Password
                                        <span class="mw-help-wrap">
                                            <button
                                                type="button"
                                                class="mw-help-icon"
                                                aria-label="Password requirements"
                                                @click="showPasswordHelp = !showPasswordHelp"
                                            >?</button>
                                            <div v-if="showPasswordHelp" class="mw-help-popover">
                                                <p class="mw-password-rules__title">Password requirements</p>
                                                <p
                                                    v-for="rule in PASSWORD_RULES"
                                                    :key="rule[0]"
                                                    :ref="(el) => setRequirementRef(el, rule[0])"
                                                    class="mw-password-rules__item"
                                                    :class="requirements[rule[0]] ? 'is-valid' : 'is-invalid'"
                                                >
                                                    <svg v-if="requirements[rule[0]]" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                    <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                                                    {{ rule[1] }}
                                                </p>
                                            </div>
                                        </span>
                                    </label>
                                    <PasswordField id="signup-user-password" name="password" v-model="userPassword" placeholder="Create a password" />
                                </div>

                                <div class="mw-login-form__field mw-login-form__field--full">
                                    <label for="signup-user-confirm">Confirm Password</label>
                                    <PasswordField id="signup-user-confirm" name="password_confirmation" v-model="userConfirmPassword" placeholder="Re-enter your password" />

                                    <div class="mw-password-rules">
                                        <div class="mw-password-strength" v-if="userPassword">
                                            <div class="mw-password-strength__head">
                                                <span>Password strength</span>
                                                <span :style="{ color: strengthColor }">{{ strengthLabel }}</span>
                                            </div>
                                            <div class="mw-password-strength__track">
                                                <div class="mw-password-strength__fill" ref="strengthFillEl"></div>
                                            </div>
                                        </div>

                                        <p v-if="passwordsMatch !== null" class="mw-password-rules__item" :class="passwordsMatch ? 'is-valid' : 'is-invalid'">
                                            <svg v-if="passwordsMatch" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                                            {{ passwordsMatch ? 'Passwords match' : 'Passwords do not match' }}
                                        </p>
                                    </div>
                                </div>
                            </template>

                            <template v-else-if="accountType === 'agent'">
                                <div class="mw-login-form__field">
                                    <label for="signup-agent-name">Full Name</label>
                                    <input type="text" id="signup-agent-name" name="name" :value="oldInput.name || ''" placeholder="Enter your full name" autocomplete="name" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-email">Email Address</label>
                                    <input type="email" id="signup-agent-email" name="email" :value="oldInput.email || ''" placeholder="Enter your email" autocomplete="email" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-phone">Phone Number</label>
                                    <input type="tel" id="signup-agent-phone" name="phone" :value="oldInput.phone || ''" placeholder="Enter phone number" autocomplete="tel">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-license">RERA / Broker License No.</label>
                                    <input type="text" id="signup-agent-license" name="brn_number" :value="oldInput.brn_number || ''" placeholder="Enter your license number">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-agency">Agency / Brokerage Name</label>
                                    <input type="text" id="signup-agent-agency" placeholder="You can link your brokerage later from your dashboard" disabled>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-password">Password</label>
                                    <PasswordField id="signup-agent-password" name="password" v-model="agentPassword" placeholder="Create a password" />
                                </div>

                                <div class="mw-login-form__field mw-login-form__field--full">
                                    <label for="signup-agent-confirm">Confirm Password</label>
                                    <PasswordField id="signup-agent-confirm" name="password_confirmation" v-model="agentConfirmPassword" placeholder="Re-enter your password" />
                                </div>
                            </template>

                            <template v-else>
                                <div class="mw-login-form__field">
                                    <label for="signup-agency-name">Agency Name</label>
                                    <input type="text" id="signup-agency-name" name="company_name" :value="oldInput.company_name || ''" placeholder="Enter your agency name" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-license">Trade License Number</label>
                                    <input type="text" id="signup-agency-license" name="trade_license_no" :value="oldInput.trade_license_no || ''" placeholder="Enter your trade license number">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-contact">Contact Person Name</label>
                                    <input type="text" id="signup-agency-contact" name="name" :value="oldInput.name || ''" placeholder="Enter contact person name" autocomplete="name" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-email">Email Address</label>
                                    <input type="email" id="signup-agency-email" name="email" :value="oldInput.email || ''" placeholder="Enter your email" autocomplete="email" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-phone">Phone Number</label>
                                    <input type="tel" id="signup-agency-phone" name="phone" :value="oldInput.phone || ''" placeholder="Enter phone number" autocomplete="tel">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-address">Office Address</label>
                                    <input type="text" id="signup-agency-address" name="office_address" :value="oldInput.office_address || ''" placeholder="Enter your office address" autocomplete="street-address">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-password">Password</label>
                                    <PasswordField id="signup-agency-password" name="password" v-model="agencyPassword" placeholder="Create a password" />
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-confirm">Confirm Password</label>
                                    <PasswordField id="signup-agency-confirm" name="password_confirmation" v-model="agencyConfirmPassword" placeholder="Re-enter your password" />
                                </div>
                            </template>
                        </div>

                        <label class="mw-signup-form__terms">
                            <input type="checkbox" name="agree_terms" v-model="agreeTerms" required>
                            <span>I agree to the <router-link to="/terms-and-conditions">Terms &amp; Conditions</router-link> and Privacy Policy.</span>
                        </label>

                        <button type="submit" class="mw-login-form__submit" :disabled="accountType === 'user' && (submitting || !canSubmitUser)">
                            {{ accountType === 'user' && submitting ? 'Creating Account…' : 'Create Account' }}
                        </button>

                        <p class="mw-login-form__signup">{{ config.loginText }} <a href="/login" @click="goToLogin">Sign In</a></p>

                        <template v-if="accountType === 'user'">
                            <div class="mw-login-form__divider"><span>OR</span></div>

                            <a href="/customer/auth/google" class="mw-login-form__google">
                                <img src="/frontend/assets/images/icons/google.svg" alt="" width="18" height="18">
                                Continue with Google
                            </a>
                        </template>
                    </form>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
