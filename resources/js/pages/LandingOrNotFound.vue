<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import BlogDetails from './BlogDetails.vue';
import NotFound from './NotFound.vue';
import { useBlogPost } from '../composables/useBlogPost';
import { useLanguages } from '../composables/useLanguages';

// Catch-all route: a single-segment URL (/{slug}) may be a "Blog design" landing page — rendered
// with the blog detail layout. Anything else (or a slug that isn't one) is the normal 404 page.
const route = useRoute();
const { fetchBlogPost } = useBlogPost();
const { selectedLanguage } = useLanguages();

const slug = computed(() => {
    const segments = route.path.split('/').filter(Boolean);
    return segments.length === 1 ? decodeURIComponent(segments[0]) : null;
});
const state = ref('checking'); // checking | landing | missing

watch(slug, async (value) => {
    if (!value) {
        state.value = 'missing';
        return;
    }
    state.value = 'checking';
    const data = await fetchBlogPost(value, selectedLanguage.value?.code, 'landing');
    if (slug.value === value) state.value = data ? 'landing' : 'missing';
}, { immediate: true });

// The route's meta says "not-found-page" — a landing page reads as a blog detail page instead.
watch(state, (value) => {
    if (value === 'landing') document.body.className = 'agents-page blog-details-page';
});
</script>

<template>
    <BlogDetails v-if="state === 'landing'" :landing-slug="slug" />
    <NotFound v-else-if="state === 'missing'" />
</template>
