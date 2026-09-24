<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useLanguages } from '../composables/useLanguages';
import { useWishlist } from '../composables/useWishlist';
import { useStaticText } from '../composables/useStaticText';

const route = useRoute();
const activeNav = computed(() => route.meta.activeNav ?? '');
const isHome = computed(() => route.meta.headerVariant === 'home');
const { languages, selectedLanguage, selectLanguage } = useLanguages();
const { t } = useStaticText();

const { authenticated, user } = useWishlist();
const firstName = computed(() => (user.value?.name || '').trim().split(/\s+/)[0] || 'Account');

// SiteHeader is mounted once for the whole app (see App.vue), never remounted on navigation —
// so unlike the lang/currency menus (whose script.js widget marks the clicked option
// `.is-selected` and only closes via that same click), Profile/Logout need their own explicit
// close, or the menu would just stay open (Profile, an in-app SPA nav) or hang open during the
// logout request's round-trip.
function closeAccountMenu(e) {
    e.currentTarget.closest('[data-dropdown]')?.classList.remove('is-open');
}

function logout(e) {
    closeAccountMenu(e);
    // Always end up on /login regardless of the response — a full reload also resets every
    // page's shared session state (this header included), simplest and safest after auth changes
    // (same pattern Login.vue/VerifyOtp.vue already use).
    window.axios.post('/customer/logout').finally(() => {
        window.location.href = '/login';
    });
}
</script>

