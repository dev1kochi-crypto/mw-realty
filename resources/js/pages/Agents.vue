<script setup>
const agents = [
    { name: "Ahmed Hassan Al Mansoori", image: "/frontend/assets/images/agents/ahmed.png", years: 7, areas: "Downtown Dubai, Dubai Marina, Business Bay", delay: 0 },
    { name: "Jayme Craig", image: "/frontend/assets/images/agents/jayme.png", years: 5, areas: "Jumeirah, Business Bay", delay: 1 },
    { name: "Abhishek Mohan", image: "/frontend/assets/images/agents/abhishek.png", years: 7, areas: "Downtown Dubai, Dubai Marina, Business Bay", delay: 2 },
    { name: "James thomas", image: "/frontend/assets/images/agents/james.png", years: 8, areas: "Downtown Dubai, Dubai Marina, Business Bay", delay: 3 },
    { name: "Aslam Ali Imran", image: "/frontend/assets/images/agents/aslam.png", years: 6, areas: "Downtown Dubai, Dubai Marina, Business Bay", delay: 4 },
];
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
                    <article v-for="agent in agents" :key="agent.name" class="mw-agent-card" data-reveal :style="agent.delay ? { '--reveal-delay': agent.delay } : undefined">
                        <div class="mw-agent-card__head">
                            <div class="mw-agent-card__avatar">
                                <img :src="agent.image" :alt="agent.name" width="80" height="80">
                            </div>
                            <div class="mw-agent-card__intro">
                                <h2 class="mw-agent-card__name">{{ agent.name }}</h2>
                                <div class="mw-agent-card__tags">
                                    <span class="mw-agent-card__tag mw-agent-card__tag--fill">Serves in Dubai</span>
                                    <span class="mw-agent-card__tag">Rent : 0</span>
                                    <span class="mw-agent-card__tag">Sell : 0</span>
                                </div>
                            </div>
                        </div>
                        <div class="mw-agent-card__body">
                            <p>Years of Experience: {{ agent.years }}</p>
                            <p>Preferred Areas: {{ agent.areas }}</p>
                        </div>
                        <div class="mw-agent-card__foot">
                            <img class="mw-agent-card__logo" src="/frontend/assets/images/logo-dark.png" alt="MW Realty" width="50" height="28">
                            <span class="mw-agent-card__rule" aria-hidden="true"></span>
                            <router-link to="/agent-details" class="mw-agent-card__link">
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
