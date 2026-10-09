<template>
    <div>
        <div class="mb-3">
            <RouterLink :to="{ name: 'integrations.index' }" class="portal-link-muted small"><i class="fas fa-arrow-left me-1"></i>All integrations</RouterLink>
            <div class="portal-section-title mb-0 mt-1">Facebook Lead Ads</div>
        </div>

        <div v-if="errorAlert" class="alert alert-danger">{{ errorAlert }}</div>

        <div v-if="!data" class="text-center py-5">
            <span v-if="!loadError" class="spinner-border text-primary"></span>
            <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
        </div>
        <div v-else class="portal-card intg-card mb-3">
            <div class="intg-card__head">
                <span class="intg-logo" aria-hidden="true"><i class="fab fa-facebook-f"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <h5 class="intg-card__title">Facebook Lead Ads</h5>
                    <p class="intg-card__sub">
                        <template v-if="data.is_admin">Connect Facebook Pages and assign each one to an agency or agent — the Page's lead form leads go straight to their CRM. The ad's name becomes the lead's Source.</template>
                        <template v-else>Every lead from your Facebook / Instagram lead forms arrives in Leads automatically. The ad's name becomes the lead's Source.</template>
                    </p>
                </div>
                <span v-if="!data.configured" class="intg-status intg-status--off"><i class="fas fa-circle-pause"></i>Not set up</span>
                <span v-else-if="data.connected_count" class="intg-status intg-status--ok"><i class="fas fa-circle-check"></i>{{ data.connected_count }} page{{ data.connected_count === 1 ? '' : 's' }} connected</span>
                <span v-else class="intg-status intg-status--off">Not connected</span>
            </div>

            <div class="intg-card__body">
                <template v-if="!data.configured">
                    <div v-if="data.is_admin && data.setup" class="alert alert-warning small">
                        <div class="fw-bold mb-1"><i class="fas fa-triangle-exclamation me-1"></i>Facebook app credentials missing</div>
                        Add <code>FACEBOOK_APP_ID</code>, <code>FACEBOOK_APP_SECRET</code> and <code>FACEBOOK_WEBHOOK_VERIFY_TOKEN</code> to the <code>.env</code> file, then in the Meta app (developers.facebook.com):
                        <ul class="mb-0 mt-2">
                            <li><strong>Facebook Login › Valid OAuth Redirect URIs:</strong> <span class="intg-copy">{{ data.setup.callback_url }}</span></li>
                            <li><strong>Webhooks › Page › Callback URL:</strong> <span class="intg-copy">{{ data.setup.webhook_url }}</span> (verify token = <code>FACEBOOK_WEBHOOK_VERIFY_TOKEN</code>), subscribe to <strong>leadgen</strong>.</li>
                            <li><strong>Permissions:</strong> {{ data.setup.scopes.join(', ') }}.</li>
                        </ul>
                    </div>
                    <div v-else class="alert alert-light border small mb-0"><i class="fas fa-circle-info me-1"></i>The Facebook integration isn't available yet — MW Realty is setting it up.</div>
                </template>
                <div v-else-if="!data.can_manage" class="alert alert-light border small mb-0"><i class="fas fa-circle-info me-1"></i>Sign in to your MW Realty account to connect Facebook Pages.</div>
                <template v-else>
                    <div v-if="data.is_agency_agent" class="alert alert-light border small"><i class="fas fa-circle-info me-1"></i>Pages you connect here are your own — their leads come to you as personal leads, not to your agency. Leads from your agency's Pages are still shared with you when assigned.</div>

                    <!-- Back from Facebook Login: Super Admin assigns each Page to an account; an agency / agent ticks their own. -->
                    <form v-if="pendingPages.length" class="fbpick mb-4" @submit.prevent="storePages">
                        <div class="fbpick__head">
                            <span class="fbpick__head-icon"><i class="fab fa-facebook-f"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fbpick__title">{{ data.is_admin ? 'Assign your Facebook Pages' : 'Choose the Pages to connect' }}</div>
                                <div class="fbpick__sub">
                                    {{ data.is_admin ? 'Pick the agency or agent whose CRM receives each Page\'s leads. Pages left unassigned are skipped.' : 'Leads from the lead forms on the selected Pages will come into your CRM.' }}
                                    Each Page can belong to one account only.
                                </div>
                            </div>
                            <label v-if="!data.is_admin" class="fbpick__all"><input type="checkbox" class="form-check-input mt-0" :checked="allPicked" :indeterminate="somePicked" @change="pickAll($event.target.checked)"> Select all</label>
                        </div>

                        <div class="fbpick__grid">
                            <div v-for="(page, index) in pendingPages" :key="page.id" class="fbpick__card" :class="{ 'is-blocked': page.state === 'blocked', 'is-selected': !data.is_admin && picked[page.id] }">
                                <button type="button" class="fbpick__remove" title="Remove from this list" :aria-label="`Remove ${page.name} from this list`" @click="removePage(page)"><i class="fas fa-xmark"></i></button>
                                <label class="fbpick__main" :for="!data.is_admin && page.state !== 'blocked' ? `fbPage${index}` : null">
                                    <template v-if="!data.is_admin">
                                        <input :id="`fbPage${index}`" v-model="picked[page.id]" type="checkbox" class="fbpick__check" :disabled="page.state === 'blocked'">
                                        <span class="fbpick__tick" aria-hidden="true"><i class="fas fa-check"></i></span>
                                    </template>
                                    <img v-if="page.picture" :src="page.picture" alt="" class="fbpick__pic">
                                    <span v-else class="fbpick__pic fbpick__pic--letter">{{ page.name.charAt(0).toUpperCase() }}</span>
                                    <span class="fbpick__text">
                                        <span class="fbpick__name" :title="page.name">{{ page.name }}</span>
                                        <span class="fbpick__id">ID {{ page.id }}</span>
                                    </span>
                                </label>
                                <div class="fbpick__foot">
                                    <span v-if="page.state === 'blocked'" class="fbpick__chip fbpick__chip--blocked"><i class="fas fa-ban"></i>Connected to {{ page.connected_to }}</span>
                                    <span v-else-if="page.state === 'refresh'" class="fbpick__chip fbpick__chip--ok"><i class="fas fa-rotate"></i>Connected — access will be refreshed</span>
                                    <span v-else class="fbpick__chip"><i class="fas fa-plus"></i>New Page</span>
                                </div>
                                <RemoteSelect v-if="data.is_admin && page.state !== 'blocked'" v-model="owners[page.id]" endpoint="/integrations/facebook/accounts" placeholder="Assign to agency / agent…" />
                            </div>
                        </div>

                        <!-- Bring in the leads these Pages already have (runs in the background, duplicates skipped). -->
                        <div class="fbpick__import">
                            <label class="fbpick__switch">
                                <input v-model="importExisting" type="checkbox">
                                <span class="fbpick__switch-track" aria-hidden="true"></span>
                                <span>
                                    <span class="fw-semibold d-block">Also import the leads these Pages already have</span>
                                    <span class="small text-muted">Runs in the background. People already in the CRM aren't added twice — a lead from a new ad is added to their history.</span>
                                </span>
                            </label>
                            <select v-model="importDays" class="form-select form-select-sm fbpick__days" :disabled="!importExisting" aria-label="How far back to import">
                                <option value="all">All leads (no date limit)</option>
                                <option value="90">Last 90 days</option>
                                <option value="30">Last 30 days</option>
                                <option value="7">Last 7 days</option>
                            </select>
                        </div>

                        <div class="fbpick__actions">
                            <span v-if="!data.is_admin" class="fbpick__count">{{ pickedCount }} of {{ pickable.length }} Page{{ pickable.length === 1 ? '' : 's' }} selected</span>
                            <button type="button" class="btn btn-sm portal-btn-ghost ms-auto" @click="cancelPages">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-portal-primary" :disabled="busy || (!data.is_admin && !pickedCount)"><i class="fas fa-link me-1"></i>Connect Pages</button>
                        </div>
                    </form>

                    <!-- Shown while the chosen Pages are being connected. -->
                    <div v-if="busy" class="fbbusy">
                        <div class="fbbusy__box" role="status" aria-live="polite">
                            <div class="fbbusy__logo"><i class="fab fa-facebook-f"></i><span class="fbbusy__ring"></span></div>
                            <div class="fw-bold mt-3">{{ importExisting ? 'Connecting your Pages and starting the lead import…' : 'Connecting your Pages…' }}</div>
                            <div class="small text-muted">Subscribing them to Facebook lead notifications. This takes a few seconds.</div>
                            <div class="fbbar mt-3"><span></span></div>
                        </div>
                    </div>

                    <div v-if="brokenPages.length" class="alert alert-warning small d-flex gap-2 align-items-start">
                        <i class="fas fa-plug-circle-xmark mt-1"></i>
                        <div>
                            <strong>{{ joinNames(brokenPages.map((c) => c.page_name)) }}</strong> {{ brokenPages.length === 1 ? 'has' : 'have' }} stopped sending leads — Facebook needs a fresh login (the access expired or a permission is missing).
                            Click <strong>Reconnect</strong>, log in with a Facebook account that is an admin of the Page, tick it and click <strong>Connect Pages</strong>. Then use <strong>Sync now</strong> to fetch the leads missed meanwhile.
                        </div>
                    </div>

                    <!-- Nothing connected yet: one clear starting point, with how it works alongside. -->
                    <div v-if="!data.connected_count && data.search === ''" class="fbempty">
                        <div class="fbempty__main">
                            <div class="fbempty__art" aria-hidden="true">
                                <span class="fbempty__bubble fbempty__bubble--fb"><i class="fab fa-facebook-f"></i></span>
                                <span class="fbempty__line"></span>
                                <span class="fbempty__bubble fbempty__bubble--crm"><i class="fas fa-address-card"></i></span>
                            </div>
                            <h6 class="fbempty__title">{{ pendingPages.length ? 'Pick your Pages above to finish' : (data.is_admin ? 'Connect Facebook Pages for your agencies & agents' : 'Connect your Facebook Page') }}</h6>
                            <p class="fbempty__text">{{ data.is_admin ? 'Log in with a Facebook account that manages the Pages, then assign each Page to the agency or agent who should get its leads.' : 'Log in with a Facebook account that is an admin of your Page. Leads from your lead ads then land in your CRM automatically.' }}</p>
                            <button type="button" class="btn fbempty__btn" @click="connectFacebook">
                                <i class="fab fa-facebook me-2"></i>Continue with Facebook
                            </button>
                            <div class="fbempty__note"><i class="fas fa-lock me-1"></i>Opens a secure Facebook window. MW Realty only reads your Pages' lead forms.</div>
                        </div>
                        <ol class="fbempty__steps">
                            <li><span class="fbempty__num">1</span><div><div class="fw-semibold">Connect the Page</div><div class="text-muted">Log in with Facebook and pick the Pages that run the lead ads — each Page goes to one account.</div></div></li>
                            <li><span class="fbempty__num">2</span><div><div class="fw-semibold">Leads arrive instantly</div><div class="text-muted">Each form submission becomes a lead — name, email, phone and answers included.</div></div></li>
                            <li><span class="fbempty__num">3</span><div><div class="fw-semibold">Sorted by ad</div><div class="text-muted">The ad's name is the lead's Source, so you can filter and report per campaign.</div></div></li>
                        </ol>
                    </div>
                    <div v-else class="fbtoolbar">
                        <div>
                            <div class="fbtoolbar__title">Connected Pages <span class="fbtoolbar__count">{{ data.connected_count }}</span></div>
                            <div class="small text-muted">{{ data.is_admin ? 'Each Page sends its leads to the account shown.' : 'New leads from these Pages arrive in Leads automatically.' }}</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <form v-if="data.is_admin" class="fbtoolbar__search" @submit.prevent="applySearch">
                                <i class="fas fa-search"></i>
                                <input v-model="searchInput" type="search" class="form-control form-control-sm" placeholder="Search Page or account…" aria-label="Search Pages">
                            </form>
                            <button type="button" class="btn btn-sm text-white" style="background: #1877f2;" title="Opens a Facebook window" @click="connectFacebook">
                                <i class="fab fa-facebook me-1"></i>Connect another Page
                            </button>
                        </div>
                    </div>

                    <template v-if="data.connections.length">
                        <!-- Bulk disconnect -->
                        <div v-if="bulk.size" class="fbbulk">
                            <span class="fbbulk__count"><i class="fas fa-check-square me-1"></i>{{ bulk.size }} selected</span>
                            <button type="button" class="btn btn-sm portal-btn-ghost" @click="bulk = new Set()">Clear</button>
                            <button type="button" class="btn btn-sm btn-danger" @click="bulkDisconnect"><i class="fas fa-link-slash me-1"></i>Disconnect selected</button>
                        </div>
                        <div class="table-responsive mb-3">
                            <table class="table portal-table align-middle mb-0">
                                <thead><tr>
                                    <th class="fbcheck-col"><input type="checkbox" class="form-check-input" aria-label="Select all Pages" :checked="bulkAll" :indeterminate="bulk.size > 0 && !bulkAll" @change="toggleBulkAll($event.target.checked)"></th>
                                    <th>Page</th><th v-if="data.is_admin">Leads go to</th><th>Status</th><th class="text-center">Leads</th><th>Last lead</th><th class="text-end">Actions</th>
                                </tr></thead>
                                <tbody>
                                    <tr v-for="connection in data.connections" :key="connection.id" :class="{ 'intg-reconnect': connection.needs_reconnect_at }">
                                        <td class="fbcheck-col"><input type="checkbox" class="form-check-input" :checked="bulk.has(connection.id)" :aria-label="`Select ${connection.page_name}`" @change="toggleBulk(connection.id, $event.target.checked)"></td>
                                        <td>
                                            <div class="fw-semibold">{{ connection.page_name }}</div>
                                            <div class="small text-muted">Connected {{ formatDate(connection.created_at) }}<template v-if="connection.connected_by"> by {{ connection.connected_by }}</template></div>
                                        </td>
                                        <td v-if="data.is_admin">
                                            <div class="fw-semibold">{{ connection.owner?.name ?? '—' }}</div>
                                            <div v-if="connection.owner" class="small text-muted">{{ connection.owner.is_agency ? 'Agency' : 'Agent' }}</div>
                                        </td>
                                        <!-- Status / leads / last-lead cells (_connection_cells) -->
                                        <td>
                                            <template v-if="connection.needs_reconnect_at">
                                                <span class="intg-status intg-status--warn"><i class="fas fa-plug-circle-xmark"></i>Reconnect needed</span>
                                                <div class="small text-danger mt-1" style="max-width: 300px;">Stopped {{ timeAgo(connection.needs_reconnect_at) }} — {{ limit(connection.last_error, 140) }}</div>
                                                <div v-if="connection.reconnect_notified_at" class="small text-muted">Owner emailed {{ formatDate(connection.reconnect_notified_at) }}</div>
                                            </template>
                                            <template v-else-if="connection.last_error">
                                                <span class="intg-status intg-status--warn" :title="connection.last_error"><i class="fas fa-triangle-exclamation"></i>Needs attention</span>
                                                <div class="small text-danger mt-1" style="max-width: 280px;">{{ limit(connection.last_error, 120) }}</div>
                                            </template>
                                            <span v-else-if="connection.subscribed_at" class="intg-status intg-status--ok"><i class="fas fa-circle-check"></i>Receiving leads</span>
                                            <span v-else class="intg-status intg-status--off">Not subscribed</span>

                                            <!-- Background lead import (ImportFacebookPageLeads) — updated live by the poller. No progress while access is broken. -->
                                            <template v-if="!connection.needs_reconnect_at">
                                                <div v-if="connection.import.in_progress" class="fbimport">
                                                    <div class="fbimport__label"><span class="fbimport__spin"></span><span>{{ importText(connection) }}</span></div>
                                                    <div class="fbbar"><span></span></div>
                                                </div>
                                                <div v-else-if="connection.import.status === 'done' && connection.import.recent" class="small text-success mt-1">
                                                    <i class="fas fa-circle-check me-1"></i>Imported {{ number(connection.import.added) }} lead{{ connection.import.added === 1 ? '' : 's' }}<template v-if="connection.import.skipped">, {{ number(connection.import.skipped) }} already in the CRM </template>
                                                </div>
                                                <div v-else-if="connection.import.status === 'empty' && connection.import.recent" class="small text-muted mt-1" style="max-width: 300px;"><i class="fas fa-inbox me-1"></i>{{ connection.import.error || 'No leads to import.' }}</div>
                                                <div v-else-if="connection.import.status === 'failed' && connection.import.recent" class="small text-danger mt-1" style="max-width: 300px;"><i class="fas fa-circle-xmark me-1"></i>Lead import stopped: {{ limit(connection.import.error, 120) }}</div>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">{{ number(connection.leads_count) }}</td>
                                        <td class="small">
                                            {{ connection.last_lead_at ? timeAgo(connection.last_lead_at) : '—' }}
                                            <div v-if="connection.last_synced_at" class="text-muted">Synced {{ timeAgo(connection.last_synced_at) }}</div>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <button v-if="connection.needs_reconnect_at" type="button" class="btn btn-sm text-white me-1" style="background: #1877f2;" @click="connectFacebook"><i class="fab fa-facebook me-1"></i>Reconnect</button>
                                            <div class="btn-group" role="group" :aria-label="`Sync ${connection.page_name}`">
                                                <button type="button" class="btn btn-sm portal-btn-ghost" title="Fetch leads since the last sync — all campaigns and forms" :disabled="working === connection.id" @click="sync(connection, false)"><i class="fas fa-rotate me-1"></i>Sync now</button>
                                                <button type="button" class="btn btn-sm portal-btn-ghost" title="Fetch every lead on this Page, no date limit — runs in the background" :disabled="working === connection.id" @click="sync(connection, true)"><i class="fas fa-clock-rotate-left me-1"></i>All leads</button>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-1" @click="disconnect(connection)">Disconnect</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <CrmPagination v-if="data.is_admin" :meta="data.meta" @change="goToPage" />
                    </template>
                    <p v-else-if="data.is_admin && data.search !== ''" class="text-muted small mb-0">No connected Pages match “{{ data.search }}”.</p>

                    <!-- How to connect + common problems. -->
                    <details class="fbguide">
                        <summary><i class="fas fa-book-open me-2"></i>How to connect a Facebook Page <span class="fbguide__hint">— steps &amp; troubleshooting</span></summary>
                        <div class="fbguide__body">
                            <div>
                                <div class="fbguide__heading">Before you start</div>
                                <ul>
                                    <li>Use a Facebook account with <strong>full control (admin)</strong> of the Page — in Meta Business Suite › Settings › Pages › People.</li>
                                    <li>Lead ads need a <strong>lead form</strong> (Instant Form) on the Page — create one in Meta Ads Manager or Business Suite.</li>
                                    <li>Allow pop-ups for this site — Facebook Login opens in a small window.</li>
                                </ul>
                            </div>
                            <div>
                                <div class="fbguide__heading">Connecting</div>
                                <ol>
                                    <li>Click <strong>{{ data.connected_count ? 'Connect another Page' : 'Continue with Facebook' }}</strong> and log in.</li>
                                    <li>If Facebook asks <em>"Continue with previous settings?"</em>, click <strong>Edit settings</strong>.</li>
                                    <li>Select <strong>all the Pages</strong> you want and keep <strong>every permission switched on</strong> (Pages, lead access, manage ads).</li>
                                    <li>Back here, {{ data.is_admin ? 'assign each Page to its agency / agent' : 'tick the Pages' }}, choose whether to import existing leads, and click <strong>Connect Pages</strong>.</li>
                                    <li>New leads now arrive in <strong>Leads</strong> within seconds. Use <strong>Sync now</strong> any time to fetch recent ones.</li>
                                </ol>
                            </div>
                            <div>
                                <div class="fbguide__heading">If something goes wrong</div>
                                <ul>
                                    <li><strong>Reconnect needed</strong> — Facebook stopped accepting access (password changed, admin removed, or a permission missing). Click <strong>Reconnect</strong> and repeat the steps; missed leads are fetched automatically.</li>
                                    <li><strong>"Requires … permission"</strong> — a permission was switched off during login. Remove the app in Facebook › Settings › Business integrations, then connect again with all switches on.</li>
                                    <li><strong>Already connected to another account</strong> — a Page can feed one account only; it must be disconnected there first.</li>
                                    <li><strong>No lead forms yet</strong> — the Page is connected; leads arrive once a lead form gets submissions.</li>
                                </ul>
                            </div>
                        </div>
                    </details>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * CRM › Integrations › Facebook Lead Ads (resources/views/portal/crm/integrations/facebook):
 * Super Admin connects Pages and assigns each to an agency / agent; agencies / independent agents
 * connect their own. Facebook Login opens in a popup (GET /integrations/facebook/connect-url); its
 * callback page tells this screen to reload over the "fb-connect" BroadcastChannel.
 */
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmPagination from '../../components/CrmPagination.vue';
import RemoteSelect from '../../components/RemoteSelect.vue';
import { useToast } from '../../composables/useToast';
import { useConfirm } from '../../composables/useConfirm';
import { formatDate, number, timeAgo } from '../../utils/format';

