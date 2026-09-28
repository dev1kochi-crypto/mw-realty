<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useLanguages } from '../composables/useLanguages';
import { useWishlist } from '../composables/useWishlist';
import { useStaticText } from '../composables/useStaticText';

const route = useRoute();
const isHome = computed(() => route.meta.headerVariant === 'home');
const { languages, selectedLanguage, selectLanguage } = useLanguages();
const { t } = useStaticText();

// Buy / Rent are the /properties listing pre-filtered by purpose (PropertiesDubai.vue reads
// ?listing_type=), so they're told apart by the query rather than by a route of their own.
const activeNav = computed(() => {
    if (route.name === 'properties-dubai') {
        return { sale: 'buy', rent: 'rent' }[route.query.listing_type] ?? '';
    }
    return route.meta.activeNav ?? '';
});

const primaryLinks = computed(() => [
    { key: 'home', to: '/', label: t('nav.home') },
    { key: 'buy', to: { path: '/properties', query: { listing_type: 'sale' } }, label: t('nav.buy', 'Buy') },
    { key: 'rent', to: { path: '/properties', query: { listing_type: 'rent' } }, label: t('nav.rent', 'Rent') },
    { key: 'commercial', to: '/commercial', label: t('nav.commercial') },
    { key: 'agents', to: '/agents', label: t('nav.agents') },
    { key: 'agencies', to: '/agencies', label: t('nav.agencies') },
    { key: 'market-insights', to: '/market-insights', label: t('nav.market_insights', 'Market Insights') },
]);

const exploreLinks = computed(() => [
    { key: 'about', to: '/about', label: t('nav.about') },
    { key: 'contact', to: '/contact', label: t('nav.contact') },
    { key: 'blogs', to: '/blogs', label: t('nav.blog', 'Blog') },
    { key: 'careers', to: '/careers', label: t('nav.careers', 'Careers') },
]);

const exploreActive = computed(() => exploreLinks.value.some((link) => link.key === activeNav.value));

// Desktop "Explore" menu: opens on hover/focus via CSS, and on click/tap/keyboard via this flag
// (touch screens and keyboard users have no hover). Closes on navigation, outside click and Esc.
const exploreOpen = ref(false);
const exploreEl = ref(null);
// Mobile drawer: Explore is an accordion. Its links are always rendered (v-show, not v-if) —
// script.js binds "close the drawer on link click" once, to whatever links exist at page load.
const mobileExploreOpen = ref(false);

watch(() => route.fullPath, () => { exploreOpen.value = false; });
watch(exploreActive, (active) => { if (active) mobileExploreOpen.value = true; }, { immediate: true });

function onDocumentClick(e) {
    if (exploreOpen.value && exploreEl.value && !exploreEl.value.contains(e.target)) exploreOpen.value = false;
}

function onKeydown(e) {
    if (e.key === 'Escape' && exploreOpen.value) {
        exploreOpen.value = false;
        exploreEl.value?.querySelector('.mw-nav__trigger')?.focus();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});

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
                        <li v-for="link in primaryLinks" :key="link.key">
                            <router-link :to="link.to" :class="{ 'is-active': activeNav === link.key }">{{ link.label }}</router-link>
                        </li>
                        <li ref="exploreEl" class="mw-nav__item--has-sub" :class="{ 'is-open': exploreOpen }">
                            <button
                                type="button"
                                class="mw-nav__trigger"
                                :class="{ 'is-active': exploreActive }"
                                aria-haspopup="true"
                                :aria-expanded="exploreOpen ? 'true' : 'false'"
                                aria-controls="nav-explore-menu"
                                @click="exploreOpen = !exploreOpen"
                            >
                                <span>{{ t('nav.explore', 'Explore') }}</span>
                                <svg class="mw-nav__chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <ul id="nav-explore-menu" class="mw-nav__submenu">
                                <li v-for="link in exploreLinks" :key="link.key">
                                    <router-link :to="link.to" :class="{ 'is-active': activeNav === link.key }">{{ link.label }}</router-link>
                                </li>
                            </ul>
                        </li>
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
                    <li v-for="link in primaryLinks" :key="link.key">
                        <router-link :to="link.to" :class="{ 'is-active': activeNav === link.key }">{{ link.label }}</router-link>
                    </li>
                    <li class="mw-mobile-nav__group" :class="{ 'is-open': mobileExploreOpen }">
                        <button
                            type="button"
                            class="mw-mobile-nav__group-toggle"
                            :class="{ 'is-active': exploreActive }"
                            :aria-expanded="mobileExploreOpen ? 'true' : 'false'"
                            aria-controls="mobile-nav-explore"
                            @click="mobileExploreOpen = !mobileExploreOpen"
                        >
                            <span>{{ t('nav.explore', 'Explore') }}</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                        </button>
                        <ul v-show="mobileExploreOpen" id="mobile-nav-explore" class="mw-mobile-nav__sub">
                            <li v-for="link in exploreLinks" :key="link.key">
                                <router-link :to="link.to" :class="{ 'is-active': activeNav === link.key }">{{ link.label }}</router-link>
                            </li>
                        </ul>
                    </li>
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
                <div class="mw-mobile-nav__prefs">
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
