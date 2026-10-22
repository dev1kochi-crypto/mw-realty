<template>
    <div v-if="!status" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="start">Try again</button></div>
    </div>
    <div v-else class="sec-page">
        <div class="sec-head">
            <span class="sec-head__icon"><i class="fas fa-shield-halved"></i></span>
            <div>
                <div v-if="onboarding" class="sec-head__eyebrow">Last step · Secure your account</div>
                <h1 class="sec-head__title">{{ reconfiguring ? 'Move 2FA to a new phone' : 'Two-Factor Authentication' }}</h1>
                <p class="sec-head__sub">{{ reconfiguring
                    ? 'Scan the new QR code with your new phone. Your current app keeps working until you verify the new one.'
                    : 'Protect your account with a 6-digit code from an authenticator app every time you sign in.' }}</p>
            </div>
        </div>

        <!-- Just enabled / regenerated: the recovery codes are shown this one time only. -->
        <div v-if="recoveryCodes" class="sec-card" style="max-width: 620px; padding: 2rem;">
            <span class="tfa-success-icon"><i class="fas fa-check"></i></span>
            <h2 class="h5 fw-bold mb-2">Save your recovery codes</h2>
            <p class="sec-card__text mb-0">If you lose your phone, sign in with one of these codes instead of the app code. Each code works <strong>once</strong>. Keep them somewhere safe; they won't be shown again.</p>
            <div class="tfa-codes">
                <span v-for="code in recoveryCodes" :key="code">{{ code }}</span>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-portal-light btn-sm" @click="copy('codes', recoveryCodes.join('\n'))"><i class="fas me-1" :class="copied === 'codes' ? 'fa-check' : 'fa-copy'"></i>{{ copied === 'codes' ? 'Copied' : 'Copy' }}</button>
                <button type="button" class="btn btn-portal-light btn-sm" @click="downloadCodes"><i class="fas fa-download me-1"></i>Download</button>
                <RouterLink :to="doneRoute" class="btn btn-portal-primary btn-sm ms-auto">I've saved them, continue <i class="fas fa-arrow-right ms-1"></i></RouterLink>
            </div>
        </div>

        <template v-else-if="pending">
            <div v-if="status.required && !reconfiguring" class="alert alert-warning d-flex gap-2 align-items-center"><i class="fas fa-building-shield"></i>Your company requires two-factor authentication. Set it up to continue using the portal.</div>
            <div v-else-if="flow.enforcing" class="alert alert-info d-flex gap-2 align-items-center"><i class="fas fa-building-shield"></i>Set up 2FA on your own account first. Mandatory 2FA for your company turns on automatically when you finish.</div>

            <div class="sec-card p-0">
                <div class="tfa-grid">
                    <div class="tfa-steps">
                        <div class="tfa-step">
                            <span class="tfa-step__num">1</span>
                            <div>
                                <div class="tfa-step__title">Install an authenticator app</div>
                                <div class="tfa-step__text">Any of these free apps works on iPhone and Android.</div>
                                <div class="tfa-apps"><span>Authy</span><span>Google Authenticator</span><span>Microsoft Authenticator</span></div>
                            </div>
                        </div>
                        <div class="tfa-step">
                            <span class="tfa-step__num">2</span>
                            <div>
                                <div class="tfa-step__title">Scan the QR code</div>
                                <div class="tfa-step__text">In the app, tap <strong>+</strong> / <strong>Add account</strong> and scan the code on this page. "MW Realty" will appear in your app.</div>
                            </div>
                        </div>
                        <div class="tfa-step">
                            <span class="tfa-step__num">3</span>
                            <div class="flex-grow-1">
                                <div class="tfa-step__title">Enter the 6-digit code</div>
                                <div class="tfa-step__text">Type the code the app shows for MW Realty.</div>
                                <form @submit.prevent="confirm">
                                    <div class="tfa-otp" :class="{ 'is-invalid': codeError }">
                                        <template v-for="(digit, i) in digits" :key="i">
                                            <span v-if="i === 3" class="tfa-otp__gap"></span>
                                            <input :ref="(el) => { boxes[i] = el; }" type="text" inputmode="numeric" maxlength="1" :autocomplete="i === 0 ? 'one-time-code' : 'off'" :aria-label="`Digit ${i + 1}`" :value="digit"
                                                @input="onInput(i, $event)" @keydown="onKeydown(i, $event)" @paste.prevent="onPaste" @focus="$event.target.select()">
                                        </template>
                                    </div>
                                    <div v-if="codeError" class="tfa-error"><i class="fas fa-circle-exclamation me-1"></i>{{ codeError }}</div>
                                    <button type="submit" class="btn btn-portal-primary px-4" :disabled="busy"><i class="fas fa-lock me-2"></i>{{ reconfiguring ? 'Verify new phone' : 'Verify & Enable' }}</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="tfa-qr-panel">
                        <div class="tfa-qr" v-html="pending.qr_svg"></div>
                        <div class="tfa-qr-label">Can't scan? Enter this key in the app instead:</div>
                        <div class="tfa-key">
                            <span>{{ pending.secret.match(/.{1,4}/g).join(' ') }}</span>
                            <button type="button" title="Copy key" aria-label="Copy key" @click="copy('key', pending.secret)"><i class="fas" :class="copied === 'key' ? 'fa-check' : 'fa-copy'"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="reconfiguring || !status.required" class="text-center">
                <!-- New accounts stay on this step until they set it up or explicitly skip. -->
                <button v-if="onboarding && !reconfiguring" type="button" class="btn btn-link p-0 tfa-footer-link" :disabled="busy" @click="skip">Skip for now, I'll do this later from Security</button>
                <RouterLink v-else :to="{ name: 'security' }" class="tfa-footer-link">Cancel</RouterLink>
            </div>
        </template>
    </div>