const route = useRoute();
const router = useRouter();
const { success } = useToast();
const { confirm } = useConfirm();

const data = ref(null);
const loadError = ref('');
const errorAlert = ref('');
const searchInput = ref('');
const working = ref(null);
const busy = ref(false);

// Pages picker (after Facebook Login).
const pendingPages = ref([]);
const picked = reactive({});
const owners = reactive({});
const importExisting = ref(false);
const importDays = ref('all');

// Bulk disconnect.
const bulk = ref(new Set());

// Live import progress, polled while any Page is importing.
const progress = reactive({});
let pollTimer = null;

const brokenPages = computed(() => (data.value?.connections ?? []).filter((c) => c.needs_reconnect_at));
const pickable = computed(() => pendingPages.value.filter((page) => page.state !== 'blocked'));
const pickedCount = computed(() => pickable.value.filter((page) => picked[page.id]).length);
const allPicked = computed(() => pickable.value.length > 0 && pickedCount.value === pickable.value.length);
const somePicked = computed(() => pickedCount.value > 0 && pickedCount.value < pickable.value.length);
const bulkAll = computed(() => bulk.value.size > 0 && bulk.value.size === data.value.connections.length);

const limit = (text, max) => (text && text.length > max ? `${text.slice(0, max)}...` : text || '');
const joinNames = (names) => (names.length > 1 ? `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}` : names[0]);

