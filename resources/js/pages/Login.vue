<script setup>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
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
                        <form class="mw-login-form" action="/portal/login" method="post">
                            <input type="hidden" name="_token" :value="csrfToken">
                            <h1 class="mw-login-form__title">Welcome back</h1>
                            <p class="mw-login-form__subtitle">Sign in with your agent or agency account to manage your listings and leads.</p>

                            <div class="mw-login-form__field">
                                <label for="login-email">Email</label>
                                <input type="email" id="login-email" name="email" placeholder="Enter your email" autocomplete="email" required>
                            </div>

                            <div class="mw-login-form__field">
                                <label for="login-password">Password</label>
                                <input type="password" id="login-password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                            </div>

                            <a href="#" class="mw-login-form__forgot">Forgot your password?</a>

                            <button type="submit" class="mw-login-form__submit">Sign In</button>

                            <p class="mw-login-form__signup">Don't have an account? <router-link to="/signup">Sign Up</router-link></p>

                            <div class="mw-login-form__divider"><span>OR</span></div>

                            <button type="button" class="mw-login-form__google">
                                <img src="/frontend/assets/images/icons/google.svg" alt="" width="18" height="18">
                                Continue with Google
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
