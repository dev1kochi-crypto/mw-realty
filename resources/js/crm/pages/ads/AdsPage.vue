<template>
    <AdminAdsIndex v-if="user?.is_super_admin" />
    <AdsIndex v-else />
</template>

<script setup>
/** /crm/ads — Super Admin manages every ad (AdminAdsIndex); an agency / agent sees and books their own (AdsIndex). */
import { useSession } from '../../composables/useSession';
import AdminAdsIndex from './AdminAdsIndex.vue';
import AdsIndex from './AdsIndex.vue';

const { user } = useSession();
</script>
