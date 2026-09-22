<script setup>
import { computed, ref } from 'vue';
import { useFormErrors } from '../composables/useFormErrors';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const { messages: errorMessages, old: oldInput } = useFormErrors();
const accountType = ref(oldInput.login_type || 'user');

const formAction = computed(() => (accountType.value === 'user' ? '/customer/login' : '/portal/login'));
const subtitle = computed(() => (accountType.value === 'user'
    ? 'Sign in to save properties, track enquiries and manage your wishlist.'
    : 'Sign in with your agent or agency account to manage your listings and leads.'));
</script>

<template>
    <main>
        <section class="mw-login-band">
            <div class="container-ctn">
                <div class="mw-login-panel">
                    <div class="mw-login-panel__photo">
                        <img src="/frontend/assets/images/login/panel-photo.png" alt="Cozy Dubai apartment interior">
                    </div>
                    <div class="mw-login-panel__form">
                        <div class="mw-pill-tabs" data-tab-group>
                            <button type="button" :class="{ 'is-active': accountType === 'user' }" @click="accountType = 'user'">User</button>
                            <button type="button" :class="{ 'is-active': accountType === 'agent' }" @click="accountType = 'agent'">Agent / Agency</button>
                        </div>

                        <form class="mw-login-form" :action="formAction" method="post">
                            <input type="hidden" name="_token" :value="csrfToken">
                            <input type="hidden" name="login_type" :value="accountType">
                            <h1 class="mw-login-form__title">Welcome back</h1>
                            <p class="mw-login-form__subtitle">{{ subtitle }}</p>

                            <p v-if="errorMessages.length" class="mw-form-feedback mw-form-feedback--error">{{ errorMessages[0] }}</p>

                            <div class="mw-login-form__field">
                                <label for="login-email">Email</label>
                                <input type="email" id="login-email" name="email" :value="oldInput.email || ''" placeholder="Enter your email" autocomplete="email" required>
                            </div>

                            <div class="mw-login-form__field">
                                <label for="login-password">Password</label>
                                <input type="password" id="login-password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                            </div>

                            <a href="#" class="mw-login-form__forgot">Forgot your password?</a>

                            <button type="submit" class="mw-login-form__submit">Sign In</button>

                            <p class="mw-login-form__signup">Don't have an account? <router-link to="/signup">Sign Up</router-link></p>

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
