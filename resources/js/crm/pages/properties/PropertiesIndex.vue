<template>
    <div v-if="!page" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <button type="button" class="btn btn-link" @click="load">Try again</button></div>
    </div>
    <div v-else>
        <!-- One panel: title + search + plan chips + actions, then filters and the DLD permit pills. -->
        <div class="pl-panel mb-3">
            <div class="pl-panel__row">
                <div class="pl-panel__title">
                    <div class="form-check mb-0" title="Select all on this page">
                        <input id="selectAllProperties" class="form-check-input" type="checkbox" :disabled="!items.length" :checked="allSelected" :indeterminate="someSelected" @change="selectAll($event.target.checked)">
                        <label class="form-check-label small text-nowrap portal-muted" for="selectAllProperties">Select all</label>
                    </div>
                    <div class="portal-section-title mb-0 text-nowrap">{{ page.is_admin ? `All ${section.title}` : `My ${section.title}` }}</div>
                </div>

                <form class="portal-list-search" role="search" @submit.prevent="applySearch">
                    <div class="portal-list-search__field">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input v-model="searchInput" type="search" class="form-control" maxlength="100"
                               :placeholder="`Search by title, ref no, RERA, address, community or city${page.is_admin ? ', agent / agency' : ''}`" :aria-label="`Search ${section.title.toLowerCase()}`">
                    </div>
                    <RouterLink v-if="page.search !== ''" :to="{ query: { ...cleanFilters() } }" class="portal-list-search__clear" title="Clear search" aria-label="Clear search"><i class="fas fa-times"></i></RouterLink>
                    <button type="submit" class="btn btn-portal-primary btn-sm px-3">Search</button>
                </form>

                <div v-if="page.plan_usage || page.featured_quota" class="portal-list-toolbar__chips">
                    <UsageChips :plan-usage="page.plan_usage" :quota="page.featured_quota" />
                </div>

                <div class="pl-panel__actions">
                    <!-- Grid (cards) / List (table) — remembered on this device. -->
                    <div class="pv-toggle" role="group" aria-label="Layout">
                        <a href="#" :class="{ 'is-active': viewMode === 'grid' }" title="Grid view" aria-label="Grid view" :aria-current="viewMode === 'grid' ? 'true' : null" @click.prevent="setView('grid')"><i class="fas fa-grip"></i><span>Grid</span></a>
                        <a href="#" :class="{ 'is-active': viewMode === 'list' }" title="List view" aria-label="List view" :aria-current="viewMode === 'list' ? 'true' : null" @click.prevent="setView('list')"><i class="fas fa-list"></i><span>List</span></a>
                    </div>
                    <CrmDropdown v-if="selected.size" button-class="btn btn-portal-danger btn-sm dropdown-toggle">
                        <template #toggle>Bulk Actions (<span>{{ selected.size }}</span>)</template>
                        <li><button class="dropdown-item" type="button" :disabled="bulkBusy" @click="bulk('active')"><i class="fas fa-check-circle text-success me-2"></i>Mark Active</button></li>
                        <li><button class="dropdown-item" type="button" :disabled="bulkBusy" @click="bulk('inactive')"><i class="fas fa-times-circle text-secondary me-2"></i>Mark Inactive</button></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button class="dropdown-item" type="button" :disabled="bulkBusy" @click="bulk('delete')"><i class="fas fa-trash text-danger me-2"></i>Delete Selected</button></li>
                    </CrmDropdown>
                    <RouterLink :to="{ name: section.routes.create }" class="btn btn-portal-primary btn-sm text-nowrap">
                        <i class="fas fa-plus me-1"></i> Add {{ section.itemLabel }}
                    </RouterLink>
                </div>
            </div>

            <div class="pl-panel__row pl-panel__row--filters">
                <!-- Filters — applied on change. -->
                <div class="pf-bar">
                    <span class="pf-bar__label"><i class="fas fa-filter me-1"></i>Filter</span>
                    <div v-if="page.can_filter_agent" ref="agentCombo" class="pf-combo">
                        <button type="button" class="pf-select pf-combo__toggle" :class="{ 'is-set': page.filters.agent }" aria-haspopup="listbox" :aria-expanded="agentOpen" @click="toggleAgentMenu">
                            <i class="fas fa-user-tie me-1"></i>
                            <span>{{ page.filters.agent === 'none' ? 'No agent (agency listings)' : (page.filter_agent?.name ?? 'All agents') }}</span>
                            <i class="fas fa-chevron-down ms-1 small"></i>
                        </button>
                        <div v-show="agentOpen" class="pf-combo__menu">
                            <input ref="agentSearchInput" v-model="agentSearch" type="search" class="form-control form-control-sm" placeholder="Search agents…" autocomplete="off" @input="onAgentSearch" @keydown.esc="agentOpen = false" @keydown.enter.prevent>
                            <div class="pf-combo__list" @scroll="onAgentScroll">
                                <template v-if="!agentTerm">
                                    <button type="button" class="pf-combo__item" :class="{ 'is-current': !page.filters.agent }" @click="setFilter('agent', null)">All agents</button>
                                    <button type="button" class="pf-combo__item" :class="{ 'is-current': page.filters.agent === 'none' }" @click="setFilter('agent', 'none')"><i class="fas fa-building me-1"></i>No agent (agency listings)</button>
                                </template>
                                <button v-for="agent in agents" :key="agent.id" type="button" class="pf-combo__item" :class="{ 'is-current': String(page.filters.agent) === String(agent.id) }" @click="setFilter('agent', agent.id)">{{ agent.name }}</button>
                                <div v-if="agentsLoaded && !agents.length && agentPage === 2" class="pf-combo__status">No agents found.</div>
                                <div v-if="agentsFailed" class="pf-combo__status">Could not load agents.</div>
                            </div>
                        </div>
                    </div>
                    <select class="pf-select" :class="{ 'is-set': page.filters.listing }" aria-label="Buy or rent" :value="page.filters.listing || ''" @change="setFilter('listing', $event.target.value)">
                        <option value="">Buy &amp; Rent</option>
                        <option value="sale">Buy</option>
                        <option value="rent">Rent</option>
                    </select>
                    <select class="pf-select" :class="{ 'is-set': page.filters.status }" aria-label="Status" :value="page.filters.status || ''" @change="setFilter('status', $event.target.value)">
                        <option value="">Active &amp; Inactive</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <label class="pf-select pf-check" :class="{ 'is-set': page.filters.premium }">
                        <input type="checkbox" :checked="page.filters.premium" @change="setFilter('premium', $event.target.checked ? 1 : null)"> <i class="fas fa-star text-warning"></i> Premium only
                    </label>
                    <RouterLink v-if="page.filtered" :to="{ query: page.search ? { q: page.search } : {} }" class="pf-clear"><i class="fas fa-times me-1"></i>Clear filters</RouterLink>
                </div>

                <!-- DLD permit review: each pill filters the grid (click again to clear). -->
                <div v-if="reviewTotal > 0" class="pr-review" aria-label="DLD permit review">
                    <span class="pr-review__title" title="A listing goes live once its advertising permit is verified (Validate in Core details).">
                        <i class="fas fa-file-shield"></i> Permit
                        <span v-if="!page.is_admin && needsAction > 0" class="pr-review__alert" title="Listings that need your action">{{ needsAction }}</span>
                    </span>
                    <div class="pr-review__pills">
                        <RouterLink v-for="pill in reviewPills" :key="pill.key" :to="{ query: { ...cleanFilters(), review: pill.active ? undefined : pill.key, q: page.search || undefined } }"
                                    class="pr-review__pill" :class="[`pr-tone-${pill.key}`, { 'is-active': pill.active, 'is-empty': pill.count === 0, 'is-urgent': pill.actionable && pill.count > 0 }]"
                                    :aria-current="pill.active ? 'true' : null" :title="`${pill.label}: ${pill.hint} — ${pill.active ? 'click to show all' : 'click to show only these'}`">
                            <i class="fas" :class="pill.icon"></i>
                            <strong>{{ number(pill.count) }}</strong>
                            <span>{{ pill.label }}</span>
                            <i v-if="pill.active" class="fas fa-times pr-review__x" aria-hidden="true"></i>
                        </RouterLink>
                    </div>
                </div>
            </div>

            <div v-if="page.search !== '' || page.filtered" class="pl-panel__foot">
                <strong>{{ page.meta.total }}</strong> result{{ page.meta.total === 1 ? '' : 's' }}<template v-if="page.search !== ''"> for &ldquo;<strong>{{ page.search }}</strong>&rdquo;</template> · drag to reorder is off while searching or filtering &mdash; use <i class="fas fa-sort"></i> <strong>Move to</strong> on a card instead.
            </div>
            <div v-else-if="canDrag" class="pl-panel__foot"><i class="fas fa-grip-vertical me-1"></i> Drag a card by its handle to reorder this page, or use <i class="fas fa-sort"></i> <strong>Move to</strong> to send it to any position.</div>
        </div>

        <!-- List view -->
        <div v-if="viewMode === 'list' && items.length" class="portal-card p-0 pl-list">
            <div class="table-responsive">
                <table class="table mb-0 pl-list__table">
                    <thead>
                        <tr>
                            <th class="pl-list__check"></th>
                            <th>Listing</th>
                            <th>Details</th>
                            <th class="text-center">Quality</th>
                            <th>Agent</th>
                            <th class="text-center">Leads</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th class="pl-list__actions-col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in items" :key="p.id" class="portal-property-col" :data-id="p.id" data-property-row>
                            <td class="pl-list__check"><input class="form-check-input property-select-checkbox" type="checkbox" :checked="selected.has(p.id)" aria-label="Select this property" @change="toggleSelect(p, $event.target.checked)"></td>
                            <td>
                                <div class="pl-list__listing">
                                    <img :src="p.thumb || 'https://placehold.co/96x72?text=%20'" alt="" class="pl-list__thumb">
                                    <div class="min-w-0">
                                        <RouterLink :to="{ name: section.routes.show, params: { id: p.id } }" class="pl-list__title" data-property-title :title="p.title">{{ p.title }}</RouterLink>
                                        <div class="pl-list__sub">
                                            <span>{{ p.reference_no || `#${p.id}` }}</span>
                                            <span v-if="p.featured" class="pl-list__tag is-premium"><i class="fas fa-star"></i> Premium</span>
                                            <span v-if="!p.live" class="pl-list__tag" :class="`pr-tone-${p.compliance_status}`">{{ p.compliance_label }}</span>
                                        </div>
                                        <div v-if="p.short_address" class="pl-list__loc" :title="p.short_address"><i class="fas fa-location-dot"></i>{{ p.short_address }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="pl-list__details">
                                <div>{{ [p.listing_label || ucfirst(p.listing_type || ''), p.type_label].filter(Boolean).join(' · ') || '—' }}</div>
                                <div class="pl-list__muted">{{ [p.bedrooms ? `${p.bedrooms} bd` : null, p.sqft ? `${money(p.sqft)} sqft` : null].filter(Boolean).join(' · ') || '—' }}</div>
                            </td>
                            <td class="text-center">
                                <button type="button" class="pq-ring pq-ring--inline listing-insights" :class="`is-${p.quality.tone}`" :style="{ '--pq': p.quality.score }" :aria-label="`Quality score ${p.quality.score} of 100`"
                                        @click="insightsId = p.id" @mouseenter="showQuality(p, $event.currentTarget)" @mouseleave="hideQuality"><span>{{ p.quality.score }}%</span></button>
                            </td>
                            <td><span class="pl-list__agent" :title="p.agent?.name">{{ p.agent?.name ?? 'Agency listing' }}</span></td>
                            <td class="text-center">
                                <button type="button" class="pl-list__leads listing-insights" title="Listing performance & leads" @click="insightsId = p.id">{{ p.leads_count || '-' }}</button>
                            </td>
                            <td class="pl-list__price">{{ p.price ? `${money(p.price)} ${p.currency}` : 'On request' }}</td>
                            <td>
                                <div class="form-check form-switch mb-0 d-flex align-items-center gap-2">
                                    <input :id="`statusToggleList${p.id}`" class="form-check-input property-status-toggle m-0" type="checkbox" role="switch" :checked="p.status" :disabled="statusBusy.has(p.id)" @change="toggleStatus(p, $event.target)">
                                    <label class="form-check-label small fw-semibold status-toggle-label" :class="p.status ? 'text-success' : 'text-secondary'" :for="`statusToggleList${p.id}`">{{ p.status ? 'Active' : 'Inactive' }}</label>
                                </div>
                            </td>
                            <td class="pl-list__actions-col">
                                <div class="pl-list__actions">
                                    <button type="button" class="pl-list__icon listing-insights" title="Listing performance" aria-label="Listing performance" @click="insightsId = p.id"><i class="fas fa-chart-line"></i></button>
                                    <CrmDropdown button-class="pl-list__icon" title="More actions" aria-label="More actions">
                                        <template #toggle><i class="fas fa-ellipsis"></i></template>
                                        <li><RouterLink class="dropdown-item" :to="{ name: section.routes.edit, params: { id: p.id } }"><i class="fas fa-edit me-2"></i>Edit</RouterLink></li>
                                        <li><RouterLink class="dropdown-item" :to="{ name: section.routes.show, params: { id: p.id } }"><i class="fas fa-eye me-2"></i>View</RouterLink></li>
                                        <li><button type="button" class="dropdown-item" @click="openSold(p)"><i class="fas fa-handshake me-2"></i>Mark as {{ p.listing_type === 'rent' ? 'rented' : 'sold' }}</button></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><button type="button" class="dropdown-item text-danger" @click="destroy(p)"><i class="fas fa-trash me-2"></i>Delete</button></li>
                                    </CrmDropdown>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Grid view -->
        <div v-else-if="items.length" id="propertyGrid" class="row g-4" @dragover="onDragOver" @drop.prevent>
            <div v-for="(p, index) in items" :key="p.id" class="col-sm-6 col-lg-4 col-xl-3 portal-property-col" :class="{ 'is-dragging': dragId === p.id, 'is-moved': movedId === p.id }"
                 :data-id="p.id" :draggable="armedId === p.id" @dragstart="onDragStart($event, p)" @dragend="onDragEnd">
                <PropertyCard :p="p" :section="section" :is-admin="page.is_admin" :quota="page.featured_quota" :selected="selected.has(p.id)" :can-drag="canDrag"
                              :position="positionOffset !== null ? positionOffset + index + 1 : null" :total-listings="page.total_listings" :status-busy="statusBusy.has(p.id)"
                              @select="toggleSelect(p, $event)" @arm-drag="armedId = p.id" @insights="insightsId = p.id"
                              @quality-enter="showQuality(p, $event)" @quality-leave="hideQuality" @toggle-status="toggleStatus(p, $event)"
                              @feature="openFeature(p)" @edit-feature="(live) => openFeatureEdit(p, live)" @stop-feature="(scheduled) => stopFeature(p, scheduled)"
                              @sold="openSold(p)" @delete="destroy(p)" @move="(position) => move(p, position)" @move-to="openMoveTo(p, index)" />
            </div>
        </div>

        <div v-else class="portal-card p-5 text-center portal-empty">
            <template v-if="page.search !== '' || page.filtered">
                Nothing matches{{ page.search !== '' ? ` “${page.search}”` : '' }}{{ page.filtered ? ' these filters' : '' }}. <RouterLink :to="{ query: {} }">Clear search &amp; filters</RouterLink>.
            </template>
            <template v-else>
                No {{ section.title.toLowerCase() }} yet. <RouterLink :to="{ name: section.routes.create }">Add your first listing</RouterLink>.
            </template>
        </div>

        <div v-if="page.meta.total > 0" class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="portal-muted small">Showing {{ page.meta.from }}–{{ page.meta.to }} of {{ page.meta.total }}</span>
            <div><CrmPagination :meta="page.meta" :on-each-side="1" @change="goToPage" /></div>
        </div>

        <FeatureModal :item="featureItem" :mode="featureMode" :is-admin="page.is_admin" :quota="page.featured_quota" :admin-max-days="page.feature_max_days" @close="featureItem = null" @saved="featureItem = null; load()" />
        <SoldModal :item="soldItem" @close="soldItem = null" @saved="afterSold" />
        <ListingPerformance :property-id="insightsId" @close="insightsId = null" />

        <!-- Quality score hover breakdown, placed next to the ring (fixed, so card overflow never clips it). -->
        <Teleport to="body">
            <div v-if="qualityPop" class="pq-pop" role="tooltip" :style="qualityPop.style">
                <div class="pq-pop-head"><span>Quality score</span><span class="pq-pop-score" :class="`is-${qualityPop.quality.tone}`"><i class="fas fa-gauge-high me-1"></i>{{ qualityPop.quality.score }}/100</span></div>
                <QualityBreakdown :quality="qualityPop.quality" />
            </div>
        </Teleport>

        <!-- Move to position -->
        <CrmModal :open="!!moveItem" size="sm" bare @close="moveItem = null">
            <template #header>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-sort me-2"></i>Move to position</h5>
                    <button type="button" class="btn-close" aria-label="Close" @click="moveItem = null"></button>
                </div>
            </template>
            <form v-if="moveItem" novalidate @submit.prevent="submitMove">
                <div class="modal-body">
                    <p class="small portal-muted mb-2 text-truncate">{{ moveItem.title }}{{ moveItem.current ? ` — now #${moveItem.current}` : '' }}</p>
                    <label class="form-label fw-semibold" for="movePosition">New position</label>
                    <input id="movePosition" ref="moveInput" v-model.number="moveItem.position" type="number" class="form-control" min="1" :max="page.total_listings" required>
                    <div class="form-text">1 is shown first. {{ page.total_listings }} listings in total, {{ page.meta.per_page }} per page.</div>
                    <div v-if="moveItem.error" class="alert alert-danger small mt-3 mb-0">{{ moveItem.error }}</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-portal-light btn-sm" @click="moveItem = null">Cancel</button>
                    <button type="submit" class="btn btn-portal-primary btn-sm" :disabled="moveItem.saving">Move</button>
                </div>
            </form>
        </CrmModal>
    </div>
</template>

<script setup>
/**
 * Properties / Commercial listing page (resources/views/portal/properties/index) — card grid or
 * list, search and filters (in the URL), DLD permit review pills, bulk actions, drag-to-reorder,
 * "Move to", premium, mark sold / rented and the Listing Performance panel. GET /properties?segment=…
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import CrmDropdown from '../../components/CrmDropdown.vue';
import CrmModal from '../../components/CrmModal.vue';
import CrmPagination from '../../components/CrmPagination.vue';
import FeatureModal from './components/FeatureModal.vue';
import ListingPerformance from './components/ListingPerformance.vue';
import PropertyCard from './components/PropertyCard.vue';
import QualityBreakdown from './components/QualityBreakdown.vue';
import SoldModal from './components/SoldModal.vue';
import UsageChips from './components/UsageChips.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { number, ucfirst } from '../../utils/format';
import { money, sectionFor } from './sections';

const route = useRoute();
const router = useRouter();
const { confirm } = useConfirm();
const { error: toastError, success } = useToast();

const section = computed(() => sectionFor(route.meta.segment));
const page = ref(null);
const items = ref([]);
const loadError = ref('');
const searchInput = ref('');
const selected = ref(new Set());
const bulkBusy = ref(false);
const statusBusy = ref(new Set());
const insightsId = ref(null);
const featureItem = ref(null);
const featureMode = ref('create');
const soldItem = ref(null);
const moveItem = ref(null);
const moveInput = ref(null);
const qualityPop = ref(null);
const movedId = ref(null);

// Grid (cards) or List (table), remembered on this device.
const VIEW_KEY = 'crm.listing_view';
const readView = () => { try { return localStorage.getItem(VIEW_KEY) === 'list' ? 'list' : 'grid'; } catch (e) { return 'grid'; } };
const viewMode = ref(readView());
function setView(mode) {
    viewMode.value = mode;
    try { localStorage.setItem(VIEW_KEY, mode); } catch (e) { /* private window */ }
}

