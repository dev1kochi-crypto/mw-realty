<template>
    <CrmModal :open="!!item" size="lg" bare @close="close">
        <template #header>
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-handshake me-2"></i>Mark as sold / rented</h5>
                <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
            </div>
        </template>

        <form v-if="item" novalidate @submit.prevent="submit">
            <div class="modal-body">
                <div class="sm-property mb-3">
                    <img :src="item.thumb || 'https://placehold.co/64x48?text=%20'" alt="">
                    <div class="min-w-0">
                        <div class="fw-bold text-truncate">{{ item.title || 'Property' }}</div>
                        <div class="small portal-muted">{{ item.ref ? `Ref: ${item.ref}` : '' }}</div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Outcome</label>
                        <div class="sm-seg">
                            <input id="soldTypeSold" v-model="form.type" type="radio" value="sold"><label for="soldTypeSold"><i class="fas fa-key me-1"></i>Sold</label>
                            <input id="soldTypeRented" v-model="form.type" type="radio" value="rented"><label for="soldTypeRented"><i class="fas fa-file-signature me-1"></i>Rented</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="soldPrice"><span>{{ rented ? 'Rent (agreed)' : 'Sold price' }}</span> <span class="portal-muted small">({{ item.currency || 'AED' }})</span></label>
                        <input id="soldPrice" v-model="form.price" type="number" class="form-control" min="0" step="any" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="soldDate"><span>{{ rented ? 'Rented on' : 'Sale date' }}</span></label>
                        <input id="soldDate" v-model="form.sold_at" type="date" class="form-control" :max="todayIso" required>
                    </div>
                    <div v-if="rented" class="col-md-6">
                        <label class="form-label fw-semibold" for="soldRentedUntil">Lease ends <span class="portal-muted small">(optional)</span></label>
                        <input id="soldRentedUntil" v-model="form.rented_until" type="date" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="soldCommission">Commission <span class="portal-muted small">(optional)</span></label>
                        <input id="soldCommission" v-model="form.commission" type="number" class="form-control" min="0" step="any">
                    </div>
                </div>

                <hr class="my-3">

                <label class="form-label fw-semibold"><span>{{ rented ? 'Tenant' : 'Buyer' }}</span></label>
                <div class="sm-seg mb-3">
                    <input id="soldBuyerExisting" v-model="form.buyer_mode" type="radio" value="existing"><label for="soldBuyerExisting"><i class="fas fa-address-book me-1"></i>Existing lead</label>
                    <input id="soldBuyerNew" v-model="form.buyer_mode" type="radio" value="new"><label for="soldBuyerNew"><i class="fas fa-user-plus me-1"></i>New buyer</label>
                </div>

                <div v-if="form.buyer_mode === 'existing'">
                    <div ref="combo" class="sm-combo">
                        <input ref="searchInput" v-model="search" type="search" class="form-control" :class="{ 'is-picked': form.lead_id }" placeholder="Search leads by name, email or phone"
                               autocomplete="off" role="combobox" :aria-expanded="listOpen" aria-controls="soldLeadList"
                               @focus="onFocus" @click="listOpen = true" @input="onInput" @keydown.esc.stop="listOpen = false" @keydown.tab="listOpen = false">
                        <div v-show="listOpen" id="soldLeadList" class="sm-leads" role="listbox" @mousedown.prevent @scroll="onScroll">
                            <button v-for="lead in leads" :key="lead.id" type="button" class="sm-lead" :class="{ 'is-picked': form.lead_id === lead.id }" @click="pick(lead)">
                                <span><strong>{{ lead.name }}</strong><br><small>{{ lead.contact || '—' }}</small></span>
                                <span v-if="lead.this_property" class="badge bg-success-subtle text-success-emphasis">Enquired on this listing</span>
                            </button>
                            <div v-if="loading" class="sm-leads-status">Loading…</div>
                            <div v-else-if="loadFailed" class="sm-leads-status">Could not load leads.</div>
                            <div v-else-if="!leads.length" class="sm-leads-status">No leads found. Use “New buyer” to add them.</div>
                        </div>
                    </div>
                    <div class="form-text mt-2">Click to pick a lead — ones that enquired about this listing are shown first.</div>
                </div>

                <div v-else>
                    <div class="row g-2">
                        <div class="col-md-12"><input v-model="form.buyer.name" type="text" class="form-control" placeholder="Full name *" maxlength="255"></div>
                        <div class="col-md-6"><input v-model="form.buyer.email" type="email" class="form-control" placeholder="Email" maxlength="255"></div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <input v-model="form.buyer.phone_country_code" type="text" class="form-control" style="max-width: 5.5rem;" maxlength="8" aria-label="Country code">
                                <input v-model="form.buyer.phone" type="tel" class="form-control" placeholder="Phone" maxlength="30">
                            </div>
                        </div>
                    </div>
                    <div class="form-text">Saved as a new lead in your CRM, linked to this listing.</div>
                </div>

                <label class="form-label fw-semibold mt-3" for="soldNotes">Notes <span class="portal-muted small">(optional)</span></label>
                <textarea id="soldNotes" v-model="form.notes" class="form-control" rows="2" maxlength="2000" placeholder="Payment terms, handover, anything worth keeping"></textarea>

                <hr class="my-3">

                <div class="fw-semibold mb-1">Proof of the deal</div>
                <div class="form-text mt-0 mb-2">Official documents for this transaction. Kept private — only your account and MW Realty admins can open them.</div>
                <div class="row g-3">
                    <div v-for="doc in documents" :key="doc.key" class="col-md-6">
                        <label class="form-label small fw-semibold mb-1" :for="doc.id">{{ doc.label }} <span v-if="doc.hint" class="portal-muted fw-normal">{{ doc.hint }}</span></label>
                        <label class="sm-upload" :class="{ 'has-file': files[doc.key] }">
                            <i class="far fa-file-lines portal-muted"></i>
                            <span class="sm-upload-name">{{ files[doc.key]?.name || 'Click to upload' }}</span>
                            <span class="sm-upload-btn">{{ files[doc.key] ? 'Change' : 'Upload' }}</span>
                            <input :id="doc.id" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" @change="files[doc.key] = $event.target.files[0] || null">
                        </label>
                        <div class="form-text">PDF, JPG or PNG · max 10 MB</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold mb-1" for="soldDocsPassword">Document password <span class="portal-muted fw-normal">(optional)</span></label>
                        <input id="soldDocsPassword" v-model="form.documents_password" type="text" class="form-control" maxlength="255" autocomplete="off" placeholder="Add a password if your files are password protected">
                    </div>
                </div>

                <div class="alert alert-info small mt-3 mb-0"><i class="fas fa-info-circle me-1"></i>The listing is taken off the website and moved to <strong>Sold Listings</strong>. The lead is moved to your won stage. You can undo this later.</div>
                <div v-if="error" class="alert alert-danger small mt-3 mb-0">{{ error }}</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-portal-light btn-sm" @click="close">Cancel</button>
                <button type="submit" class="btn btn-portal-primary btn-sm" :disabled="saving"><i class="fas fa-check me-1"></i>{{ rented ? 'Mark as rented' : 'Mark as sold' }}</button>
            </div>
        </form>
    </CrmModal>
