<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useOtpInputs } from '../composables/useOtpInputs';
import { animateOtpFocus, animateDigitEntry, morphBoxesToGrid, collapseOtpBoxes, animateOtpSuccess, animateOtpFailure } from '../composables/useOtpAnimations';

const route = useRoute();
const router = useRouter();
const userId = route.query.user_id;

const otpBoxesWrap = ref(null);
const checkmarkContainer = ref(null);
const checkmarkPath = ref(null);
const particlesContainer = ref(null);
const revealEls = ref([]);

function setRevealRef(el) {
    if (el && !revealEls.value.includes(el)) revealEls.value.push(el);
}

const crossmarkContainer = ref(null);
const crossPath1 = ref(null);
const crossPath2 = ref(null);
const errorRevealEls = ref([]);

function setErrorRevealRef(el) {
    if (el && !errorRevealEls.value.includes(el)) errorRevealEls.value.push(el);
}

// The 4 lines connecting the boxes once they reflow into a 2x2 cluster (see morphBoxesToGrid).
const linkEls = ref([]);
function setLinkRef(el, index) {
    if (el) linkEls.value[index] = el;
}

function wait(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

/** Keeps the "verifying" grid on screen for at least `minMs` total, even when the real request
 *  resolves almost instantly — otherwise the reflow animation would barely be visible. */
function holdMinimum(startedAt, minMs) {
    const elapsed = Date.now() - startedAt;
    return elapsed >= minMs ? Promise.resolve() : wait(minMs - elapsed);
}

const { digits, inputEls, setInputRef, onInput, onKeydown, onPaste, reset, code, isComplete } = useOtpInputs(4);

const verifying = ref(false);
const verified = ref(false);
const errorState = ref(false);
const errorMessage = ref(null);
const resendError = ref(null);
const resending = ref(false);
const resendCooldown = ref(30);
let cooldownTimer = null;
let errorAutoTimer = null;

function startCooldown() {
    resendCooldown.value = 30;
    clearInterval(cooldownTimer);
    cooldownTimer = setInterval(() => {
        if (resendCooldown.value <= 1) {
            clearInterval(cooldownTimer);
            resendCooldown.value = 0;
        } else {
            resendCooldown.value -= 1;
        }
    }, 1000);
}

function handleInput(index, event) {
    const entered = onInput(index, event);
    if (entered) {
        nextTick(() => animateDigitEntry(inputEls.value[index]));
    }
    if (isComplete.value) {
        submitCode();
    }
}

function handleFocus(index) {
    animateOtpFocus(inputEls.value[index]);
}

function handlePaste(event) {
    const complete = onPaste(event);
    nextTick(() => inputEls.value.forEach((el) => el && animateDigitEntry(el)));
    if (complete) {
        submitCode();
    }
}

async function submitCode() {
    if (verifying.value || verified.value || errorState.value) return;
    verifying.value = true;
    resendError.value = null;
    // Reflow the row into a 2x2 "verifying" cluster right away — held on screen for a minimum
    // stretch below, since the real request usually resolves too fast for it to even be seen.
    morphBoxesToGrid(otpBoxesWrap.value, inputEls.value, linkEls.value);
    const startedAt = Date.now();

    try {
        const { data } = await window.axios.post('/customer/verify-otp', { user_id: userId, code: code.value });
        await holdMinimum(startedAt, 900);

        // The cluster shrinks down to a point BEFORE the template swaps to the success view —
        // swapping first would unmount the boxes instantly and skip their exit animation (see
        // collapseOtpBoxes's docblock). The checkmark then grows in from that same point, so
        // the two halves read as one continuous "boxes collapse into a square" morph.
        await collapseOtpBoxes(otpBoxesWrap.value);
        verified.value = true;
        await nextTick();
        animateOtpSuccess({
            checkmarkContainer: checkmarkContainer.value,
            checkmarkPath: checkmarkPath.value,
            particlesContainer: particlesContainer.value,
            revealEls: revealEls.value,
        });
        setTimeout(() => {
            window.location.href = data.redirect || '/profile';
        }, 1600);
    } catch (error) {
        const message = error.response?.data?.message || 'Invalid or expired code.';
        await holdMinimum(startedAt, 900);
        await collapseOtpBoxes(otpBoxesWrap.value);
        verifying.value = false;
        errorMessage.value = message;
        errorState.value = true;
        // Each retry re-mounts this template from scratch — clear stale refs from a previous
        // attempt so the reveal animation doesn't also tween already-detached elements.
        errorRevealEls.value = [];
        await nextTick();
        animateOtpFailure({
            crossmarkContainer: crossmarkContainer.value,
            crossPath1: crossPath1.value,
            crossPath2: crossPath2.value,
            revealEls: errorRevealEls.value,
        });
        // No "try again" button — this reads the same way the success card does, then returns
        // to a fresh entry form on its own once there's been time to read the message.
        clearTimeout(errorAutoTimer);
        errorAutoTimer = setTimeout(dismissError, 2400);
    }
}

/** Returns to the entry form once the error card's auto-dismiss timer elapses. */
function dismissError() {
    clearTimeout(errorAutoTimer);
    errorState.value = false;
    errorMessage.value = null;
    reset();
    nextTick(() => inputEls.value[0]?.focus());
}

async function handleResend() {
    if (resendCooldown.value > 0 || resending.value) return;
    // Immediate feedback on click — the actual request can take a few seconds (a real SMTP
    // round-trip, not instant), so without this the button just looks unresponsive until it
    // resolves.
    resending.value = true;
    resendError.value = null;
    try {
        await window.axios.post('/customer/resend-otp', { user_id: userId });
        startCooldown();
    } catch (error) {
        resendError.value = error.response?.data?.message || 'Could not resend the code — please try again.';
    } finally {
        resending.value = false;
    }
}

onMounted(() => {
    if (!userId) {
        router.replace('/signup');
        return;
    }
    startCooldown();
    nextTick(() => inputEls.value[0]?.focus());
});

onBeforeUnmount(() => {
    clearInterval(cooldownTimer);
    clearTimeout(errorAutoTimer);
});

const maskedNote = computed(() => 'We\'ve sent a 4-digit code to your email.');
</script>

<template>
    <main>
        <section class="mw-login-band">
            <div class="container-ctn">
                <div class="mw-login-panel">
                    <div class="mw-login-panel__photo">
                        <img src="/frontend/assets/images/login/panel-photo.png" alt="Cozy Dubai apartment interior">
                    </div>
                    <div class="mw-login-panel__form mw-otp-card">
                        <div
                            class="mw-otp-ambient"
                            :class="{ 'is-verifying': verifying, 'is-success': verified, 'is-error': errorState }"
                            aria-hidden="true"
                        ></div>

                        <template v-if="verified">
                            <div class="mw-otp-result">
                                <div class="mw-otp-checkmark" ref="checkmarkContainer">
                                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                                        <path ref="checkmarkPath" d="M11 21l6 6 12-14" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                    </svg>
                                    <div class="mw-otp-particles" ref="particlesContainer"></div>
                                </div>

                                <h1 class="mw-login-form__title" :ref="setRevealRef">Verified Successfully</h1>
                                <p class="mw-login-form__subtitle" :ref="setRevealRef">Your email has been verified.</p>

                                <span class="mw-otp-badge" :ref="setRevealRef">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2l7 3v6c0 5-3.5 8.5-7 11-3.5-2.5-7-6-7-11V5l7-3Z" stroke="currentColor" stroke-width="1.6"/></svg>
                                    Verified and Secure
                                </span>

                                <button type="button" class="mw-login-form__submit" :ref="setRevealRef" @click="() => (window.location.href = '/profile')">Continue to Account</button>
                            </div>
                        </template>

                        <template v-else-if="errorState">
                            <div class="mw-otp-result">
                                <div class="mw-otp-checkmark mw-otp-checkmark--error" ref="crossmarkContainer">
                                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                                        <path ref="crossPath1" d="M13 13l14 14" stroke="#fff" stroke-width="3.5" stroke-linecap="round"/>
                                        <path ref="crossPath2" d="M27 13 13 27" stroke="#fff" stroke-width="3.5" stroke-linecap="round"/>
                                    </svg>
                                </div>

                                <h1 class="mw-login-form__title" :ref="setErrorRevealRef">Verification Failed</h1>
                                <p class="mw-login-form__subtitle" :ref="setErrorRevealRef">{{ errorMessage }}</p>
                            </div>
                        </template>

                        <template v-else>
                            <span class="mw-otp-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M4 5h16a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H9l-4.5 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <h1 class="mw-login-form__title">Let's verify your email</h1>
                            <p class="mw-login-form__subtitle">{{ maskedNote }}<br>It'll auto-verify once entered.</p>

                            <p v-if="resendError" class="mw-form-feedback mw-form-feedback--error" role="alert">{{ resendError }}</p>

                            <div class="mw-otp-boxes" ref="otpBoxesWrap" @paste="handlePaste">
                                <div class="mw-otp-halo" aria-hidden="true"></div>
                                <div v-for="i in 4" :key="`link-${i}`" class="mw-otp-link" :ref="(el) => setLinkRef(el, i - 1)"></div>
                                <input
                                    v-for="(digit, i) in digits"
                                    :key="i"
                                    :ref="(el) => setInputRef(el, i)"
                                    type="text"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    maxlength="1"
                                    class="mw-otp-box"
                                    :class="{ 'is-filled': digit }"
                                    :value="digit"
                                    :disabled="verifying"
                                    @input="handleInput(i, $event)"
                                    @keydown="onKeydown(i, $event)"
                                    @focus="handleFocus(i)"
                                >
                            </div>

                            <p v-if="verifying" class="mw-otp-verifying">
                                <span class="mw-otp-spinner"></span>
                                Verifying your code...
                            </p>

                            <p class="mw-otp-resend">
                                Didn't receive the code?
                                <button type="button" :disabled="resendCooldown > 0 || resending" @click="handleResend">
                                    {{ resending ? 'Sending…' : (resendCooldown > 0 ? `Resend in ${resendCooldown}s` : 'Resend') }}
                                </button>
                            </p>
                        </template>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
