<template>
    <AdminAdForm v-if="user?.is_super_admin" :id="id" />
    <AdForm v-else :id="id" />
</template>

<script setup>
/** /crm/ads/create and /ads/{id}/edit — Super Admin: an MW Realty ad (AdminAdForm); clients: book / change their ad (AdForm). */
import { useSession } from '../../composables/useSession';
import AdForm from './AdForm.vue';
import AdminAdForm from './AdminAdForm.vue';

defineProps({ id: { type: Number, default: null } });

const { user } = useSession();
</script>