</template>

<script setup>
/**
 * "Mark as sold / rented" popup for the Properties / Commercial cards (resources/views/portal/properties/_sold_modal).
 * The buyer is an existing lead (paged server-side picker, 20 a request, loads more on scroll) or a
 * new buyer saved as a lead. On success the listing has moved to Sold Listings (`redirect`).
 */
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import http from '../../../api/http';
import CrmModal from '../../../components/CrmModal.vue';

const props = defineProps({
    item: { type: Object, default: null }, // { id, title, ref, thumb, price, currency, type }
});
const emit = defineEmits(['close', 'saved']);

const todayIso = new Date().toLocaleDateString('en-CA');
const blankForm = () => ({
    type: 'sold', price: '', sold_at: todayIso, rented_until: '', commission: '', notes: '', buyer_mode: 'existing', lead_id: null,
    buyer: { name: '', email: '', phone_country_code: '+971', phone: '' }, documents_password: '',
});
const form = reactive(blankForm());
const files = reactive({ ownership_document: null, contract_document: null });
const rented = computed(() => form.type === 'rented');
const documents = computed(() => [
    { key: 'ownership_document', id: 'soldOwnershipDoc', label: 'Property ownership document', hint: '(title deed)' },
    { key: 'contract_document', id: 'soldContractDoc', label: rented.value ? 'Ejari (official DLD document)' : 'Sale contract (Form F / MOU)', hint: '' },
]);

