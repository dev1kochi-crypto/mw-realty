<script setup>
import { onMounted, ref } from 'vue';
import { playAuthEntrance } from '../composables/useAuthEntrance';
import { useAuthPageTransition } from '../composables/useAuthPageTransition';
import AuthGlassAmbient from '../components/auth/AuthGlassAmbient.vue';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const panelFormEl = ref(null);
const { navigateWithEffect } = useAuthPageTransition(panelFormEl);
const goToLogin = navigateWithEffect('/login');

const email = ref('');
const submitting = ref(false);
const errorMessage = ref(null);
const sent = ref(false);

async function handleSubmit(e) {
    if (submitting.value) return;
    submitting.value = true;
    errorMessage.value = null;

    try {
        await window.axios.post('/customer/forgot-password', new FormData(e.target));
        sent.value = true;
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Something went wrong — please try again.';
    } finally {
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

                        <template v-if="!sent">
                            <form class="mw-login-form" method="post" @submit.prevent="handleSubmit">
                                <input type="hidden" name="_token" :value="csrfToken">
                                <h1 class="mw-login-form__title">Forgot your password?</h1>
                                <p class="mw-login-form__subtitle">Enter your email and we'll send you a link to reset it.</p>

                                <p v-if="errorMessage" class="mw-form-feedback mw-form-feedback--error" role="alert">{{ errorMessage }}</p>

                                <div class="mw-login-form__field">
                                    <label for="forgot-email">Email</label>
                                    <input type="email" id="forgot-email" name="email" v-model="email" placeholder="Enter your email" autocomplete="email" required>
                                </div>

                                <button type="submit" class="mw-login-form__submit" :disabled="submitting">{{ submitting ? 'Sending…' : 'Send Reset Link' }}</button>

                                <p class="mw-login-form__signup"><a href="/login" @click="goToLogin">Back to Sign In</a></p>
                            </form>
                        </template>

                        <template v-else>
                            <h1 class="mw-login-form__title">Check your email</h1>
                            <p class="mw-login-form__subtitle">If an account exists for {{ email }}, we've sent a link to reset your password. The link expires in 60 minutes.</p>
                            <p class="mw-login-form__signup"><a href="/login" @click="goToLogin">Back to Sign In</a></p>
                        </template>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
