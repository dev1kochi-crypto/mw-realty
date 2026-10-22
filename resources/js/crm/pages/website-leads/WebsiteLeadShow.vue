<template>
    <div v-if="!page" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <RouterLink :to="{ name: 'website-leads.index' }">Back to website leads</RouterLink></div>
    </div>
    <div v-else class="portal-lead-page">
        <!-- Header -->
        <div class="portal-card portal-lp-header mb-3">
            <div class="portal-lp-header-top">
                <RouterLink :to="{ name: 'website-leads.index', query: { status: 'all' } }" class="portal-lp-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Website leads</RouterLink>
                <div class="portal-lp-nav" role="group" aria-label="Lead navigation">
                    <RouterLink v-if="page.previous_lead_id" :to="leadRoute(page.previous_lead_id)" class="portal-lp-icon-btn" title="Previous lead" aria-label="Previous lead"><i class="fas fa-chevron-left"></i></RouterLink>
                    <a v-else class="portal-lp-icon-btn disabled" title="Previous lead" aria-label="Previous lead"><i class="fas fa-chevron-left"></i></a>
                    <RouterLink v-if="page.next_lead_id" :to="leadRoute(page.next_lead_id)" class="portal-lp-icon-btn" title="Next lead" aria-label="Next lead"><i class="fas fa-chevron-right"></i></RouterLink>
                    <a v-else class="portal-lp-icon-btn disabled" title="Next lead" aria-label="Next lead"><i class="fas fa-chevron-right"></i></a>
                    <RouterLink :to="{ name: 'website-leads.index' }" class="portal-lp-icon-btn" title="Close — back to website leads" aria-label="Close"><i class="fas fa-xmark"></i></RouterLink>
                </div>
            </div>

            <div class="portal-lp-header-main">
                <span class="portal-lp-initials">{{ initials(lead.display_name) }}</span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h1 class="portal-lp-name">{{ lead.display_name }}</h1>
                        <span class="portal-lp-status" :class="routed.length ? 'is-active' : 'is-inactive'">{{ routed.length ? 'Routed' : 'In pool' }}</span>
                    </div>
                    <div class="portal-lp-header-meta">
                        <span><i class="fas fa-hashtag" aria-hidden="true"></i>{{ lead.id }}</span>
                        <span><i class="far fa-calendar" aria-hidden="true"></i>First seen {{ formatDateTime(stats.first_seen) }}</span>
                        <span><i class="fas fa-arrow-right-to-bracket" aria-hidden="true"></i>via {{ lead.source_label }}</span>
                    </div>
                    <div class="portal-lp-header-chips">
                        <span v-if="lead.has_account" class="portal-lp-badge portal-lp-badge-accent"><i class="fas fa-user-check" aria-hidden="true"></i>Customer account</span>
                        <span v-for="crmLead in routed" :key="crmLead.id" class="portal-lp-badge" style="--badge: #1d7a3a;"><i class="fas fa-share" aria-hidden="true"></i>{{ crmLead.owner }}</span>
                        <span v-if="!routed.length" class="portal-lp-badge portal-lp-badge-warning"><i class="fas fa-inbox" aria-hidden="true"></i>Not routed yet</span>
                    </div>
                </div>
                <div class="portal-lp-quick">
                    <template v-if="lead.formatted_phone">
                        <a :href="telHref(lead.formatted_phone)" class="portal-lp-quick-btn" :title="`Call ${lead.formatted_phone}`"><i class="fas fa-phone"></i><span>Call</span></a>
                        <a :href="whatsappHref(lead.formatted_phone)" target="_blank" rel="noopener" class="portal-lp-quick-btn is-whatsapp" title="WhatsApp"><i class="fab fa-whatsapp"></i><span>WhatsApp</span></a>
                    </template>
                    <a v-if="lead.email" :href="`mailto:${lead.email}`" class="portal-lp-quick-btn" :title="`Email ${lead.email}`"><i class="fas fa-envelope"></i><span>Email</span></a>
                    <button type="button" class="portal-lp-quick-btn" title="Transfer to an agency or agent" @click="openTransfer"><i class="fas fa-share"></i><span>Transfer</span></button>
                </div>
            </div>

            <div class="portal-lp-stats">
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon"><i class="fas fa-house"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Property views</div><div class="portal-lp-stat-value">{{ stats.property_views }} <small class="text-muted fw-normal">· {{ stats.properties_viewed }} listings</small></div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon is-accent"><i class="fas fa-clock"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Time on site</div><div class="portal-lp-stat-value">{{ duration(stats.site_seconds) }}</div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon is-warning"><i class="fas fa-robot"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">AI chats</div><div class="portal-lp-stat-value">{{ stats.chats }}</div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon is-success"><i class="fas fa-clock-rotate-left"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Last active</div><div class="portal-lp-stat-value">{{ stats.last_seen ? timeAgo(stats.last_seen) : '—' }}</div></div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav portal-lp-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link" :class="{ active: tab === 'profile' }" role="tab" @click="setTab('profile')"><i class="fas fa-id-card" aria-hidden="true"></i>Profile</button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link" :class="{ active: tab === 'insights' }" role="tab" @click="setTab('insights')"><i class="fas fa-chart-line" aria-hidden="true"></i>Insights <span class="portal-lp-tab-count">{{ stats.property_views }}</span></button>
            </li>
        </ul>

        <!-- ============ Profile ============ -->
        <div v-show="tab === 'profile'" class="row g-3">
            <div class="col-lg-7">
                <section class="portal-lp-card">
                    <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon"><i class="fas fa-user"></i></span>Name</h2></header>
                    <div class="portal-lp-card-body">
                        <div class="portal-lp-field"><i class="far fa-user" aria-hidden="true"></i>{{ lead.name || '—' }}</div>
                    </div>
                </section>

                <section class="portal-lp-card">
                    <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon"><i class="fas fa-address-card"></i></span>Contact</h2></header>
                    <div class="portal-lp-card-body">
                        <div class="d-grid gap-2">
                            <div v-if="lead.formatted_phone" class="portal-lp-contact is-primary">
                                <span class="portal-lp-contact-icon"><i class="fas fa-phone" aria-hidden="true"></i></span>
                                <a class="portal-lp-contact-value" :href="telHref(lead.formatted_phone)">{{ lead.formatted_phone }}</a>
                                <div class="portal-lp-contact-actions"><a :href="whatsappHref(lead.formatted_phone)" target="_blank" rel="noopener" title="WhatsApp" class="is-whatsapp"><i class="fab fa-whatsapp"></i></a></div>
                            </div>
                            <div v-if="lead.email" class="portal-lp-contact is-primary">
                                <span class="portal-lp-contact-icon is-accent"><i class="fas fa-envelope" aria-hidden="true"></i></span>
                                <a class="portal-lp-contact-value" :href="`mailto:${lead.email}`">{{ lead.email }}</a>
                            </div>
                            <div v-if="!lead.formatted_phone && !lead.email" class="portal-lp-empty">No contact details.</div>
                        </div>
                    </div>
                </section>

                <section class="portal-lp-card">
                    <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon"><i class="fas fa-circle-info"></i></span>Lead Basic Details</h2></header>
                    <div class="portal-lp-card-body">
                        <dl class="portal-lp-grid">
                            <div class="portal-lp-fact"><dt><i class="fas fa-arrow-right-to-bracket" aria-hidden="true"></i>Came in via</dt><dd>{{ lead.source_label }}</dd></div>
                            <div class="portal-lp-fact"><dt><i class="fas fa-user-check" aria-hidden="true"></i>Customer account</dt><dd>{{ lead.has_account ? 'Yes' : 'No' }}</dd></div>
                            <div class="portal-lp-fact"><dt><i class="far fa-calendar" aria-hidden="true"></i>First seen</dt><dd>{{ formatDateTime(stats.first_seen) }}</dd></div>
                            <div class="portal-lp-fact"><dt><i class="fas fa-clock-rotate-left" aria-hidden="true"></i>Last active</dt><dd>{{ formatDateTime(stats.last_seen) }}</dd></div>
                            <div class="portal-lp-fact"><dt><i class="fas fa-magnifying-glass" aria-hidden="true"></i>Searches</dt><dd>{{ stats.searches }}</dd></div>
                            <div class="portal-lp-fact"><dt><i class="fas fa-heart" aria-hidden="true"></i>Favorites / saved searches</dt><dd>{{ stats.favorites }} / {{ stats.saved_searches }}</dd></div>
                            <div class="portal-lp-fact"><dt><i class="fas fa-envelope-open-text" aria-hidden="true"></i>Form enquiries</dt><dd>{{ stats.enquiries }}</dd></div>
                            <div class="portal-lp-fact"><dt><i class="fas fa-stopwatch" aria-hidden="true"></i>Time on listings</dt><dd>{{ duration(stats.property_seconds) }}</dd></div>
                        </dl>
                    </div>
                </section>
            </div>

            <div class="col-lg-5">
                <section class="portal-lp-card">
                    <header class="portal-lp-card-head">
                        <h2><span class="portal-lp-card-icon is-success"><i class="fas fa-share"></i></span>Routed To <span v-if="page.crm_leads.length > 1" class="portal-lp-count">{{ page.crm_leads.length }}</span></h2>
                        <button type="button" class="portal-lp-card-add" title="Transfer to an agency or agent" @click="openTransfer"><i class="fas fa-plus"></i></button>
                    </header>
                    <div class="portal-lp-card-body">
                        <div class="d-grid gap-2">
                            <RouterLink v-for="crmLead in page.crm_leads" :key="crmLead.id" :to="{ name: 'leads.show', params: { id: crmLead.id } }" class="portal-lp-contact text-decoration-none">
                                <span class="portal-lp-contact-icon is-accent"><i class="fas fa-building-user" aria-hidden="true"></i></span>
                                <span class="min-w-0 flex-grow-1">
                                    <span class="d-block fw-semibold text-truncate">{{ crmLead.owner ?? 'Unassigned Leads' }}</span>
                                    <small class="text-muted d-block text-truncate">{{ crmLead.agent ? `Agent ${crmLead.agent} · ` : '' }}{{ formatDate(crmLead.created_at) }}{{ crmLead.property ? ` · ${crmLead.property}` : '' }}</small>
                                </span>
                                <i class="fas fa-chevron-right text-muted small" aria-hidden="true"></i>
                            </RouterLink>
                            <div v-if="!page.crm_leads.length" class="portal-lp-empty">Not routed yet — they haven't viewed a listing that belongs to an agency or agent.</div>
                        </div>
                        <button type="button" class="btn btn-portal-primary w-100 mt-3" @click="openTransfer"><i class="fas fa-share me-1"></i>Transfer lead</button>
                    </div>
                </section>

                <section class="portal-lp-card">
                    <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon is-warning"><i class="fas fa-fire"></i></span>Most Interested In</h2></header>
                    <div class="portal-lp-card-body">
                        <div class="d-grid gap-2">
                            <div v-for="(row, i) in page.top_properties" :key="i" class="portal-lp-property mb-0">
                                <span class="portal-lp-property-icon"><i class="fas fa-building"></i></span>
                                <div class="min-w-0 flex-grow-1">
                                    <a :href="row.url" target="_blank" rel="noopener" class="portal-lp-property-title">{{ row.title }} <i class="fas fa-arrow-up-right-from-square"></i></a>
                                    <div class="portal-lp-property-ref">{{ row.views }} {{ plural('view', row.views) }} · {{ duration(row.seconds) }} · {{ row.owner }}</div>
                                </div>
                            </div>
                            <div v-if="!page.top_properties.length" class="portal-lp-empty">No property detail pages viewed yet.</div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- ============ Insights ============ -->
        <div v-if="tab === 'insights'">
            <VisitorInsightsPanel :key="lead.id" :base-url="`/website-leads/${lead.id}/insights`" :lead-name="lead.display_name" />
        </div>

        <TransferModal :selection="transferSelection" @close="transferSelection = null" @done="afterTransfer" />
    </div>
