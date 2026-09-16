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
                        <h2 class="mw-about-title mw-page-contact__title">Get in Touch</h2>
                        <p class="mw-page-contact__lead">Have questions about our properties or services? Our team is here to help you.</p>
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
                            <div class="mw-page-contact__item">
                                <p class="mw-page-contact__label">Office Address</p>
                                <p class="mw-page-contact__value">Office No: 703 - Churchill Tower Business Bay - Dubai, UAE</p>
                            </div>
                            <div class="mw-page-contact__item">
                                <p class="mw-page-contact__label">Call Us</p>
                                <a class="mw-page-contact__value mw-page-contact__value--lg" href="tel:+971585899990">+971 58 589 9990</a>
                            </div>
                            <div class="mw-page-contact__item">
                                <p class="mw-page-contact__label">Email Us</p>
                                <a class="mw-page-contact__value" href="mailto:info@mightywarnersrealty.com">info@mightywarnersrealty.com</a>
                            </div>
                            <div class="mw-page-contact__item">
                                <p class="mw-page-contact__label">WhatsApp</p>
                                <a class="mw-page-contact__value mw-page-contact__value--lg" href="https://wa.me/971585899990" target="_blank" rel="noopener">+971 58 589 9990</a>
                            </div>
                            <div class="mw-page-contact__item">
                                <p class="mw-page-contact__label">Working Hours</p>
                                <p class="mw-page-contact__value">Mon - Sat: 9:00 AM - 6:00 PM</p>
                            </div>
                            <div class="mw-page-contact__item">
                                <p class="mw-page-contact__label">Follow Us</p>
                                <div class="mw-page-contact__social">
                                    <a href="#" aria-label="Twitter">
                                        <img src="/frontend/assets/images/icons/contact-twitter.svg" alt="" width="24" height="24">
                                    </a>
                                    <a href="#" aria-label="Facebook">
                                        <img src="/frontend/assets/images/icons/contact-facebook.svg" alt="" width="24" height="24">
                                    </a>
                                    <a href="#" aria-label="Instagram">
                                        <img src="/frontend/assets/images/icons/contact-instagram.svg" alt="" width="24" height="24">
                                    </a>
                                    <a href="#" aria-label="LinkedIn">
                                        <img src="/frontend/assets/images/icons/contact-linkedin.svg" alt="" width="24" height="24">
                                    </a>
                                </div>
                            </div>
                        </div>

                        <a class="mw-page-contact__map" href="https://www.google.com/maps/search/?api=1&query=Churchill+Tower+Business+Bay+Dubai" target="_blank" rel="noopener">
                            <img src="/frontend/assets/images/contact/map.png" alt="Map showing Churchill Towers in Business Bay, Dubai" width="569" height="321">
                        </a>
                    </div>
                </div>
            </div>

            <div class="mw-page-contact__photo">
                <img src="/frontend/assets/images/contact/villa.png" alt="Poolside villa at sunset" width="392" height="875">
            </div>
        </section>
    </main>
</template>
