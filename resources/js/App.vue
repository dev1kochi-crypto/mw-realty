<script setup>
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import SiteHeader from './layouts/SiteHeader.vue';
import SiteFooter from './layouts/SiteFooter.vue';
import ChatWidget from './components/ChatWidget.vue';
import CompareTray from './components/compare/CompareTray.vue';

const route = useRoute();

// Pages are lazy chunks: a footer drawn before the page arrives sits mid-screen and then jumps
// down (the home page's whole layout shift) — show it once the first page has loaded.
const pageReady = ref(false);
useRouter().isReady().then(() => { pageReady.value = true; });

watch(
    () => route.meta,
    (meta) => {
        document.title = meta.title ?? 'MW Realty';
        document.body.className = meta.bodyClass ?? '';
    },
    { immediate: true },
);
</script>

<template>
    <SiteHeader />
    <!-- Keyed by route name: two routes sharing a component (/properties and /premium-properties
         both use PropertiesDubai.vue) get a fresh instance, while a query-only change (filters,
         paging) on the same route keeps the page as it is. -->
    <router-view v-slot="{ Component, route: current }">
        <component :is="Component" :key="current.name" />
    </router-view>
    <SiteFooter v-if="pageReady" />
    <CompareTray />
    <ChatWidget />
</template>
