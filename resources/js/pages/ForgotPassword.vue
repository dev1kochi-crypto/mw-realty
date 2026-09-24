<script setup>
import { computed, onMounted, ref } from 'vue';
import { playAuthEntrance } from '../composables/useAuthEntrance';
import { useAuthPageTransition } from '../composables/useAuthPageTransition';
import { useStaticText } from '../composables/useStaticText';
import AuthGlassAmbient from '../components/auth/AuthGlassAmbient.vue';

const { t } = useStaticText();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const panelFormEl = ref(null);
const { navigateWithEffect } = useAuthPageTransition(panelFormEl);
const goToLogin = navigateWithEffect('/login');

const email = ref('');
const submitting = ref(false);
const errorMessage = ref(null);
const sent = ref(false);

const sentSubtitle = computed(() => t('forgot_password.sent_subtitle').replace('{email}', email.value));

async function handleSubmit(e) {
    if (submitting.value) return;
    submitting.value = true;
    errorMessage.value = null;

    try {
        await window.axios.post('/customer/forgot-password', new FormData(e.target));
        sent.value = true;
    } catch (error) {
        errorMessage.value = error.response?.data?.message || t('forgot_password.generic_error');
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
                        <img src="/frontend/assets/images/login/panel-photo.png" :alt="t('forgot_password.photo_alt')">
                    </div>
                    <div class="mw-login-panel__form" ref="panelFormEl">
                        <AuthGlassAmbient />

                        <template v-if="!sent">
                            <form class="mw-login-form" method="post" @submit.prevent="handleSubmit">
                                <input type="hidden" name="_token" :value="csrfToken">
                                <h1 class="mw-login-form__title">{{ t('forgot_password.title') }}</h1>
                                <p class="mw-login-form__subtitle">{{ t('forgot_password.subtitle') }}</p>

                                <p v-if="errorMessage" class="mw-form-feedback mw-form-feedback--error" role="alert">{{ errorMessage }}</p>

                                <div class="mw-login-form__field">
                                    <label for="forgot-email">{{ t('forgot_password.email_label') }}</label>
                                    <input type="email" id="forgot-email" name="email" v-model="email" :placeholder="t('forgot_password.email_placeholder')" autocomplete="email" required>
                                </div>

                                <button type="submit" class="mw-login-form__submit" :disabled="submitting">{{ submitting ? t('forgot_password.submitting') : t('forgot_password.submit') }}</button>

                                <p class="mw-login-form__signup"><a href="/login" @click="goToLogin">{{ t('forgot_password.back_to_sign_in') }}</a></p>
                            </form>
                        </template>

                        <template v-else>
                            <h1 class="mw-login-form__title">{{ t('forgot_password.sent_title') }}</h1>
                            <p class="mw-login-form__subtitle">{{ sentSubtitle }}</p>
                            <p class="mw-login-form__signup"><a href="/login" @click="goToLogin">{{ t('forgot_password.back_to_sign_in') }}</a></p>
                        </template>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
