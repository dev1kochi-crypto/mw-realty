<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { useFormErrors } from '../composables/useFormErrors';
import { playAuthEntrance } from '../composables/useAuthEntrance';
import { useAuthPageTransition } from '../composables/useAuthPageTransition';
import { useStaticText } from '../composables/useStaticText';
import AuthGlassAmbient from '../components/auth/AuthGlassAmbient.vue';
import PasswordField from '../components/auth/PasswordField.vue';

const { t } = useStaticText();

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
// Still read once on mount for the one remaining full-reload path this page can land from:
// a failed Google OAuth callback (CustomerAuthController::handleGoogleCallback redirects back
// with a flashed error — that's a real browser redirect, not an axios call, so it can't return
// JSON). Login itself now submits via axios below and manages its own error state locally.
const { messages: errorMessages, old: oldInput } = useFormErrors();
const accountType = ref(oldInput.login_type || 'user');
const panelFormEl = ref(null);
const { navigateWithEffect } = useAuthPageTransition(panelFormEl);
const goToSignup = navigateWithEffect('/signup');
const goToForgotPassword = navigateWithEffect('/forgot-password');

const formAction = computed(() => (accountType.value === 'user' ? '/customer/login' : '/portal/login'));

const errorMessage = ref(errorMessages.length ? errorMessages[0] : null);
const submitting = ref(false);
// v-model, not just :value — a plain :value binding gets force-reset to this initial expression
// on every re-render (Vue always re-syncs an <input>'s value/checked/selected on patch), which
// silently wiped whatever the user had typed the moment errorMessage/submitting changed and
// triggered one. v-model keeps these in sync in both directions, so a re-render just reapplies
// the same current value instead of stomping it.
const email = ref(oldInput.email || '');
const password = ref('');

async function handleSubmit(e) {
    if (submitting.value) return;
    submitting.value = true;
    errorMessage.value = null;
    const loginType = accountType.value;
    const loginAction = loginType === 'user' ? '/customer/login' : '/portal/login';

    try {
        const { data } = await window.axios.post(loginAction, new FormData(e.target));
        if (data.challenge) {
            showChallenge(data.challenge, data.email);
            submitting.value = false;
            return;
        }
        window.location.href = data.redirect || (loginType === 'user' ? '/profile' : '/portal/dashboard');
    } catch (error) {
        errorMessage.value = error.response?.data?.message || t('login.generic_error');
        submitting.value = false;
    }
}

// Agent/Agency logins can need extra steps after the password (PortalAuthController::login()):
// 'email' — a code mailed because this browser is new for the account; 'totp' — the 6-digit
// authenticator-app (Authy etc.) code, or a recovery code, when 2FA is on.
const step = ref('credentials');
const maskedEmail = ref('');
const code = ref('');
const codeInput = ref(null);
const infoMessage = ref(null);
const resending = ref(false);

function showChallenge(challenge, emailHint) {
    step.value = challenge;
    if (emailHint) maskedEmail.value = emailHint;
    code.value = '';
    errorMessage.value = null;
    infoMessage.value = null;
    nextTick(() => codeInput.value?.focus());
}

function backToCredentials() {
    step.value = 'credentials';
    code.value = '';
    password.value = '';
    infoMessage.value = null;
}

async function submitChallenge() {
    if (submitting.value || !code.value.trim()) return;
    submitting.value = true;
    errorMessage.value = null;
    infoMessage.value = null;
    const url = step.value === 'email' ? '/portal/login/verify-email' : '/portal/login/two-factor';

    try {
        const { data } = await window.axios.post(url, { code: code.value });
        if (data.challenge) {
            showChallenge(data.challenge);
            submitting.value = false;
            return;
        }
        window.location.href = data.redirect || '/portal/dashboard';
    } catch (error) {
        const response = error.response?.data || {};
        if (response.restart) backToCredentials();
        errorMessage.value = response.message || t('login.generic_error');
        code.value = '';
        submitting.value = false;
        nextTick(() => codeInput.value?.focus());
    }
}

async function resendEmailCode() {
    if (resending.value) return;
    resending.value = true;
    errorMessage.value = null;
    try {
        const { data } = await window.axios.post('/portal/login/resend-email');
        infoMessage.value = data.message || t('login.code_resent', 'A new code has been sent.');
    } catch (error) {
        const response = error.response?.data || {};
        if (response.restart) backToCredentials();
        errorMessage.value = response.message || t('login.generic_error');
    } finally {
        resending.value = false;
    }
}

onMounted(() => playAuthEntrance(panelFormEl.value));
</script>