// Drag reorder only makes sense on the unfiltered list (a search result isn't a contiguous slice of
// the display order); the per-card "Move to" actions work either way.
const unfiltered = computed(() => page.value.search === '' && !page.value.filtered);
const canDrag = computed(() => unfiltered.value && items.value.length > 1);
const positionOffset = computed(() => (unfiltered.value ? (page.value.meta.current_page - 1) * page.value.meta.per_page : null));

const allSelected = computed(() => items.value.length > 0 && items.value.every((p) => selected.value.has(p.id)));
const someSelected = computed(() => selected.value.size > 0 && !allSelected.value);

// DLD permit review pills: [key, icon, hint, needs the account's action?]
const REVIEW_ORDER = [
    ['changes_requested', 'fa-rotate-left', 'Taken down by MW Realty — fix and save', true],
    ['expired', 'fa-ban', 'Add the renewed permit', true],
    ['draft', 'fa-file-circle-exclamation', 'Add permit details', true],
    ['pending', 'fa-shield-halved', 'Validate the permit / waiting for MW Realty approval', true],
    ['approved', 'fa-circle-check', 'Permit verified — can be live', false],
];
const reviewTotal = computed(() => Object.values(page.value.review_counts || {}).reduce((a, b) => a + Number(b), 0));
const reviewPills = computed(() => REVIEW_ORDER.map(([key, icon, hint, actionable]) => ({
    key, icon, hint, actionable,
    label: page.value.review_labels[key],
    count: Number(page.value.review_counts[key] ?? 0),
    active: page.value.filters.review === key,
})));
const needsAction = computed(() => reviewPills.value.filter((p) => p.actionable).reduce((sum, p) => sum + p.count, 0));

