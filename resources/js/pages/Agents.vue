<script setup>
import { nextTick, onMounted, watch } from 'vue';
import { useAgents } from '../composables/useAgents';
import { useLanguages } from '../composables/useLanguages';

const { agentsListing, fetchAgentsListing } = useAgents();
const { selectedLanguage } = useLanguages();

function load() {
    fetchAgentsListing(selectedLanguage.value?.code);
}

onMounted(load);
watch(selectedLanguage, load);

// The reveal-on-scroll animation (legacy assets/js/script.js) only scans the DOM once —
// once the async fetch replaces the empty grid with real cards, those freshly rendered
// elements need that binding run again (window.MWRealty.refresh is idempotent).
watch(agentsListing, () => {
    nextTick(() => window.MWRealty && window.MWRealty.refresh());
});
</script>

<template>
    <main>
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>Our Agent</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" aria-label="Breadcrumb">
                <div class="container-ctn">
                    <p><router-link to="/">Home</router-link><span class="mw-about-crumb__sep"> / </span><span>Agent</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-agents">
            <div class="container-ctn">
                <div class="mw-agents__grid">
                    <article v-for="(agent, index) in (agentsListing?.agents || [])" :key="agent.slug" class="mw-agent-card" data-reveal :style="{ '--reveal-delay': index % 4 }">
                        <div class="mw-agent-card__head">
                            <div class="mw-agent-card__avatar">
                                <img :src="agent.avatar_url || '/frontend/assets/images/agents/ahmed.png'" :alt="agent.name" width="80" height="80">
                            </div>
                            <div class="mw-agent-card__intro">
                                <h2 class="mw-agent-card__name">{{ agent.name }}</h2>
                                <div class="mw-agent-card__tags">
                                    <span class="mw-agent-card__tag mw-agent-card__tag--fill">Serves in Dubai</span>
                                    <span class="mw-agent-card__tag">Rent : {{ agent.rent_count }}</span>
                                    <span class="mw-agent-card__tag">Sell : {{ agent.sell_count }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="mw-agent-card__body">
                            <p>Years of Experience: {{ agent.years_of_experience ?? '—' }}</p>
                            <p>Preferred Areas: {{ (agent.preferred_areas || []).join(', ') || '—' }}</p>
                        </div>
                        <div class="mw-agent-card__foot">
                            <img class="mw-agent-card__logo" src="/frontend/assets/images/logo-dark.png" alt="MW Realty" width="50" height="28">
                            <span class="mw-agent-card__rule" aria-hidden="true"></span>
                            <router-link :to="`/agent-details/${agent.slug}`" class="mw-agent-card__link">
                                View Details
                                <img src="/frontend/assets/images/icons/find-cta-arrow.svg" alt="" width="18" height="18">
                            </router-link>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </main>
</template>
