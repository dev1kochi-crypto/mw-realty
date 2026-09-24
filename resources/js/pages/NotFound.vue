<script setup>
import { useStaticText } from '../composables/useStaticText';

const { t } = useStaticText();
</script>

<template>
    <main>
        <section class="mw-about-hero">
            <div class="mw-about-hero__band"></div>
        </section>

        <section class="mw-not-found">
            <div class="container-ctn">
                <img src="/frontend/assets/images/404/illustration-404.svg" alt="" class="mw-not-found__illustration" width="667" height="385" data-reveal>
                <h1 class="mw-not-found__title" data-reveal>{{ t('not_found.title') }}</h1>
                <p class="mw-not-found__text" data-reveal>{{ t('not_found.text') }}</p>
                <router-link to="/" class="mw-not-found__cta" data-reveal>{{ t('not_found.cta') }}</router-link>
            </div>
        </section>
    </main>
</template>
