<script setup>
import { computed, onMounted, ref } from 'vue';
import { useFormErrors } from '../composables/useFormErrors';
import { playAuthEntrance } from '../composables/useAuthEntrance';
import { useAuthPageTransition } from '../composables/useAuthPageTransition';
import AuthGlassAmbient from '../components/auth/AuthGlassAmbient.vue';
import PasswordField from '../components/auth/PasswordField.vue';

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
        errorMessage.value = error.response?.data?.message || 'Something went wrong — please try again.';
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
                        <img src="/frontend/assets/images/login/panel-photo.png" alt="Cozy Dubai apartment interior">
                    </div>
                    <div class="mw-login-panel__form" ref="panelFormEl">
                        <AuthGlassAmbient />
                        <div class="mw-pill-tabs" data-tab-group>
                            <button type="button" :class="{ 'is-active': accountType === 'user' }" @click="accountType = 'user'">User</button>
                            <button type="button" :class="{ 'is-active': accountType === 'agent' }" @click="accountType = 'agent'">Agent / Agency</button>
                        </div>

                        <form class="mw-login-form" :action="formAction" method="post" @submit.prevent="handleSubmit">
                            <input type="hidden" name="_token" :value="csrfToken">
                            <input type="hidden" name="login_type" :value="accountType">
                            <h1 class="mw-login-form__title">Welcome back</h1>
                            <p class="mw-login-form__subtitle">Please enter your details to sign in.</p>

                            <p v-if="errorMessage" class="mw-form-feedback mw-form-feedback--error" role="alert">{{ errorMessage }}</p>

                            <div class="mw-login-form__field">
                                <label for="login-email">Email</label>
                                <input type="email" id="login-email" name="email" v-model="email" placeholder="Enter your email" autocomplete="email" required>
                            </div>

                            <div class="mw-login-form__field">
                                <label for="login-password">Password</label>
                                <PasswordField id="login-password" name="password" v-model="password" placeholder="Enter your password" autocomplete="current-password" />
                            </div>

                            <a href="/forgot-password" class="mw-login-form__forgot" @click="goToForgotPassword">Forgot your password?</a>

                            <button type="submit" class="mw-login-form__submit" :disabled="submitting">{{ submitting ? 'Signing In…' : 'Sign In' }}</button>

                            <p class="mw-login-form__signup">Don't have an account? <a href="/signup" @click="goToSignup">Sign Up</a></p>

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