/** The current filters as query params (no empty ones). */
function cleanFilters() {
    const f = page.value.filters;
    return Object.fromEntries(Object.entries({ agent: f.agent, listing: f.listing, status: f.status, premium: f.premium ? 1 : null, review: f.review })
        .filter(([, v]) => v !== null && v !== '' && v !== false && v !== undefined));
}

function load() {
    loadError.value = '';
    const q = route.query;
    return http.get('/properties', { params: { segment: section.value.segment, q: q.q, agent: q.agent, listing: q.listing, status: q.status, premium: q.premium, review: q.review, page: q.page } })
        .then((res) => {
            page.value = res.data;
            items.value = res.data.data;
            searchInput.value = res.data.search;
            selected.value = new Set();
            highlightMoved();
        })
        .catch((e) => {
            loadError.value = errorMessage(e);
        });
}

function applySearch() {
    router.push({ query: { ...cleanFilters(), q: searchInput.value.trim() || undefined } });
}

function setFilter(key, value) {
    agentOpen.value = false;
    router.push({ query: { ...cleanFilters(), [key]: value === '' || value === null ? undefined : value, q: page.value.search || undefined } });
}

function goToPage(p) {
    router.push({ query: { ...route.query, page: p } });
}

/* ---------- selection + bulk ---------- */
function toggleSelect(p, checked) {
    const set = new Set(selected.value);
    checked ? set.add(p.id) : set.delete(p.id);
    selected.value = set;
}
function selectAll(checked) {
    selected.value = checked ? new Set(items.value.map((p) => p.id)) : new Set();
}