function importText(connection) {
    const live = progress[connection.id];
    if (!live) return connection.import.status === 'queued' ? 'Starting lead import…' : `Importing leads… ${connection.import.added} added`;
    return live.status === 'queued' ? 'Starting lead import…' : `Importing leads… ${live.added} added${live.skipped ? `, ${live.skipped} already in CRM` : ''}`;
}

function load() {
    loadError.value = '';
    return http.get('/integrations/facebook', { params: { q: route.query.q, page: route.query.page } })
        .then((res) => {
            data.value = res.data;
            searchInput.value = res.data.search;
            bulk.value = new Set();
            setPending(res.data.pending_pages);
            if (res.data.notice) {
                if (res.data.notice.type === 'error') errorAlert.value = res.data.notice.message;
                else success(res.data.notice.message);
            }
            schedulePoll();
        })
        .catch((e) => {
            loadError.value = errorMessage(e);
        });
}

function setPending(pages) {
    pendingPages.value = pages;
    Object.keys(picked).forEach((key) => delete picked[key]);
    Object.keys(owners).forEach((key) => delete owners[key]);
    pages.forEach((page) => {
        picked[page.id] = page.state !== 'blocked';
        owners[page.id] = null;
    });
    importExisting.value = false;
    importDays.value = 'all';
}

