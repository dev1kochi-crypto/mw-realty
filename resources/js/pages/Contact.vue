<template>
    <main>
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>Contact Us</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><span>Contact Us</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-page-contact">
            <div class="container-ctn">
                <div class="mw-page-contact__layout">
                    <div class="mw-page-contact__form-col" data-reveal="left">
                        <h2 class="mw-about-title mw-page-contact__title">{{ title }}</h2>
                        <p class="mw-page-contact__lead">{{ description }}</p>
                        <form class="mw-page-contact__form" novalidate @submit.prevent>
                            <div class="mw-page-contact__field">
                                <label for="page-contact-name">Name*</label>
                                <input type="text" id="page-contact-name" name="name" placeholder="Your name" required>
                            </div>
                            <div class="mw-page-contact__field">
                                <label for="page-contact-email">Email*</label>
                                <input type="email" id="page-contact-email" name="email" placeholder="Your email" required>
                            </div>
                            <div class="mw-page-contact__field">
                                <label for="page-contact-phone">Phone</label>
                                <input type="tel" id="page-contact-phone" name="phone" placeholder="Your phone">
                            </div>
                            <div class="mw-page-contact__field">
                                <label for="page-contact-message">Message</label>
                                <textarea id="page-contact-message" name="message" rows="4" placeholder="How can we help you?"></textarea>
                            </div>
                            <button type="submit" class="mw-btn mw-btn--gradient mw-page-contact__submit">Send Message</button>
                        </form>
                    </div>

                    <div class="mw-page-contact__info-col" data-reveal="right">
                        <div class="mw-page-contact__details">
                            <div class="mw-page-contact__item" v-if="address">
                                <p class="mw-page-contact__label">Office Address</p>
                                <p class="mw-page-contact__value">{{ address }}</p>
                            </div>
                            <div class="mw-page-contact__item" v-if="phone">
                                <p class="mw-page-contact__label">Call Us</p>
                                <a class="mw-page-contact__value mw-page-contact__value--lg" :href="'tel:' + phoneHref">{{ phone }}</a>
                            </div>
                            <div class="mw-page-contact__item" v-if="email">
                                <p class="mw-page-contact__label">Email Us</p>
                                <a class="mw-page-contact__value" :href="'mailto:' + email">{{ email }}</a>
                            </div>
                            <div class="mw-page-contact__item" v-if="whatsappNumber">
                                <p class="mw-page-contact__label">WhatsApp</p>
                                <a class="mw-page-contact__value mw-page-contact__value--lg" :href="'https://wa.me/' + whatsappNumber" target="_blank" rel="noopener">{{ whatsappDisplay }}</a>
                            </div>
                            <div class="mw-page-contact__item" v-if="workingHours">
                                <p class="mw-page-contact__label">Working Hours</p>
                                <p class="mw-page-contact__value">{{ workingHours }}</p>
                            </div>
                            <div class="mw-page-contact__item">
                                <p class="mw-page-contact__label">Follow Us</p>
                                <div class="mw-page-contact__social">
                                    <a :href="social.twitter || '#'" aria-label="Twitter">
                                        <img src="/frontend/assets/images/icons/contact-twitter.svg" alt="" width="24" height="24">
                                    </a>
                                    <a :href="social.facebook || '#'" aria-label="Facebook">
                                        <img src="/frontend/assets/images/icons/contact-facebook.svg" alt="" width="24" height="24">
                                    </a>
                                    <a :href="social.instagram || '#'" aria-label="Instagram">
                                        <img src="/frontend/assets/images/icons/contact-instagram.svg" alt="" width="24" height="24">
                                    </a>
                                    <a :href="social.linkedin || '#'" aria-label="LinkedIn">
                                        <img src="/frontend/assets/images/icons/contact-linkedin.svg" alt="" width="24" height="24">
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="mw-page-contact__map">
                            <iframe v-if="mapUrl" :src="mapUrl" style="border:0; width:100%; height:100%;" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Office location map"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mw-page-contact__photo">
                <img :src="heroImage" :alt="heroImageAlt" width="392" height="875">
            </div>
        </section>
    </main>
</template>

<script setup>
import { computed } from 'vue';
import { useContactPage } from '../composables/useContactPage';

const { contactPage } = useContactPage();

const title = computed(() => contactPage.value?.title || 'Get in Touch');
const description = computed(() => contactPage.value?.description || 'Have questions about our properties or services? Our team is here to help you.');
const address = computed(() => contactPage.value?.address || 'Office No: 703 - Churchill Tower Business Bay - Dubai, UAE');
const phone = computed(() => contactPage.value?.phone || '+971 58 589 9990');
const phoneHref = computed(() => phone.value.replace(/[^\d+]/g, ''));
const email = computed(() => contactPage.value?.email || 'info@mightywarnersrealty.com');
const whatsappNumber = computed(() => contactPage.value?.whatsapp_number || '971585899990');
const whatsappDisplay = computed(() => phone.value);
const workingHours = computed(() => contactPage.value?.working_hours || 'Mon - Sat: 9:00 AM - 6:00 PM');
const social = computed(() => contactPage.value?.social || {});
const mapUrl = computed(() => contactPage.value?.map_url || 'https://maps.google.com/maps?q=Churchill+Tower+Business+Bay+Dubai&output=embed');
const heroImage = computed(() => contactPage.value?.image || '/frontend/assets/images/contact/villa.png');
const heroImageAlt = computed(() => contactPage.value?.image_alt || 'Poolside villa at sunset');
</script>