async function bulk(action) {
    const ids = [...selected.value];
    if (!ids.length) return;
    if (action === 'delete' && !(await confirm({
        title: `Delete ${ids.length} listing${ids.length === 1 ? '' : 's'}?`,
        message: `The selected listing${ids.length === 1 ? '' : 's'} and all their photos will be removed. This can't be undone.`,
        confirmText: `Delete ${ids.length}`,
        tone: 'danger',
    }))) return;
    bulkBusy.value = true;
    http.post('/properties/bulk-action', { ids, action })
        .then(() => load())
        .catch(() => toastError('Could not apply the bulk action. Please try again.'))
        .finally(() => { bulkBusy.value = false; });
}

/* ---------- per-listing actions ---------- */
function toggleStatus(p, input) {
    const busy = new Set(statusBusy.value);
    busy.add(p.id);
    statusBusy.value = busy;
    p.status = input.checked;
    http.post(`/properties/${p.id}/toggle-status`)
        .catch((e) => {
            p.status = !p.status;
            input.checked = p.status;
            toastError(e.response?.data?.message || 'Could not update the status. Please try again.');
        })
        .finally(() => {
            const next = new Set(statusBusy.value);
            next.delete(p.id);
            statusBusy.value = next;
        });
}

async function destroy(p) {
    const ok = await confirm({
        title: `Delete this ${section.value.itemLabel.toLowerCase()}?`,
        message: `${p.title ? `“${p.title}” ` : 'This listing '}and all its photos will be removed. This can't be undone.`,
        confirmText: 'Delete',
        tone: 'danger',
    });
    if (!ok) return;
    http.delete(`/properties/${p.id}`).finally(() => load());
}