</template>

<script setup>
/**
 * Website lead profile (Super Admin) — resources/views/portal/crm/website-leads/show: same layout
 * as the CRM lead page (header, stats, tabs, cards). Profile tab = who they are and where they were
 * routed, plus Transfer; Insights tab = their tracked website activity (VisitorInsightsPanel).
 */
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import VisitorInsightsPanel from '../../components/VisitorInsightsPanel.vue';
import TransferModal from './TransferModal.vue';
import { useToast } from '../../composables/useToast';
import { useSession } from '../../composables/useSession';
import { duration, formatDate, formatDateTime, initials, plural, telHref, timeAgo, whatsappHref } from '../../utils/format';

const props = defineProps({
    id: { type: Number, required: true },
});

const route = useRoute();
const router = useRouter();
const { success } = useToast();
const { refreshNavigation } = useSession();

const page = ref(null);
const loadError = ref('');
const tab = ref('profile');
const transferSelection = ref(null);

const lead = computed(() => page.value.lead);
const stats = computed(() => page.value.stats);
const routed = computed(() => page.value.crm_leads.filter((crmLead) => crmLead.routed));

const leadRoute = (id) => ({ name: 'website-leads.show', params: { id } });

function setTab(key) {
    tab.value = key;
    router.replace({ hash: key === 'profile' ? '' : `#${key}` });
}

function load() {
    return http.get(`/website-leads/${props.id}`)
        .then((res) => {
            page.value = res.data;
            document.title = `${res.data.lead.display_name} — Website Lead - Partner Portal`;
        })
        .catch((e) => {
            loadError.value = errorMessage(e);
        });
}

function openTransfer() {
    transferSelection.value = { ids: [lead.value.id], count: 1 };
}

function afterTransfer(message) {
    transferSelection.value = null;
    success(message);
    load();
    refreshNavigation();
}

// …/website-leads/{id}#insights opens straight on the Insights tab; previous / next reuse this screen.
watch(() => props.id, () => {
    page.value = null;
    loadError.value = '';
    tab.value = route.hash === '#insights' ? 'insights' : 'profile';
    load();
}, { immediate: true });
</script>