</template>

<script setup>
/**
 * Two-factor set-up (resources/views/portal/two-factor/setup) — also the step after sign-up
 * (?onboarding=1), and where portal.2fa sends an account that must set it up. Shows the recovery
 * codes once they're issued (here or from Security).
 */
import { computed, nextTick, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import { useTwoFactorFlow } from '../../composables/useTwoFactorFlow';
import { useToast } from '../../composables/useToast';

const route = useRoute();
const router = useRouter();
const { success, error: toastError } = useToast();
const { flow } = useTwoFactorFlow();

const status = ref(null);
const loadError = ref('');
const pending = ref(null);
const reconfiguring = ref(false);
const recoveryCodes = ref(null);
const digits = ref(['', '', '', '', '', '']);
const boxes = [];
const codeError = ref('');
const busy = ref(false);
const copied = ref(null);

const onboarding = computed(() => route.query.onboarding === '1' || !!status.value?.can_skip);
const doneRoute = computed(() => ({ name: onboarding.value ? 'dashboard' : 'security' }));

function start() {
    loadError.value = '';
    http.get('/account/two-factor')
        .then(async (res) => {
            status.value = res.data;
            // Handed over from Security: fresh recovery codes, or a set-up started with the password.
            if (flow.recoveryCodes) {
                recoveryCodes.value = flow.recoveryCodes;
                flow.recoveryCodes = null;
                return;
            }
            if (flow.reconfiguring && flow.pending) {
                pending.value = flow.pending;
                reconfiguring.value = true;
                flow.pending = null;
                flow.reconfiguring = false;
                return;
            }
            // Already on — nothing to set up (a new phone goes through Security's Reconfigure).
            if (res.data.enabled) {
                router.replace({ name: 'security' });
                return;
            }
            const setup = await http.post('/account/two-factor/setup');
            pending.value = setup.data;
            nextTick(() => boxes[0]?.focus());
        })
        .catch((e) => { loadError.value = errorMessage(e); });
}

// 6 single-digit boxes: auto-advance, backspace, paste, auto-submit.
function fill(start, value) {
    value.split('').forEach((d, i) => { if (start + i < 6) digits.value[start + i] = d; });
    boxes[Math.min(start + value.length, 5)]?.focus();
    if (digits.value.join('').length === 6) confirm();
}

function onInput(i, event) {
    const value = event.target.value.replace(/\D/g, '');
    digits.value[i] = '';
    event.target.value = '';
    if (value) fill(i, value);
}

function onKeydown(i, event) {
    if (event.key === 'Backspace' && !digits.value[i] && i > 0) {
        digits.value[i - 1] = '';
        boxes[i - 1].focus();
        event.preventDefault();
    }
    if (event.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
    if (event.key === 'ArrowRight' && i < 5) boxes[i + 1].focus();
}

function onPaste(event) {
    const value = (event.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
    if (value) fill(0, value);
}

function confirm() {
    const code = digits.value.join('');
    if (code.length !== 6) {
        boxes[digits.value.findIndex((d) => !d)]?.focus();
        return;
    }
    if (busy.value) return;
    busy.value = true;
    codeError.value = '';
    http.post('/account/two-factor/confirm', { code, enforce: flow.enforcing })
        .then((res) => {
            success(reconfiguring.value ? 'Your new authenticator app is set up. The old one no longer works.' : res.data.message);
            flow.enforcing = false;
            recoveryCodes.value = res.data.recovery_codes;
            pending.value = null;
        })
        .catch((e) => {
            codeError.value = e.response?.data?.errors?.code?.[0] || errorMessage(e);
            digits.value = ['', '', '', '', '', ''];
            nextTick(() => boxes[0]?.focus());
        })
        .finally(() => { busy.value = false; });
}

function skip() {
    busy.value = true;
    http.post('/account/two-factor/skip')
        .then(() => router.push({ name: 'dashboard' }))
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { busy.value = false; });
}

function copy(what, text) {
    navigator.clipboard.writeText(text).then(() => {
        copied.value = what;
        setTimeout(() => { if (copied.value === what) copied.value = null; }, 1500);
    });
}

function downloadCodes() {
    const blob = new Blob([`MW Realty recovery codes (${status.value.email})\n\n${recoveryCodes.value.join('\n')}\n`], { type: 'text/plain' });
    const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: 'mw-realty-recovery-codes.txt' });
    a.click();
    URL.revokeObjectURL(a.href);
}

start();
</script>
