<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { playAuthEntrance } from '../composables/useAuthEntrance';
import { usePasswordValidation } from '../composables/usePasswordValidation';
import { animatePasswordValidation, animatePasswordStrength } from '../composables/usePasswordAnimations';
import AuthGlassAmbient from '../components/auth/AuthGlassAmbient.vue';
import PasswordField from '../components/auth/PasswordField.vue';

const route = useRoute();
const router = useRouter();
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const panelFormEl = ref(null);

const token = String(route.query.token || '');
const email = String(route.query.email || '');
// No token/email in the URL means this wasn't reached via the emailed reset link — nothing to do here.
const linkInvalid = !token || !email;

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

const password = ref('');
const confirmPassword = ref('');
const { requirements, strength, strengthPercent, isValid: passwordValid, passwordsMatch } =
    usePasswordValidation(password, confirmPassword);

const STRENGTH_COLORS = { weak: '#c92844', medium: '#e08a1e', strong: '#1f9d55' };
const STRENGTH_LABELS = { weak: 'Weak', medium: 'Medium', strong: 'Strong' };
const strengthColor = computed(() => STRENGTH_COLORS[strength.value]);
const strengthLabel = computed(() => STRENGTH_LABELS[strength.value]);

const canSubmit = computed(() => passwordValid.value && passwordsMatch.value === true);

const errorMessage = ref(null);
const submitting = ref(false);
const done = ref(false);

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

async function handleSubmit(e) {
    if (submitting.value || !canSubmit.value) return;
    submitting.value = true;
    errorMessage.value = null;

    try {
        await window.axios.post('/customer/reset-password', new FormData(e.target));
        done.value = true;
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Something went wrong — please try again.';
    } finally {
        submitting.value = false;
    }
}

function goToLogin() {
    router.push('/login');
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

                        <template v-if="linkInvalid">
                            <h1 class="mw-login-form__title">Invalid reset link</h1>
                            <p class="mw-login-form__subtitle">This password reset link is invalid or incomplete. Please request a new one.</p>
                            <p class="mw-login-form__signup"><a href="/forgot-password" @click.prevent="router.push('/forgot-password')">Request New Link</a></p>
                        </template>

                        <template v-else-if="done">
                            <h1 class="mw-login-form__title">Password reset</h1>
                            <p class="mw-login-form__subtitle">Your password has been reset — you can now sign in with your new password.</p>
                            <button type="button" class="mw-login-form__submit" @click="goToLogin">Continue to Sign In</button>
                        </template>

                        <form v-else class="mw-login-form" method="post" @submit.prevent="handleSubmit">
                            <input type="hidden" name="_token" :value="csrfToken">
                            <input type="hidden" name="token" :value="token">
                            <input type="hidden" name="email" :value="email">
                            <h1 class="mw-login-form__title">Reset your password</h1>
                            <p class="mw-login-form__subtitle">Choose a new password for {{ email }}.</p>

                            <p v-if="errorMessage" class="mw-form-feedback mw-form-feedback--error" role="alert">{{ errorMessage }}</p>

                            <div class="mw-login-form__field">
                                <label for="reset-password">
                                    New Password
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
                                <PasswordField id="reset-password" name="password" v-model="password" placeholder="Create a new password" />
                            </div>

                            <div class="mw-login-form__field">
                                <label for="reset-password-confirm">Confirm New Password</label>
                                <PasswordField id="reset-password-confirm" name="password_confirmation" v-model="confirmPassword" placeholder="Re-enter your new password" />

                                <div class="mw-password-rules">
                                    <div class="mw-password-strength" v-if="password">
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

                            <button type="submit" class="mw-login-form__submit" :disabled="submitting || !canSubmit">{{ submitting ? 'Resetting…' : 'Reset Password' }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
