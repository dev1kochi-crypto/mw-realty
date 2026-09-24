<script setup>
import { computed } from 'vue';
import { useStaticText } from '../composables/useStaticText';

const { t } = useStaticText();

const props = defineProps({
    title: { type: String, default: null },
    message: { type: String, default: null },
});

const displayTitle = computed(() => props.title || t('empty_state.default_title'));
const displayMessage = computed(() => props.message || t('empty_state.default_message'));
</script>

<template>
    <div class="mw-empty-state">
        <span class="mw-empty-state__icon">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="10.5" cy="10.5" r="6.5" stroke="currentColor" stroke-width="1.6"/>
                <path d="M20 20l-4.6-4.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                <path d="M8 8l5 5M13 8l-5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
        </span>
        <h3 class="mw-empty-state__title">{{ displayTitle }}</h3>
        <p class="mw-empty-state__message">{{ displayMessage }}</p>
        <slot />
    </div>
</template>
