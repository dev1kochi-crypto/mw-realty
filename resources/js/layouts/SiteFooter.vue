<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useSiteInformation } from '../composables/useSiteInformation';
import { useLanguages } from '../composables/useLanguages';
import { useRecaptcha } from '../composables/useRecaptcha';
import { useStaticText } from '../composables/useStaticText';
import { useWishlist } from '../composables/useWishlist';

const route = useRoute();
const isHome = computed(() => route.meta.isHome === true);
const { authenticated } = useWishlist();

// Mobile bottom nav — each item is a real route, and the highlighted one follows the page you're on.
// Buy / Rent are the /properties listing filtered by ?listing_type= (same as the header's Buy / Rent).
const activeBottom = computed(() => {
    const name = route.name;
    if (name === 'home') return 'home';
    if (name === 'properties-dubai' || name === 'properties-map') {
        return { sale: 'buy', rent: 'rent' }[route.query.listing_type] ?? '';
    }
    if (['profile', 'login', 'signup', 'verify-otp', 'forgot-password', 'reset-password'].includes(name)) return 'account';
    return '';
});
// Search jumps to the hero search on the home page; everywhere else it opens the filterable listing.
const searchTo = computed(() => (isHome.value ? { hash: '#search' } : '/properties'));
const accountTo = computed(() => (authenticated.value ? '/profile' : '/login'));
const accountLabel = computed(() => (authenticated.value ? t('nav.profile', 'Profile') : t('footer.bottom_nav.login')));

// The footer sits outside <router-view> and mounts once for the whole app lifetime, so this
// only ever fires on first load and on a language switch — not on every navigation.
const { siteInformation, fetchSiteInformation } = useSiteInformation();
const { selectedLanguage } = useLanguages();
const { t } = useStaticText();

onMounted(() => fetchSiteInformation(selectedLanguage.value?.code));
watch(selectedLanguage, () => fetchSiteInformation(selectedLanguage.value?.code));

const copyrightYear = new Date().getFullYear();

// Link columns. Quick Links are the site pages (the header covers Buy/Rent/Commercial/Agents/Agencies); the Properties / Buy / Off-Plan columns open
// the listing page pre-filtered (same query keys as the header's Buy/Rent and the listing filters).
const FOOTER_CITIES = ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras Al Khaimah', 'Umm Al Quwain'];
const listing = (query) => ({ path: '/properties', query });

const quickLinks = computed(() => [
    { to: '/', label: t('nav.home') },
    { to: '/market-insights', label: t('nav.market_insights', 'Market Insights') },
    { to: '/about', label: t('nav.about') },
    { to: '/blogs', label: t('nav.blog', 'Blog') },
    { to: '/careers', label: t('nav.careers', 'Careers') },
    { to: '/contact', label: t('nav.contact') },
]);

const propertyLinks = computed(() => [
    ...FOOTER_CITIES.map((city) => ({ to: listing({ location: city }), label: `${t('footer.properties_col.properties_in', 'Properties in')} ${city}` })),
    { to: '/premium-properties', label: t('footer.properties_col.premium_properties', 'Premium Properties') },
]);

const buyLinks = computed(() => [
    { to: listing({ listing_type: 'sale' }), label: t('footer.buy.properties_for_sale') },
    { to: listing({ listing_type: 'sale', property_type: 'apartment' }), label: t('footer.buy.apartments_for_sale', 'Apartments for Sale') },
    { to: listing({ listing_type: 'sale', property_type: 'villa' }), label: t('footer.buy.villas_for_sale', 'Villas for Sale') },
    { to: listing({ listing_type: 'sale', property_type: 'townhouse' }), label: t('footer.buy.townhouses_for_sale', 'Townhouses for Sale') },
    { to: listing({ listing_type: 'sale', property_type: 'penthouse' }), label: t('footer.buy.penthouses_for_sale', 'Penthouses for Sale') },
    { to: listing({ listing_type: 'sale', completion_status: 'ready' }), label: t('footer.buy.ready_to_move', 'Ready to Move In') },
]);

const offPlanLinks = computed(() => [
    { to: listing({ completion_status: 'off_plan' }), label: t('footer.off_plan.all_off_plan', 'All Off-Plan Projects') },
    ...FOOTER_CITIES.map((city) => ({ to: listing({ completion_status: 'off_plan', location: city }), label: `${t('footer.off_plan.off_plan_in', 'Off-Plan in')} ${city}` })),
]);

