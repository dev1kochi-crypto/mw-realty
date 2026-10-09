<template>
    <button type="button" class="portal-help-btn" :class="{ 'is-new': isNew }" :title="guide ? `How ${guide.title} works` : 'How this page works'"
            aria-label="Help: how this page works" @click="show">
        <i class="fas fa-circle-exclamation"></i>
    </button>

    <Teleport to="body">
        <template v-if="open && guide">
            <div class="modal d-block portal-tour" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="crmHelpTitle"
                 @click.self="close" @keydown.right.prevent="next" @keydown.left.prevent="go(current - 1)" @keydown.esc="close">
                <div class="modal-dialog modal-dialog-centered">
                    <div ref="dialog" class="modal-content" tabindex="-1">
                        <button type="button" class="btn-close portal-tour__close" aria-label="Close" @click="close"></button>

                        <div class="portal-tour__slides">
                            <section v-for="(slide, index) in slides" :key="slide" class="portal-tour__slide"
                                     :class="{ 'is-active': index === current, 'is-left': index < current }" :aria-hidden="index !== current">
                                <template v-if="slide === 'welcome'">
                                    <div class="portal-tour__hero">
                                        <span class="portal-tour__ring portal-tour__ring--1"></span>
                                        <span class="portal-tour__ring portal-tour__ring--2"></span>
                                        <span class="portal-tour__hero-icon"><i class="fas" :class="guide.icon || 'fa-circle-info'"></i></span>
                                    </div>
                                    <div class="portal-tour__welcome">
                                        <div class="portal-tour__eyebrow">Quick guide</div>
                                        <h5 id="crmHelpTitle" class="portal-tour__title">Welcome to {{ guide.title }}</h5>
                                        <p v-if="guide.intro" class="portal-tour__lead">{{ guide.intro }}</p>
                                        <div class="portal-tour__meta">
                                            <i class="fas fa-clock"></i> {{ slides.length - 1 }} short {{ slides.length - 1 === 1 ? 'step' : 'steps' }} &middot; under a minute
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="portal-tour__head">
                                        <span class="portal-tour__head-icon" :class="`portal-tour__head-icon--${slide}`"><i class="fas" :class="slideMeta[slide].icon"></i></span>
                                        <div>
                                            <div class="portal-tour__eyebrow">{{ guide.title }}</div>
                                            <h6 class="portal-tour__head-title">{{ slideMeta[slide].title }}</h6>
                                        </div>
                                    </div>
                                    <div class="portal-tour__body">
                                        <ul v-if="slide === 'features'" class="portal-tour__features">
                                            <li v-for="feature in guide.features" :key="feature.title">
                                                <span class="portal-tour__feature-icon"><i class="fas" :class="feature.icon || 'fa-check'"></i></span>
                                                <div>
                                                    <div class="portal-tour__item-title">{{ feature.title }}</div>
                                                    <div class="portal-tour__item-text">{{ feature.text }}</div>
                                                </div>
                                            </li>
                                        </ul>
                                        <ol v-else-if="slide === 'steps'" class="portal-tour__steps">
                                            <li v-for="(step, i) in guide.steps" :key="step.title">
                                                <span class="portal-tour__step-num">{{ i + 1 }}</span>
                                                <div>
                                                    <div class="portal-tour__item-title">{{ step.title }}</div>
                                                    <div class="portal-tour__item-text">{{ step.text }}</div>
                                                </div>
                                            </li>
                                        </ol>
                                        <template v-else>
                                            <ul class="portal-tour__tips">
                                                <li v-for="tip in guide.tips" :key="tip"><i class="fas fa-circle-check"></i><span>{{ tip }}</span></li>
                                            </ul>
                                            <div class="portal-tour__reopen">
                                                <span class="portal-tour__reopen-icon"><i class="fas fa-circle-exclamation"></i></span>
                                                <span>Need this again? Click the <strong>!</strong> icon at the top of the page anytime.</span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </section>
                        </div>

                        <div class="portal-tour__foot">
                            <button type="button" class="portal-tour__skip" :class="{ 'is-hidden': isLast }" @click="close">Skip</button>
                            <div class="portal-tour__dots" role="tablist" aria-label="Guide steps">
                                <button v-for="(slide, index) in slides" :key="slide" type="button" class="portal-tour__dot"
                                        :class="{ 'is-active': index === current, 'is-done': index < current }" :aria-label="`Go to step ${index + 1}`" @click="go(index)"></button>
                            </div>
                            <div class="portal-tour__nav">
                                <button type="button" class="portal-tour__back" :class="{ 'is-hidden': current === 0 }" aria-label="Back" @click="go(current - 1)"><i class="fas fa-arrow-left"></i></button>
                                <button type="button" class="portal-tour__next" :class="{ 'is-finish': isLast }" @click="next">
                                    <span>{{ isLast ? 'Got it' : (current === 0 ? "Let's go" : 'Next') }}</span> <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop show"></div>
        </template>
    </Teleport>
</template>

<script setup>
/**
 * The top bar's "!" — the current screen's help guide as a short slide tour (the portal's
 * layouts/_help: Welcome → What you can do → How it works → Good to know, empty ones skipped).
 * Content: GET /api/crm/help/{topic} (route meta `help`). The very first guide an agent / company
 * sees opens by itself ~2.5s in, once ever (`help_auto_open` on /auth/me → POST /help/seen).
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import http from '../api/http';
import { useSession } from '../composables/useSession';

const props = defineProps({ topic: { type: String, required: true } });

const { user } = useSession();
const open = ref(false);
const guide = ref(null);
const current = ref(0);
const isNew = ref(false);
const dialog = ref(null);
let autoTimer = null;

const slideMeta = {
    features: { icon: 'fa-wand-magic-sparkles', title: 'What you can do here' },
    steps: { icon: 'fa-route', title: 'How it works' },
    tips: { icon: 'fa-lightbulb', title: 'Good to know' },
};

const slides = computed(() => (guide.value ? [
    'welcome',
    guide.value.features?.length ? 'features' : null,
    guide.value.steps?.length ? 'steps' : null,
    guide.value.tips?.length ? 'tips' : null,
].filter(Boolean) : []));
const isLast = computed(() => current.value === slides.value.length - 1);

function load() {
    if (guide.value) return Promise.resolve();
    return http.get(`/help/${props.topic}`).then((res) => {
        guide.value = res.data;
    });
}

function go(index) {
    current.value = Math.max(0, Math.min(slides.value.length - 1, index));
}

function next() {
    if (isLast.value) close();
    else go(current.value + 1);
}

function show() {
    isNew.value = false;
    load().then(() => {
        current.value = 0;
        open.value = true;
        document.body.classList.add('modal-open');
        nextTick(() => dialog.value?.focus());
    });
}

function close() {
    open.value = false;
    document.body.classList.remove('modal-open');
}

watch(() => props.topic, () => {
    guide.value = null;
});

onMounted(() => {
    // The account's first time in the CRM: open the tour after a moment, once, ever.
    if (!user.value?.help_auto_open) return;
    user.value.help_auto_open = false;
    isNew.value = true;
    autoTimer = setTimeout(() => {
        if (document.querySelector('.modal.d-block')) return; // don't stack on another open dialog
        show();
        http.post('/help/seen').catch(() => {});
    }, 2500);
});

onBeforeUnmount(() => {
    clearTimeout(autoTimer);
    if (open.value) close();
});
</script>
