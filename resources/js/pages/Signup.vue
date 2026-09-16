<script setup>
import { computed, ref } from 'vue';

const accountType = ref('user');

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

                    <form class="mw-login-form mw-signup-form" action="#" method="post" @submit.prevent>
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
                                    <input type="text" id="signup-user-name" name="full_name" placeholder="Enter your full name" autocomplete="name">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-user-email">Email Address</label>
                                    <input type="email" id="signup-user-email" name="email" placeholder="Enter your email" autocomplete="email">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-user-phone">Phone Number</label>
                                    <input type="tel" id="signup-user-phone" name="phone" placeholder="Enter phone number" autocomplete="tel">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-user-password">Password</label>
                                    <input type="password" id="signup-user-password" name="password" placeholder="Create a password" autocomplete="new-password">
                                </div>

                                <div class="mw-login-form__field mw-login-form__field--full">
                                    <label for="signup-user-confirm">Confirm Password</label>
                                    <input type="password" id="signup-user-confirm" name="confirm_password" placeholder="Re-enter your password" autocomplete="new-password">
                                </div>
                            </template>

                            <template v-else-if="accountType === 'agent'">
                                <div class="mw-login-form__field">
                                    <label for="signup-agent-name">Full Name</label>
                                    <input type="text" id="signup-agent-name" name="full_name" placeholder="Enter your full name" autocomplete="name">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-email">Email Address</label>
                                    <input type="email" id="signup-agent-email" name="email" placeholder="Enter your email" autocomplete="email">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-phone">Phone Number</label>
                                    <input type="tel" id="signup-agent-phone" name="phone" placeholder="Enter phone number" autocomplete="tel">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-license">RERA / Broker License No.</label>
                                    <input type="text" id="signup-agent-license" name="license_number" placeholder="Enter your license number">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-agency">Agency / Brokerage Name</label>
                                    <input type="text" id="signup-agent-agency" name="agency_name" placeholder="Enter the agency you work with">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agent-password">Password</label>
                                    <input type="password" id="signup-agent-password" name="password" placeholder="Create a password" autocomplete="new-password">
                                </div>

                                <div class="mw-login-form__field mw-login-form__field--full">
                                    <label for="signup-agent-confirm">Confirm Password</label>
                                    <input type="password" id="signup-agent-confirm" name="confirm_password" placeholder="Re-enter your password" autocomplete="new-password">
                                </div>
                            </template>

                            <template v-else>
                                <div class="mw-login-form__field">
                                    <label for="signup-agency-name">Agency Name</label>
                                    <input type="text" id="signup-agency-name" name="agency_name" placeholder="Enter your agency name">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-license">Trade License Number</label>
                                    <input type="text" id="signup-agency-license" name="trade_license" placeholder="Enter your trade license number">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-contact">Contact Person Name</label>
                                    <input type="text" id="signup-agency-contact" name="contact_person" placeholder="Enter contact person name" autocomplete="name">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-email">Email Address</label>
                                    <input type="email" id="signup-agency-email" name="email" placeholder="Enter your email" autocomplete="email">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-phone">Phone Number</label>
                                    <input type="tel" id="signup-agency-phone" name="phone" placeholder="Enter phone number" autocomplete="tel">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-address">Office Address</label>
                                    <input type="text" id="signup-agency-address" name="office_address" placeholder="Enter your office address" autocomplete="street-address">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-password">Password</label>
                                    <input type="password" id="signup-agency-password" name="password" placeholder="Create a password" autocomplete="new-password">
                                </div>

                                <div class="mw-login-form__field">
                                    <label for="signup-agency-confirm">Confirm Password</label>
                                    <input type="password" id="signup-agency-confirm" name="confirm_password" placeholder="Re-enter your password" autocomplete="new-password">
                                </div>
                            </template>
                        </div>

                        <label class="mw-signup-form__terms">
                            <input type="checkbox" name="agree_terms" required>
                            <span>I agree to the <router-link to="/terms-and-conditions">Terms &amp; Conditions</router-link> and Privacy Policy.</span>
                        </label>

                        <button type="submit" class="mw-login-form__submit">Create Account</button>

                        <p class="mw-login-form__signup">{{ config.loginText }} <router-link to="/login">Sign In</router-link></p>

                        <div class="mw-login-form__divider"><span>OR</span></div>

                        <button type="button" class="mw-login-form__google">
                            <img src="/frontend/assets/images/icons/google.svg" alt="" width="18" height="18">
                            Continue with Google
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>
</template>