const { getRecaptchaToken } = useRecaptcha();
const newsletterEmail = ref('');
const newsletterAgreed = ref(false);
const newsletterSubmitting = ref(false);
const newsletterFeedback = ref(null);

async function handleNewsletterSubmit() {
    if (newsletterSubmitting.value) return;

    if (!newsletterAgreed.value) {
        // Drop the error for a frame first so a repeat click replays the shake animation.
        newsletterFeedback.value = null;
        await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        newsletterFeedback.value = { type: 'error', text: t('footer.newsletter.error_agree') };
        return;
    }

    newsletterSubmitting.value = true;
    newsletterFeedback.value = null;

    try {
        const recaptcha_token = await getRecaptchaToken('newsletter');
        const { data } = await window.axios.post('/api/newsletter/subscribe', { email: newsletterEmail.value, recaptcha_token });
        newsletterFeedback.value = { type: 'success', text: data.message };
        newsletterEmail.value = '';
        newsletterAgreed.value = false;
    } catch (error) {
        newsletterFeedback.value = { type: 'error', text: error.response?.data?.message || t('footer.newsletter.error_generic') };
    } finally {
        newsletterSubmitting.value = false;
    }
}
</script>

<template>
    <footer class="mw-footer">
        <div class="container-ctn">
            <div class="mw-footer__grid">
                <div>
                    <h3 class="mw-footer__col-title">{{ t('footer.quick_links.title') }}</h3>
                    <ul class="mw-footer__links">
                        <li v-for="link in quickLinks" :key="link.label"><router-link :to="link.to">{{ link.label }}</router-link></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mw-footer__col-title">{{ t('footer.properties_col.title') }}</h3>
                    <ul class="mw-footer__links">
                        <li v-for="link in propertyLinks" :key="link.label"><router-link :to="link.to">{{ link.label }}</router-link></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mw-footer__col-title">{{ t('footer.buy.title') }}</h3>
                    <ul class="mw-footer__links">
                        <li v-for="link in buyLinks" :key="link.label"><router-link :to="link.to">{{ link.label }}</router-link></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mw-footer__col-title">{{ t('footer.off_plan.title') }}</h3>
                    <ul class="mw-footer__links">
                        <li v-for="link in offPlanLinks" :key="link.label"><router-link :to="link.to">{{ link.label }}</router-link></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mw-footer__col-title">{{ t('footer.legal.title') }}</h3>
                    <ul class="mw-footer__links">
                        <li><router-link to="/terms-and-conditions">{{ t('footer.legal.terms_of_use') }}</router-link></li>
                        <li><router-link to="/privacy-policy">{{ t('footer.legal.privacy_policy') }}</router-link></li>
                        <li><router-link to="/security-policy">{{ t('footer.legal.security_policy') }}</router-link></li>
                        <li><router-link to="/cookie-settings">{{ t('footer.legal.cookie_settings') }}</router-link></li>
                    </ul>
                </div>
            </div>

            <div class="mw-footer__meta">
                <div class="mw-footer__contact-list">
                    <div v-if="siteInformation?.address" class="mw-footer__contact-item">
                        <span class="mw-footer__contact-label">{{ t('footer.contact.address_label') }}</span>
                        <span class="mw-footer__contact-value">{{ siteInformation.address }}</span>
                    </div>
                    <div v-if="siteInformation?.toll_free" class="mw-footer__contact-item">
                        <span class="mw-footer__contact-label">{{ t('footer.contact.toll_free_label') }}</span>
                        <span class="mw-footer__contact-value mw-footer__contact-value--phone">{{ siteInformation.toll_free }}</span>
                    </div>
                    <div v-if="siteInformation?.phone" class="mw-footer__contact-item">
                        <span class="mw-footer__contact-label">{{ t('footer.contact.call_us_label') }}</span>
                        <span class="mw-footer__contact-value mw-footer__contact-value--phone"><a :href="`tel:${siteInformation.phone}`">{{ siteInformation.phone }}</a></span>
                    </div>
                    <div v-if="siteInformation?.email" class="mw-footer__contact-item">
                        <span class="mw-footer__contact-label">{{ t('footer.contact.email_label') }}</span>
                        <span class="mw-footer__contact-value"><a :href="`mailto:${siteInformation.email}`">{{ siteInformation.email }}</a></span>
                    </div>
                </div>

                <div class="mw-footer__newsletter">
                    <h3 class="mw-footer__newsletter-title">{{ t('footer.newsletter.title') }}</h3>
                    <form class="mw-footer__newsletter-form" :class="{ 'is-submitting': newsletterSubmitting, 'is-subscribed': newsletterFeedback?.type === 'success', 'has-error': newsletterFeedback?.type === 'error' }" @submit.prevent="handleNewsletterSubmit">
                        <input type="email" :placeholder="t('footer.newsletter.placeholder')" v-model="newsletterEmail" :disabled="newsletterSubmitting" required>
                        <button type="submit" :aria-label="t('footer.newsletter.subscribe_aria')" :disabled="newsletterSubmitting">
                            <img src="/frontend/assets/images/icons/footer-arrow.svg" alt="" width="24" height="24">
                        </button>
                    </form>
                    <label class="mw-footer__newsletter-terms">
                        <input type="checkbox" v-model="newsletterAgreed" required>
                        {{ t('footer.newsletter.terms_text') }}
                    </label>
                    <p v-if="newsletterFeedback" class="mw-form-feedback" :class="`mw-form-feedback--${newsletterFeedback.type}`">{{ newsletterFeedback.text }}</p>
                </div>
            </div>

            <div class="mw-footer__bottom">
                <p class="mw-footer__copyright">{{ t('footer.copyright.prefix') }} {{ copyrightYear }} {{ siteInformation?.company_name || 'Mighty Warner Realty' }} &nbsp;|&nbsp; <a href="https://www.mightywarner.ae/" target="_blank" rel="noopener">{{ t('footer.copyright.designed_by') }}</a></p>
                <div class="mw-footer__social">
                    <a v-if="siteInformation?.social?.twitter" :href="siteInformation.social.twitter" target="_blank" rel="noopener"><img src="/frontend/assets/images/icons/social-twitter.svg" alt="" width="18" height="18"> {{ t('footer.social.twitter') }}</a>
                    <a v-if="siteInformation?.social?.facebook" :href="siteInformation.social.facebook" target="_blank" rel="noopener"><img src="/frontend/assets/images/icons/social-facebook.svg" alt="" width="18" height="18"> {{ t('footer.social.facebook') }}</a>
                    <a v-if="siteInformation?.social?.instagram" :href="siteInformation.social.instagram" target="_blank" rel="noopener"><img src="/frontend/assets/images/icons/social-instagram.svg" alt="" width="18" height="18"> {{ t('footer.social.instagram') }}</a>
                    <a v-if="siteInformation?.social?.linkedin" :href="siteInformation.social.linkedin" target="_blank" rel="noopener"><img src="/frontend/assets/images/icons/social-linkedin.svg" alt="" width="18" height="18"> {{ t('footer.social.linkedin') }}</a>
                </div>
            </div>
        </div>
    </footer>

    <nav class="mw-bottom-nav" :aria-label="t('footer.bottom_nav.aria_label')">
        <router-link to="/" class="mw-bottom-nav__item" :class="{ 'is-active': activeBottom === 'home' }" data-bottom-nav="home">
            <img src="/frontend/assets/images/icons/icon-home.svg" alt="">
            <span>{{ t('footer.bottom_nav.home') }}</span>
        </router-link>
        <router-link :to="{ path: '/properties', query: { listing_type: 'sale' } }" class="mw-bottom-nav__item" :class="{ 'is-active': activeBottom === 'buy' }" data-bottom-nav="buy">
            <img src="/frontend/assets/images/icons/building.svg" alt="">
            <span>{{ t('footer.bottom_nav.buy') }}</span>
        </router-link>
        <router-link :to="searchTo" class="mw-bottom-nav__item mw-bottom-nav__item--search" :aria-label="t('nav.search', 'Search')" data-bottom-nav="search">
            <span class="mw-bottom-nav__fab" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="11" cy="11" r="6.25" stroke="#fff" stroke-width="1.8"/>
                    <path d="M15.8 15.8L20 20" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            <span></span>
        </router-link>
        <router-link :to="{ path: '/properties', query: { listing_type: 'rent' } }" class="mw-bottom-nav__item" :class="{ 'is-active': activeBottom === 'rent' }" data-bottom-nav="rent">
            <img src="/frontend/assets/images/icons/icon-building-a.svg" alt="">
            <span>{{ t('nav.rent', 'Rent') }}</span>
        </router-link>
        <router-link :to="accountTo" class="mw-bottom-nav__item" :class="{ 'is-active': activeBottom === 'account' }" data-bottom-nav="account">
            <img src="/frontend/assets/images/icons/user.svg" alt="">
            <span>{{ accountLabel }}</span>
        </router-link>
    </nav>
</template>
