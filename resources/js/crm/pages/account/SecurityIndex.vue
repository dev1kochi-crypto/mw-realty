<template>
    <div v-if="!s" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else class="sec-page">
        <div class="sec-head">
            <span class="sec-head__icon"><i class="fas fa-shield-halved"></i></span>
            <div>
                <h1 class="sec-head__title">Security</h1>
                <p class="sec-head__sub">Manage two-factor authentication and the devices that can sign in to your account.</p>
            </div>
        </div>

        <div class="sec-layout">
            <div class="sec-main">
                <div v-if="s.is_agency" class="sec-card">
                    <div class="sec-card__title"><span>2-Factor Authentication (My Company)</span></div>
                    <div class="sec-row">
                        <div class="sec-method__desc">Enable mandatory 2FA for all users in my company. Agents without it will be asked to set it up before they can use the portal.</div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input sec-switch" type="checkbox" role="switch" :checked="s.company_enforced" :disabled="busy" aria-label="Mandatory 2FA for my company" @change="toggleEnforce($event.target)">
                        </div>
                    </div>
                </div>

                <div class="sec-card">
                    <div class="sec-card__title">
                        <span>2-Factor Authentication</span>
                        <span class="sec-pill" :class="s.enabled ? 'is-on' : 'is-off'">{{ s.enabled ? 'Enabled' : 'Disabled' }}</span>
                    </div>
                    <p class="sec-card__text">2-factor authentication adds an extra layer of security to your account. After your password, you'll enter a code from your phone.</p>
                    <div v-if="s.required && !s.is_agency" class="alert alert-info py-2 small"><i class="fas fa-building me-1"></i>Your agency has made 2FA mandatory, so it can't be turned off.</div>

                    <div class="sec-methods">
                        <div class="sec-methods__label">2-factor authentication methods</div>
                        <div class="sec-method">
                            <span class="sec-method__icon"><i class="fas fa-mobile-screen-button"></i></span>
                            <div class="sec-method__body">
                                <div class="sec-method__name">Authenticator App <span v-if="s.enabled" class="sec-chip is-green">Configured</span></div>
                                <div class="sec-method__desc">Use Authy, Google Authenticator or Microsoft Authenticator to get the OTP code.</div>
                            </div>
                            <button v-if="s.enabled" type="button" class="btn btn-portal-light btn-sm" @click="ask('reconfigure')">Reconfigure</button>
                            <RouterLink v-else :to="{ name: 'two-factor.setup' }" class="btn btn-portal-primary btn-sm">Set up</RouterLink>
                        </div>
                        <div class="sec-method">
                            <span class="sec-method__icon"><i class="fas fa-envelope"></i></span>
                            <div class="sec-method__body">
                                <div class="sec-method__name">New device email check <span class="sec-chip" :class="s.enabled ? 'is-green' : 'is-grey'">{{ s.enabled ? 'On' : 'Off' }}</span></div>
                                <div class="sec-method__desc">{{ s.enabled
                                    ? `Signing in from a new device or browser also sends a code to ${s.email}.`
                                    : `Turns on together with the authenticator app: new devices must then also enter a code sent to ${s.email}.` }}</div>
                            </div>
                        </div>
                    </div>

                    <div v-if="s.enabled" class="sec-row flex-wrap mt-3">
                        <div class="sec-method__desc"><i class="fas fa-key me-1"></i>{{ s.recovery_codes_left }} of 8 recovery codes left
                            · <a href="#" @click.prevent="ask('recovery')">Generate new codes</a></div>
                        <button v-if="!s.required" type="button" class="btn btn-portal-danger btn-sm" @click="ask('disable')">Disable 2FA</button>
                    </div>
                </div>

                <div class="sec-card">
                    <div class="sec-card__title">
                        <span>Recognised devices</span>
                        <button v-if="s.trusted_devices.length > 1" type="button" class="btn btn-portal-light btn-sm" :disabled="busy" @click="forgetDevices">Forget other devices</button>
                    </div>
                    <p class="sec-card__text mb-1">Browsers that passed email verification. Any other device must enter an emailed code to sign in.</p>
                    <div v-for="(device, i) in s.trusted_devices" :key="i" class="sec-device">
                        <span class="sec-device__icon"><i class="fas" :class="/Mobile|Android|iPhone/i.test(device.user_agent || '') ? 'fa-mobile-screen' : 'fa-laptop'"></i></span>
                        <div class="flex-grow-1 text-truncate" :title="device.user_agent">{{ limit(device.user_agent || 'Unknown device', 80) }}</div>
                        <div class="text-muted text-nowrap small">{{ device.ip_address }} · {{ timeAgo(device.last_used_at) }}</div>
                    </div>
                    <div v-if="!s.trusted_devices.length" class="sec-method__desc">No devices yet.</div>
                </div>
            </div>

            <!-- Side panel: how the sign-in protection works, step by step. -->
            <aside class="sec-aside">
                <div class="sec-card">
                    <div class="sec-card__title"><span><i class="fas fa-route me-2 text-muted"></i>How it works</span></div>
                    <ol class="sec-flow">
                        <li :class="s.enabled ? 'is-done' : 'is-current'">
                            <strong>Set up the authenticator app</strong>
                            <span>Click <em>Set up</em>, scan the QR code with Authy (or Google / Microsoft Authenticator) and enter the 6-digit code. Save your recovery codes.</span>
                        </li>
                        <li v-if="s.is_agency" :class="s.company_enforced ? 'is-done' : (s.enabled ? 'is-current' : '')">
                            <strong>Make it mandatory (optional)</strong>
                            <span>Turn on <em>2-Factor Authentication (My Company)</em>. Every agent in your company must then set up 2FA before using the portal.</span>
                        </li>
                        <li :class="{ 'is-done': s.enabled }">
                            <strong>Sign in with password + code</strong>
                            <span>Each login asks for your password, then the current code from the app.</span>
                        </li>
                        <li :class="{ 'is-done': s.enabled }">
                            <strong>New device? Email check</strong>
                            <span>With 2FA on, signing in from a new computer or browser also asks for a code sent to your email. After that, the device is remembered.</span>
                        </li>
                    </ol>
                </div>
                <div class="sec-card sec-tip">
                    <div class="fw-bold mb-1"><i class="fas fa-mobile-screen-button me-2"></i>Changing your phone?</div>
                    <div class="sec-method__desc">Use <em>Reconfigure</em> and scan the new QR code. The old phone keeps working until the new one is verified. Lost your phone? Sign in with a recovery code, or ask MW Realty support to reset 2FA.</div>
                </div>
            </aside>
        </div>

        <!-- Password confirmation for reconfigure / new recovery codes / disable. -->
        <CrmModal :open="!!pwd.action" bare content-class="border-0" @close="pwd.action = null">
            <template #header><span></span></template>
            <form style="border-radius: 18px;" @submit.prevent="confirmPassword">
                <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">{{ actions[pwd.action]?.title }}</h5><button type="button" class="btn-close" aria-label="Close" @click="pwd.action = null"></button></div>
                <div class="modal-body">
                    <p class="sec-method__desc">{{ actions[pwd.action]?.text }}</p>
                    <label class="form-label fw-semibold" for="tfaPassword">Your password</label>
                    <input id="tfaPassword" ref="pwdInput" v-model="pwd.password" type="password" class="form-control" :class="{ 'is-invalid': pwd.error }" autocomplete="current-password" required>
                    <div v-if="pwd.error" class="invalid-feedback">{{ pwd.error }}</div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-portal-light" @click="pwd.action = null">Cancel</button>
                    <button type="submit" class="btn" :class="pwd.action === 'disable' ? 'btn-portal-danger' : 'btn-portal-primary'" :disabled="busy">{{ actions[pwd.action]?.button }}</button>
                </div>
            </form>
        </CrmModal>
    </div>
