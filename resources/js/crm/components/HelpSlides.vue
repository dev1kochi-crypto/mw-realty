<template>
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
                    <h5 :id="titleId" class="portal-tour__title">Welcome to {{ guide.title }}</h5>
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
        <button type="button" class="portal-tour__skip" :class="{ 'is-hidden': isLast || !skippable }" @click="emit('close')">Skip</button>
        <div class="portal-tour__dots" role="tablist" aria-label="Guide steps">
            <button v-for="(slide, index) in slides" :key="slide" type="button" class="portal-tour__dot"
                    :class="{ 'is-active': index === current, 'is-done': index < current }" :aria-label="`Go to step ${index + 1}`" @click="go(index)"></button>
        </div>
        <div class="portal-tour__nav">
            <button type="button" class="portal-tour__back" :class="{ 'is-hidden': current === 0 }" aria-label="Back" @click="go(current - 1)"><i class="fas fa-arrow-left"></i></button>
            <button type="button" class="portal-tour__next" :class="{ 'is-finish': isLast }" @click="next">
                <span>{{ isLast ? finishLabel : (current === 0 ? "Let's go" : 'Next') }}</span> <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
/**
 * A help guide's slides — Welcome → What you can do → How it works → Good to know (empty ones
 * skipped) — plus the dots / Back / Next footer. HelpTour wraps it in the "!" popup; the Help Desk
 * page shows it inline. Emits `close` (Skip) and `finish` (the last slide's button).
 */
import { computed, ref, watch } from 'vue';

const props = defineProps({
    guide: { type: Object, required: true },
    finishLabel: { type: String, default: 'Got it' },
    skippable: { type: Boolean, default: true },
    titleId: { type: String, default: 'crmHelpTitle' },
});
const emit = defineEmits(['close', 'finish']);

const current = ref(0);

const slideMeta = {
    features: { icon: 'fa-wand-magic-sparkles', title: 'What you can do here' },
    steps: { icon: 'fa-route', title: 'How it works' },
    tips: { icon: 'fa-lightbulb', title: 'Good to know' },
};

const slides = computed(() => [
    'welcome',
    props.guide.features?.length ? 'features' : null,
    props.guide.steps?.length ? 'steps' : null,
    props.guide.tips?.length ? 'tips' : null,
].filter(Boolean));
const isLast = computed(() => current.value === slides.value.length - 1);

function go(index) {
    current.value = Math.max(0, Math.min(slides.value.length - 1, index));
}

function next() {
    if (isLast.value) emit('finish');
    else go(current.value + 1);
}

// Another guide shown in the same place starts from its Welcome slide.
watch(() => props.guide, () => { current.value = 0; });

defineExpose({ go, next, back: () => go(current.value - 1) });
</script>
