<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { playAuthEntrance } from '../composables/useAuthEntrance';
import { useAuthPageTransition } from '../composables/useAuthPageTransition';
import { usePasswordValidation } from '../composables/usePasswordValidation';
import { animatePasswordValidation, animatePasswordStrength } from '../composables/usePasswordAnimations';
import { useStaticText } from '../composables/useStaticText';
import AuthGlassAmbient from '../components/auth/AuthGlassAmbient.vue';
import PasswordField from '../components/auth/PasswordField.vue';

const { t } = useStaticText();
const route = useRoute();
const router = useRouter();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const panelFormEl = ref(null);
const { navigateWithEffect } = useAuthPageTransition(panelFormEl);
const goToLogin = navigateWithEffect('/login');

// 'email' → account must exist and be verified, the server hands back a reset token;
// 'password' → new password + confirm, posted with that token, then straight to /login
// (which shows the success message on the same tab).
const step = ref('email');

// Customers (users table) and agents/agencies (portal_users) are separate accounts with separate
// endpoints — same two tabs as Login.vue, which passes its current tab as ?type=.
const accountType = ref(route.query.type === 'agent' ? 'agent' : 'user');
const apiPrefix = computed(() => (accountType.value === 'user' ? '/customer' : '/portal'));

function selectAccountType(type) {
    accountType.value = type;
    errorMessage.value = null;
}
const email = ref('');
const resetToken = ref('');
const submitting = ref(false);
const errorMessage = ref(null);

async function handleEmailSubmit() {
    if (submitting.value) return;
    submitting.value = true;
    errorMessage.value = null;

    try {
        const { data } = await window.axios.post(`${apiPrefix.value}/forgot-password/check-email`, { email: email.value });
        resetToken.value = data.token;
        step.value = 'password';
    } catch (error) {
        errorMessage.value = error.response?.data?.message || t('forgot_password.generic_error');
    } finally {
        submitting.value = false;
    }
}

// ── Step 2: new password ────────────────────────────────────────────────────
const password = ref('');
const confirmPassword = ref('');
const { requirements, strength, strengthPercent, passwordsMatch } = usePasswordValidation(password, confirmPassword);

const STRENGTH_COLORS = { weak: '#c92844', medium: '#e08a1e', strong: '#1f9d55' };
const strengthColor = computed(() => STRENGTH_COLORS[strength.value]);
const strengthLabel = computed(() => ({ weak: t('reset_password.strength_weak'), medium: t('reset_password.strength_medium'), strong: t('reset_password.strength_strong') }[strength.value]));

// Same rule the server enforces (Password::min(8)) — the strength bar is guidance only.
const canSubmitPassword = computed(() => requirements.value.length && passwordsMatch.value === true);

const lengthRuleEl = ref(null);
watch(() => requirements.value.length, () => animatePasswordValidation(lengthRuleEl.value));

const strengthFillEl = ref(null);
watch(strengthPercent, (percent) => animatePasswordStrength(strengthFillEl.value, percent, strengthColor.value));

async function handlePasswordSubmit() {
    if (submitting.value || !canSubmitPassword.value) return;
    submitting.value = true;
    errorMessage.value = null;

    try {
        await window.axios.post(`${apiPrefix.value}/reset-password`, {
            token: resetToken.value,
            email: email.value,
            password: password.value,
            password_confirmation: confirmPassword.value,
        });
        router.replace({ path: '/login', query: { reset: 1, type: accountType.value, email: email.value } });
    } catch (error) {
        errorMessage.value = error.response?.data?.message || t('reset_password.generic_error');
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

                        <div v-if="step === 'email'" class="mw-role-switch mw-login-role-switch" role="tablist">
                            <button type="button" :class="{ 'is-active': accountType === 'user' }" @click="selectAccountType('user')">{{ t('login.tab_user') }}</button>
                            <button type="button" :class="{ 'is-active': accountType === 'agent' }" @click="selectAccountType('agent')">{{ t('login.tab_agent_agency') }}</button>
                        </div>

                        <form v-if="step === 'email'" class="mw-login-form" method="post" @submit.prevent="handleEmailSubmit">
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

                        <form v-else-if="step === 'password'" class="mw-login-form" method="post" @submit.prevent="handlePasswordSubmit">
                            <span class="mw-otp-badge">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2l7 3v6c0 5-3.5 8.5-7 11-3.5-2.5-7-6-7-11V5l7-3Z" stroke="currentColor" stroke-width="1.6"/></svg>
                                {{ t('forgot_password.verified_badge') }}
                            </span>
                            <h1 class="mw-login-form__title">{{ t('reset_password.title') }}</h1>
                            <p class="mw-login-form__subtitle">{{ t('reset_password.subtitle').replace('{email}', email) }}</p>

                            <p v-if="errorMessage" class="mw-form-feedback mw-form-feedback--error" role="alert">{{ errorMessage }}</p>

                            <div class="mw-login-form__field">
                                <label for="forgot-new-password">{{ t('reset_password.new_password_label') }}</label>
                                <PasswordField id="forgot-new-password" name="password" v-model="password" :placeholder="t('reset_password.password_create_placeholder')" />
                            </div>

                            <div class="mw-login-form__field">
                                <label for="forgot-new-password-confirm">{{ t('reset_password.confirm_password_label') }}</label>
                                <PasswordField id="forgot-new-password-confirm" name="password_confirmation" v-model="confirmPassword" :placeholder="t('reset_password.confirm_password_placeholder')" />

                                <div class="mw-password-rules">
                                    <div class="mw-password-strength" v-if="password">
                                        <div class="mw-password-strength__head">
                                            <span>{{ t('reset_password.password_strength_label') }}</span>
                                            <span :style="{ color: strengthColor }">{{ strengthLabel }}</span>
                                        </div>
                                        <div class="mw-password-strength__track">
                                            <div class="mw-password-strength__fill" ref="strengthFillEl"></div>
                                        </div>
                                    </div>

                                    <p v-if="password" ref="lengthRuleEl" class="mw-password-rules__item" :class="requirements.length ? 'is-valid' : 'is-invalid'">
                                        <svg v-if="requirements.length" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                                        {{ t('reset_password.rule_length') }}
                                    </p>

                                    <p v-if="passwordsMatch !== null" class="mw-password-rules__item" :class="passwordsMatch ? 'is-valid' : 'is-invalid'">
                                        <svg v-if="passwordsMatch" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
                                        {{ passwordsMatch ? t('reset_password.passwords_match') : t('reset_password.passwords_no_match') }}
                                    </p>
                                </div>
                            </div>

                            <button type="submit" class="mw-login-form__submit" :disabled="submitting || !canSubmitPassword">{{ submitting ? t('reset_password.submitting') : t('reset_password.submit') }}</button>
                        </form>

                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