</template>

<script setup>
/**
 * Security (resources/views/portal/security/index) — authenticator-app 2FA, a company's
 * "mandatory for my company" switch, recognised devices. Set-up itself is TwoFactorSetup.vue.
 */
import { nextTick, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmModal from '../../components/CrmModal.vue';
import { useTwoFactorFlow } from '../../composables/useTwoFactorFlow';
import { useToast } from '../../composables/useToast';
import { timeAgo } from '../../utils/format';

const router = useRouter();
const { success, error: toastError } = useToast();
const { flow, reset } = useTwoFactorFlow();

const s = ref(null);
const loadError = ref('');
const busy = ref(false);
const pwdInput = ref(null);
const pwd = reactive({ action: null, password: '', error: '' });

const actions = {
    reconfigure: { title: 'Move to a new phone', text: "You'll scan a new QR code. Your current app keeps working until the new one is verified.", button: 'Continue' },
    recovery: { title: 'Generate new recovery codes', text: 'Your old recovery codes will stop working.', button: 'Generate' },
    disable: { title: 'Disable 2FA', text: 'Signing in will only need your password (plus the email code on new devices).', button: 'Disable 2FA' },
};

const limit = (text, max) => (text.length > max ? `${text.slice(0, max)}...` : text);

function load() {
    loadError.value = '';
    return http.get('/account/two-factor')
        .then((res) => { s.value = res.data; })
        .catch((e) => { loadError.value = errorMessage(e); });
}

function ask(action) {
    Object.assign(pwd, { action, password: '', error: '' });
    nextTick(() => pwdInput.value?.focus());
}

function confirmPassword() {
    busy.value = true;
    pwd.error = '';
    const password = pwd.password;
    const request = {
        reconfigure: () => http.post('/account/two-factor/setup', { password }).then((res) => {
            reset();
            Object.assign(flow, { pending: res.data, reconfiguring: true });
            router.push({ name: 'two-factor.setup' });
        }),
        recovery: () => http.post('/account/two-factor/recovery-codes', { password }).then((res) => {
            reset();
            flow.recoveryCodes = res.data.recovery_codes;
            success('New recovery codes generated — the old ones no longer work.');
            router.push({ name: 'two-factor.setup' });
        }),
        disable: () => http.post('/account/two-factor/disable', { password }).then((res) => {
            success(res.data.message);
            pwd.action = null;
            return load();
        }),
    }[pwd.action];

    request()
        .catch((e) => {
            if (e.response?.data?.errors?.password) pwd.error = e.response.data.errors.password[0];
            else {
                pwd.action = null;
                toastError(errorMessage(e));
            }
        })
        .finally(() => { busy.value = false; });
}

function toggleEnforce(input) {
    const enforce = input.checked;
    busy.value = true;
    http.post('/account/two-factor/enforce', { enforce })
        .then((res) => {
            success(res.data.message);
            return load();
        })
        .catch((e) => {
            input.checked = !enforce;
            // The company account needs 2FA itself first — set it up, and enforcement switches on right after.
            if (e.response?.data?.setup_first) {
                reset();
                flow.enforcing = true;
                router.push({ name: 'two-factor.setup' });
                return;
            }
            toastError(errorMessage(e));
        })
        .finally(() => { busy.value = false; });
}

function forgetDevices() {
    busy.value = true;
    http.post('/account/two-factor/forget-devices')
        .then((res) => {
            success(res.data.message);
            return load();
        })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { busy.value = false; });
}

// Back here without finishing set-up = the "make it mandatory" request was abandoned.
flow.enforcing = false;
load();
</script>
