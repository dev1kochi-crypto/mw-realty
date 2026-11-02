<template>
    <div class="lr-slider">
        <a v-if="photos.length" :href="photos[index]" target="_blank" rel="noopener" class="lr-slider__frame" title="Open full size">
            <img :src="photos[index]" alt="" loading="lazy">
        </a>
        <div v-else class="lr-slider__frame lr-slider__empty"><i class="far fa-image"></i>No photos</div>
        <template v-if="photos.length > 1">
            <button type="button" class="lr-slider__nav lr-slider__nav--prev" aria-label="Previous photo" @click="go(-1)"><i class="fas fa-chevron-left"></i></button>
            <button type="button" class="lr-slider__nav lr-slider__nav--next" aria-label="Next photo" @click="go(1)"><i class="fas fa-chevron-right"></i></button>
            <span class="lr-slider__count">{{ index + 1 }} / {{ photos.length }}</span>
        </template>
    </div>
</template>

<script setup>
/** One listing's photos on the Listing Review comparison: one at a time, arrows + counter, click = full size. */
import { ref, watch } from 'vue';

const props = defineProps({
    photos: { type: Array, default: () => [] },
});

const index = ref(0);
watch(() => props.photos, () => { index.value = 0; });

function go(step) {
    index.value = (index.value + step + props.photos.length) % props.photos.length;
}
</script>