function openFeature(p) {
    featureMode.value = 'create';
    featureItem.value = { id: p.id, title: p.title, thumb: p.thumb, ref: p.reference_no };
}
function openFeatureEdit(p, live) {
    featureMode.value = 'edit';
    featureItem.value = { id: p.id, title: p.title, thumb: p.thumb, ref: p.reference_no, live, start: live ? (p.featured_from || new Date().toLocaleDateString('en-CA')) : p.featured_from, end: p.featured_until };
}

async function stopFeature(p, scheduled) {
    const name = p.title;
    const quota = page.value.featured_quota;
    const ok = await confirm(scheduled ? {
        title: 'Cancel scheduled premium?',
        message: `${name ? `“${name}” won't be premium. ` : ''}The booking is removed, so it no longer counts toward your plan.`,
        confirmText: 'Cancel premium',
        cancelText: 'Keep it',
        tone: 'warning',
    } : {
        title: 'Remove premium now?',
        message: `${name ? `“${name}” ` : 'This listing '}loses its Premium badge and top placement on the website right away.${quota && quota.per_month ? ' It still counts toward this month\'s quota.' : ''}`,
        confirmText: 'Remove premium',
        cancelText: 'Keep premium',
        tone: 'warning',
    });
    if (!ok) return;
    http.delete(`/properties/${p.id}/feature`)
        .then(() => load())
        .catch(() => toastError('Could not update premium. Please try again.'));
}

