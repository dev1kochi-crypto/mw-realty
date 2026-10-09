<template>
    <span>{{ shown }}</span>
</template>

<script setup>
/** A number that counts up on load (the dashboard's .dz-count) — skipped for reduced motion. */
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    to: { type: Number, default: 0 },
    suffix: { type: String, default: '' },
});

const format = (value) => `${Math.round(value).toLocaleString('en')}${props.suffix}`;
const shown = ref(format(props.to));

function run() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        shown.value = format(props.to);
        return;
    }
    const start = performance.now();
    const step = (t) => {
        const p = Math.min(1, (t - start) / 900);
        shown.value = format(props.to * (1 - (1 - p) ** 3));
        if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}

onMounted(run);
watch(() => props.to, run);
</script>
