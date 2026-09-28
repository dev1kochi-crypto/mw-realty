<script setup>
import { watch } from 'vue';
import { useRoute } from 'vue-router';
import SiteHeader from './layouts/SiteHeader.vue';
import SiteFooter from './layouts/SiteFooter.vue';
import ChatWidget from './components/ChatWidget.vue';

const route = useRoute();

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
    <SiteFooter />
    <ChatWidget />
</template>