<template>
    <header class="mw-header" :class="{ 'mw-header--light': !isHome }">
        <div class="container-ctn">
            <div class="mw-header__row">
                <router-link to="/" class="mw-header__logo">
                    <template v-if="isHome">
                        <img src="/frontend/assets/images/logo.png" alt="MW Realty" class="mw-header__logo-img mw-header__logo-img--light">
                        <img src="/frontend/assets/images/logo-dark.png" alt="" class="mw-header__logo-img mw-header__logo-img--color" aria-hidden="true">
                    </template>
                    <img v-else src="/frontend/assets/images/logo-dark.png" alt="MW Realty" class="mw-header__logo-img">
                </router-link>

                <nav :aria-label="t('nav.primary_aria')">
                    <ul class="mw-nav">
                        <li><router-link to="/" :class="{ 'is-active': activeNav === 'home' }">{{ t('nav.home') }}</router-link></li>
                        <li><router-link to="/commercial" :class="{ 'is-active': activeNav === 'commercial' }">{{ t('nav.commercial') }}</router-link></li>
                        <li><router-link to="/agents" :class="{ 'is-active': activeNav === 'agents' }">{{ t('nav.agents') }}</router-link></li>
                        <li><router-link to="/agencies" :class="{ 'is-active': activeNav === 'agencies' }">{{ t('nav.agencies') }}</router-link></li>
                        <li><router-link to="/blogs" :class="{ 'is-active': activeNav === 'blogs' }">{{ t('nav.blogs') }}</router-link></li>
                        <li><router-link to="/about" :class="{ 'is-active': activeNav === 'about' }">{{ t('nav.about') }}</router-link></li>
                        <li><router-link to="/contact" :class="{ 'is-active': activeNav === 'contact' }">{{ t('nav.contact') }}</router-link></li>
                    </ul>
                </nav>

                <div class="mw-header__actions">
                    <div v-if="authenticated" class="mw-dropdown" data-dropdown>
                        <button type="button" class="mw-header__login mw-header__account" data-dropdown-trigger>
                            <img v-if="user?.avatar_url" :src="user.avatar_url" alt="" class="mw-header__account-avatar" width="22" height="22">
                            <img v-else src="/frontend/assets/images/icons/user.svg" alt="" width="18" height="18">
                            <span>{{ firstName }}</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" width="12" height="12">
                        </button>
                        <ul class="mw-dropdown__menu mw-header__login-menu" data-dropdown-menu>
                            <li><router-link to="/profile" @click="closeAccountMenu">{{ t('nav.profile') }}</router-link></li>
                            <li><a href="#" @click.prevent="logout">{{ t('nav.logout') }}</a></li>
                        </ul>
                    </div>
                    <router-link v-else to="/login" class="mw-header__login">
                        <img src="/frontend/assets/images/icons/user.svg" alt="" width="18" height="18">
                        <span>{{ t('nav.login') }}</span>
                    </router-link>
                    <div class="mw-dropdown" data-dropdown>
                        <button type="button" class="mw-header__lang" data-dropdown-trigger>
                            <img v-if="selectedLanguage?.flag_url" :src="selectedLanguage.flag_url" alt="" class="mw-header__lang-flag" width="22" height="16">
                            <span>{{ selectedLanguage?.code?.toUpperCase() }}</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" width="14" height="14">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li v-for="lang in languages" :key="lang.id">
                                <button type="button" :class="{ 'is-selected': selectedLanguage?.id === lang.id }" data-dropdown-option @click="selectLanguage(lang)">{{ lang.name }}</button>
                            </li>
                        </ul>
                    </div>
                    <div class="mw-dropdown" data-dropdown>
                        <button type="button" class="mw-header__currency" data-dropdown-trigger>
                            <span data-dropdown-label>AED</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" width="14" height="14">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option>AED</button></li>
                            <li><button type="button" data-dropdown-option>USD</button></li>
                            <li><button type="button" data-dropdown-option>EUR</button></li>
                            <li><button type="button" data-dropdown-option>GBP</button></li>
                        </ul>
                    </div>
                    <button type="button" class="mobile-menu-toggle" data-bs-toggle="offcanvas" data-bs-target="#mobile-nav" :aria-label="t('nav.open_menu_aria')" aria-controls="mobile-nav" aria-expanded="false">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M3 6h18M3 12h18M3 18h18" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <div class="offcanvas offcanvas-end mw-mobile-nav" tabindex="-1" id="mobile-nav" aria-labelledby="mobileNavLabel">
        <div class="offcanvas-header mw-mobile-nav__top">
            <router-link to="/" class="mw-mobile-nav__logo" id="mobileNavLabel">
                <img src="/frontend/assets/images/logo-dark.png" alt="MW Realty">
            </router-link>
            <button type="button" class="mw-mobile-nav__close" data-bs-dismiss="offcanvas" :aria-label="t('nav.close_menu_aria')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="offcanvas-body mw-mobile-nav__body">
            <nav :aria-label="t('nav.mobile_aria')">
                <ul>
                    <li><router-link to="/" :class="{ 'is-active': activeNav === 'home' }">{{ t('nav.home') }}</router-link></li>
                    <li><router-link to="/commercial" :class="{ 'is-active': activeNav === 'commercial' }">{{ t('nav.commercial') }}</router-link></li>
                    <li><router-link to="/agents" :class="{ 'is-active': activeNav === 'agents' }">{{ t('nav.agents') }}</router-link></li>
                    <li><router-link to="/agencies" :class="{ 'is-active': activeNav === 'agencies' }">{{ t('nav.agencies') }}</router-link></li>
                    <li><router-link to="/blogs" :class="{ 'is-active': activeNav === 'blogs' }">{{ t('nav.blogs') }}</router-link></li>
                    <li><router-link to="/about" :class="{ 'is-active': activeNav === 'about' }">{{ t('nav.about') }}</router-link></li>
                    <li><router-link to="/contact" :class="{ 'is-active': activeNav === 'contact' }">{{ t('nav.contact') }}</router-link></li>
                </ul>
            </nav>
            <div class="mw-mobile-nav__foot">
                <div class="mw-mobile-nav__login-options">
                    <template v-if="authenticated">
                        <router-link to="/profile" class="mw-mobile-nav__login-option">
                            <span>{{ firstName }} — {{ t('nav.profile') }}</span>
                            <img v-if="user?.avatar_url" :src="user.avatar_url" alt="" width="24" height="24" class="mw-mobile-nav__login-avatar">
                        </router-link>
                        <a href="#" class="mw-mobile-nav__login-option" @click.prevent="logout">{{ t('nav.logout') }}</a>
                    </template>
                    <router-link v-else to="/login" class="mw-mobile-nav__login-option">{{ t('nav.login') }}</router-link>
                </div>
                <div v-if="isHome" class="mw-mobile-nav__prefs">
                    <div class="mw-dropdown" data-dropdown>
                        <button type="button" class="mw-header__lang" data-dropdown-trigger>
                            <img v-if="selectedLanguage?.flag_url" :src="selectedLanguage.flag_url" alt="" class="mw-header__lang-flag" width="22" height="16">
                            <span>{{ selectedLanguage?.code?.toUpperCase() }}</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" width="14" height="14">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li v-for="lang in languages" :key="lang.id">
                                <button type="button" :class="{ 'is-selected': selectedLanguage?.id === lang.id }" data-dropdown-option @click="selectLanguage(lang)">{{ lang.name }}</button>
                            </li>
                        </ul>
                    </div>
                    <div class="mw-dropdown" data-dropdown>
                        <button type="button" class="mw-header__currency" data-dropdown-trigger>
                            <span data-dropdown-label>AED</span>
                            <img src="/frontend/assets/images/icons/chevron-down.svg" alt="" width="14" height="14">
                        </button>
                        <ul class="mw-dropdown__menu" data-dropdown-menu>
                            <li><button type="button" class="is-selected" data-dropdown-option>AED</button></li>
                            <li><button type="button" data-dropdown-option>USD</button></li>
                            <li><button type="button" data-dropdown-option>EUR</button></li>
                            <li><button type="button" data-dropdown-option>GBP</button></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
