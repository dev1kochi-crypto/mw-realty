<template>
    <div v-if="!p" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <RouterLink :to="{ name: 'listing-permits.index' }">Back to listing permits</RouterLink></div>
    </div>
    <div v-else>
        <RouterLink :to="{ name: 'listing-permits.index', query: { tab: p.compliance_status } }" class="small text-decoration-none d-inline-block mb-2"><i class="fas fa-arrow-left me-1"></i>Back to {{ p.compliance_label.toLowerCase() }}</RouterLink>

        <div class="portal-card p-3 p-md-4 mb-3" :class="`la-tone-${p.compliance_status}`">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <img :src="p.thumb || 'https://placehold.co/192x144?text=%20'" alt="" class="la-thumb" style="width: 96px; height: 72px;">
                <div class="flex-grow-1 min-w-0">
                    <div class="portal-section-title mb-1 text-truncate">{{ p.title }}</div>
                    <div class="la-sub">{{ p.reference_no }} &middot; {{ p.owner }}<template v-if="p.agent"> &middot; Agent: {{ p.agent }}</template></div>
                </div>
                <span class="la-chip" style="background: var(--tone-soft); color: var(--tone); font-size: .85rem; padding: 6px 14px;"><i class="fas fa-file-shield"></i>{{ p.compliance_label }}</span>
                <RouterLink :to="{ name: p.segment === 'commercial' ? 'commercial.show' : 'properties.show', params: { id: p.id } }" class="btn btn-sm portal-btn-ghost" target="_blank"><i class="fas fa-eye me-1"></i>Full listing</RouterLink>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="portal-card p-4 mb-3">
                    <div class="la-section-title"><i class="fas fa-file-shield"></i>{{ permit.needs_permit ? `${permit.issuer} Advertising Permit` : 'Advertising Permit' }}</div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-4"><div class="la-field-label">Emirate</div><div class="la-field-value">{{ p.emirate }}</div></div>
                        <div class="col-sm-4"><div class="la-field-label">Permit type</div><div class="la-field-value">{{ permit.type_label }}<template v-if="permit.city"> · {{ permit.city }}</template></div></div>
                        <div v-if="permit.license_label" class="col-sm-4"><div class="la-field-label">{{ permit.license_label }}</div><div class="la-field-value">{{ permit.license_no }}</div></div>
                    </div>
                    <div v-if="!permit.needs_permit" class="alert alert-light border small mb-0"><i class="fas fa-circle-info me-1"></i>
                        {{ permit.type === 'none' ? 'DIFC / JAFZA free-zone listing — no advertising permit is issued.' : 'No advertising permit is needed for this location.' }}
                        The listing can be live without a permit.</div>
                    <template v-else>
                        <div v-if="permit.validates" class="alert small d-flex gap-2 align-items-start" :class="permit.verified_at ? 'alert-success' : 'alert-warning'">
                            <i class="fas mt-1" :class="permit.verified_at ? 'fa-circle-check' : 'fa-triangle-exclamation'"></i>
                            <div>
                                <template v-if="permit.verified_online"><strong>Verified with {{ permit.issuer }}</strong> on {{ formatDateTime(permit.verified_at) }} — the details below come from {{ permit.issuer }}'s record.</template>
                                <template v-else-if="permit.verified_at"><strong>Checked by MW Realty</strong> on {{ formatDate(permit.verified_at) }} (approved without online validation).</template>
                                <template v-else-if="p.awaiting_approval"><strong>Not verified with {{ permit.issuer }}.</strong> The agency / agent hasn't validated this permit online — check it yourself before approving.</template>
                                <template v-else><strong>Not verified.</strong> The agency / agent hasn't validated this permit with {{ permit.issuer }} yet, so the listing is not on the website. It goes live by itself once they click Validate in the property form — or check it yourself and approve it.</template>
                            </div>
                        </div>
                        <div class="row g-4 align-items-start">
                            <div class="col-sm-auto text-center">
                                <template v-if="permit.qr">
                                    <a :href="permit.qr" target="_blank" rel="noopener"><img :src="permit.qr" alt="Permit QR" class="la-qr"></a>
                                    <div class="la-sub mt-1">Scan to open {{ permit.issuer }}'s record</div>
                                </template>
                                <div v-else class="la-qr d-grid place-items-center text-center la-sub" style="place-items: center;">No QR uploaded</div>
                            </div>
                            <div class="col">
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <div class="la-field-label">Permit number</div>
                                        <div class="la-field-value fs-5">{{ permit.number || '—' }}</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="la-field-label">Expires</div>
                                        <div class="la-field-value fs-5" :class="{ 'text-danger': permit.expired }">{{ permit.expires_at ? formatDate(permit.expires_at) : '—' }}</div>
                                        <div v-if="permit.expires_at && !permit.expired" class="la-sub">{{ timeAgo(permit.expires_at) }}</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="la-field-label">Verification link</div>
                                        <a v-if="permit.verification_url" :href="permit.verification_url" target="_blank" rel="noopener noreferrer" class="text-break small">{{ permit.verification_url }}</a>
                                        <span v-else class="la-sub">Not provided — use the QR or the {{ permit.issuer }} website.</span>
                                    </div>
                                    <div v-if="permit.type === 'rera'" class="col-12">
                                        <a :href="DLD_URL" target="_blank" rel="noopener" class="btn btn-sm portal-btn-ghost"><i class="fas fa-shield-halved me-1"></i>Check permit number on DLD</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="portal-card p-4 mb-3">
                    <div class="la-section-title"><i class="fas fa-code-compare"></i>Does the ad match the permit?</div>
                    <!-- Verified online: the authority's record next to the ad, mismatches highlighted. -->
                    <template v-if="p.comparison">
                        <p class="la-sub mb-3">{{ permit.issuer }}'s record for this permit, next to what the ad says.</p>
                        <div class="table-responsive">
                            <table class="table table-sm small align-middle mb-0">
                                <thead><tr><th></th><th>{{ permit.issuer }} permit</th><th>This ad</th></tr></thead>
                                <tbody>
                                    <tr v-for="row in p.comparison" :key="row.label" :class="{ 'table-warning': row.mismatch }">
                                        <th class="fw-semibold">{{ row.label }}</th>
                                        <td>{{ row.permit ?? '—' }}</td>
                                        <td>{{ row.ad ?? '—' }} <i v-if="row.mismatch" class="fas fa-triangle-exclamation text-warning ms-1" title="Differs from the permit"></i></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                    <template v-else>
                        <p class="la-sub mb-3">The permit is issued for these exact details. Compare them with {{ permit.needs_permit ? `${permit.issuer}'s record` : 'the listing documents' }}.</p>
                        <div class="la-compare small">
                            <div v-for="fact in p.facts" :key="fact.label"><div class="la-field-label">{{ fact.label }}</div><div class="la-field-value text-truncate" :title="fact.value">{{ fact.value }}</div></div>
                        </div>
                    </template>
                </div>

                <div class="portal-card p-4">
                    <div class="la-section-title"><i class="fas fa-clock-rotate-left"></i>Review history</div>
                    <div class="la-timeline">
                        <div v-for="(log, i) in p.logs" :key="i" class="la-timeline__item" :class="`la-tone-${log.status}`">
                            <div><span class="fw-semibold">{{ log.label }}</span> <span class="la-sub">· {{ log.actor }} · {{ formatDateTime(log.at) }}</span></div>
                            <div v-if="log.note" class="mt-1" style="white-space: pre-line;">{{ log.note }}</div>
                        </div>
                        <div v-if="!p.logs.length" class="la-sub">No history yet.</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="la-sticky">
                    <!-- Listing Review (its own section): the same unit from another agency, risk flags, a rejection. -->
                    <div v-if="p.review.rejection" class="portal-card p-4 mb-3" style="border-color: #be123c;">
                        <div class="fw-bold mb-1 text-danger"><i class="fas fa-ban me-1"></i>Rejected on Listing Review</div>
                        <p class="small mb-1">{{ p.review.rejection.reason }}</p>
                        <p class="small la-sub mb-0">Off the website until the agency fixes it and saves it — it then goes back to Listing Review.</p>
                    </div>
                    <div v-for="r in p.review.cases" :key="r.id" class="portal-card p-4 mb-3" style="border-color: #b7791f;">
                        <div class="fw-bold mb-1" style="color: #92590f;"><i class="fas me-1" :class="r.type === 'duplicate' ? 'fa-clone' : 'fa-triangle-exclamation'"></i>{{ r.type === 'duplicate' ? 'Same unit listed by another agency' : 'Flagged on Listing Review' }}</div>
                        <template v-if="r.type === 'duplicate'">
                            <div v-for="o in r.others" :key="o.reference_no" class="small d-flex justify-content-between gap-2 py-1 border-bottom">
                                <span class="min-w-0 text-truncate"><strong>{{ o.reference_no }}</strong> · {{ o.owner }}<span v-if="o.first" class="la-sub"> · listed first</span></span>
                                <span class="text-nowrap">{{ o.price }} <span class="la-chip ms-1" :class="o.live ? 'la-chip--ok' : ''">{{ o.live ? 'Live' : 'Off' }}</span></span>
                            </div>
                        </template>
                        <div v-for="s in r.signals" :key="s.code" class="small mb-1"><strong>{{ s.label }}:</strong> {{ s.detail }}</div>
                        <p class="small la-sub mt-2 mb-2">
                            <template v-if="r.held">This listing is off the website until it is approved here or on Listing Review.</template>
                            <template v-else-if="r.waiting">This listing stays on the website while Listing Review checks it — Reject takes it down.</template>
                            <template v-else>This listing was listed first and stays on the website; the other one waits on Listing Review.</template>
                        </p>
                        <div class="d-grid gap-2">
                            <RouterLink :to="{ name: 'listing-review.show', params: { id: r.id } }" class="btn btn-sm portal-btn-ghost"><i class="fas fa-table-columns me-1"></i>Compare side by side</RouterLink>
                            <template v-if="r.waiting && !p.is_sold">
                                <button type="button" class="btn btn-sm btn-success" :disabled="busy" @click="openReview('approve', r)"><i class="fas fa-check me-1"></i>Approve listing</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" :disabled="busy" @click="openReview('reject', r)"><i class="fas fa-ban me-1"></i>Reject</button>
                            </template>
                        </div>
                    </div>

                    <div class="portal-card p-4 mb-3">
                        <div class="la-section-title"><i class="fas fa-list-check"></i>Checklist</div>
                        <ul class="la-checklist">
                            <li v-for="check in p.checks" :key="check.label"><i class="fas" :class="check.ok ? 'fa-circle-check' : 'fa-circle-xmark'"></i><span>{{ check.label }}</span></li>
                        </ul>
                        <div v-if="p.missing.length" class="alert alert-warning small mt-3 mb-0"><strong>Missing:</strong> {{ p.missing.join(', ') }}.</div>
                    </div>

                    <div v-if="p.is_sold" class="alert alert-info small">This listing is marked {{ p.sold_type }} — nothing to check.</div>
                    <template v-else>
                        <!-- No approval step: a verified permit makes the listing live by itself. -->
                        <div v-if="p.compliance_status === 'approved'" class="portal-card p-4 mb-3" style="border-color: #0f8a4f;">
                            <div class="fw-bold mb-1 text-success"><i class="fas fa-circle-check me-1"></i>{{ permit.needs_permit ? 'Permit verified' : 'No permit needed' }}</div>
                            <p class="small la-sub mb-0">
                                <template v-if="!permit.needs_permit">This listing doesn't need an advertising permit, so it can be live.</template>
                                <template v-else-if="permit.verified_online">{{ permit.issuer }} verified the permit on {{ formatDate(permit.verified_at) }} — the listing can be live.</template>
                                <template v-else>Approved by MW Realty on {{ p.reviewed_at ? formatDate(p.reviewed_at) : '' }} — the listing can be live.</template>
                            </p>
                        </div>
                        <!-- Waiting for Super Admin (LISTING_SUPERADMIN_APPROVAL, a DTCM / None permit), or a permit nobody could validate online. -->
                        <div v-else-if="p.compliance_status === 'pending' && !p.missing.length" class="portal-card p-4 mb-3" style="border-color: #0f8a4f;">
                            <div class="fw-bold mb-1 text-success"><i class="fas fa-circle-check me-1"></i>{{ p.awaiting_approval ? 'Awaiting your approval' : 'Approve' }}</div>
                            <p class="small la-sub">
                                <template v-if="!permit.needs_permit">No advertising permit is issued for this listing — check the listing details, then approve it.</template>
                                <template v-else-if="permit.verified_online">{{ permit.issuer }} verified the permit — check the ad matches it, then approve.</template>
                                <template v-else-if="permit.validates">The permit isn't verified with {{ permit.issuer }}. Check it on the {{ permit.issuer }} website (or the QR) before approving.</template>
                                <template v-else>{{ permit.issuer }} permits can't be validated online — check the permit number and expiry against the permit before approving.</template>
                                Approving publishes the listing on the website.
                                <template v-if="p.review.cases.some((r) => r.waiting)"> It also approves its Listing Review check above.</template>
                            </p>
                            <button type="button" class="btn btn-success btn-sm w-100" :disabled="busy" @click="approve"><i class="fas fa-check me-1"></i>Approve &amp; publish</button>
                        </div>
                        <!-- Nothing for Super Admin to do: the agency / agent validates the permit in the property form. -->
                        <div v-else class="portal-card p-4 mb-3">
                            <div class="fw-bold mb-1"><i class="fas fa-eye-slash me-1"></i>Not on the website</div>
                            <p class="small la-sub mb-0">
                                <template v-if="p.compliance_status === 'expired'">The permit expired. It comes back once the agency / agent enters and validates the renewed permit.</template>
                                <template v-else-if="p.compliance_status === 'changes_requested'">You took this listing down. It stays offline until the agency / agent changes the permit details.</template>
                                <template v-else-if="p.missing.length">Permit details are incomplete — waiting for the agency / agent to add and validate them.</template>
                                <template v-else>The permit isn't verified yet. It goes live by itself once the agency / agent clicks Validate in the property form.</template>
                            </p>
                        </div>

                        <div v-if="p.compliance_status !== 'changes_requested'" class="portal-card p-4">
                            <div class="fw-bold mb-1 text-danger"><i class="fas fa-ban me-1"></i>Take down</div>
                            <p class="small la-sub">Takes the listing off the website (e.g. wrong details or a DLD complaint). The agency / agent is told by email and in the portal; it stays offline until they change the permit details and validate it again.</p>
                            <button type="button" class="btn btn-outline-danger btn-sm w-100" :disabled="busy" @click="takeDown">Take down</button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <CrmModal :open="reviewAction.open" :title="reviewAction.kind === 'approve' ? 'Approve listing' : 'Reject listing'" @close="reviewAction.open = false">
            <p class="small la-sub">
                <template v-if="reviewAction.kind === 'approve'">The Listing Review check is approved{{ reviewAction.type === 'duplicate' ? ' — both agencies can advertise this unit' : '' }}. It goes on the website once its permit is verified too.</template>
                <template v-else>It stays off the website. The agency sees this reason; once they fix the listing and save it, it goes back to Listing Review.</template>
            </p>
            <label class="form-label small fw-semibold">{{ reviewAction.kind === 'reject' ? 'Reason (sent to the agency)' : 'Note (optional, for the record)' }}</label>
            <textarea v-model="reviewAction.note" class="form-control form-control-sm" rows="3" maxlength="1000"></textarea>
            <template #footer>
                <button type="button" class="btn btn-sm portal-btn-ghost" @click="reviewAction.open = false">Cancel</button>
                <button type="button" class="btn btn-sm" :class="reviewAction.kind === 'reject' ? 'btn-danger' : 'btn-success'" :disabled="busy || (reviewAction.kind === 'reject' && !reviewAction.note.trim())" @click="submitReview">{{ reviewAction.kind === 'reject' ? 'Reject' : 'Approve' }}</button>
            </template>
        </CrmModal>
    </div>