function pickAll(checked) {
    pickable.value.forEach((page) => {
        picked[page.id] = checked;
    });
}

function removePage(page) {
    pendingPages.value = pendingPages.value.filter((p) => p.id !== page.id);
    delete picked[page.id];
    delete owners[page.id];
    if (!pendingPages.value.length) cancelPages();
}

function cancelPages() {
    http.post('/integrations/facebook/pages/cancel').finally(load);
}

function storePages() {
    const body = { import_existing: importExisting.value, import_days: importExisting.value ? importDays.value : undefined };
    if (data.value.is_admin) {
        body.owners = Object.fromEntries(pendingPages.value.filter((p) => p.state !== 'blocked').map((p) => [p.id, owners[p.id] || null]));
    } else {
        body.page_ids = pickable.value.filter((p) => picked[p.id]).map((p) => p.id);
    }
    busy.value = true;
    errorAlert.value = '';
    http.post('/integrations/facebook/pages', body)
        .then((res) => {
            if (res.data.message) success(res.data.message);
            if (res.data.error) errorAlert.value = res.data.error;
            return load();
        })
        .catch((e) => {
            errorAlert.value = errorMessage(e);
        })
        .finally(() => {
            busy.value = false;
        });
}

// Facebook Login in a centred popup over this screen (it tells this screen to reload when done);
// popup blocked → this tab goes to Facebook instead.
function connectFacebook() {
    const w = 600;
    const h = 720;
    const left = window.screenX + Math.max(0, (window.outerWidth - w) / 2);
    const top = window.screenY + Math.max(0, (window.outerHeight - h) / 2);
    const popup = window.open('', 'fbConnect', `width=${w},height=${h},left=${left},top=${top},scrollbars=yes`);
    errorAlert.value = '';
    http.get('/integrations/facebook/connect-url', { params: { popup: popup ? 1 : 0 } })
        .then((res) => {
            if (popup && !popup.closed) {
                popup.location.href = res.data.url;
                popup.focus();
            } else {
                window.location.href = res.data.url;
            }
        })
        .catch((e) => {
            popup?.close();
            errorAlert.value = errorMessage(e);
        });
}

