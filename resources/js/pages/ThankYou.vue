<template>
    <main>
        <section class="mw-about-hero">
            <div class="mw-about-hero__band"></div>
        </section>

        <section class="mw-thank-you">
            <div class="container-ctn">
                <img src="/frontend/assets/images/thank-you/illustration-thank-you.svg" alt="" class="mw-thank-you__illustration" width="285" height="234" data-reveal>
                <h1 class="mw-thank-you__title" data-reveal>Thank You!</h1>
                <p class="mw-thank-you__text" data-reveal>
                    Your submission has been received.<br>
                    We will be in touch and contact you soon!
                </p>
                <router-link to="/" class="mw-thank-you__cta" data-reveal>Back to Homepage</router-link>
            </div>
        </section>
    </main>
</template>
