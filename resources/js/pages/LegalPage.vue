<script setup>
import { nextTick, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useLegalPage } from '../composables/useLegalPage';
import { useLanguages } from '../composables/useLanguages';
import { useStaticText } from '../composables/useStaticText';

const route = useRoute();
const { legalPage, notFound, fetchLegalPage } = useLegalPage();
const { selectedLanguage } = useLanguages();
const { t } = useStaticText();

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
            <nav class="mw-about-crumb" :aria-label="t('legal_page.breadcrumb_aria')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('legal_page.breadcrumb_home') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ legalPage.title }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-terms">
            <div class="container-ctn">
                <div v-if="legalPage.content" class="mw-blog-details__content" data-reveal v-html="legalPage.content"></div>
                <p v-else>{{ t('legal_page.unpublished_message') }}</p>
            </div>
        </section>
    </main>

    <main v-else-if="notFound">
        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ t('legal_page.not_found_title') }}</h1>
                </div>
            </div>
        </section>
        <section class="mw-terms">
            <div class="container-ctn">
                <p>{{ t('legal_page.not_found_message') }} <router-link to="/">{{ t('legal_page.back_to_home') }}</router-link></p>
            </div>
        </section>
    </main>
</template>
