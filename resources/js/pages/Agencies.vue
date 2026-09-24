<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useAgencies } from '../composables/useAgencies';
import { useLanguages } from '../composables/useLanguages';
import { useStaticText } from '../composables/useStaticText';

const { agenciesListing, fetchAgenciesListing } = useAgencies();
const { selectedLanguage } = useLanguages();
const { t } = useStaticText();

const locationQuery = ref('');
const nameQuery = ref('');
const appliedLocation = ref('');
const appliedName = ref('');

function load() {
    fetchAgenciesListing(selectedLanguage.value?.code);
}

function applyFilters() {
    appliedLocation.value = locationQuery.value.trim().toLowerCase();
    appliedName.value = nameQuery.value.trim().toLowerCase();
}

const filteredAgencies = computed(() => {
    const agencies = agenciesListing.value?.agencies || [];
    return agencies.filter((agency) => {
        const matchesLocation = !appliedLocation.value || (agency.office_address || '').toLowerCase().includes(appliedLocation.value);
        const matchesName = !appliedName.value || agency.name.toLowerCase().includes(appliedName.value);
        return matchesLocation && matchesName;
    });
});

function agencyAria(template, name) {
    return t(template).replace('{name}', name);
}

onMounted(load);
watch(selectedLanguage, load);

// See Blogs.vue for why this re-run is needed after the async fetch populates the page.
watch(agenciesListing, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});
watch(filteredAgencies, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});
</script>

<template>
    <main>
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ agenciesListing?.title || t('agencies.hero_default_title') }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" :aria-label="t('agencies.breadcrumb_aria')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('agencies.breadcrumb_home') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ t('agencies.breadcrumb_current') }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-agencies-filter">
            <div class="container-ctn">
                <div class="mw-agencies-filter__bar">
                    <div class="mw-agencies-filter__field">
                        <label for="agency-location">{{ t('agencies.filter.location_label') }}</label>
                        <input type="text" id="agency-location" name="location" :placeholder="t('agencies.filter.location_placeholder')" autocomplete="off" v-model="locationQuery" @keyup.enter="applyFilters">
                    </div>
                    <div class="mw-agencies-filter__field">
                        <label for="agency-name">{{ t('agencies.filter.name_label') }}</label>
                        <input type="text" id="agency-name" name="agency-name" :placeholder="t('agencies.filter.name_placeholder')" autocomplete="off" v-model="nameQuery" @keyup.enter="applyFilters">
                    </div>
                    <button type="button" class="mw-agencies-filter__search" @click="applyFilters">
                        <img src="/frontend/assets/images/agencies/icon-search.svg" alt="" width="18" height="18">
                        {{ t('agencies.filter.search') }}
                    </button>
                    <div class="mw-agencies-filter__views" role="group" :aria-label="t('agencies.filter.views_aria')">
                        <button type="button" class="mw-agencies-filter__view-btn is-active" data-agencies-view="grid" :aria-label="t('agencies.filter.grid_view_aria')" aria-pressed="true">
                            <img src="/frontend/assets/images/agencies/icon-grid-view.svg" alt="" width="24" height="24">
                        </button>
                        <button type="button" class="mw-agencies-filter__view-btn" data-agencies-view="list" :aria-label="t('agencies.filter.list_view_aria')" aria-pressed="false">
                            <img src="/frontend/assets/images/agencies/icon-list-view.svg" alt="" width="24" height="24">
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-agencies">
            <div class="container-ctn">
                <div class="mw-agencies__head">
                    <h2 class="mw-agencies__title" data-reveal>{{ agenciesListing?.section_title || t('agencies.section_default_title') }}</h2>
                    <p class="mw-agencies__text" data-reveal>{{ agenciesListing?.section_description || t('agencies.section_default_description') }}</p>
                </div>

                <p v-if="agenciesListing && !filteredAgencies.length" class="mw-agencies__text">{{ t('agencies.no_results') }}</p>

                <div class="mw-agencies__grid" data-agencies-grid>
                    <article v-for="(agency, index) in filteredAgencies" :key="agency.slug" class="mw-agency-card" data-reveal :style="{ '--reveal-delay': index % 4 }">
                        <div class="mw-agency-card__logo">
                            <img :src="agency.logo_url || '/frontend/assets/images/agencies/logo-kaal.png'" :alt="agency.name" width="200" height="120" loading="lazy">
                        </div>
                        <div class="mw-agency-card__body">
                            <h3 class="mw-agency-card__name">{{ agency.name }}</h3>
                            <div class="mw-agency-card__meta">
                                <span class="mw-agency-card__meta-item"><img src="/frontend/assets/images/icons/building.svg" alt="" width="18" height="18">{{ t('agencies.card.properties_prefix') }} : {{ agency.properties_count }}</span>
                                <span class="mw-agency-card__meta-item"><img src="/frontend/assets/images/icons/location.svg" alt="" width="18" height="18">{{ agency.office_address || t('agencies.card.default_service_area') }}</span>
                            </div>
                        </div>
                        <div class="mw-agency-card__foot">
                            <div class="mw-agency-card__contacts">
                                <a v-if="agency.phone" :href="`tel:${agency.phone}`" class="mw-agency-card__icon-btn" :aria-label="agencyAria('agencies.card.call_aria', agency.name)">
                                    <img src="/frontend/assets/images/icons/phone.svg" alt="" width="24" height="24">
                                </a>
                                <a v-if="agency.email" :href="`mailto:${agency.email}`" class="mw-agency-card__icon-btn" :aria-label="agencyAria('agencies.card.email_aria', agency.name)">
                                    <img src="/frontend/assets/images/icons/email.svg" alt="" width="24" height="24">
                                </a>
                            </div>
                            <router-link :to="`/agency-details/${agency.slug}`" class="mw-agency-card__link">
                                {{ t('agencies.card.view_details') }}
                                <img src="/frontend/assets/images/icons/find-cta-arrow.svg" alt="" width="18" height="18">
                            </router-link>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </main>
</template>
