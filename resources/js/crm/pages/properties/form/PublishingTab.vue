<template>
    <div class="property-tab-pane-head">
        <div class="property-tab-pane-title">Publishing</div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Published At</label>
            <input v-model="form.published_at" type="datetime-local" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Display Order</label>
            <input v-model="form.order_index" type="number" class="form-control" min="0">
        </div>
    </div>
    <hr class="my-4">
    <div class="property-tab-pane-title mb-1" style="font-size: 0.95rem;">SEO Metadata</div>
    <p class="text-muted small mb-3">Used on the property detail page. If empty, the page falls back to the property title and current URL.</p>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Meta Title</label>
            <input v-model="form.metadata.meta_title" type="text" class="form-control" placeholder="Page title for search engines">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Canonical URL</label>
            <input v-model="form.metadata.canonical_url" type="url" class="form-control" placeholder="https://example.com/property-details/slug">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Meta Description</label>
            <textarea v-model="form.metadata.meta_description" class="form-control" rows="2" placeholder="Page description"></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Meta Keywords</label>
            <input v-model="form.metadata.meta_keywords" type="text" class="form-control" placeholder="keyword1, keyword2">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">OG Title</label>
            <input v-model="form.metadata.og_title" type="text" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">OG Description</label>
            <textarea v-model="form.metadata.og_description" class="form-control" rows="2"></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">OG Image</label>
            <input type="file" class="form-control" accept="image/*" @change="f.state.files.metadata_og_image = $event.target.files[0] || null">
            <img v-if="f.state.property?.files.og_image" :src="f.state.property.files.og_image" class="mt-2 rounded" style="height:50px;">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Other Meta Tags</label>
            <textarea v-model="form.metadata.other_meta_tags" class="form-control" rows="2" placeholder='&lt;meta name="robots" content="index,follow"&gt;'></textarea>
        </div>
    </div>
</template>

<script setup>
/** Property form › Publishing: publish date, display order and SEO metadata. */
import { inject } from 'vue';

const f = inject('listingForm');
const form = f.form;
</script>
