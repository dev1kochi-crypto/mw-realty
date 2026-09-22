<script setup>
import { computed, ref } from 'vue';
import { useFormErrors } from '../composables/useFormErrors';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const { messages: errorMessages, old: oldInput } = useFormErrors();
const accountType = ref(oldInput.signup_type || 'user');

const formAction = computed(() => (accountType.value === 'user' ? '/customer/register' : '/portal/register'));
const portalType = computed(() => (accountType.value === 'agency' ? 'company' : 'agent'));

const typeConfig = {
    user: {
        label: 'User',
        badge: '/frontend/assets/images/icons/user.svg',
        subtitle: 'Sign up as a user to save favorites and enquire on properties.',
        loginText: 'Already have an account?',
    },
    agent: {
        label: 'Agent',
        badge: '/frontend/assets/images/icons/verified.svg',
        subtitle: 'Sign up as an agent to list properties and manage your clients.',
        loginText: 'Already have an agent account?',
    },
    agency: {
        label: 'Agency',
        badge: '/frontend/assets/images/icons/building.svg',
        subtitle: 'Register your agency to list properties and manage your agents.',
        loginText: 'Already have an agency account?',
    },
};

const config = computed(() => typeConfig[accountType.value]);
</script>

<template>
    <main>
        <section class="mw-login-band">
            <div class="container-ctn">
                <div class="mw-signup-card">
                    <div class="mw-signup-card__head">
                        <span class="mw-signup-card__badge">
                            <img :src="config.badge" alt="">
                        </span>
                        <h1 class="mw-login-form__title">Create your account</h1>
                        <p class="mw-login-form__subtitle">{{ config.subtitle }}</p>
                    </div>

                    <p v-if="errorMessages.length" class="mw-form-feedback mw-form-feedback--error" style="margin: 0 0 16px;">{{ errorMessages[0] }}</p>

                    <form class="mw-login-form mw-signup-form" :action="formAction" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <input type="hidden" name="signup_type" :value="accountType">
                        <input v-if="accountType !== 'user'" type="hidden" name="type" :value="portalType">
                        <div class="mw-login-form__grid">
                            <div class="mw-login-form__field mw-login-form__field--full">
                                <label for="signup-account-type">Account Type</label>
                                <div class="mw-dropdown" data-dropdown>
                                    <input type="text" id="signup-account-type" :value="config.label" readonly data-dropdown-trigger>
                                    <ul class="mw-dropdown__menu" data-dropdown-menu>
                                        <li><button type="button" :class="{ 'is-selected': accountType === 'user' }" data-dropdown-option @click="accountType = 'user'">User</button></li>
                                        <li><button type="button" :class="{ 'is-selected': accountType === 'agent' }" data-dropdown-option @click="accountType = 'agent'">Agent</button></li>
                                        <li><button type="button" :class="{ 'is-selected': accountType === 'agency' }" data-dropdown-option @click="accountType = 'agency'">Agency</button></li>
                                    </ul>
                                </div>
                            </div>

                            <template v-if="accountType === 'user'">
                                <div class="mw-login-form__field">
                                    <label for="signup-user-name">Full Name</label>
                                    <input type="text" id="signup-user-name" name="name" :value="oldInput.name || ''" placeholder="Enter your full name" autocomplete="name" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-user-email">Email Address</label>
                                    <input type="email" id="signup-user-email" name="email" :value="oldInput.email || ''" placeholder="Enter your email" autocomplete="email" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-user-phone">Phone Number</label>
                                    <input type="tel" id="signup-user-phone" name="phone" :value="oldInput.phone || ''" placeholder="Enter phone number" autocomplete="tel">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-user-password">Password</label>
                                    <input type="password" id="signup-user-password" name="password" placeholder="Create a password" autocomplete="new-password" required>
                                </div>

                                <div class="mw-login-form__field mw-login-form__field--full">
                                    <label for="signup-user-confirm">Confirm Password</label>
                                    <input type="password" id="signup-user-confirm" name="password_confirmation" placeholder="Re-enter your password" autocomplete="new-password" required>
                                </div>
                            </template>

                            <template v-else-if="accountType === 'agent'">
                                <div class="mw-login-form__field">
                                    <label for="signup-agent-name">Full Name</label>
                                    <input type="text" id="signup-agent-name" name="name" :value="oldInput.name || ''" placeholder="Enter your full name" autocomplete="name" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-email">Email Address</label>
                                    <input type="email" id="signup-agent-email" name="email" :value="oldInput.email || ''" placeholder="Enter your email" autocomplete="email" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-phone">Phone Number</label>
                                    <input type="tel" id="signup-agent-phone" name="phone" :value="oldInput.phone || ''" placeholder="Enter phone number" autocomplete="tel">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-license">RERA / Broker License No.</label>
                                    <input type="text" id="signup-agent-license" name="brn_number" :value="oldInput.brn_number || ''" placeholder="Enter your license number">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-agency">Agency / Brokerage Name</label>
                                    <input type="text" id="signup-agent-agency" placeholder="You can link your brokerage later from your dashboard" disabled>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-password">Password</label>
                                    <input type="password" id="signup-agent-password" name="password" placeholder="Create a password" autocomplete="new-password" required>
                                </div>

                                <div class="mw-login-form__field mw-login-form__field--full">
                                    <label for="signup-agent-confirm">Confirm Password</label>
                                    <input type="password" id="signup-agent-confirm" name="password_confirmation" placeholder="Re-enter your password" autocomplete="new-password" required>
                                </div>
                            </template>

                            <template v-else>
                                <div class="mw-login-form__field">
                                    <label for="signup-agency-name">Agency Name</label>
                                    <input type="text" id="signup-agency-name" name="company_name" :value="oldInput.company_name || ''" placeholder="Enter your agency name" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-license">Trade License Number</label>
                                    <input type="text" id="signup-agency-license" name="trade_license_no" :value="oldInput.trade_license_no || ''" placeholder="Enter your trade license number">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-contact">Contact Person Name</label>
                                    <input type="text" id="signup-agency-contact" name="name" :value="oldInput.name || ''" placeholder="Enter contact person name" autocomplete="name" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-email">Email Address</label>
                                    <input type="email" id="signup-agency-email" name="email" :value="oldInput.email || ''" placeholder="Enter your email" autocomplete="email" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-phone">Phone Number</label>
                                    <input type="tel" id="signup-agency-phone" name="phone" :value="oldInput.phone || ''" placeholder="Enter phone number" autocomplete="tel">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-address">Office Address</label>
                                    <input type="text" id="signup-agency-address" name="office_address" :value="oldInput.office_address || ''" placeholder="Enter your office address" autocomplete="street-address">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-password">Password</label>
                                    <input type="password" id="signup-agency-password" name="password" placeholder="Create a password" autocomplete="new-password" required>
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-confirm">Confirm Password</label>
                                    <input type="password" id="signup-agency-confirm" name="password_confirmation" placeholder="Re-enter your password" autocomplete="new-password" required>
                                </div>
                            </template>
                        </div>

                        <label class="mw-signup-form__terms">
                            <input type="checkbox" name="agree_terms" required>
                            <span>I agree to the <router-link to="/terms-and-conditions">Terms &amp; Conditions</router-link> and Privacy Policy.</span>
                        </label>

                        <button type="submit" class="mw-login-form__submit">Create Account</button>

                        <p class="mw-login-form__signup">{{ config.loginText }} <router-link to="/login">Sign In</router-link></p>

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
        </section>
    </main>
</template>
