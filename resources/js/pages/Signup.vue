<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useFormErrors } from '../composables/useFormErrors';
import { playAuthEntrance } from '../composables/useAuthEntrance';
import { useAuthPageTransition } from '../composables/useAuthPageTransition';
import { usePasswordValidation } from '../composables/usePasswordValidation';
import { useStaticText } from '../composables/useStaticText';
import AuthGlassAmbient from '../components/auth/AuthGlassAmbient.vue';
import PasswordCreationField from '../components/auth/PasswordCreationField.vue';

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PHONE_RE = /^[+]?[\d\s()-]{7,20}$/;
function isValidEmail(value) {
    return EMAIL_RE.test((value || '').trim());
}
function isValidPhone(value) {
    const v = (value || '').trim();
    if (v === '') return true; // phone is optional on every signup tab
    if (!PHONE_RE.test(v)) return false;
    const digitCount = v.replace(/\D/g, '').length;
    return digitCount >= 7 && digitCount <= 15;
}

const { t } = useStaticText();
const router = useRouter();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const { messages: errorMessages, old: oldInput } = useFormErrors();
const accountType = ref(oldInput.signup_type || 'user');
const cardEl = ref(null);
const { navigateWithEffect } = useAuthPageTransition(cardEl);
const goToLogin = navigateWithEffect('/login');

const formAction = computed(() => (accountType.value === 'user' ? '/customer/register' : '/portal/register'));
const portalType = computed(() => (accountType.value === 'agency' ? 'company' : 'agent'));

const typeConfig = computed(() => ({
    user: {
        badge: '/frontend/assets/images/icons/user.svg',
        subtitle: t('signup.subtitle_user'),
        loginText: t('signup.login_text_user'),
    },
    agent: {
        badge: '/frontend/assets/images/icons/verified.svg',
        subtitle: t('signup.subtitle_agent'),
        loginText: t('signup.login_text_agent'),
    },
    agency: {
        badge: '/frontend/assets/images/icons/building.svg',
        subtitle: t('signup.subtitle_agency'),
        loginText: t('signup.login_text_agency'),
    },
}));

const config = computed(() => typeConfig.value[accountType.value]);

// Shared by all three account-type tabs — only one tab is ever mounted at a time (v-if/else-if/
// else), so one pair of refs is enough; PasswordCreationField.vue owns the requirements/strength/
// match UI, this just needs the same refs for the submit-gating computeds below.
const password = ref('');
const confirmPassword = ref('');
watch(accountType, () => {
    password.value = '';
    confirmPassword.value = '';
});
const { isValid: passwordValid, passwordsMatch } = usePasswordValidation(password, confirmPassword);

// All three account types now register via axios (see handleSubmit below) and get the
// email-OTP step. v-model (not :value) here matters: a plain :value binding gets forcibly
// reset on any unrelated re-render, which would silently wipe whatever the user typed the
// moment errorMessage/submitting changes (same bug fixed on Login.vue earlier).
const userName = ref(oldInput.name || '');
const userEmail = ref(oldInput.email || '');
const userPhone = ref(oldInput.phone || '');
const agreeTerms = ref(false);

const agentName = ref(oldInput.name || '');
const agentEmail = ref(oldInput.email || '');
const agentPhone = ref(oldInput.phone || '');

const agencyName = ref(oldInput.company_name || '');
const agencyContactName = ref(oldInput.name || '');
const agencyEmail = ref(oldInput.email || '');
const agencyPhone = ref(oldInput.phone || '');

// Whether a field has been blurred at least once — so a format error only appears once the
// visitor has actually finished typing it, not the instant the page loads.
const touched = reactive({});
function touch(field) {
    touched[field] = true;
}

const canSubmitUser = computed(() => (
    userName.value.trim() !== ''
    && isValidEmail(userEmail.value)
    && isValidPhone(userPhone.value)
    && passwordValid.value
    && passwordsMatch.value === true
    && agreeTerms.value
));

const canSubmitAgent = computed(() => (
    agentName.value.trim() !== ''
    && isValidEmail(agentEmail.value)
    && isValidPhone(agentPhone.value)
    && passwordValid.value
    && passwordsMatch.value === true
    && agreeTerms.value
));

const canSubmitAgency = computed(() => (
    agencyName.value.trim() !== ''
    && agencyContactName.value.trim() !== ''
    && isValidEmail(agencyEmail.value)
    && isValidPhone(agencyPhone.value)
    && passwordValid.value
    && passwordsMatch.value === true
    && agreeTerms.value
));

const canSubmit = computed(() => {
    if (accountType.value === 'agent') return canSubmitAgent.value;
    if (accountType.value === 'agency') return canSubmitAgency.value;
    return canSubmitUser.value;
});