const combo = ref(null);
const searchInput = ref(null);
const search = ref('');
const leads = ref([]);
const listOpen = ref(false);
const loading = ref(false);
const loadFailed = ref(false);
const error = ref('');
const saving = ref(false);
let page = 1;
let more = false;
let term = '';
let timer = null;
let requestNo = 0;

function loadLeads(reset) {
    if (loading.value && !reset) return;
    if (reset) {
        page = 1;
        leads.value = [];
    }
    loading.value = true;
    loadFailed.value = false;
    const mine = ++requestNo;
    http.get(`/properties/${props.item.id}/sale-leads`, { params: { page, q: term } })
        .then((res) => {
            if (mine !== requestNo) return;
            leads.value.push(...res.data.results);
            more = res.data.more;
            page++;
        })
        .catch(() => {
            if (mine === requestNo) loadFailed.value = true;
        })
        .finally(() => {
            if (mine === requestNo) loading.value = false;
        });
}

function onScroll(event) {
    const list = event.target;
    if (more && !loading.value && list.scrollTop + list.clientHeight >= list.scrollHeight - 40) loadLeads(false);
}

function onFocus() {
    // Picked already: reopen the full list rather than one filtered to the picked label.
    if (form.lead_id && term !== '') {
        term = '';
        loadLeads(true);
    }
    listOpen.value = true;
}

function onInput() {
    form.lead_id = null;
    listOpen.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => {
        term = search.value.trim();
        loadLeads(true);
    }, 300);
}

function pick(lead) {
    form.lead_id = lead.id;
    search.value = lead.name + (lead.contact ? ` · ${lead.contact}` : '');
    listOpen.value = false;
}

// Close on a click outside — not on blur, so switching windows keeps it open.
function onOutside(event) {
    if (combo.value && !combo.value.contains(event.target)) listOpen.value = false;
}

watch(() => props.item, (item) => {
    if (!item) return;
    Object.assign(form, blankForm(), { type: item.type || 'sold', price: item.price ?? '' });
    files.ownership_document = null;
    files.contract_document = null;
    search.value = '';
    term = '';
    listOpen.value = false;
    error.value = '';
    loadLeads(true);
});

function close() {
    if (!saving.value) emit('close');
}

function submit() {
    error.value = '';
    if (form.buyer_mode === 'existing' && !form.lead_id) {
        error.value = 'Pick a lead from the list, or switch to “New buyer”.';
        searchInput.value?.focus();
        return;
    }
    const missing = documents.value.find((doc) => !files[doc.key]);
    if (missing) {
        error.value = `Upload both documents: ${missing.label}${missing.hint ? ` ${missing.hint}` : ''} is missing.`;
        return;
    }

    const body = new FormData();
    ['type', 'price', 'sold_at', 'commission', 'notes', 'buyer_mode', 'documents_password'].forEach((key) => body.append(key, form[key] ?? ''));
    if (rented.value) body.append('rented_until', form.rented_until || '');
    if (form.buyer_mode === 'existing') body.append('lead_id', form.lead_id);
    else Object.entries(form.buyer).forEach(([key, value]) => body.append(`buyer[${key}]`, value || ''));
    body.append('ownership_document', files.ownership_document);
    body.append('contract_document', files.contract_document);

    saving.value = true;
    http.post(`/properties/${props.item.id}/mark-sold`, body)
        .then((res) => emit('saved', res.data))
        .catch((e) => {
            const data = e.response?.data;
            error.value = data?.errors ? Object.values(data.errors).flat().join(' ') : (data?.message || 'Could not save. Please try again.');
        })
        .finally(() => {
            saving.value = false;
        });
}

onMounted(() => document.addEventListener('pointerdown', onOutside));
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onOutside);
    clearTimeout(timer);
});
</script>