</template>

<script setup>
/**
 * A listing's permit review (Super Admin) — resources/views/portal/listing-approvals/show: the permit,
 * the authority's record next to the ad, checklist, history, and Approve & publish / Take down.
 */
import { computed, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmModal from '../../components/CrmModal.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { formatDate, formatDateTime, timeAgo } from '../../utils/format';

const DLD_URL = 'https://dubailand.gov.ae/en/eservices/validate-real-estate-licenses-and-permits/';

const props = defineProps({
    id: { type: Number, required: true },
});

const router = useRouter();
const { confirm } = useConfirm();
const { success, error: toastError } = useToast();
const p = ref(null);
const loadError = ref('');
const busy = ref(false);
const permit = computed(() => p.value.permit);

function load() {
    p.value = null;
    loadError.value = '';
    http.get(`/listing-permits/${props.id}`)
        .then((res) => {
            p.value = res.data;
            document.title = `Listing Permit - Partner Portal`;
        })
        .catch((e) => {
            loadError.value = errorMessage(e);
        });
}

async function approve() {
    if (!(await confirm({ title: 'Approve this listing?', message: 'It goes live on the website now. The agency / agent is told by email and in the portal.', confirmText: 'Approve & publish', tone: 'primary' }))) return;
    busy.value = true;
    http.post(`/listing-permits/${props.id}/approve`)
        .then((res) => {
            success(res.data.message);
            router.push({ name: 'listing-permits.index', query: { tab: 'pending' } });
        })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { busy.value = false; });
}

async function takeDown() {
    if (!(await confirm({ title: 'Take this listing down?', message: 'It goes off the website now and stays offline until the agency / agent changes the permit details and validates it again.', confirmText: 'Take down', tone: 'danger' }))) return;
    busy.value = true;
    http.post(`/listing-permits/${props.id}/take-down`)
        .then((res) => {
            success(res.data.message);
            router.push({ name: 'listing-permits.index' });
        })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { busy.value = false; });
}

/* Listing Review check on this listing (approved / rejected through Listing Review's own API). */
const reviewAction = reactive({ open: false, kind: 'approve', id: null, type: null, note: '' });

function openReview(kind, review) {
    Object.assign(reviewAction, { open: true, kind, id: review.id, type: review.type, note: '' });
}

function submitReview() {
    busy.value = true;
    http.post(`/listing-review/${reviewAction.id}/${reviewAction.kind}`, { property_id: props.id, note: reviewAction.note.trim() || null })
        .then((res) => {
            success(res.data.message);
            reviewAction.open = false;
            load();
        })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { busy.value = false; });
}

watch(() => props.id, load, { immediate: true });
</script>
