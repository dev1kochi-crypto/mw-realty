<template>
    <canvas ref="canvas"></canvas>
</template>

<script setup>
/**
 * A Chart.js chart (window.Chart, loaded by crm/app.blade.php). `config(canvas)` returns the
 * chart config — it gets the canvas so it can build gradients; redrawn when `config` changes.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({ config: { type: Function, required: true } });

const canvas = ref(null);
let chart = null;

function waitForChart() {
    return new Promise((resolve) => {
        const check = () => (window.Chart ? resolve(window.Chart) : setTimeout(check, 50));
        check();
    });
}

async function draw() {
    const Chart = await waitForChart();
    if (!canvas.value) return;
    chart?.destroy();
    Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
    Chart.defaults.color = '#7a7f9a';
    chart = new Chart(canvas.value, props.config(canvas.value));
}

onMounted(draw);
watch(() => props.config, draw);
onBeforeUnmount(() => chart?.destroy());
</script>
