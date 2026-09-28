<script setup>
// Small up / down / flat indicator for a Market Insights headline figure.
defineProps({
    trend: { type: String, default: 'flat' },
});
</script>

<template>
    <span class="mw-insight-trend" :class="`mw-insight-trend--${trend}`" aria-hidden="true">
        <svg v-if="trend === 'up'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
        <svg v-else-if="trend === 'down'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7l10 10M17 9v8H9"/></svg>
        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M5 12h14"/></svg>
    </span>
</template>