let channel = null;
try {
    channel = new BroadcastChannel('fb-connect');
    channel.onmessage = () => load();
} catch (e) {
    channel = null;
}

function after(promise) {
    errorAlert.value = '';
    return promise
        .then((res) => {
            success(res.data.message);
            return load();
        })
        .catch((e) => {
            errorAlert.value = errorMessage(e);
            return load();
        });
}

function sync(connection, all) {
    working.value = connection.id;
    after(http.post(`/integrations/facebook/${connection.id}/sync`, { all: all ? 1 : 0 })).finally(() => {
        working.value = null;
    });
}

async function disconnect(connection) {
    if (!(await confirm({ title: `Disconnect ${connection.page_name}?`, message: 'New Facebook leads from this Page will stop coming in. Leads already in the CRM stay.', confirmText: 'Disconnect', tone: 'danger' }))) return;
    after(http.delete(`/integrations/facebook/${connection.id}`));
}

function toggleBulk(id, checked) {
    const set = new Set(bulk.value);
    checked ? set.add(id) : set.delete(id);
    bulk.value = set;
}

function toggleBulkAll(checked) {
    bulk.value = checked ? new Set(data.value.connections.map((c) => c.id)) : new Set();
}

async function bulkDisconnect() {
    const names = data.value.connections.filter((c) => bulk.value.has(c.id)).map((c) => c.page_name);
    if (!(await confirm({ title: `Disconnect ${names.length} Page${names.length === 1 ? '' : 's'}?`, message: `${names.join(', ')} will stop sending new Facebook leads. Leads already in the CRM stay.`, confirmText: 'Disconnect', tone: 'danger' }))) return;
    after(http.post('/integrations/facebook/bulk-disconnect', { ids: [...bulk.value] }));
}

// Background lead imports: poll their progress; reload once they've all finished to show the totals.
function schedulePoll(delay = 3000) {
    clearTimeout(pollTimer);
    const importing = data.value?.connections.some((c) => c.import.in_progress && !c.needs_reconnect_at);
    if (importing) pollTimer = setTimeout(poll, delay);
}

function poll() {
    http.get('/integrations/facebook/import-status')
        .then((res) => {
            if (!res.data.importing.length) {
                load();
                return;
            }
            res.data.importing.forEach((row) => {
                progress[row.id] = row;
            });
            schedulePoll(3000);
        })
        .catch(() => schedulePoll(10000));
}

function applySearch() {
    router.push({ query: { q: searchInput.value.trim() || undefined } });
}

function goToPage(p) {
    router.push({ query: { ...route.query, page: p } });
}

watch(() => route.query, () => {
    if (route.name === 'integrations.facebook') load();
}, { immediate: true });

onBeforeUnmount(() => {
    clearTimeout(pollTimer);
    channel?.close();
});
</script>
