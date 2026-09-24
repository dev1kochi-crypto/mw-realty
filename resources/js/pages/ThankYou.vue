<script setup>
import { useStaticText } from '../composables/useStaticText';

const { t } = useStaticText();
</script>

<template>
    <main>
        <section class="mw-about-hero">
            <div class="mw-about-hero__band"></div>
        </section>

        <section class="mw-thank-you">
            <div class="container-ctn">
                <img src="/frontend/assets/images/thank-you/illustration-thank-you.svg" alt="" class="mw-thank-you__illustration" width="285" height="234" data-reveal>
                <h1 class="mw-thank-you__title" data-reveal>{{ t('thank_you.title') }}</h1>
                <p class="mw-thank-you__text" data-reveal>
                    {{ t('thank_you.line1') }}<br>
                    {{ t('thank_you.line2') }}
                </p>
                <router-link to="/" class="mw-thank-you__cta" data-reveal>{{ t('thank_you.cta') }}</router-link>
            </div>
        </section>
    </main>
</template>
