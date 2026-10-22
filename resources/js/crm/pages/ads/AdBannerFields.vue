<template>
    <div class="mb-3">
        <label class="form-label fw-semibold" for="adName">Ad name <span class="text-danger">*</span></label>
        <input id="adName" v-model="form.name" type="text" maxlength="120" class="form-control" :class="{ 'is-invalid': errors.name }" placeholder="e.g. Marina Heights launch" required>
        <div class="form-text">{{ nameHint }}</div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-7">
            <label class="form-label fw-semibold">Banner image <span v-if="imageRequired" class="text-danger">*</span></label>
            <label class="adp-drop" :class="{ 'has-file': imageUrl }">
                <input type="file" accept="image/*" @change="$emit('pick', $event, 'image')">
                <template v-if="imageUrl"><img :src="imageUrl" alt="" class="adp-drop__img"><span class="adp-drop__name">{{ form.image?.name ?? 'Current banner' }} · click to change</span></template>
                <template v-else><i class="fas fa-image"></i><strong>Choose a wide image</strong><span class="small">About 16 : 3, e.g. 2400 × 450 px · max {{ sizeLimits.image }}</span></template>
            </label>
            <div v-if="errors.image" class="invalid-feedback d-block">{{ errors.image[0] }}</div>
        </div>
        <div class="col-md-5">
            <label class="form-label fw-semibold">Phone image <span class="portal-muted fw-normal">(optional)</span></label>
            <label class="adp-drop" :class="{ 'has-file': mobileUrl }">
                <input type="file" accept="image/*" @change="$emit('pick', $event, 'mobile_image')">
                <template v-if="mobileUrl"><img :src="mobileUrl" alt="" class="adp-drop__img"><span class="adp-drop__name">{{ form.mobile_image?.name ?? 'Current phone image' }} · click to change</span></template>
                <template v-else><i class="fas fa-mobile-screen"></i><strong>Taller image for phones</strong><span class="small">2 : 1, e.g. 800 × 400 px · max {{ sizeLimits.mobile }}</span></template>
            </label>
            <button v-if="mobileUrl" type="button" class="btn btn-link btn-sm px-0" @click="$emit('remove-mobile')">Remove phone image</button>
            <div v-if="errors.mobile_image" class="invalid-feedback d-block">{{ errors.mobile_image[0] }}</div>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-semibold" for="adAlt">Image description</label>
        <input id="adAlt" v-model="form.image_alt" type="text" maxlength="255" class="form-control" placeholder="What the image shows — read out by screen readers">
    </div>

    <div class="d-flex justify-content-between align-items-baseline">
        <label class="form-label fw-semibold mb-2">Text over the image <span class="portal-muted fw-normal">(optional)</span></label>
        <small class="portal-muted">Leave empty if your image already has its text.</small>
    </div>
    <ul v-if="languages.length > 1" class="nav portal-lang-tabs mb-3" role="tablist">
        <li v-for="lang in languages" :key="lang.code" class="nav-item" role="presentation">
            <button class="nav-link" :class="{ active: activeLang === lang.code }" type="button" role="tab" @click="$emit('update:activeLang', lang.code)">
                <i class="fas fa-language me-1 opacity-75"></i>{{ lang.name }}
            </button>
        </li>
    </ul>
    <template v-for="lang in languages" :key="lang.code">
        <div v-show="activeLang === lang.code" class="row g-3">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Small heading</label>
                <input v-model="form.translations[lang.code].eyebrow" type="text" :maxlength="limits.eyebrow" class="form-control" placeholder="e.g. New launch">
                <div class="adp-count" :class="countClass(lang.code, 'eyebrow')">{{ used(lang.code, 'eyebrow') }} / {{ limits.eyebrow }}</div>
            </div>
            <div class="col-md-8">
                <label class="form-label small fw-semibold">Title</label>
                <input v-model="form.translations[lang.code].title" type="text" :maxlength="limits.title" class="form-control" placeholder="e.g. Marina Heights — sea-view living">
                <div class="adp-count" :class="countClass(lang.code, 'title')">{{ used(lang.code, 'title') }} / {{ limits.title }} · up to 2 lines on the banner</div>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Short text</label>
                <input v-model="form.translations[lang.code].text" type="text" :maxlength="limits.text" class="form-control" placeholder="e.g. 1–3 bed apartments from AED 1.2M · 60/40 payment plan">
                <div class="adp-count" :class="countClass(lang.code, 'text')">{{ used(lang.code, 'text') }} / {{ limits.text }}</div>
            </div>
        </div>
    </template>
</template>

<script setup>
/**
 * The banner part of an ad form (client booking and MW Realty's own ads): name, wide + phone image,
 * image description, and optional text over the image per language. Edits `form` in place; images
 * go through useAdImages in the page (it also feeds the preview).
 */
import { computed } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    languages: { type: Array, required: true },
    images: { type: Object, default: () => ({}) },
    imageUrl: { type: String, default: null },
    mobileUrl: { type: String, default: null },
    imageRequired: { type: Boolean, default: true },
    activeLang: { type: String, default: '' },
    nameHint: { type: String, default: 'Only you see this — it names the ad in your list and reports.' },
    // Ad::TEXT_LIMITS — longest text that fits the website banner.
    textLimits: { type: Object, default: () => ({ eyebrow: 30, title: 60, text: 120 }) },
});
defineEmits(['pick', 'remove-mobile', 'update:activeLang']);

const limits = computed(() => props.textLimits);
const used = (code, field) => (props.form.translations[code]?.[field] ?? '').length;
const countClass = (code, field) => ({ 'is-near': used(code, field) >= limits.value[field] * 0.85, 'is-full': used(code, field) >= limits.value[field] });

const sizeLimits = computed(() => {
    const mb = (c) => (c?.max_size ? `${Math.round(c.max_size / 1024)} MB` : '');
    return { image: mb(props.images?.image), mobile: mb(props.images?.mobile_image) };
});
</script>
