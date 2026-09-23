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
    <router-view />
    <SiteFooter />
    <ChatWidget />
</template>