<template>
    <main>
        <section class="mw-login-band">
            <div class="container-ctn">
                <div class="mw-login-panel">
                    <div class="mw-login-panel__photo">
                        <img src="/frontend/assets/images/login/panel-photo.png" :alt="t('login.photo_alt')">
                    </div>
                    <div class="mw-login-panel__form" ref="panelFormEl">
                        <AuthGlassAmbient />
                        <form v-if="step !== 'credentials'" class="mw-login-form" @submit.prevent="submitChallenge">
                            <template v-if="step === 'email'">
                                <h1 class="mw-login-form__title">{{ t('login.new_device_title', 'Verify it\'s you') }}</h1>
                                <p class="mw-login-form__subtitle">{{ t('login.new_device_subtitle', 'You\'re signing in from a new device. We\'ve sent a verification code to') }} <strong>{{ maskedEmail }}</strong></p>
                            </template>
                            <template v-else>
                                <h1 class="mw-login-form__title">{{ t('login.two_factor_title', 'Two-factor authentication') }}</h1>
                                <p class="mw-login-form__subtitle">{{ t('login.two_factor_subtitle', 'Open your authenticator app (Authy, Google Authenticator…) and enter the 6-digit code for MW Realty.') }}</p>
                            </template>

                            <p v-if="errorMessage" class="mw-form-feedback mw-form-feedback--error" role="alert">{{ errorMessage }}</p>
                            <p v-if="infoMessage" class="mw-form-feedback" role="status">{{ infoMessage }}</p>

                            <div class="mw-login-form__field">
                                <label for="login-code">{{ step === 'email' ? t('login.email_code_label', 'Verification code') : t('login.two_factor_code_label', 'Authentication code') }}</label>
                                <input
                                    id="login-code"
                                    ref="codeInput"
                                    v-model="code"
                                    type="text"
                                    :inputmode="step === 'email' ? 'numeric' : 'text'"
                                    autocomplete="one-time-code"
                                    maxlength="20"
                                    :placeholder="step === 'email' ? '••••••' : '000000'"
                                    style="letter-spacing: 0.3em; text-align: center; font-weight: 700;"
                                    required
                                >
                            </div>

                            <p v-if="step === 'totp'" class="mw-login-form__subtitle" style="font-size: 0.85em;">{{ t('login.recovery_hint', 'Lost your phone? Enter one of your recovery codes instead.') }}</p>

                            <button type="submit" class="mw-login-form__submit" :disabled="submitting">{{ submitting ? t('login.verifying', 'Verifying…') : t('login.verify', 'Verify') }}</button>

                            <p class="mw-login-form__signup">
                                <template v-if="step === 'email'">
                                    <a href="#" @click.prevent="resendEmailCode">{{ resending ? t('login.resending', 'Sending…') : t('login.resend_code', 'Resend code') }}</a>
                                    &nbsp;·&nbsp;
                                </template>
                                <a href="#" @click.prevent="backToCredentials">{{ t('login.back_to_login', 'Back to sign in') }}</a>
                            </p>
                        </form>

                        <div v-if="step === 'credentials'" class="mw-role-switch mw-login-role-switch" role="tablist">
                            <button type="button" :class="{ 'is-active': accountType === 'user' }" @click="accountType = 'user'">{{ t('login.tab_user') }}</button>
                            <button type="button" :class="{ 'is-active': accountType === 'agent' }" @click="accountType = 'agent'">{{ t('login.tab_agent_agency') }}</button>
                        </div>

                        <form v-if="step === 'credentials'" class="mw-login-form" :action="formAction" method="post" @submit.prevent="handleSubmit">
                            <input type="hidden" name="_token" :value="csrfToken">
                            <input type="hidden" name="login_type" :value="accountType">
                            <h1 class="mw-login-form__title">{{ t('login.title') }}</h1>
                            <p class="mw-login-form__subtitle">{{ t('login.subtitle') }}</p>

                            <p v-if="errorMessage" class="mw-form-feedback mw-form-feedback--error" role="alert">
                                {{ errorMessage }}
                                <template v-if="accountType === 'user'"><br><a href="#" @click.prevent="accountType = 'agent'; errorMessage = null">{{ t('login.agent_tab_hint') }}</a></template>
                            </p>

                            <div class="mw-login-form__field">
                                <label for="login-email">{{ t('login.email_label') }}</label>
                                <input type="email" id="login-email" name="email" v-model="email" :placeholder="t('login.email_placeholder')" autocomplete="email" required>
                            </div>

                            <div class="mw-login-form__field">
                                <label for="login-password">{{ t('login.password_label') }}</label>
                                <PasswordField id="login-password" name="password" v-model="password" :placeholder="t('login.password_placeholder')" autocomplete="current-password" />
                            </div>

                            <a href="/forgot-password" class="mw-login-form__forgot" @click="goToForgotPassword">{{ t('login.forgot_password') }}</a>

                            <button type="submit" class="mw-login-form__submit" :disabled="submitting">{{ submitting ? t('login.submitting') : t('login.submit') }}</button>

                            <p class="mw-login-form__signup">{{ t('login.signup_prompt') }} <a href="/signup" @click="goToSignup">{{ t('login.signup_link') }}</a></p>

                            <template v-if="accountType === 'user'">
                                <div class="mw-login-form__divider"><span>{{ t('login.divider_or') }}</span></div>

                                <a href="/customer/auth/google" class="mw-login-form__google">
                                    <img src="/frontend/assets/images/icons/google.svg" alt="" width="18" height="18">
                                    {{ t('login.google_cta') }}
                                </a>
                            </template>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
