<script setup>
import WithAdSidebar from '../components/WithAdSidebar.vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAgents } from '../composables/useAgents';
import { useLanguages } from '../composables/useLanguages';
import { useStaticText } from '../composables/useStaticText';

const { agentsListing, fetchAgentsListing } = useAgents();
const { selectedLanguage } = useLanguages();
const { t } = useStaticText();
const route = useRoute();

const locationQuery = ref('');
const nameQuery = ref('');
const appliedLocation = ref('');
const appliedName = ref('');
// Agency: free text matches the agency's name; ?agency={slug} (an agency page's View all) matches exactly.
const agencyQuery = ref('');
const appliedAgency = ref('');
const agencySlug = ref(typeof route.query.agency === 'string' ? route.query.agency : '');
const view = ref('grid');

function load() {
    fetchAgentsListing(selectedLanguage.value?.code);
}

function applyFilters() {
    appliedLocation.value = locationQuery.value.trim().toLowerCase();
    appliedName.value = nameQuery.value.trim().toLowerCase();
    // Editing the prefilled agency name switches from the exact ?agency= match to a name search.
    if (agencySlug.value && agencyQuery.value.trim() !== slugAgencyName.value) agencySlug.value = '';
    appliedAgency.value = agencySlug.value ? '' : agencyQuery.value.trim().toLowerCase();
}

// The ?agency= agency's name, shown in the Agency box once the agents have loaded.
const slugAgencyName = computed(() => (agentsListing.value?.agents || []).find((a) => a.company?.slug === agencySlug.value)?.company.name || '');
watch(slugAgencyName, (name) => { if (name && agencySlug.value) agencyQuery.value = name; }, { immediate: true });

// Same search bar as Agencies.vue — location matches the agent's preferred areas.
const filteredAgents = computed(() => {
    const agents = agentsListing.value?.agents || [];
    return agents.filter((agent) => {
        const matchesLocation = !appliedLocation.value
            || (agent.preferred_areas || []).some((area) => String(area).toLowerCase().includes(appliedLocation.value));
        const matchesName = !appliedName.value || (agent.name || '').toLowerCase().includes(appliedName.value);
        const matchesAgency = agencySlug.value
            ? agent.company?.slug === agencySlug.value
            : !appliedAgency.value || (agent.company?.name || '').toLowerCase().includes(appliedAgency.value);
        return matchesLocation && matchesName && matchesAgency;
    });
});

onMounted(load);
watch(selectedLanguage, load);

// The reveal-on-scroll animation (legacy assets/js/script.js) only scans the DOM once —
// once the async fetch replaces the empty grid with real cards, those freshly rendered
// elements need that binding run again (window.MWRealty.refresh is idempotent).
watch(filteredAgents, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});
</script>
<template>
    <main>
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ agentsListing?.title || t('agents.hero_default_title') }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" :aria-label="t('agents.breadcrumb_aria')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('agents.breadcrumb_home') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ t('agents.breadcrumb_current') }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-agencies-filter">
            <div class="container-ctn">
                <div class="mw-agencies-filter__bar mw-agencies-filter__bar--three">
                    <div class="mw-agencies-filter__field">
                        <label for="agent-location">{{ t('agents.filter.location_label') }}</label>
                        <input type="text" id="agent-location" name="location" :placeholder="t('agents.filter.location_placeholder')" autocomplete="off" v-model="locationQuery" @keyup.enter="applyFilters">
                    </div>
                    <div class="mw-agencies-filter__field">
                        <label for="agent-name">{{ t('agents.filter.name_label') }}</label>
                        <input type="text" id="agent-name" name="agent-name" :placeholder="t('agents.filter.name_placeholder')" autocomplete="off" v-model="nameQuery" @keyup.enter="applyFilters">
                    </div>
                    <div class="mw-agencies-filter__field">
                        <label for="agent-agency">{{ t('agents.filter.agency_label') }}</label>
                        <input type="text" id="agent-agency" name="agency" :placeholder="t('agents.filter.agency_placeholder')" autocomplete="off" v-model="agencyQuery" @keyup.enter="applyFilters">
                    </div>
                    <button type="button" class="mw-agencies-filter__search" @click="applyFilters">
                        <img src="/frontend/assets/images/agencies/icon-search.svg" alt="" width="18" height="18">
                        {{ t('agents.filter.search') }}
                    </button>
                    <div class="mw-agencies-filter__views" role="group" :aria-label="t('agents.filter.views_aria')">
                        <button type="button" class="mw-agencies-filter__view-btn" :class="{ 'is-active': view === 'grid' }" :aria-label="t('agents.filter.grid_view_aria')" :aria-pressed="view === 'grid'" @click="view = 'grid'">
                            <img src="/frontend/assets/images/agencies/icon-grid-view.svg" alt="" width="24" height="24">
                        </button>
                        <button type="button" class="mw-agencies-filter__view-btn" :class="{ 'is-active': view === 'list' }" :aria-label="t('agents.filter.list_view_aria')" :aria-pressed="view === 'list'" @click="view = 'list'">
                            <img src="/frontend/assets/images/agencies/icon-list-view.svg" alt="" width="24" height="24">
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="mw-agents">
            <div class="container-ctn">
                <p v-if="agentsListing && !filteredAgents.length" class="mw-agencies__text">{{ t('agents.no_results') }}</p>

                <WithAdSidebar placement="agents">
                <div class="mw-agents__grid" :class="{ 'is-list-view': view === 'list' }">
                    <article v-for="(agent, index) in filteredAgents":key="agent.slug" class="mw-agent-card" data-reveal :style="{ '--reveal-delay': index % 4 }">
                        <div class="mw-agent-card__head">
                            <div class="mw-agent-card__avatar">
                                <img :src="agent.avatar_url || '/frontend/assets/images/placeholders/avatar.svg'" :alt="agent.name" width="80" height="80" loading="lazy">
                            </div>
                            <div class="mw-agent-card__intro">
                                <h2 class="mw-agent-card__name">{{ agent.name }}</h2>
                                <div class="mw-agent-card__tags">
                                    <span class="mw-agent-card__tag mw-agent-card__tag--fill">{{ t('agents.card.serves_in_dubai') }}</span>
                                    <span class="mw-agent-card__tag">{{ t('agents.card.rent_prefix') }} : {{ agent.rent_count }}</span>
                                    <span class="mw-agent-card__tag">{{ t('agents.card.sell_prefix') }} : {{ agent.sell_count }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="mw-agent-card__body">
                            <p>{{ t('agents.card.years_of_experience_prefix') }}: {{ agent.years_of_experience ?? '—' }}</p>
                            <p>{{ t('agents.card.preferred_areas_prefix') }}: {{ (agent.preferred_areas || []).join(', ') || '—' }}</p>
                        </div>
                        <div class="mw-agent-card__foot">
                            <img class="mw-agent-card__logo" src="/frontend/assets/images/logo-dark.png" alt="MW Realty" width="50" height="28">
                            <span class="mw-agent-card__rule" aria-hidden="true"></span>
                            <router-link :to="`/agent-details/${agent.slug}`" class="mw-agent-card__link">
                                {{ t('agents.card.view_details') }}
                                <img src="/frontend/assets/images/icons/find-cta-arrow.svg" alt="" width="18" height="18">
                            </router-link>
                        </div>
                    </article>
                </div>
                </WithAdSidebar>
            </div>
        </section>
    </main>
</template>
