<template>
    <div>
        <div class="mb-3">
            <RouterLink :to="{ name: 'integrations.index' }" class="portal-link-muted small"><i class="fas fa-arrow-left me-1"></i>All integrations</RouterLink>
            <div class="portal-section-title mb-0 mt-1">Property Finder</div>
        </div>

        <div v-if="errorAlert" class="alert alert-danger">{{ errorAlert }}</div>

        <div v-if="!data" class="text-center py-5">
            <span v-if="!loadError" class="spinner-border text-primary"></span>
            <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
        </div>
        <div v-else id="property-finder" class="portal-card pf pf-card mb-3">
            <div class="pf-hero">
                <div class="pf-hero__top">
                    <span class="pf-hero__logo" aria-hidden="true"><i class="fas fa-house-chimney"></i></span>
                    <div class="min-w-0">
                        <h5 class="pf-hero__name">Property Finder</h5>
                        <p class="pf-hero__tag">
                            <template v-if="data.is_admin">Agencies and agents import their Property Finder listings. Each one waits for your review before it can go on the website.</template>
                            <template v-else>Bring your Property Finder listings into MW Realty — all of them once, then just the new ones.</template>
                        </p>
                    </div>
                    <span class="pf-hero__status">
                        <template v-if="data.is_admin">
                            <span v-if="data.pending" class="intg-status intg-status--warn"><i class="fas fa-hourglass-half"></i>{{ data.pending }} to review</span>
                            <span v-else class="intg-status intg-status--off">{{ data.accounts }} account{{ data.accounts === 1 ? '' : 's' }} connected</span>
                        </template>
                        <span v-else-if="pf?.syncing" class="intg-status intg-status--warn"><i class="fas fa-rotate fa-spin"></i>Syncing</span>
                        <span v-else-if="pf" class="intg-status intg-status--ok"><i class="fas fa-circle-check"></i>Connected</span>
                        <span v-else class="intg-status intg-status--off">Not connected</span>
                    </span>
                </div>

                <div v-if="!data.is_admin" class="pf-steps" aria-label="Progress">
                    <div v-for="(s, i) in steps" :key="s.title" class="pf-step" :class="stepState(i + 1)" :aria-current="stepState(i + 1) === 'is-current' ? 'step' : null">
                        <span class="pf-step__icon" aria-hidden="true"><i :class="stepState(i + 1) === 'is-done' ? 'fas fa-check' : s.icon"></i></span>
                        <span class="min-w-0"><span class="pf-step__title">{{ s.title }}</span><span class="pf-step__text">{{ s.text }}</span></span>
                    </div>
                </div>
                <div v-else class="pb-4"></div>
            </div>

            <div class="pf-body">
                <!-- Super Admin: totals + the review queue. -->
                <template v-if="data.is_admin">
                    <div class="pf-stats">
                        <div class="pf-stat"><div class="pf-stat__label"><i class="fas fa-building"></i>Connected accounts</div><div class="pf-stat__value">{{ data.accounts }}</div></div>
                        <div class="pf-stat" :class="{ 'pf-stat--warn': data.pending }"><div class="pf-stat__label"><i class="fas fa-hourglass-half"></i>Waiting for review</div><div class="pf-stat__value">{{ data.pending }}</div></div>
                    </div>
                    <RouterLink :to="{ name: 'integrations.property-finder.review' }" class="btn pf-btn"><i class="fas fa-list-check me-1"></i>Review imported listings <template v-if="data.pending">({{ data.pending }})</template></RouterLink>
                </template>

                <div v-else-if="!pf && !data.can_manage" class="pf-info d-flex gap-3 align-items-start">
                    <i class="fas fa-circle-info mt-1" style="color: var(--pf);"></i>
                    <div><div class="fw-semibold mb-1">Your agency hasn't connected Property Finder yet</div><p>Once it does, you'll see the connection here and can sync its listings.</p></div>
                </div>

                <!-- Not connected: API key + secret. -->
                <div v-else-if="!pf" class="pf-grid">
                    <form class="pf-panel" @submit.prevent="connect(connectForm)">
                        <div class="pf-panel__title">Connect your Property Finder account</div>
                        <div class="pf-panel__sub">Paste the API credentials from your Property Finder account.</div>

                        <div class="pf-field">
                            <label for="pfApiKey">API key</label>
                            <i class="fas fa-key pf-field__icon" aria-hidden="true"></i>
                            <input id="pfApiKey" v-model="connectForm.api_key" type="text" class="form-control" placeholder="Paste your API key" required autocomplete="off" spellcheck="false">
                        </div>
                        <div class="pf-field">
                            <label for="pfApiSecret">API secret</label>
                            <i class="fas fa-lock pf-field__icon" aria-hidden="true"></i>
                            <input id="pfApiSecret" v-model="connectForm.api_secret" :type="connectForm.show ? 'text' : 'password'" class="form-control" placeholder="Paste your API secret" required autocomplete="new-password" spellcheck="false">
                            <button type="button" class="pf-field__eye" :aria-label="connectForm.show ? 'Hide secret' : 'Show secret'" @click="connectForm.show = !connectForm.show"><i class="fas" :class="connectForm.show ? 'fa-eye-slash' : 'fa-eye'"></i></button>
                        </div>

                        <div class="pf-switch" style="cursor: default;">
                            <span class="pf-switch__text"><i class="fas fa-hand-pointer me-1" style="color: var(--pf);"></i>Nothing is imported yet<span class="pf-switch__hint">After connecting, click <strong>Import all listings</strong> when you're ready.</span></span>
                        </div>

                        <button type="submit" class="btn pf-btn pf-btn--block" :disabled="connectForm.saving">
                            <template v-if="connectForm.saving"><span class="spinner-border spinner-border-sm me-2"></span>Checking with Property Finder…</template>
                            <template v-else><i class="fas fa-plug me-2"></i>Verify &amp; connect</template>
                        </button>
                        <div class="pf-secure"><i class="fas fa-shield-halved me-1"></i>Checked with Property Finder before saving · stored encrypted</div>
                    </form>

                    <div class="pf-side">
                        <div class="pf-info">
                            <div class="pf-info__title"><i class="fas fa-box-open"></i>What gets imported</div>
                            <ul class="pf-checks">
                                <li><i class="fas fa-check"></i>Title &amp; description</li>
                                <li><i class="fas fa-check"></i>Price, sale or rent</li>
                                <li><i class="fas fa-check"></i>Beds, baths &amp; size</li>
                                <li><i class="fas fa-check"></i>Location &amp; map pin</li>
                                <li><i class="fas fa-check"></i>Photos (watermarked)</li>
                                <li><i class="fas fa-check"></i>Amenities &amp; permit no.</li>
                            </ul>
                        </div>
                        <div class="pf-info">
                            <div class="pf-info__title"><i class="fas fa-clone"></i>No duplicates</div>
                            <p>Each listing is imported once. Drafts are skipped, and so is any listing whose permit you already have here.</p>
                        </div>
                        <div class="pf-info pf-info--tip">
                            <div class="pf-info__title"><i class="fas fa-lightbulb"></i>Where to find your keys</div>
                            <p>In your Property Finder account (PF Expert) under API settings — or ask your Property Finder account manager for Enterprise API access.</p>
                        </div>
                    </div>
                </div>

                <!-- Connected. -->
                <template v-else>
                    <div class="pf-stats">
                        <div class="pf-stat"><div class="pf-stat__label"><i class="fas fa-key"></i>API key</div><div class="pf-stat__value pf-stat__value--mono">{{ pf.masked_key }}</div></div>
                        <div class="pf-stat"><div class="pf-stat__label"><i class="fas fa-house"></i>Imported</div><div class="pf-stat__value">{{ data.imported }}</div></div>
                        <div class="pf-stat" :class="{ 'pf-stat--warn': data.pending }"><div class="pf-stat__label"><i class="fas fa-hourglass-half"></i>In review</div><div class="pf-stat__value">{{ data.pending }}</div></div>
                        <div class="pf-stat"><div class="pf-stat__label"><i class="fas fa-clock"></i>Last sync</div><div class="pf-stat__value" style="font-size: .95rem;">{{ pf.last_synced_at ? timeAgo(pf.last_synced_at) : 'Never' }}</div></div>
                    </div>

                    <!-- Queued job running (or waiting for the worker): the same animated card as the click overlay, in the page. -->
                    <div v-if="pf.syncing" class="pf-running" role="status" aria-live="polite">
                        <PfFlow />
                        <div class="pf-running__title">{{ pf.sync.mode === 'new' ? 'Checking for new listings…' : 'Importing your listings…' }}</div>
                        <div class="pf-running__msg pf-msg" :class="{ 'is-out': rotator.out }">{{ live.status === 'running' ? rotator.text : (live.status === 'queued' ? 'Queued — starting in a moment…' : 'Fetching your live listings…') }}</div>
                        <div class="pf-running__nums">
                            <span class="pf-num pf-num--ok"><PfCount :value="live.added" />imported</span>
                            <span class="pf-num"><PfCount :value="live.skipped" />already here</span>
                            <span v-if="live.failed" class="pf-num pf-num--bad"><PfCount :value="live.failed" />failed</span>
                        </div>
                        <div class="fbbar"><span></span></div>
                        <div class="pf-running__note">
                            <i class="fas fa-circle-info me-1"></i>Runs in the background — you can leave this page; new listings wait for MW Realty's review.
                        </div>
                        <div v-if="live.waiting" class="pf-running__note pf-running__note--warn">
                            <i class="fas fa-hourglass-half me-1"></i>Waiting for the background worker to pick it up — this usually takes under a minute.
                        </div>
                    </div>
                    <div v-else-if="pf.sync.status === 'failed'" class="pf-result is-error"><i class="fas fa-triangle-exclamation mt-1"></i><div>Last sync failed: {{ pf.sync.error }}</div></div>
                    <div v-else-if="pf.sync.status" class="pf-result" :class="{ 'is-error': pf.sync.error }">
                        <i class="fas mt-1" :class="pf.sync.error ? 'fa-circle-exclamation' : 'fa-circle-check'"></i>
                        <div>
                            Last sync{{ pf.sync.finished_at ? ` ${timeAgo(pf.sync.finished_at)}` : '' }}: <strong>{{ pf.sync.added }}</strong> new listing{{ pf.sync.added === 1 ? '' : 's' }}, {{ pf.sync.skipped }} already here{{ pf.sync.failed ? `, ${pf.sync.failed} failed` : '' }}.
                            <template v-if="pf.sync.added">New listings wait for MW Realty's review.</template>
                            <div v-if="pf.sync.error" class="mt-1">{{ pf.sync.error }}</div>
                        </div>
                    </div>

                    <div class="pf-bar">
                        <span class="pf-bar__meta">
                            Connected {{ formatDate(pf.created_at) }}<template v-if="pf.connected_by"> by {{ pf.connected_by }}</template>
                            <template v-if="!data.can_manage"> · managed by {{ pf.owner ?? 'your agency' }}</template>
                        </span>
                        <template v-if="pf.last_full_sync_at">
                            <button type="button" class="btn btn-sm portal-btn-ghost" :disabled="pf.syncing || !!syncing" title="Go through every live listing — anything already imported is skipped" @click="sync('all')"><i class="fas fa-clock-rotate-left me-1"></i>Re-check all</button>
                            <button type="button" class="btn btn-sm pf-btn" :disabled="pf.syncing || !!syncing" title="Only listings added on Property Finder since the last sync" @click="sync('new')"><i class="fas fa-rotate me-1"></i>Sync new listings</button>
                        </template>
                        <button v-else type="button" class="btn btn-sm pf-btn" :disabled="pf.syncing || !!syncing" @click="sync('all')"><i class="fas fa-cloud-arrow-down me-1"></i>Import all listings</button>
                        <div v-if="data.can_manage" ref="menu" class="dropdown">
                            <button type="button" class="btn btn-sm portal-btn-ghost" :aria-expanded="menuOpen" aria-label="More options" @click="menuOpen = !menuOpen"><i class="fas fa-ellipsis"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end" :class="{ show: menuOpen }" style="right: 0; left: auto;">
                                <li><button type="button" class="dropdown-item" @click="keysOpen = !keysOpen; menuOpen = false"><i class="fas fa-key me-2"></i>Update API keys</button></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><button type="button" class="dropdown-item text-danger" @click="disconnect"><i class="fas fa-link-slash me-2"></i>Disconnect</button></li>
                            </ul>
                        </div>
                    </div>

                    <form v-if="data.can_manage" v-show="keysOpen" class="pf-keys" @submit.prevent="connect(keysForm)">
                        <div class="fw-semibold small mb-3">Replace the API key and secret</div>
                        <div class="row g-3">
                            <div class="col-md-6 pf-field mb-0"><label for="pfNewKey">New API key</label><i class="fas fa-key pf-field__icon" aria-hidden="true"></i><input id="pfNewKey" v-model="keysForm.api_key" type="text" class="form-control" required autocomplete="off" spellcheck="false"></div>
                            <div class="col-md-6 pf-field mb-0">
                                <label for="pfNewSecret">New API secret</label><i class="fas fa-lock pf-field__icon" aria-hidden="true"></i>
                                <input id="pfNewSecret" v-model="keysForm.api_secret" :type="keysForm.show ? 'text' : 'password'" class="form-control" required autocomplete="new-password">
                                <button type="button" class="pf-field__eye" :aria-label="keysForm.show ? 'Hide secret' : 'Show secret'" @click="keysForm.show = !keysForm.show"><i class="fas" :class="keysForm.show ? 'fa-eye-slash' : 'fa-eye'"></i></button>
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-sm pf-btn" :disabled="keysForm.saving">
                                <template v-if="keysForm.saving"><span class="spinner-border spinner-border-sm me-2"></span>Checking with Property Finder…</template>
                                <template v-else>Verify &amp; save</template>
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>

        <!-- Shown the moment Import / Sync is clicked, until the screen has the queued job's card. -->
        <div v-if="syncing" class="pf-busy pf">
            <div class="pf-busy__box" role="status" aria-live="polite">
                <PfFlow />
                <div class="pf-busy__title">{{ syncing === 'new' ? 'Checking for new listings…' : 'Importing your listings…' }}</div>
                <div class="pf-busy__msg pf-msg" :class="{ 'is-out': rotator.out }">{{ rotator.text }}</div>
                <div class="fbbar"><span></span></div>
                <div class="pf-busy__note">Duplicates are skipped automatically — this can take a minute with many photos.</div>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * CRM › Integrations › Property Finder (resources/views/portal/crm/integrations/_property_finder).
 * An agency / independent agent connects with its API key + secret and imports its listings; an
 * agency agent sees the agency's connection and may sync it; Super Admin reviews every imported
 * listing before it can go live (PropertyFinderReview.vue).
 */
