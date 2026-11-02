<template>
    <div v-if="!c" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <RouterLink :to="{ name: 'listing-review.index' }">Back to listing review</RouterLink></div>
    </div>
    <div v-else>
        <RouterLink :to="{ name: 'listing-review.index', query: { tab: isOpen ? c.type : 'resolved' } }" class="small text-decoration-none d-inline-block mb-2"><i class="fas fa-arrow-left me-1"></i>Back to listing review</RouterLink>

        <div class="portal-card p-3 p-md-4 mb-3" :class="`la-tone-${isOpen ? c.type : 'resolved'}`">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="la-status__icon" style="background: var(--tone-soft); color: var(--tone);"><i class="fas" :class="isDuplicate ? 'fa-clone' : 'fa-triangle-exclamation'"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="portal-section-title mb-1">{{ isDuplicate ? `Same unit listed by ${c.listings.length} agencies` : 'Listing to check' }}</div>
                    <div class="la-sub">Flagged {{ formatDateTime(c.created_at) }} · {{ timeAgo(c.created_at) }}</div>
                </div>
                <span class="la-chip" style="background: var(--tone-soft); color: var(--tone); font-size: .85rem; padding: 6px 14px;">
                    <i class="fas" :class="isOpen ? 'fa-hourglass-half' : 'fa-circle-check'"></i>{{ isOpen ? `${waiting.length} waiting for you` : RESOLUTIONS[c.resolution] }}
                </span>
            </div>
        </div>

        <!-- What happened and what each button does, in plain words. -->
        <div v-if="isOpen" class="la-hint mb-3" :class="`la-tone-${c.type}`">
            <i class="fas fa-circle-info"></i>
            <div>
                <template v-if="isDuplicate && original">
                    <strong>{{ original.reference_no }}</strong> ({{ original.facts.Advertiser }}) listed this unit first — it stays on the website.
                    {{ waiting.map((l) => `${l.reference_no} (${l.facts.Advertiser})`).join(', ') }} listed the same unit later, so {{ waiting.length === 1 ? 'it is' : 'they are' }} off the website until you decide.
                </template>
                <template v-else-if="waiting[0]?.held">This listing is off the website until you decide. Check what was flagged below.</template>
                <template v-else>This listing <strong>stays on the website</strong> while you check what was flagged below — Reject takes it down.</template>
                <div class="mt-1"><strong>Approve</strong> — it goes on the website{{ isDuplicate ? ' (both agencies can advertise the unit, each with its own permit)' : '' }}.
                    <strong>Reject</strong> — it stays off the website; the agency sees your reason, and once they fix it and save, it comes back here.</div>
            </div>
        </div>

        <div v-if="!isOpen" class="alert alert-light border small">
            <i class="fas fa-circle-check text-success me-1"></i>
            <strong>{{ RESOLUTIONS[c.resolution] }}</strong><template v-if="c.resolver"> by {{ c.resolver }}</template> on {{ formatDateTime(c.resolved_at) }}.
            <div v-if="c.resolution_note" class="mt-1" style="white-space: pre-line;">{{ c.resolution_note }}</div>
        </div>

        <div v-if="c.price" class="alert small d-flex gap-2 align-items-start" :class="c.price.conflict ? 'alert-danger' : 'alert-success'">
            <i class="fas fa-arrows-left-right mt-1"></i>
            <div>
                <strong>{{ c.price.conflict ? 'Prices differ' : 'Prices agree' }}:</strong>
                {{ c.price.min }} to {{ c.price.max }} — {{ c.price.variance_pct }}% apart.
                <template v-if="c.price.conflict">The same unit at very different prices — check which price is right before you approve.</template>
            </div>
        </div>

        <div v-if="c.signals.length" class="portal-card p-4 mb-3">
            <div class="la-section-title"><i class="fas fa-triangle-exclamation"></i>What was flagged</div>
            <div v-for="s in c.signals" :key="s.code" class="lr-signal">
                <div class="fw-bold">{{ s.label }}</div>
                <div class="small">{{ s.detail }}</div>
            </div>
            <p class="la-sub mt-3 mb-0">Automated screening only — check the listing and its documents before you decide. It doesn't confirm or rule out ownership.</p>
        </div>

        <!-- Price risk: exactly which listings it was compared with, to check by hand. -->
        <div v-if="c.price_check" class="portal-card p-4 mb-3">
            <div class="la-section-title mb-1"><i class="fas fa-scale-balanced"></i>Compared with</div>
            <p class="small mb-3">
                Live <strong>{{ c.price_check.type }}</strong> listings for <strong>{{ c.price_check.purpose }}</strong> in <strong>{{ c.price_check.location }}</strong>
                ({{ c.price_check.listings.length }}). Their typical (median) price is <strong>{{ c.price_check.median }} / sq.ft</strong>; this listing is
                <strong class="text-danger">{{ c.price_check.rate }} / sq.ft — {{ Math.abs(c.price_check.percent) }}% {{ c.price_check.percent < 0 ? 'below' : 'above' }}</strong>.
                Normal range: {{ c.price_check.low }} – {{ c.price_check.high }} / sq.ft.
            </p>
            <div class="table-responsive">
                <table class="table table-sm small align-middle mb-0 lr-comparables">
                    <thead><tr><th>Ref</th><th>Listing</th><th>Agency</th><th class="text-end">Beds</th><th class="text-end">Size</th><th class="text-end">Price</th><th class="text-end">Price / sq.ft</th></tr></thead>
                    <tbody>
                        <template v-for="row in comparableRows" :key="row.key">
                            <tr v-if="row.median" class="lr-comparables__median"><td colspan="7"><i class="fas fa-grip-lines me-1"></i>Median {{ c.price_check.median }} / sq.ft</td></tr>
                            <tr v-else :class="{ 'lr-comparables__self': row.self }">
                                <td class="text-nowrap">
                                    <RouterLink :to="{ name: row.segment === 'commercial' ? 'commercial.show' : 'properties.show', params: { id: row.id } }" target="_blank">{{ row.reference_no }}</RouterLink>
                                </td>
                                <td class="text-truncate" style="max-width: 280px;" :title="row.title">{{ row.self ? 'This listing — ' : '' }}{{ row.title }}</td>
                                <td class="text-truncate" style="max-width: 180px;">{{ row.owner }}</td>
                                <td class="text-end">{{ row.bedrooms ?? '—' }}</td>
                                <td class="text-end text-nowrap">{{ row.size }}</td>
                                <td class="text-end text-nowrap">{{ row.price }}</td>
                                <td class="text-end text-nowrap fw-semibold">{{ row.rate }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="portal-card p-0 mb-3">
            <div class="p-4 pb-2">
                <div class="la-section-title mb-1"><i class="fas fa-table-columns"></i>{{ isDuplicate ? 'Side by side' : 'The listing' }}</div>
                <div v-if="overflows" class="d-flex align-items-center gap-2 mb-1">
                    <span class="la-sub flex-grow-1"><i class="fas fa-arrows-left-right me-1"></i>{{ c.listings.length }} listings — slide to see them all; the row names stay in place.</span>
                    <button type="button" class="lr-slide-btn" aria-label="Previous listings" @click="slide(-1)"><i class="fas fa-chevron-left"></i></button>
                    <button type="button" class="lr-slide-btn" aria-label="Next listings" @click="slide(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
                <div v-if="isDuplicate" class="lr-legend">
                    <span><i class="lr-legend__dot lr-legend__dot--same"></i>Same in every listing</span>
                    <span><i class="lr-legend__dot lr-legend__dot--diff"></i>Different between the listings</span>
                </div>
            </div>
            <div ref="scroller" class="table-responsive">
                <table class="lr-compare" :style="{ '--lr-col': `${100 / c.listings.length}%` }">
                    <thead>
                        <tr>
                            <th></th>
                            <td v-for="l in c.listings" :key="l.id" :class="cellClass(l)">
                                <ReviewPhotos :photos="l.photos" class="mb-2" />
                                <RouterLink :to="{ name: l.segment === 'commercial' ? 'commercial.show' : 'properties.show', params: { id: l.id } }" target="_blank" class="la-title" :title="l.title">{{ l.title }}</RouterLink>
                                <div class="la-sub">{{ l.reference_no }} · {{ l.photo_count }} {{ plural('photo', l.photo_count) }}</div>
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    <span v-if="l.original && isOpen" class="la-chip la-chip--ok"><i class="fas fa-flag"></i>Listed first · stays live</span>
                                    <span v-if="l.waiting" class="la-chip la-chip--warn"><i class="fas fa-hourglass-half"></i>Waiting for your decision</span>
                                    <span v-if="l.outcome" class="la-chip" :class="l.outcome === 'approved' ? 'la-chip--ok' : 'la-chip--bad'">{{ l.outcome === 'approved' ? 'Approved' : 'Rejected' }}</span>
                                    <span class="la-chip" :class="l.live ? 'la-chip--ok' : ''">{{ l.website }}</span>
                                    <!-- Listing Permits (its own section): not approved there yet. -->
                                    <RouterLink v-if="!l.permit.approved && l.outcome !== 'rejected'" :to="{ name: 'listing-permits.show', params: { id: l.id } }" class="la-chip la-chip--warn text-decoration-none" title="Open on Listing Permits">
                                        <i class="fas fa-file-shield"></i>Listing Permits: {{ l.permit.label }}
                                    </RouterLink>
                                </div>
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr :class="{ 'is-diff': c.price?.conflict, 'is-same': c.price && !c.price.variance_pct }">
                            <th><i class="lr-row-icon"></i>Price</th>
                            <td v-for="l in c.listings" :key="l.id" :class="cellClass(l)">
                                <div class="fw-bold" :class="priceClass(l)">{{ l.price }}</div>
                                <div v-if="l.rate" class="la-sub">{{ l.rate }}</div>
                            </td>
                        </tr>
                        <tr v-for="label in factLabels" :key="label" :class="{ 'is-diff': c.differs.includes(label), 'is-same': c.same.includes(label) }">
                            <th><i class="lr-row-icon"></i>{{ label }}</th>
                            <td v-for="l in c.listings" :key="l.id" :class="cellClass(l)">{{ l.facts[label] }}</td>
                        </tr>
                        <tr>
                            <th>Permit</th>
                            <td v-for="l in c.listings" :key="l.id" :class="cellClass(l)">
                                <span v-if="l.permit.expired" class="la-chip la-chip--bad"><i class="fas fa-ban"></i>Expired</span>
                                <span v-else-if="l.permit.verified_online" class="la-chip la-chip--ok"><i class="fas fa-circle-check"></i>Verified with {{ l.permit.issuer }}</span>
                                <span v-else-if="l.permit.verified" class="la-chip la-chip--ok"><i class="fas fa-circle-check"></i>Checked by MW Realty</span>
                                <span v-else class="la-chip la-chip--warn"><i class="fas fa-shield-halved"></i>Not verified</span>
                                <div v-if="l.permit.expires_at" class="la-sub mt-1">Until {{ formatDate(l.permit.expires_at) }}</div>
                                <RouterLink :to="{ name: 'listing-permits.show', params: { id: l.id } }" class="d-block small mt-1" :class="l.permit.approved ? 'text-success' : 'text-warning-emphasis fw-semibold'">
                                    <i class="fas fa-file-shield me-1"></i>Listing Permits: {{ l.permit.label }} <i class="fas fa-arrow-right ms-1"></i>
                                </RouterLink>
                            </td>
                        </tr>
                        <tr>
                            <th>Advertiser KYC</th>
                            <td v-for="l in c.listings" :key="l.id" :class="cellClass(l)">
                                <span class="la-chip" :class="l.kyc_approved ? 'la-chip--ok' : 'la-chip--warn'">{{ l.kyc_approved ? 'Approved' : 'Not approved' }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Listed</th>
                            <td v-for="l in c.listings" :key="l.id" :class="cellClass(l)">{{ formatDateTime(l.listed_at) }} <span class="la-sub">· {{ timeAgo(l.listed_at) }}</span></td>
                        </tr>
                        <tr v-if="isDuplicate">
                            <th>Why matched</th>
                            <td v-for="l in c.listings" :key="l.id" :class="cellClass(l)">
                                <div v-for="r in l.reasons" :key="r" class="small">{{ r }}</div>
                            </td>
                        </tr>
                        <tr>
                            <th>Decision</th>
                            <td v-for="l in c.listings" :key="l.id" :class="cellClass(l)">
                                <div v-if="l.waiting" class="d-grid gap-2">
                                    <button type="button" class="btn btn-sm btn-success" :disabled="busy" @click="openAction('approve', l)"><i class="fas fa-check me-1"></i>Approve</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" :disabled="busy" @click="openAction('reject', l)"><i class="fas fa-ban me-1"></i>Reject</button>
                                </div>
                                <template v-else-if="l.outcome">
                                    <div class="fw-semibold" :class="l.outcome === 'approved' ? 'text-success' : 'text-danger'">{{ l.outcome === 'approved' ? 'Approved' : 'Rejected' }}</div>
                                    <div v-if="l.note" class="small" style="white-space: pre-line;">{{ l.note }}</div>
                                </template>
                                <template v-else-if="l.original && isOpen">
                                    <div class="la-sub mb-2">Already on the website — nothing to decide. Reject it only if this listing is the wrong one.</div>
                                    <button type="button" class="btn btn-sm btn-outline-danger w-100" :disabled="busy" @click="openAction('reject', l)"><i class="fas fa-ban me-1"></i>Reject</button>
                                </template>
                                <span v-else class="la-sub">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CrmModal :open="action.open" :title="action.kind === 'approve' ? `Approve ${action.listing?.reference_no}` : `Reject ${action.listing?.reference_no}`" @close="action.open = false">
            <p class="small la-sub">
                <template v-if="action.kind === 'approve'">
                    It goes on the website{{ isDuplicate ? ', next to the other agency\'s listing of this unit' : '' }}. The agency gets a notification.
                    <strong v-if="action.listing && !action.listing.permit.approved" class="d-block mt-2 text-warning-emphasis">
                        Its permit isn't approved yet ({{ action.listing.permit.label }}) — it goes live only after it's approved on Listing Permits too.
                    </strong>
                </template>
                <template v-else>It {{ action.listing?.held ? 'stays' : 'goes' }} off the website. The agency sees this reason; once they fix the listing and click Update, it comes back here for another check.</template>
            </p>
            <label class="form-label small fw-semibold">{{ action.kind === 'reject' ? 'Reason (sent to the agency)' : 'Note (optional, for the record)' }}</label>
            <textarea v-model="action.note" class="form-control form-control-sm" rows="3" maxlength="1000" :placeholder="action.kind === 'reject' ? 'e.g. The price doesn\'t match the DLD permit, or you have no authority from the owner.' : ''"></textarea>
            <template #footer>
                <button type="button" class="btn btn-sm portal-btn-ghost" @click="action.open = false">Cancel</button>
                <button type="button" class="btn btn-sm" :class="action.kind === 'reject' ? 'btn-danger' : 'btn-success'" :disabled="busy || (action.kind === 'reject' && !action.note.trim())" @click="submitAction">{{ action.kind === 'reject' ? 'Reject' : 'Approve' }}</button>
            </template>
        </CrmModal>
    </div>
</template>

<script setup>
/**
 * One Listing Review case (Super Admin): the listings side by side with what differs highlighted,
 * the price spread and risk signals — then Approve / Reject each listing that is waiting.
 */
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import http, { errorMessage } from '../../api/http';
import CrmModal from '../../components/CrmModal.vue';
import ReviewPhotos from './ReviewPhotos.vue';
import { useToast } from '../../composables/useToast';
import { formatDate, formatDateTime, plural, timeAgo } from '../../utils/format';

const RESOLUTIONS = { approved: 'Approved', rejected: 'Rejected', auto_cleared: 'Fixed by the agency' };

const props = defineProps({
    id: { type: Number, required: true },
});

const { success, error: toastError } = useToast();
const c = ref(null);
const loadError = ref('');
const busy = ref(false);
const action = reactive({ open: false, kind: 'approve', listing: null, note: '' });

const isOpen = computed(() => c.value.status === 'open');
const isDuplicate = computed(() => c.value.type === 'duplicate');
const waiting = computed(() => c.value.listings.filter((l) => l.waiting));
const original = computed(() => c.value.listings.find((l) => l.original));
const factLabels = computed(() => Object.keys(c.value.listings[0]?.facts ?? {}));
/** The compared listings by price / sq.ft with this listing slotted in and a median divider. */
const comparableRows = computed(() => {
    const check = c.value.price_check;
    if (!check) return [];
    const rows = [...check.listings.map((l) => ({ ...l, key: `c${l.id}` })), { ...check.self, key: 'self', self: true }]
        .sort((a, b) => a.rate_value - b.rate_value);
    const at = rows.findIndex((r) => r.rate_value > check.median_value);
    rows.splice(at === -1 ? rows.length : at, 0, { key: 'median', median: true });
    return rows;
});

// The listings share the full width; only when they no longer fit (each column has a minimum width)
// does the table slide sideways — with ‹ › buttons.
const scroller = ref(null);
const overflows = ref(false);
const COLUMN_MIN_WIDTH = 260;
const observer = new ResizeObserver(() => {
    const el = scroller.value;
    overflows.value = !!el && el.scrollWidth > el.clientWidth + 1;
});

watch(scroller, (el, old) => {
    if (old) observer.unobserve(old);
    if (el) {
        observer.observe(el);
        observer.observe(el.firstElementChild);
    }
});
onBeforeUnmount(() => observer.disconnect());

function slide(direction) {
    scroller.value?.scrollBy({ left: direction * COLUMN_MIN_WIDTH, behavior: 'smooth' });
}

const prices = computed(() => c.value.listings.filter((l) => l.outcome !== 'rejected').map((l) => l.price_value).filter((v) => v > 0));

function cellClass(l) {
    return { 'is-out': l.outcome === 'rejected', 'is-kept': l.original && isOpen.value };
}

function priceClass(l) {
    if (!c.value.price?.conflict || l.outcome === 'rejected') return '';
    if (l.price_value === Math.min(...prices.value)) return 'lr-price-low';
    if (l.price_value === Math.max(...prices.value)) return 'lr-price-high';
    return '';
}

function load() {
    loadError.value = '';
    return http.get(`/listing-review/${props.id}`)
        .then((res) => {
            c.value = res.data;
            document.title = 'Listing Review - Partner Portal';
        })
        .catch((e) => {
            loadError.value = errorMessage(e);
        });
}

function openAction(kind, listing) {
    Object.assign(action, { open: true, kind, listing, note: '' });
}

function submitAction() {
    busy.value = true;
    http.post(`/listing-review/${props.id}/${action.kind}`, { property_id: action.listing.id, note: action.note.trim() || null })
        .then((res) => {
            success(res.data.message);
            action.open = false;
            return load();
        })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => { busy.value = false; });
}

watch(() => props.id, () => {
    c.value = null;
    load();
}, { immediate: true });
</script>