function openSold(p) {
    soldItem.value = { id: p.id, title: p.title, ref: p.reference_no, thumb: p.thumb, price: p.price ?? '', currency: p.currency || 'AED', type: p.listing_type === 'rent' ? 'rented' : 'sold' };
}
function afterSold(data) {
    soldItem.value = null;
    success(data.message);
    // The listing moved to Sold Listings (still a /portal screen).
    window.location.href = data.redirect;
}

/* ---------- move to top / bottom / position ---------- */
// Renumbers the whole list server-side (so it works across pages), then opens the page the listing
// landed on and highlights it.
function move(p, position) {
    return http.post(`/properties/${p.id}/move`, { position })
        .then((res) => {
            movedId.value = p.id;
            if (!unfiltered.value) return load();
            const targetPage = res.data.page > 1 ? res.data.page : undefined;
            if (String(targetPage ?? '') === String(route.query.page ?? '')) return load();
            return router.push({ query: { ...route.query, page: targetPage } });
        })
        .catch((e) => {
            movedId.value = null;
            throw new Error(errorMessage(e, 'Could not move this listing.'));
        });
}
function openMoveTo(p, index) {
    const current = positionOffset.value !== null ? positionOffset.value + index + 1 : null;
    moveItem.value = { id: p.id, title: p.title, current, position: current || '', error: '', saving: false };
    nextTick(() => moveInput.value?.select());
}
function submitMove() {
    const item = moveItem.value;
    const max = page.value.total_listings;
    if (!item.position || item.position < 1 || item.position > max) {
        item.error = `Enter a position between 1 and ${max}.`;
        return;
    }
    item.saving = true;
    move({ id: item.id }, item.position)
        .then(() => { moveItem.value = null; })
        .catch((err) => { item.error = err.message; item.saving = false; });
}
function highlightMoved() {
    if (!movedId.value) return;
    const id = movedId.value;
    nextTick(() => {
        document.querySelector(`.portal-property-col[data-id="${id}"]`)?.scrollIntoView({ block: 'center' });
        setTimeout(() => { if (movedId.value === id) movedId.value = null; }, 2400);
    });
}