import { computed, h, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import http, { errorMessage } from '../../api/http';
import PfFlow from './PfFlow.vue';
import { useToast } from '../../composables/useToast';
import { useConfirm } from '../../composables/useConfirm';
import { formatDate, timeAgo } from '../../utils/format';

const { success } = useToast();
const { confirm } = useConfirm();

// Rotating status lines under the animation.
const MESSAGES = ['Connecting to Property Finder…', 'Fetching your live listings…', 'Matching locations & amenities…', 'Downloading photos…', 'Skipping listings already here…', 'Sending new ones for review…'];
const steps = [
    { icon: 'fas fa-plug', title: 'Connect', text: 'API key & secret' },
    { icon: 'fas fa-cloud-arrow-down', title: 'Import', text: 'Live listings come in' },
    { icon: 'fas fa-user-shield', title: 'Review', text: 'MW Realty checks them' },
    { icon: 'fas fa-globe', title: 'Live', text: 'On the website' },
];

// A count that "bumps" each time it changes (.pf-count.is-bump).
const PfCount = {
    props: { value: { type: Number, default: 0 } },
    setup(props) {
        const bump = ref(false);
        watch(() => props.value, () => {
            bump.value = false;
            requestAnimationFrame(() => {
                bump.value = true;
            });
        });
        return () => h('strong', { class: ['pf-count', { 'is-bump': bump.value }] }, String(props.value));
    },
};

const data = ref(null);
const loadError = ref('');
const errorAlert = ref('');
const syncing = ref(null); // the mode just clicked, while its overlay shows
const menu = ref(null);
const menuOpen = ref(false);
const keysOpen = ref(false);
const connectForm = reactive({ api_key: '', api_secret: '', show: false, saving: false });
const keysForm = reactive({ api_key: '', api_secret: '', show: false, saving: false });
const live = reactive({ status: null, added: 0, skipped: 0, failed: 0, waiting: false });
const rotator = reactive({ text: MESSAGES[0], out: false });
let rotateTimer = null;
let pollTimer = null;

const pf = computed(() => data.value?.connection);

// Where the account is in the flow (the step strip): 1 connect · 2 import · 3 review · 4 live.
const step = computed(() => {
    if (!pf.value) return 1;
    if (!pf.value.last_full_sync_at || pf.value.syncing) return 2;
    if (data.value.pending > 0) return 3;
    return 4;
});
const stepState = (n) => (n < step.value || (n === 4 && step.value === 4) ? 'is-done' : (n === step.value ? 'is-current' : ''));

function startRotating(from = 0) {
    if (rotateTimer) return;
    let i = from;
    rotator.text = MESSAGES[i];
    rotateTimer = setInterval(() => {
        rotator.out = true;
        setTimeout(() => {
            i = (i + 1) % MESSAGES.length;
            rotator.text = MESSAGES[i];
            rotator.out = false;
        }, 300);
    }, 2600);
}

function stopRotating() {
    clearInterval(rotateTimer);
    rotateTimer = null;
}

function load() {
    loadError.value = '';
    return http.get('/integrations/property-finder')
        .then((res) => {
            data.value = res.data;
            const sync = res.data.connection?.sync;
            if (sync) Object.assign(live, { status: sync.status, added: sync.added, skipped: sync.skipped, failed: sync.failed, waiting: sync.waiting });
            stopRotating();
            rotator.text = MESSAGES[1];
            clearTimeout(pollTimer);
            if (res.data.connection?.syncing) poll();
        })
        .catch((e) => {
            loadError.value = errorMessage(e);
        });
}

// Live progress of a running sync; reload once it's done to show the result.
function poll() {
    http.get('/integrations/property-finder/status')
        .then((res) => {
            const d = res.data;
            if (d.status !== 'queued' && d.status !== 'running') {
                load();
                return;
            }
            // Status lines rotate once the worker has really started.
            if (d.status === 'running') startRotating(1);
            Object.assign(live, { status: d.status, added: d.added, skipped: d.skipped, failed: d.failed, waiting: d.waiting });
            pollTimer = setTimeout(poll, 2500);
        })
        .catch(() => {
            pollTimer = setTimeout(poll, 10000);
        });
}

// "Verify & connect" talks to Property Finder first.
function connect(form) {
    form.saving = true;
    errorAlert.value = '';
    http.post('/integrations/property-finder', { api_key: form.api_key, api_secret: form.api_secret })
        .then((res) => {
            success(res.data.message);
            Object.assign(form, { api_key: '', api_secret: '', show: false });
            keysOpen.value = false;
            return load();
        })
        .catch((e) => {
            errorAlert.value = errorMessage(e);
            form.api_secret = '';
        })
        .finally(() => {
            form.saving = false;
        });
}

function sync(mode) {
    syncing.value = mode;
    errorAlert.value = '';
    startRotating();
    http.post('/integrations/property-finder/sync', { mode })
        .then((res) => {
            success(res.data.message);
            return load();
        })
        .catch((e) => {
            errorAlert.value = errorMessage(e);
        })
        .finally(() => {
            syncing.value = null;
        });
}

async function disconnect() {
    menuOpen.value = false;
    if (!(await confirm({ title: 'Disconnect Property Finder?', message: 'Syncing stops. Listings already imported stay in your properties.', confirmText: 'Disconnect', tone: 'danger' }))) return;
    errorAlert.value = '';
    http.delete('/integrations/property-finder')
        .then((res) => {
            success(res.data.message);
            return load();
        })
        .catch((e) => {
            errorAlert.value = errorMessage(e);
        });
}

function onDocumentClick(event) {
    if (menu.value && !menu.value.contains(event.target)) menuOpen.value = false;
}

onMounted(() => {
    load();
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    stopRotating();
    clearTimeout(pollTimer);
    document.removeEventListener('click', onDocumentClick);
});
</script>
