<template>
    <button type="button" class="mw-compare-toggle" :class="{ 'is-on': on, 'mw-compare-toggle--lg': large }" :aria-pressed="on ? 'true' : 'false'" @click.stop.prevent="toggle(property)">
        <span class="mw-compare-toggle__box" aria-hidden="true">
            <svg v-if="on" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
        </span>
        {{ on ? t('compare.added', 'Comparing') : t('compare.add', 'Compare') }}
    </button>
</template>

<script setup>
import { computed } from 'vue';
import { useCompare } from '../../composables/useCompare';
import { useStaticText } from '../../composables/useStaticText';

/** "Compare" checkbox-button on a property card / the property page — adds it to the compare tray. */
const props = defineProps({
    property: { type: Object, required: true }, // { slug, name, image | images }
    large: { type: Boolean, default: false },
});

const { t } = useStaticText();
const { has, toggle } = useCompare();
const on = computed(() => has(props.property.slug));
</script>