/* ---------- drag-and-drop reorder (grid; only the grip handle arms a drag) ---------- */
const armedId = ref(null);
const dragId = ref(null);
function onDragStart(event, p) {
    if (armedId.value !== p.id) return;
    dragId.value = p.id;
    event.dataTransfer.effectAllowed = 'move';
}
function onDragOver(event) {
    if (!dragId.value) return;
    event.preventDefault();
    const target = event.target.closest('.portal-property-col');
    if (!target || Number(target.dataset.id) === dragId.value) return;
    const rect = target.getBoundingClientRect();
    const list = [...items.value];
    const from = list.findIndex((p) => p.id === dragId.value);
    const [moved] = list.splice(from, 1);
    let to = list.findIndex((p) => p.id === Number(target.dataset.id));
    if (event.clientX - rect.left > rect.width / 2) to += 1;
    list.splice(to, 0, moved);
    items.value = list;
}
function onDragEnd() {
    if (!dragId.value) return;
    dragId.value = null;
    armedId.value = null;
    http.post('/properties/reorder', { segment: section.value.segment, order: items.value.map((p) => p.id) })
        .then(() => load())
        .catch(() => { toastError('Could not save the new order.'); load(); });
}
function disarm() { if (!dragId.value) armedId.value = null; }
document.addEventListener('mouseup', disarm);

