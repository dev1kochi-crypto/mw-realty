<script setup>
import { nextTick, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useLegalPage } from '../composables/useLegalPage';
import { useLanguages } from '../composables/useLanguages';

const route = useRoute();
const { legalPage, notFound, fetchLegalPage } = useLegalPage();
const { selectedLanguage } = useLanguages();

function load() {
    fetchLegalPage(route.meta.legalKey, selectedLanguage.value?.code);
}

onMounted(load);
watch(() => route.meta.legalKey, load);
watch(selectedLanguage, load);
watch(
    () => legalPage.value?.title,
    (title) => {
        if (title) document.title = `${title} | MW Realty`;
    },
);

// See Blogs.vue for why this re-run is needed after the async fetch populates the page.
watch(legalPage, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});
</script>

<template>
    <main v-if="legalPage">
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ legalPage.title }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ legalPage.title }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-terms">
            <div class="container-ctn">
                <div v-if="legalPage.content" class="mw-blog-details__content" data-reveal v-html="legalPage.content"></div>
                <p v-else>This page hasn't been published yet.</p>
            </div>
        </section>
    </main>

    <main v-else-if="notFound">
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>Page Not Found</h1>
                </div>
            </div>
        </section>
        <section class="mw-terms">
            <div class="container-ctn">
                <p>This page doesn't exist. <router-link to="/">Back to Home</router-link></p>
            </div>
        </section>
    </main>
</template>
