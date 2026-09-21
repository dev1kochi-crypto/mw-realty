<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useSiteInformation } from '../composables/useSiteInformation';
import { useLanguages } from '../composables/useLanguages';
import { useRecaptcha } from '../composables/useRecaptcha';

const route = useRoute();
const activeBottom = computed(() => route.meta.activeBottom ?? '');
const isHome = computed(() => route.meta.isHome === true);
const homeAnchor = computed(() => (isHome.value ? '' : '/'));

// The footer sits outside <router-view> and mounts once for the whole app lifetime, so this
// only ever fires on first load and on a language switch — not on every navigation.
const { siteInformation, fetchSiteInformation } = useSiteInformation();
const { selectedLanguage } = useLanguages();

onMounted(() => fetchSiteInformation(selectedLanguage.value?.code));
watch(selectedLanguage, () => fetchSiteInformation(selectedLanguage.value?.code));

const copyrightYear = new Date().getFullYear();

const { getRecaptchaToken } = useRecaptcha();
const newsletterEmail = ref('');
const newsletterAgreed = ref(false);
const newsletterSubmitting = ref(false);
const newsletterFeedback = ref(null);

async function handleNewsletterSubmit() {
    if (newsletterSubmitting.value) return;

    if (!newsletterAgreed.value) {
        newsletterFeedback.value = { type: 'error', text: 'Please agree to the terms & conditions to subscribe.' };
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
        newsletterFeedback.value = { type: 'error', text: error.response?.data?.message || 'Something went wrong — please try again.' };
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
                    <h3 class="mw-footer__col-title">Quick Links</h3>
                    <ul class="mw-footer__links">
                        <li><router-link to="/">Home</router-link></li>
                        <li><router-link to="/about">About Us</router-link></li>
                        <li><a href="#">Properties</a></li>
                        <li><a href="#">Mortgage Calculator</a></li>
                        <li><a href="#">Delhi Expo</a></li>
                        <li><a href="#">Invest in Dubai</a></li>
                        <li><router-link to="/contact">Contact</router-link></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mw-footer__col-title">Properties</h3>
                    <ul class="mw-footer__links">
                        <li><router-link to="/properties">Properties in Dubai</router-link></li>
                        <li><a href="#">Properties in Abu dhabi</a></li>
                        <li><a href="#">Properties in Ajman</a></li>
                        <li><a href="#">Properties in Ras Al Khaimah</a></li>
                        <li><a href="#">Sobha Siniya Island</a></li>
                        <li><a href="#">Sobha Elwood</a></li>
                        <li><a href="#">Binghatti</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mw-footer__col-title">Buy</h3>
                    <ul class="mw-footer__links">
                        <li><a href="#">Properties for Sale</a></li>
                        <li><a href="#">Guide to Buying</a></li>
                        <li><a href="#">Mortgages</a></li>
                        <li><a href="#">Property Management</a></li>
                        <li><a href="#">Legal Services</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mw-footer__col-title">Off-Plan</h3>
                    <ul class="mw-footer__links">
                        <li><a href="#">New Projects</a></li>
                        <li><a href="#">Guide to Buying Off Plan</a></li>
                        <li><a href="#">Best Dubai Communities</a></li>
                        <li><a href="#">Top Dubai Developers</a></li>
                        <li><a href="#">Snagging &amp; Inspection</a></li>
                        <li><a href="#">Upcoming Roadshows</a></li>
                        <li><a href="#">Branded Residences</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mw-footer__col-title">Legal</h3>
                    <ul class="mw-footer__links">
                        <li><router-link to="/terms-and-conditions">Terms of use</router-link></li>
                        <li><router-link to="/privacy-policy">Privacy policy</router-link></li>
                        <li><router-link to="/security-policy">Security Policy</router-link></li>
                        <li><router-link to="/cookie-settings">Cookie settings</router-link></li>
                    </ul>
                </div>
            </div>

            <div class="mw-footer__meta">
                <div class="mw-footer__contact-list">
                    <div v-if="siteInformation?.address" class="mw-footer__contact-item">
                        <span class="mw-footer__contact-label">Address</span>
                        <span class="mw-footer__contact-value">{{ siteInformation.address }}</span>
                    </div>
                    <div v-if="siteInformation?.toll_free" class="mw-footer__contact-item">
                        <span class="mw-footer__contact-label">Toll Free</span>
                        <span class="mw-footer__contact-value mw-footer__contact-value--phone">{{ siteInformation.toll_free }}</span>
                    </div>
                    <div v-if="siteInformation?.phone" class="mw-footer__contact-item">
                        <span class="mw-footer__contact-label">Call Us</span>
                        <span class="mw-footer__contact-value mw-footer__contact-value--phone"><a :href="`tel:${siteInformation.phone}`">{{ siteInformation.phone }}</a></span>
                    </div>
                    <div v-if="siteInformation?.email" class="mw-footer__contact-item">
                        <span class="mw-footer__contact-label">Email</span>
                        <span class="mw-footer__contact-value"><a :href="`mailto:${siteInformation.email}`">{{ siteInformation.email }}</a></span>
                    </div>
                </div>

                <div class="mw-footer__newsletter">
                    <h3 class="mw-footer__newsletter-title">Newsletter</h3>
                    <form class="mw-footer__newsletter-form" @submit.prevent="handleNewsletterSubmit">
                        <input type="email" placeholder="Your email address" v-model="newsletterEmail" required>
                        <button type="submit" aria-label="Subscribe" :disabled="newsletterSubmitting">
                            <img src="/frontend/assets/images/icons/footer-arrow.svg" alt="" width="24" height="24">
                        </button>
                    </form>
                    <label class="mw-footer__newsletter-terms">
                        <input type="checkbox" v-model="newsletterAgreed" required>
                        I have read and agree to the terms &amp; conditions
                    </label>
                    <p v-if="newsletterFeedback" class="mw-form-feedback" :class="`mw-form-feedback--${newsletterFeedback.type}`">{{ newsletterFeedback.text }}</p>
                </div>
            </div>

            <div class="mw-footer__bottom">
                <p class="mw-footer__copyright">Copyright &copy; {{ copyrightYear }} {{ siteInformation?.company_name || 'Mighty Warner Realty' }} &nbsp;|&nbsp; Designed by : Mighty Warners Technologies</p>
                <div class="mw-footer__social">
                    <a v-if="siteInformation?.social?.twitter" :href="siteInformation.social.twitter" target="_blank" rel="noopener"><img src="/frontend/assets/images/icons/social-twitter.svg" alt="" width="18" height="18"> Twitter</a>
                    <a v-if="siteInformation?.social?.facebook" :href="siteInformation.social.facebook" target="_blank" rel="noopener"><img src="/frontend/assets/images/icons/social-facebook.svg" alt="" width="18" height="18"> Facebook</a>
                    <a v-if="siteInformation?.social?.instagram" :href="siteInformation.social.instagram" target="_blank" rel="noopener"><img src="/frontend/assets/images/icons/social-instagram.svg" alt="" width="18" height="18"> Instagram</a>
                    <a v-if="siteInformation?.social?.linkedin" :href="siteInformation.social.linkedin" target="_blank" rel="noopener"><img src="/frontend/assets/images/icons/social-linkedin.svg" alt="" width="18" height="18"> LinkedIn</a>
                </div>
            </div>
        </div>
    </footer>

    <nav class="mw-bottom-nav" aria-label="Quick links">
        <router-link to="/" class="mw-bottom-nav__item" :class="{ 'is-active': activeBottom === 'home' }" data-bottom-nav="home">
            <img src="/frontend/assets/images/icons/icon-home.svg" alt="">
            <span>Home</span>
        </router-link>
        <router-link :to="`${homeAnchor}#premium-properties`" class="mw-bottom-nav__item" :class="{ 'is-active': activeBottom === 'buy' }" data-bottom-nav="buy">
            <img src="/frontend/assets/images/icons/building.svg" alt="">
            <span>Buy</span>
        </router-link>
        <router-link :to="`${homeAnchor}#search`" class="mw-bottom-nav__item mw-bottom-nav__item--search" :class="{ 'is-active': activeBottom === 'search' }" data-bottom-nav="search">
            <span class="mw-bottom-nav__fab" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="11" cy="11" r="6.25" stroke="#fff" stroke-width="1.8"/>
                    <path d="M15.8 15.8L20 20" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            <span></span>
        </router-link>
        <router-link :to="`${homeAnchor}#find-properties`" class="mw-bottom-nav__item" :class="{ 'is-active': activeBottom === 'properties' }" data-bottom-nav="properties">
            <img src="/frontend/assets/images/icons/icon-building-a.svg" alt="">
            <span>Properties</span>
        </router-link>
        <router-link to="/login" class="mw-bottom-nav__item" :class="{ 'is-active': activeBottom === 'login' }" data-bottom-nav="login">
            <img src="/frontend/assets/images/icons/user.svg" alt="">
            <span>Login</span>
        </router-link>
    </nav>
</template>