/* ---------- quality score hover breakdown ---------- */
let hideTimer = null;
function showQuality(p, ring) {
    clearTimeout(hideTimer);
    const r = ring.getBoundingClientRect();
    qualityPop.value = { quality: p.quality, style: { left: '-9999px', top: '0px' } };
    nextTick(() => {
        const pop = document.querySelector('.pq-pop');
        if (!pop || !qualityPop.value) return;
        const width = pop.offsetWidth;
        const height = pop.offsetHeight;
        const left = Math.min(window.innerWidth - width - 12, Math.max(12, r.right - width));
        const top = r.bottom + 8 + height > window.innerHeight ? r.top - height - 8 : r.bottom + 8;
        qualityPop.value.style = { left: `${left}px`, top: `${Math.max(12, top)}px` };
    });
}
function hideQuality() {
    hideTimer = setTimeout(() => { qualityPop.value = null; }, 120);
}

/* ---------- agent filter picker: searched on the server, 20 a page, more on scroll ---------- */
const agentCombo = ref(null);
const agentSearchInput = ref(null);
const agentOpen = ref(false);
const agentSearch = ref('');
const agentTerm = ref('');
const agents = ref([]);
const agentsLoaded = ref(false);
const agentsFailed = ref(false);
const agentPage = ref(1);
let agentMore = false;
let agentLoading = false;
let agentTimer = null;
let agentRequest = 0;

function loadAgents(reset) {
    if (agentLoading && !reset) return;
    if (reset) {
        agentPage.value = 1;
        agents.value = [];
        agentsLoaded.value = false;
    }
    agentLoading = true;
    agentsFailed.value = false;
    const mine = ++agentRequest;
    http.get('/properties/agent-options', { params: { search: agentTerm.value, page: agentPage.value } })
        .then((res) => {
            if (mine !== agentRequest) return;
            agents.value.push(...res.data.data);
            agentMore = !!res.data.next_page;
            agentPage.value++;
            agentsLoaded.value = true;
        })
        .catch(() => { agentsFailed.value = true; })
        .finally(() => { if (mine === agentRequest) agentLoading = false; });
}
function toggleAgentMenu() {
    agentOpen.value = !agentOpen.value;
    if (agentOpen.value) {
        agentSearch.value = '';
        agentTerm.value = '';
        loadAgents(true);
        nextTick(() => agentSearchInput.value?.focus());
    }
}
function onAgentSearch() {
    clearTimeout(agentTimer);
    agentTimer = setTimeout(() => {
        agentTerm.value = agentSearch.value.trim();
        loadAgents(true);
    }, 250);
}
function onAgentScroll(event) {
    const list = event.target;
    if (agentMore && !agentLoading && list.scrollTop + list.clientHeight >= list.scrollHeight - 30) loadAgents(false);
}
function onPointerDown(event) {
    if (agentOpen.value && agentCombo.value && !agentCombo.value.contains(event.target)) agentOpen.value = false;
}
document.addEventListener('pointerdown', onPointerDown);

onBeforeUnmount(() => {
    document.removeEventListener('mouseup', disarm);
    document.removeEventListener('pointerdown', onPointerDown);
    clearTimeout(hideTimer);
    clearTimeout(agentTimer);
});

// Filters / page in the URL (and the menu: Properties ⇄ Commercial) → reload.
watch(() => [route.name, route.query], () => {
    if (route.name !== section.value.routes.index) return;
    load();
}, { immediate: true, deep: true });
</script>
