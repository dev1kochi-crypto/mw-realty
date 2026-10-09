<template>
    <div class="inv-page">
        <div class="inv-toolbar">
            <RouterLink :to="{ name: 'plans.payments' }" class="btn btn-sm btn-portal-light"><i class="fas fa-arrow-left me-1"></i>Back to Payment History</RouterLink>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-portal-light" :disabled="!invoice" @click="print"><i class="fas fa-print me-1"></i>Print</button>
                <button type="button" class="btn btn-sm btn-portal-primary" @click="pdf"><i class="fas fa-file-pdf me-1"></i>Download PDF</button>
            </div>
        </div>
        <div v-if="!invoice" class="text-center py-5">
            <span v-if="!loadError" class="spinner-border text-primary"></span>
            <div v-else class="text-muted">{{ loadError }}</div>
        </div>
        <!-- The shared invoice document (invoices/_document), rendered by the server. -->
        <div v-else class="inv-sheet" v-html="invoice.html"></div>
    </div>
</template>

<script setup>
/** One invoice (resources/views/portal/plans/invoice → invoices/_web) — print or download the PDF. */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import http, { errorMessage } from '../../api/http';
import { useToast } from '../../composables/useToast';
import { download } from '../../utils/download';

const props = defineProps({ id: { type: Number, required: true } });

const { error: toastError } = useToast();
const invoice = ref(null);
const loadError = ref('');

function print() {
    window.print();
}

// The print stylesheet (styles/plans.css) prints only the invoice sheet — on this page, also via Ctrl+P.
onMounted(() => document.body.classList.add('is-printing-invoice'));
onBeforeUnmount(() => document.body.classList.remove('is-printing-invoice'));

function pdf() {
    download('get', `/plans/payments/${props.id}/invoice.pdf`).catch(() => toastError('Could not download the invoice.'));
}

watch(() => props.id, (id) => {
    invoice.value = null;
    loadError.value = '';
    http.get(`/plans/payments/${id}/invoice`)
        .then((res) => {
            invoice.value = res.data;
            document.title = `Invoice ${res.data.number} - Partner Portal`;
        })
        .catch((e) => { loadError.value = errorMessage(e); });
}, { immediate: true });
</script>
