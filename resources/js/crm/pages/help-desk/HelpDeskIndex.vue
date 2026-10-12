<template>
    <div>
        <div class="st-head">
            <div>
                <h1 class="st-head__title">Help Desk</h1>
                <p class="st-head__sub">Every workflow in the CRM, explained. Pick a topic to see what you can do there and how it works.</p>
            </div>
        </div>

        <div v-if="!guides" class="text-center py-5">
            <span v-if="!loadError" class="spinner-border text-primary"></span>
            <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
        </div>
        <div v-else-if="!guides.length" class="st-empty">
            <i class="fas fa-circle-question d-block"></i>
            <div class="fw-bold mb-1">No guides yet</div>
            <div class="small">Need help? <RouterLink :to="{ name: 'contact.index' }">Contact us</RouterLink> and our team will help.</div>
        </div>
        <div v-else class="row g-3">
            <div class="col-lg-3">
                <div class="portal-card p-3 hd-topics">
                    <input v-model="search" type="search" class="form-control form-control-sm mb-2" placeholder="Search topics">
                    <nav class="hd-topics__list">
                        <button v-for="guide in filtered" :key="guide.topic" type="button" class="hd-topic"
                                :class="{ 'is-active': guide.topic === current?.topic }" @click="select(guide)">
                            <span class="hd-topic__icon"><i class="fas" :class="guide.icon || 'fa-circle-info'"></i></span>
                            <span class="hd-topic__title">{{ guide.title }}</span>
                        </button>
                        <div v-if="!filtered.length" class="text-muted small text-center py-3">No topics match your search.</div>
                    </nav>
                </div>
            </div>

            <div v-if="current" class="col-lg-9">
                <article class="hd-article">
                    <header class="hd-article__head">
                        <span class="hd-article__icon"><i class="fas" :class="current.icon || 'fa-circle-info'"></i></span>
                        <div class="hd-article__heading">
                            <div class="hd-article__eyebrow">Topic {{ index + 1 }} of {{ guides.length }}</div>
                            <h2 class="hd-article__title">{{ current.title }}</h2>
                            <p v-if="current.intro" class="hd-article__intro">{{ current.intro }}</p>
                        </div>
                        <RouterLink v-if="screenFor(current.topic)" :to="screenFor(current.topic)" class="btn btn-portal-primary btn-sm hd-article__go">
                            Go to screen <i class="fas fa-arrow-right ms-1"></i>
                        </RouterLink>
                    </header>

                    <section v-if="current.features.length" class="hd-section">
                        <h3 class="hd-section__title"><span class="portal-tour__head-icon portal-tour__head-icon--features"><i class="fas fa-wand-magic-sparkles"></i></span> What you can do here</h3>
                        <ul class="portal-tour__features hd-features">
                            <li v-for="feature in current.features" :key="feature.title">
                                <span class="portal-tour__feature-icon"><i class="fas" :class="feature.icon || 'fa-check'"></i></span>
                                <div>
                                    <div class="portal-tour__item-title">{{ feature.title }}</div>
                                    <div class="portal-tour__item-text">{{ feature.text }}</div>
                                </div>
                            </li>
                        </ul>
                    </section>

                    <section v-if="current.steps.length" class="hd-section">
                        <h3 class="hd-section__title"><span class="portal-tour__head-icon portal-tour__head-icon--steps"><i class="fas fa-route"></i></span> How it works</h3>
                        <ol class="portal-tour__steps">
                            <li v-for="(step, i) in current.steps" :key="step.title">
                                <span class="portal-tour__step-num">{{ i + 1 }}</span>
                                <div>
                                    <div class="portal-tour__item-title">{{ step.title }}</div>
                                    <div class="portal-tour__item-text">{{ step.text }}</div>
                                </div>
                            </li>
                        </ol>
                    </section>

                    <section v-if="current.tips.length" class="hd-section">
                        <h3 class="hd-section__title"><span class="portal-tour__head-icon portal-tour__head-icon--tips"><i class="fas fa-lightbulb"></i></span> Good to know</h3>
                        <ul class="portal-tour__tips">
                            <li v-for="tip in current.tips" :key="tip"><i class="fas fa-circle-check"></i><span>{{ tip }}</span></li>
                        </ul>
                    </section>

                    <footer class="hd-article__nav">
                        <button v-if="prevGuide" type="button" class="hd-nav-btn" @click="select(prevGuide)">
                            <i class="fas fa-arrow-left"></i>
                            <span><small>Previous</small>{{ prevGuide.title }}</span>
                        </button>
                        <span v-else></span>
                        <button v-if="nextGuide" type="button" class="hd-nav-btn hd-nav-btn--next" @click="select(nextGuide)">
                            <span><small>Next</small>{{ nextGuide.title }}</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </footer>
                </article>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * Help Desk — every workflow guide on one page (GET /api/crm/help), the same content as each
 * screen's "!" popup but laid out as an article: topics on the left, the chosen guide's features,
 * steps and tips on the right. `?topic=` keeps the choice in the URL.
 */
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import { useSession } from '../../composables/useSession';

const route = useRoute();
const router = useRouter();
const { navigation } = useSession();
const guides = ref(null);
const loadError = ref(null);
const search = ref('');

const current = computed(() => guides.value?.find((g) => g.topic === route.query.topic) ?? guides.value?.[0] ?? null);
const index = computed(() => guides.value.indexOf(current.value));
const prevGuide = computed(() => guides.value[index.value - 1] ?? null);
const nextGuide = computed(() => guides.value[index.value + 1] ?? null);

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return guides.value;
    return guides.value.filter((guide) => [guide.title, guide.intro, ...guide.features.map((f) => f.title), ...guide.steps.map((s) => s.title)]
        .some((text) => text?.toLowerCase().includes(term)));
});

function select(guide) {
    router.replace({ query: { ...route.query, topic: guide.topic } });
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Topic → a menu item this account can open whose screen uses that guide (so no links to screens
// they can't see, or that are locked by KYC / plan).
const screens = computed(() => {
    const map = {};
    const { items = [], account = [] } = navigation.value ?? {};
    [...items, ...items.flatMap((item) => item.children), ...account]
        .filter((item) => item.route && !item.locked && router.hasRoute(item.route))
        .forEach((item) => {
            const topic = router.resolve({ name: item.route }).meta.help;
            if (topic && !map[topic]) map[topic] = { name: item.route };
        });
    return map;
});

function screenFor(topic) {
    return screens.value[topic] ?? null;
}

function load() {
    loadError.value = null;
    return http.get('/help')
        .then((res) => { guides.value = res.data.data; })
        .catch((error) => { loadError.value = errorMessage(error); });
}

load();
</script>
