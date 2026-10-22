<template>
    <div class="portal-card p-3">
        <div class="adp-preview__bar">
            <span class="adp-preview__title"><i class="fas fa-eye me-1"></i>Preview</span>
            <div class="adp-device">
                <button type="button" :class="{ 'is-active': device === 'desktop' }" @click="device = 'desktop'"><i class="fas fa-desktop me-1"></i>Desktop</button>
                <button type="button" :class="{ 'is-active': device === 'phone' }" @click="device = 'phone'"><i class="fas fa-mobile-screen me-1"></i>Phone</button>
            </div>
        </div>
        <div class="adp-site" :class="{ 'is-phone': device === 'phone' }">
            <div class="adp-site__lines"><span></span><span></span></div>
            <div class="adp-banner my-2" :class="{ 'has-text': hasText }">
                <img v-if="image" :key="image" :src="image" alt="">
                <div v-else class="adp-banner__empty"><i class="far fa-image"></i>Your banner shows here</div>
                <span class="adp-banner__label">{{ label }}</span>
                <span v-if="image && hasText" :key="`${text.title}|${device}`" class="adp-banner__text">
                    <span v-if="text.eyebrow" class="adp-banner__eyebrow">{{ text.eyebrow }}</span>
                    <span v-if="text.title" class="adp-banner__title">{{ text.title }}</span>
                    <span v-if="text.text" class="adp-banner__body">{{ text.text }}</span>
                </span>
                <span class="adp-banner__dots"><span></span><span class="is-active"></span><span></span></span>
            </div>
            <div class="adp-site__lines"><span></span><span></span></div>
        </div>
        <div class="small portal-muted mt-2">On the website the ad takes turns with the other ads on the page, every few seconds.</div>
    </div>
</template>

<script setup>
/** A mock of the website's ad slider with the ad being edited — desktop or phone (phone image if set). */
import { computed, ref } from 'vue';

const props = defineProps({
    imageUrl: { type: String, default: null },
    mobileUrl: { type: String, default: null },
    text: { type: Object, default: () => ({}) }, // { eyebrow, title, text } in the language being edited
    label: { type: String, default: 'Sponsored' },
});

const device = ref('desktop');
const image = computed(() => (device.value === 'phone' && props.mobileUrl) || props.imageUrl);
const hasText = computed(() => !!(props.text.eyebrow || props.text.title || props.text.text));
</script>
