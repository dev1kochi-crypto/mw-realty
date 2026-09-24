<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useSiteInformation } from '../composables/useSiteInformation';
import { useLanguages } from '../composables/useLanguages';
import { useRecaptcha } from '../composables/useRecaptcha';
import { useStaticText } from '../composables/useStaticText';

const route = useRoute();
const activeBottom = computed(() => route.meta.activeBottom ?? '');
const isHome = computed(() => route.meta.isHome === true);
const homeAnchor = computed(() => (isHome.value ? '' : '/'));

// The footer sits outside <router-view> and mounts once for the whole app lifetime, so this
// only ever fires on first load and on a language switch — not on every navigation.
const { siteInformation, fetchSiteInformation } = useSiteInformation();
const { selectedLanguage } = useLanguages();
const { t } = useStaticText();

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
                        <li><router-link to="/">{{ t('footer.quick_links.home') }}</router-link></li>
                        <li><router-link to="/about">{{ t('footer.quick_links.about') }}</router-link></li>
                        <li><a href="#">{{ t('footer.quick_links.properties') }}</a></li>
                        <li><a href="#">{{ t('footer.quick_links.mortgage_calculator') }}</a></li>
                        <li><a href="#">{{ t('footer.quick_links.delhi_expo') }}</a></li>
                        <li><a href="#">{{ t('footer.quick_links.invest_in_dubai') }}</a></li>
                        <li><router-link to="/contact">{{ t('footer.quick_links.contact') }}</router-link></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mw-footer__col-title">{{ t('footer.properties_col.title') }}</h3>
                    <ul class="mw-footer__links">
                        <li><router-link to="/properties">{{ t('footer.properties_col.properties_in_dubai') }}</router-link></li>
                        <li><a href="#">{{ t('footer.properties_col.properties_in_abu_dhabi') }}</a></li>
                        <li><a href="#">{{ t('footer.properties_col.properties_in_ajman') }}</a></li>
                        <li><a href="#">{{ t('footer.properties_col.properties_in_ras_al_khaimah') }}</a></li>
                        <li><a href="#">{{ t('footer.properties_col.sobha_siniya_island') }}</a></li>
                        <li><a href="#">{{ t('footer.properties_col.sobha_elwood') }}</a></li>
                        <li><a href="#">{{ t('footer.properties_col.binghatti') }}</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mw-footer__col-title">{{ t('footer.buy.title') }}</h3>
                    <ul class="mw-footer__links">
                        <li><a href="#">{{ t('footer.buy.properties_for_sale') }}</a></li>
                        <li><a href="#">{{ t('footer.buy.guide_to_buying') }}</a></li>
                        <li><a href="#">{{ t('footer.buy.mortgages') }}</a></li>
                        <li><a href="#">{{ t('footer.buy.property_management') }}</a></li>
                        <li><a href="#">{{ t('footer.buy.legal_services') }}</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mw-footer__col-title">{{ t('footer.off_plan.title') }}</h3>
                    <ul class="mw-footer__links">
                        <li><a href="#">{{ t('footer.off_plan.new_projects') }}</a></li>
                        <li><a href="#">{{ t('footer.off_plan.guide_to_buying_off_plan') }}</a></li>
                        <li><a href="#">{{ t('footer.off_plan.best_dubai_communities') }}</a></li>
                        <li><a href="#">{{ t('footer.off_plan.top_dubai_developers') }}</a></li>
                        <li><a href="#">{{ t('footer.off_plan.snagging_inspection') }}</a></li>
                        <li><a href="#">{{ t('footer.off_plan.upcoming_roadshows') }}</a></li>
                        <li><a href="#">{{ t('footer.off_plan.branded_residences') }}</a></li>
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
                    <form class="mw-footer__newsletter-form" @submit.prevent="handleNewsletterSubmit">
                        <input type="email" :placeholder="t('footer.newsletter.placeholder')" v-model="newsletterEmail" required>
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
        <router-link :to="`${homeAnchor}#premium-properties`" class="mw-bottom-nav__item" :class="{ 'is-active': activeBottom === 'buy' }" data-bottom-nav="buy">
            <img src="/frontend/assets/images/icons/building.svg" alt="">
            <span>{{ t('footer.bottom_nav.buy') }}</span>
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
            <span>{{ t('footer.bottom_nav.properties') }}</span>
        </router-link>
        <router-link to="/login" class="mw-bottom-nav__item" :class="{ 'is-active': activeBottom === 'login' }" data-bottom-nav="login">
            <img src="/frontend/assets/images/icons/user.svg" alt="">
            <span>{{ t('footer.bottom_nav.login') }}</span>
        </router-link>
    </nav>
</template>
