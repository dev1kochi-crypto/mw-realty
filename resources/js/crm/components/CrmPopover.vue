<template>
    <button ref="toggle" type="button" :class="buttonClass" :title="title" @focus="show" @blur="open = false" @click="show">
        <slot />
    </button>
    <Teleport to="body">
        <div v-if="open" class="popover bs-popover-bottom show" role="tooltip" :style="style">
            <div class="popover-arrow" style="left: 50%; transform: translateX(-50%);"></div>
            <h3 v-if="title" class="popover-header">{{ title }}</h3>
            <div class="popover-body">{{ content }}</div>
        </div>
    </Teleport>
</template>

<script setup>
/** A Bootstrap popover driven by Vue (focus trigger, like data-bs-trigger="focus") — shown under its button. */
import { nextTick, ref } from 'vue';

defineProps({
    title: { type: String, default: '' },
    content: { type: String, default: '' },
    buttonClass: { type: [String, Array, Object], default: '' },
});

const toggle = ref(null);
const open = ref(false);
const style = ref({});

function show() {
    open.value = true;
    nextTick(() => {
        const rect = toggle.value.getBoundingClientRect();
        const width = Math.min(276, window.innerWidth - 16);
        style.value = {
            position: 'fixed',
            top: `${rect.bottom + 8}px`,
            left: `${Math.max(8, Math.min(rect.left + rect.width / 2 - width / 2, window.innerWidth - width - 8))}px`,
            maxWidth: `${width}px`,
            width: 'max-content',
        };
    });
}
</script>
