<template>
    <CrmModal :open="!!ad" :title="ad ? `${live ? 'Take down' : 'Reject'} “${ad.name}”` : ''" @close="$emit('close')">
        <form id="rejectAdForm" @submit.prevent="submit">
            <p class="small portal-muted mb-2">
                <template v-if="live">The ad is live — it comes off the website right away. The time it already ran isn't refunded.</template>
                <template v-else>{{ ad?.client?.name }} gets {{ ad?.paid_with === 'credits' ? 'their credits' : 'their plan ad' }} back.</template>
                The reason is shown to them, so they can book again with a better ad.
            </p>
            <label class="form-label fw-semibold" for="rejectNote">Reason <span class="text-danger">*</span></label>
            <textarea id="rejectNote" v-model="note" class="form-control" :class="{ 'is-invalid': error }" rows="3" maxlength="500" required
                      placeholder="e.g. The banner text is too small to read, or the link doesn't open."></textarea>
            <div v-if="error" class="invalid-feedback">{{ error }}</div>
            <div class="d-flex flex-wrap gap-1 mt-2">
                <button v-for="r in quick" :key="r" type="button" class="adm-pill adm-pill--sm" @click="note = r">{{ r }}</button>
            </div>
        </form>
        <template #footer>
            <button type="button" class="btn portal-btn-ghost" @click="$emit('close')">Cancel</button>
            <button type="submit" form="rejectAdForm" class="btn btn-danger" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>{{ live ? 'Take down' : 'Reject ad' }}
            </button>
        </template>
    </CrmModal>
</template>

<script setup>
/** Super Admin: reject (or take down a live) client ad with a reason the client sees — they're refunded if it hadn't run. */
import { computed, ref, watch } from 'vue';
import http, { errorMessage } from '../../api/http';
import CrmModal from '../../components/CrmModal.vue';
import { useSession } from '../../composables/useSession';
import { useToast } from '../../composables/useToast';

const props = defineProps({ ad: { type: Object, default: null } });
const emit = defineEmits(['close', 'done']);

const { success } = useToast();
const { refreshNavigation } = useSession();
const note = ref('');
const error = ref('');
const saving = ref(false);
const live = computed(() => props.ad?.state === 'live');
const quick = [
    'The banner text is too small to read.',
    'The link doesn\'t open or goes to the wrong page.',
    'The image is blurry or cut off.',
    'The content doesn\'t follow MW Realty\'s advertising rules.',
];

watch(() => props.ad, () => { note.value = ''; error.value = ''; });

function submit() {
    saving.value = true;
    error.value = '';
    http.post(`/ads/manage/${props.ad.id}/reject`, { note: note.value })
        .then((res) => { success(res.data.message); refreshNavigation(); emit('done'); })
        .catch((e) => { error.value = errorMessage(e); })
        .finally(() => { saving.value = false; });
}
</script>
