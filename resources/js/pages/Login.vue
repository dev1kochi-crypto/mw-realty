<script setup>
import { computed, onMounted, ref } from 'vue';
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

    try {
        const { data } = await window.axios.post(formAction.value, new FormData(e.target));
        window.location.href = data.redirect || '/profile';
    } catch (error) {
        errorMessage.value = error.response?.data?.message || t('login.generic_error');
        submitting.value = false;
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
                        <div class="mw-role-switch mw-login-role-switch" role="tablist">
                            <button type="button" :class="{ 'is-active': accountType === 'user' }" @click="accountType = 'user'">{{ t('login.tab_user') }}</button>
                            <button type="button" :class="{ 'is-active': accountType === 'agent' }" @click="accountType = 'agent'">{{ t('login.tab_agent_agency') }}</button>
                        </div>

                        <form class="mw-login-form" :action="formAction" method="post" @submit.prevent="handleSubmit">
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

                            <!-- Google sign-in hidden for now — keep this markup, don't remove it.
                            <template v-if="accountType === 'user'">
                                <div class="mw-login-form__divider"><span>{{ t('login.divider_or') }}</span></div>

                                <a href="/customer/auth/google" class="mw-login-form__google">
                                    <img src="/frontend/assets/images/icons/google.svg" alt="" width="18" height="18">
                                    {{ t('login.google_cta') }}
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