const errorMessage = ref(errorMessages.length ? errorMessages[0] : null);
const submitting = ref(false);

async function handleSubmit(e) {
    e.preventDefault();
    if (submitting.value || !canSubmit.value) return;
    submitting.value = true;
    errorMessage.value = null;

    try {
        const { data } = await window.axios.post(formAction.value, new FormData(e.target));
        router.push({
            path: '/verify-email',
            query: { user_id: data.user_id, email_sent: data.email_sent ? '1' : '0', account_type: accountType.value },
        });
    } catch (error) {
        submitting.value = false;
        const fieldErrors = error.response?.data?.errors;
        errorMessage.value = fieldErrors
            ? Object.values(fieldErrors).flat()[0]
            : (error.response?.data?.message || t('signup.generic_error'));
    }
}

onMounted(() => playAuthEntrance(cardEl.value));
</script>

<template>
    <main>
        <section class="mw-login-band">
            <div class="container-ctn">
                <div class="mw-login-panel mw-login-panel--signup">
                    <div class="mw-login-panel__photo">
                        <img src="/frontend/assets/images/login/panel-photo.png" :alt="t('signup.photo_alt')">
                    </div>
                    <div class="mw-login-panel__form" ref="cardEl">
                        <AuthGlassAmbient :class="{ 'is-submitting': submitting }" />
                        <div class="mw-signup-card__head">
                            <span class="mw-signup-card__badge">
                                <img :src="config.badge" alt="">
                            </span>
                            <h1 class="mw-login-form__title">{{ t('signup.title') }}</h1>
                            <p class="mw-login-form__subtitle">{{ config.subtitle }}</p>
                        </div>

                        <p v-if="errorMessage" class="mw-form-feedback mw-form-feedback--error" role="alert" style="margin: 0 0 16px;">{{ errorMessage }}</p>

                        <form class="mw-login-form mw-signup-form" :action="formAction" method="post" enctype="multipart/form-data" @submit="handleSubmit">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <input type="hidden" name="signup_type" :value="accountType">
                        <input v-if="accountType !== 'user'" type="hidden" name="type" :value="portalType">
                        <div class="mw-login-form__grid">
                            <div class="mw-login-form__field mw-login-form__field--full">
                                <label>{{ t('signup.account_type_label') }}</label>
                                <div class="mw-role-switch">
                                    <button type="button" :class="{ 'is-active': accountType === 'user' }" @click="accountType = 'user'">{{ t('signup.role_customer') }}</button>
                                    <button type="button" :class="{ 'is-active': accountType === 'agent' }" @click="accountType = 'agent'">{{ t('signup.role_agent') }}</button>
                                    <button type="button" :class="{ 'is-active': accountType === 'agency' }" @click="accountType = 'agency'">{{ t('signup.role_agency') }}</button>
                                </div>
                            </div>

                            <template v-if="accountType === 'user'">
                                <div class="mw-login-form__field">
                                    <label for="signup-user-name">{{ t('signup.full_name_label') }}</label>
                                    <input type="text" id="signup-user-name" name="name" v-model="userName" :placeholder="t('signup.full_name_placeholder')" autocomplete="name" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-user-email">{{ t('signup.email_label') }}</label>
                                    <input type="email" id="signup-user-email" name="email" v-model="userEmail" @blur="touch('userEmail')" :class="{ 'is-invalid': touched.userEmail && !isValidEmail(userEmail) }" :placeholder="t('signup.email_placeholder')" autocomplete="email" required>
                                    <p v-if="touched.userEmail && !isValidEmail(userEmail)" class="mw-field-error">{{ t('signup.email_invalid') }}</p>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-user-phone">{{ t('signup.phone_label') }}</label>
                                    <input type="tel" id="signup-user-phone" name="phone" v-model="userPhone" @blur="touch('userPhone')" :class="{ 'is-invalid': touched.userPhone && !isValidPhone(userPhone) }" :placeholder="t('signup.phone_placeholder')" autocomplete="tel">
                                    <p v-if="touched.userPhone && !isValidPhone(userPhone)" class="mw-field-error">{{ t('signup.phone_invalid') }}</p>
                                </div>

                                <PasswordCreationField id-prefix="signup-user" v-model:password="password" v-model:confirmPassword="confirmPassword" />
                            </template>

                            <template v-else-if="accountType === 'agent'">
                                <div class="mw-login-form__field">
                                    <label for="signup-agent-name">{{ t('signup.full_name_label') }}</label>
                                    <input type="text" id="signup-agent-name" name="name" v-model="agentName" :placeholder="t('signup.full_name_placeholder')" autocomplete="name" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-email">{{ t('signup.email_label') }}</label>
                                    <input type="email" id="signup-agent-email" name="email" v-model="agentEmail" @blur="touch('agentEmail')" :class="{ 'is-invalid': touched.agentEmail && !isValidEmail(agentEmail) }" :placeholder="t('signup.email_placeholder')" autocomplete="email" required>
                                    <p v-if="touched.agentEmail && !isValidEmail(agentEmail)" class="mw-field-error">{{ t('signup.email_invalid') }}</p>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-phone">{{ t('signup.phone_label') }}</label>
                                    <input type="tel" id="signup-agent-phone" name="phone" v-model="agentPhone" @blur="touch('agentPhone')" :class="{ 'is-invalid': touched.agentPhone && !isValidPhone(agentPhone) }" :placeholder="t('signup.phone_placeholder')" autocomplete="tel">
                                    <p v-if="touched.agentPhone && !isValidPhone(agentPhone)" class="mw-field-error">{{ t('signup.phone_invalid') }}</p>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-license">{{ t('signup.agent_license_label') }}</label>
                                    <input type="text" id="signup-agent-license" name="brn_number" :value="oldInput.brn_number || ''" :placeholder="t('signup.agent_license_placeholder')">
                                </div>

                                <PasswordCreationField id-prefix="signup-agent" v-model:password="password" v-model:confirmPassword="confirmPassword" />
                            </template>

                            <template v-else>
                                <div class="mw-login-form__field">
                                    <label for="signup-agency-name">{{ t('signup.agency_name_label') }}</label>
                                    <input type="text" id="signup-agency-name" name="company_name" v-model="agencyName" :placeholder="t('signup.agency_name_placeholder')" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-license">{{ t('signup.agency_license_label') }}</label>
                                    <input type="text" id="signup-agency-license" name="trade_license_no" :value="oldInput.trade_license_no || ''" :placeholder="t('signup.agency_license_placeholder')">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-contact">{{ t('signup.agency_contact_label') }}</label>
                                    <input type="text" id="signup-agency-contact" name="name" v-model="agencyContactName" :placeholder="t('signup.agency_contact_placeholder')" autocomplete="name" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-email">{{ t('signup.email_label') }}</label>
                                    <input type="email" id="signup-agency-email" name="email" v-model="agencyEmail" @blur="touch('agencyEmail')" :class="{ 'is-invalid': touched.agencyEmail && !isValidEmail(agencyEmail) }" :placeholder="t('signup.email_placeholder')" autocomplete="email" required>
                                    <p v-if="touched.agencyEmail && !isValidEmail(agencyEmail)" class="mw-field-error">{{ t('signup.email_invalid') }}</p>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-phone">{{ t('signup.phone_label') }}</label>
                                    <input type="tel" id="signup-agency-phone" name="phone" v-model="agencyPhone" @blur="touch('agencyPhone')" :class="{ 'is-invalid': touched.agencyPhone && !isValidPhone(agencyPhone) }" :placeholder="t('signup.phone_placeholder')" autocomplete="tel">
                                    <p v-if="touched.agencyPhone && !isValidPhone(agencyPhone)" class="mw-field-error">{{ t('signup.phone_invalid') }}</p>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-address">{{ t('signup.agency_address_label') }}</label>
                                    <input type="text" id="signup-agency-address" name="office_address" :value="oldInput.office_address || ''" :placeholder="t('signup.agency_address_placeholder')" autocomplete="street-address">
                                </div>

                                <PasswordCreationField id-prefix="signup-agency" v-model:password="password" v-model:confirmPassword="confirmPassword" />
                            </template>
                        </div>

                        <label class="mw-signup-form__terms">
                            <input type="checkbox" name="agree_terms" v-model="agreeTerms" required>
                            <span>{{ t('signup.terms_prefix') }} <router-link to="/terms-and-conditions" target="_blank">{{ t('signup.terms_link_text') }}</router-link> {{ t('signup.terms_suffix') }}</span>
                        </label>

                        <button type="submit" class="mw-login-form__submit" :disabled="submitting || !canSubmit">
                            <span v-if="submitting" class="mw-otp-spinner" aria-hidden="true"></span>
                            {{ submitting ? t('signup.submitting') : t('signup.submit') }}
                        </button>

                        <p class="mw-login-form__signup">{{ config.loginText }} <a href="/login" @click="goToLogin">{{ t('signup.sign_in_link') }}</a></p>

                        <!-- Google sign-in hidden for now — keep this markup, don't remove it.
                        <template v-if="accountType === 'user'">
                            <div class="mw-login-form__divider"><span>{{ t('signup.divider_or') }}</span></div>

                            <a href="/customer/auth/google" class="mw-login-form__google">
                                <img src="/frontend/assets/images/icons/google.svg" alt="" width="18" height="18">
                                {{ t('signup.google_cta') }}
                            </a>
                        </template>
                        -->
                    </form>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
