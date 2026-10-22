<template>
    <div v-if="!page" class="text-center py-5">
        <span v-if="!loadError" class="spinner-border text-primary"></span>
        <div v-else class="text-muted">{{ loadError }} <RouterLink :to="{ name: 'leads.index' }">Back to leads</RouterLink></div>
    </div>
    <div v-else class="portal-lead-page">
        <!-- Header -->
        <div class="portal-card portal-lp-header mb-3">
            <div class="portal-lp-header-top">
                <RouterLink :to="{ name: 'leads.index' }" class="portal-lp-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> All leads</RouterLink>
                <div class="portal-lp-nav" role="group" aria-label="Lead navigation">
                    <RouterLink v-if="page.previous_lead_id" :to="leadRoute(page.previous_lead_id)" class="portal-lp-icon-btn" title="Previous lead" aria-label="Previous lead"><i class="fas fa-chevron-left"></i></RouterLink>
                    <span v-else class="portal-lp-icon-btn disabled"><i class="fas fa-chevron-left"></i></span>
                    <RouterLink v-if="page.next_lead_id" :to="leadRoute(page.next_lead_id)" class="portal-lp-icon-btn" title="Next lead" aria-label="Next lead"><i class="fas fa-chevron-right"></i></RouterLink>
                    <span v-else class="portal-lp-icon-btn disabled"><i class="fas fa-chevron-right"></i></span>
                    <RouterLink :to="{ name: 'leads.index' }" class="portal-lp-icon-btn" title="Close — back to all leads" aria-label="Close"><i class="fas fa-xmark"></i></RouterLink>
                </div>
            </div>
            <div class="portal-lp-header-main">
                <span class="portal-lp-initials">{{ initials(lead.name) }}</span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h1 class="portal-lp-name">{{ lead.name || 'Unknown' }}</h1>
                        <span class="portal-lp-status" :class="`is-${status}`">{{ ucfirst(status) }}</span>
                    </div>
                    <div class="portal-lp-header-meta">
                        <span><i class="fas fa-hashtag" aria-hidden="true"></i>{{ lead.id }}</span>
                        <span><i class="far fa-calendar" aria-hidden="true"></i>Received {{ formatDateTime(lead.created_at) }}</span>
                        <span><i class="fas fa-arrow-right-to-bracket" aria-hidden="true"></i>via {{ lead.channel }}</span>
                    </div>
                    <div class="portal-lp-header-chips">
                        <span v-if="lead.stage" class="portal-lp-badge" :style="{ '--badge': lead.stage.color }" title="Stage"><span class="portal-lp-badge-dot"></span>{{ lead.stage.name }}</span>
                        <span v-else class="portal-lp-badge portal-lp-badge-empty" title="Stage"><span class="portal-lp-badge-dot"></span>No stage</span>
                        <span class="portal-lp-badge portal-lp-badge-accent" title="Source"><i class="fas fa-globe" aria-hidden="true"></i>{{ lead.source?.name || lead.channel }}</span>
                        <span v-if="lead.enquiry_count > 1" class="portal-lp-badge portal-lp-badge-warning"><i class="fas fa-repeat" aria-hidden="true"></i>Enquired {{ lead.enquiry_count }}×</span>
                        <span v-for="tag in lead.tags" :key="tag.id" class="portal-lp-badge" :style="{ '--badge': tag.color }"><i class="fas fa-tag" aria-hidden="true"></i>{{ tag.name }}</span>
                    </div>
                </div>
                <div class="portal-lp-quick">
                    <template v-if="lead.formatted_phone">
                        <a :href="telHref(lead.formatted_phone)" class="portal-lp-quick-btn" :title="`Call ${lead.formatted_phone}`"><i class="fas fa-phone"></i><span>Call</span></a>
                        <a :href="whatsappHref(lead.formatted_phone)" target="_blank" rel="noopener" class="portal-lp-quick-btn is-whatsapp" title="WhatsApp"><i class="fab fa-whatsapp"></i><span>WhatsApp</span></a>
                    </template>
                    <a v-if="lead.email" :href="`mailto:${lead.email}`" class="portal-lp-quick-btn" :title="`Email ${lead.email}`"><i class="fas fa-envelope"></i><span>Email</span></a>
                </div>
            </div>
            <div class="portal-lp-stats">
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon"><i class="fas fa-user-tie"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Assigned agent</div><div class="portal-lp-stat-value text-truncate">{{ lead.agent?.name || 'Unassigned' }}</div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon is-accent"><i class="fas fa-building"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Property</div><div class="portal-lp-stat-value text-truncate" :title="lead.property?.title">{{ lead.property?.title || '—' }}</div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon is-warning"><i class="fas fa-envelope-open-text"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Enquiries</div><div class="portal-lp-stat-value">{{ lead.enquiry_count }}</div></div>
                </div>
                <div class="portal-lp-stat">
                    <span class="portal-lp-stat-icon is-success"><i class="fas fa-clock-rotate-left"></i></span>
                    <div class="min-w-0"><div class="portal-lp-stat-label">Last updated</div><div class="portal-lp-stat-value">{{ timeAgo(lead.updated_at) }}</div></div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav portal-lp-tabs mb-3" role="tablist">
            <li v-for="t in tabs" :key="t.key" class="nav-item" role="presentation">
                <button type="button" class="nav-link" :class="{ active: tab === t.key }" role="tab" @click="setTab(t.key)">
                    <i class="fas" :class="t.icon" aria-hidden="true"></i>{{ t.label }} <span v-if="t.count !== undefined" class="portal-lp-tab-count">{{ t.count }}</span>
                </button>
            </li>
        </ul>

        <!-- ============ Profile ============ -->
        <div v-show="tab === 'profile'" class="row g-3">
            <div class="col-lg-7">
                <!-- Name -->
                <section class="portal-lp-card">
                    <header class="portal-lp-card-head">
                        <h2><span class="portal-lp-card-icon"><i class="fas fa-user"></i></span>Name</h2>
                        <button type="button" class="portal-lp-card-action" title="Edit name" @click="edit('name', { name: lead.name })"><i class="fas fa-pen"></i></button>
                    </header>
                    <div class="portal-lp-card-body">
                        <form v-if="editing === 'name'" @submit.prevent="saveFields({ name: draft.name })">
                            <input v-model="draft.name" type="text" class="form-control" required maxlength="255" aria-label="Name">
                            <FormActions :saving="saving" @cancel="editing = null" />
                        </form>
                        <div v-else class="portal-lp-field"><i class="far fa-user" aria-hidden="true"></i>{{ lead.name || '—' }}</div>
                    </div>
                </section>

                <!-- Phone numbers / Email addresses (a lead can have several of each) -->
                <section v-for="group in contactGroups" :key="group.type" class="portal-lp-card">
                    <header class="portal-lp-card-head">
                        <h2>
                            <span class="portal-lp-card-icon" :class="{ 'is-accent': group.type === 'email' }"><i class="fas" :class="group.icon"></i></span>{{ group.title }}
                            <span v-if="group.items.length > 1" class="portal-lp-count">{{ group.items.length }}</span>
                        </h2>
                        <button type="button" class="portal-lp-card-add" :title="`Add ${group.type === 'phone' ? 'phone number' : 'email'}`" @click="edit(`add-${group.type}`, { value: '', code: '+971' })"><i class="fas fa-plus"></i></button>
                    </header>
                    <div class="portal-lp-card-body">
                        <form v-if="editing === `add-${group.type}`" class="portal-lp-add-form" @submit.prevent="addContact(group.type)">
                            <PhoneInput v-if="group.type === 'phone'" v-model="draft.value" v-model:country-code="draft.code" required />
                            <input v-else v-model="draft.value" type="email" class="form-control" placeholder="name@example.com" maxlength="255" required>
                            <FormActions :saving="saving" save-label="Add" @cancel="editing = null" />
                        </form>
                        <div class="d-grid gap-2">
                            <div v-for="item in group.items" :key="item.id" class="portal-lp-contact" :class="{ 'is-primary': item.primary }">
                                <span class="portal-lp-contact-icon" :class="{ 'is-accent': group.type === 'email' }"><i class="fas" :class="group.icon" aria-hidden="true"></i></span>
                                <a class="portal-lp-contact-value" :href="group.type === 'phone' ? telHref(item.label) : `mailto:${item.value}`">{{ item.label }}</a>
                                <span v-if="item.primary" class="portal-lp-primary"><i class="fas fa-star" aria-hidden="true"></i>Primary</span>
                                <div class="portal-lp-contact-actions">
                                    <a v-if="group.type === 'phone'" :href="whatsappHref(item.label)" target="_blank" rel="noopener" title="WhatsApp" class="is-whatsapp"><i class="fab fa-whatsapp"></i></a>
                                    <button v-if="!item.primary" type="button" title="Make primary" @click="act('put', `/contacts/${item.id}/primary`)"><i class="far fa-star"></i></button>
                                    <button type="button" title="Remove" class="text-danger" @click="removeContact(item)"><i class="fas fa-trash-can"></i></button>
                                </div>
                            </div>
                            <div v-if="!group.items.length" class="portal-lp-empty">{{ group.empty }}</div>
                        </div>
                    </div>
                </section>

                <!-- Lead basic details -->
                <section class="portal-lp-card">
                    <header class="portal-lp-card-head">
                        <h2><span class="portal-lp-card-icon"><i class="fas fa-circle-info"></i></span>Lead Basic Details</h2>
                        <button type="button" class="portal-lp-card-action" title="Edit details" @click="edit('details', { company: lead.company ?? '', country: lead.country ?? '', message: lead.message ?? '' })"><i class="fas fa-pen"></i></button>
                    </header>
                    <div class="portal-lp-card-body">
                        <form v-if="editing === 'details'" @submit.prevent="saveFields({ company: draft.company, country: draft.country, message: draft.message })">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label small fw-semibold">Company</label>
                                    <input v-model="draft.company" type="text" class="form-control" maxlength="255">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label small fw-semibold">Country</label>
                                    <input v-model="draft.country" type="text" class="form-control" maxlength="100">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Enquiry message</label>
                                    <textarea v-model="draft.message" class="form-control" rows="4" maxlength="5000"></textarea>
                                </div>
                            </div>
                            <FormActions :saving="saving" @cancel="editing = null" />
                        </form>
                        <div v-else>
                            <div v-if="lead.property" class="portal-lp-property">
                                <span class="portal-lp-property-icon"><i class="fas fa-building"></i></span>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="portal-lp-label">Interested in</div>
                                    <a v-if="lead.property.url" :href="lead.property.url" target="_blank" rel="noopener" class="portal-lp-property-title">{{ lead.property.title }} <i class="fas fa-arrow-up-right-from-square"></i></a>
                                    <div v-else class="portal-lp-property-title">{{ lead.property.title }}</div>
                                    <div v-if="lead.property.reference_no" class="portal-lp-property-ref">Ref: {{ lead.property.reference_no }}</div>
                                </div>
                            </div>
                            <dl class="portal-lp-grid">
                                <div class="portal-lp-fact"><dt><i class="fas fa-briefcase" aria-hidden="true"></i>Company</dt><dd>{{ lead.company || '—' }}</dd></div>
                                <div class="portal-lp-fact"><dt><i class="fas fa-earth-asia" aria-hidden="true"></i>Country</dt><dd>{{ lead.country || '—' }}</dd></div>
                                <div class="portal-lp-fact"><dt><i class="fas fa-arrow-right-to-bracket" aria-hidden="true"></i>Came in via</dt><dd>{{ lead.channel }}</dd></div>
                                <div v-if="!lead.property" class="portal-lp-fact"><dt><i class="fas fa-building" aria-hidden="true"></i>Property</dt><dd>—</dd></div>
                                <div v-for="row in page.enquiry_details" :key="row.label" class="portal-lp-fact"><dt><i class="fas fa-list-check" aria-hidden="true"></i>{{ row.label }}</dt><dd>{{ row.value }}</dd></div>
                            </dl>
                            <div class="portal-lp-message">
                                <div class="portal-lp-label"><i class="fas fa-quote-left me-1" aria-hidden="true"></i>Enquiry message</div>
                                <div class="portal-lp-message-copy">{{ lead.message || 'No message was left with this enquiry.' }}</div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-5">
                <!-- Purchases — listings marked sold / rented to this lead -->
                <section v-if="page.purchases.length" class="portal-lp-card">
                    <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon is-success"><i class="fas fa-handshake"></i></span>Purchases</h2></header>
                    <div class="portal-lp-card-body">
                        <div v-for="(purchase, i) in page.purchases" :key="purchase.id" class="d-flex justify-content-between gap-2 py-2" :class="{ 'portal-lp-divided': i < page.purchases.length - 1 }">
                            <div class="min-w-0">
                                <a :href="purchase.url" class="fw-semibold text-decoration-none d-block text-truncate">{{ purchase.title }}</a>
                                <div class="small text-muted">
                                    {{ purchase.sold_type === 'rented' ? 'Rented' : 'Bought' }} {{ formatDate(purchase.sold_at) }}
                                    <template v-if="purchase.rented_until"> · until {{ formatDate(purchase.rented_until) }}</template>
                                    <template v-if="purchase.reference_no"> · {{ purchase.reference_no }}</template>
                                </div>
                            </div>
                            <span class="fw-bold text-nowrap">{{ purchase.currency || 'AED' }} {{ number(purchase.sold_price) }}</span>
                        </div>
                    </div>
                </section>

                <!-- Tags -->
                <section ref="tagsCard" class="portal-lp-card">
                    <header class="portal-lp-card-head">
                        <h2><span class="portal-lp-card-icon is-accent"><i class="fas fa-tags"></i></span>Tags</h2>
                        <button type="button" class="portal-lp-card-add" title="Manage tags" @click="edit('tags', { tags: [...lead.tags] })"><i class="fas fa-plus"></i></button>
                    </header>
                    <div class="portal-lp-card-body">
                        <form v-if="editing === 'tags'" @submit.prevent="saveTags">
                            <TagMultiSelect v-model="draft.tags" />
                            <FormActions :saving="saving" @cancel="editing = null" />
                        </form>
                        <div v-else class="d-flex flex-wrap gap-2">
                            <span v-for="tag in lead.tags" :key="tag.id" class="portal-lp-tag" :style="{ '--tag': tag.color }"><i class="fas fa-tag" aria-hidden="true"></i>{{ tag.name }}</span>
                            <div v-if="!lead.tags.length" class="portal-lp-empty w-100"><i class="fas fa-tags" aria-hidden="true"></i>No tags yet.</div>
                        </div>
                    </div>
                </section>

                <!-- Stage -->
                <section class="portal-lp-card">
                    <header class="portal-lp-card-head">
                        <h2><span class="portal-lp-card-icon"><i class="fas fa-layer-group"></i></span>Stage</h2>
                        <button type="button" class="portal-lp-card-action" title="Change stage" @click="edit('stage', { stage_id: lead.stage?.id ?? '' })"><i class="fas fa-pen"></i></button>
                    </header>
                    <div class="portal-lp-card-body">
                        <form v-if="editing === 'stage'" @submit.prevent="act('put', '/stage', { stage_id: draft.stage_id || null })">
                            <select v-model="draft.stage_id" class="form-select" aria-label="Stage">
                                <option value="">No stage</option>
                                <option v-for="stage in page.options.stages" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                            </select>
                            <FormActions :saving="saving" @cancel="editing = null" />
                        </form>
                        <button v-else type="button" class="portal-lp-pill-btn" title="Change stage" :style="{ '--stage': lead.stage?.color || '#6b7094' }" @click="edit('stage', { stage_id: lead.stage?.id ?? '' })">
                            <span class="portal-lp-pill-dot"></span>{{ lead.stage?.name || 'No stage' }}
                            <i class="fas fa-chevron-down portal-lp-pill-caret" aria-hidden="true"></i>
                        </button>
                    </div>
                </section>

                <!-- Source — where the lead came from; fixed once captured, so read-only. -->
                <section class="portal-lp-card">
                    <header class="portal-lp-card-head">
                        <h2><span class="portal-lp-card-icon is-accent"><i class="fas fa-globe"></i></span>Source</h2>
                        <span class="portal-lp-readonly" title="The source is fixed once the lead is captured"><i class="fas fa-lock" aria-hidden="true"></i>Read-only</span>
                    </header>
                    <div class="portal-lp-card-body">
                        <div class="portal-lp-row">
                            <span class="portal-lp-row-icon is-accent"><i class="fas fa-share-nodes"></i></span>
                            <div class="min-w-0">
                                <div class="portal-lp-row-title">{{ lead.source?.name || 'No source' }}</div>
                                <div class="portal-lp-row-sub">Came in via {{ lead.channel }}</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Owner (Super Admin) -->
                <section v-if="page.permissions.can_transfer" class="portal-lp-card">
                    <header class="portal-lp-card-head">
                        <h2><span class="portal-lp-card-icon"><i class="fas fa-building-user"></i></span>Owner</h2>
                        <button type="button" class="portal-lp-card-action" title="Transfer lead" @click="edit('owner', { owner_id: lead.owner?.id ?? null })"><i class="fas fa-pen"></i></button>
                    </header>
                    <div class="portal-lp-card-body">
                        <form v-if="editing === 'owner'" @submit.prevent="saveFields({ owner_id: draft.owner_id })">
                            <RemoteSelect v-model="draft.owner_id" endpoint="/leads/owner-options" :initial="lead.owner ? { id: lead.owner.id, name: lead.owner.name } : null" :clearable="false" placeholder="Choose an agency / agent" />
                            <div class="form-text">Transfers the lead to that account (its agent assignment is reset).</div>
                            <FormActions :saving="saving" @cancel="editing = null" />
                        </form>
                        <div v-else class="portal-lp-row">
                            <span class="portal-lp-avatar">{{ lead.owner ? initials(lead.owner.name, 1) : 'SA' }}</span>
                            <div class="min-w-0">
                                <div class="portal-lp-row-title text-truncate">{{ lead.owner?.name || 'Unassigned (Super Admin)' }}</div>
                                <div class="portal-lp-row-sub">{{ lead.owner ? `${lead.owner.is_agency ? 'Agency' : 'Agent'} account` : 'Not transferred yet' }}</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Assigned agent -->
                <section class="portal-lp-card">
                    <header class="portal-lp-card-head">
                        <h2><span class="portal-lp-card-icon is-success"><i class="fas fa-user-tie"></i></span>Assigned Agent</h2>
                        <button v-if="page.assignment.can_assign" type="button" class="portal-lp-card-action" title="Assign agent" @click="edit('agent', { agent_id: lead.agent?.id ?? '' })"><i class="fas fa-pen"></i></button>
                    </header>
                    <div class="portal-lp-card-body">
                        <form v-if="editing === 'agent'" @submit.prevent="act('post', '/assign', { agent_id: draft.agent_id || null })">
                            <select v-model="draft.agent_id" class="form-select" aria-label="Agent">
                                <option value="">— Unassigned (agency level) —</option>
                                <option v-for="agent in page.assignment.agent_options" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                            </select>
                            <FormActions :saving="saving" save-label="Assign" @cancel="editing = null" />
                        </form>
                        <div v-else class="portal-lp-row">
                            <span class="portal-lp-avatar" :class="lead.agent ? 'is-success' : 'is-empty'"><template v-if="lead.agent">{{ initials(lead.agent.name, 1) }}</template><i v-else class="fas fa-user-slash"></i></span>
                            <div class="min-w-0">
                                <div class="portal-lp-row-title text-truncate">{{ lead.agent?.name || (lead.owner?.is_agency ? 'Unassigned (agency level)' : 'Unassigned') }}</div>
                                <div v-if="lead.agent && lead.assignment_label" class="portal-lp-row-sub">{{ lead.assignment_label }}{{ lead.assigned_at ? ` · ${formatDateTime(lead.assigned_at)}` : '' }}</div>
                            </div>
                        </div>
                        <details v-if="page.assignment.history.length" class="portal-lp-history">
                            <summary><i class="fas fa-clock-rotate-left me-1" aria-hidden="true"></i>Assignment history ({{ page.assignment.history.length }})</summary>
                            <ul class="list-unstyled small mt-2 mb-0">
                                <li v-for="(row, i) in page.assignment.history" :key="i" class="mb-1">
                                    {{ formatDateTime(row.assigned_at) }} — {{ row.agent?.name || 'Unassigned' }}<template v-if="row.by"> · by {{ ucfirst(row.by) }}</template><template v-if="row.note"> · {{ row.note }}</template>
                                </li>
                            </ul>
                        </details>
                    </div>
                </section>
            </div>
        </div>

        <!-- ============ Timeline ============ -->
        <LeadTimelineTab v-show="tab === 'timeline'" ref="timelineTab" :lead-id="lead.id" :timeline="page.timeline" :notes="page.notes" :source-history="page.source_history" @note-added="reload" />

        <!-- ============ Summary ============ -->
        <div v-show="tab === 'summary'" class="row g-3">
            <div class="col-lg-6">
                <section class="portal-lp-card">
                    <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon"><i class="fas fa-id-badge"></i></span>Lead</h2></header>
                    <div class="portal-lp-card-body">
                        <dl class="portal-lead-page-summary mb-0">
                            <div><dt>Status</dt><dd><span class="portal-lp-status" :class="`is-${status}`">{{ ucfirst(status) }}</span></dd></div>
                            <div><dt>Stage</dt><dd>{{ lead.stage?.name || 'No stage' }}</dd></div>
                            <div><dt>Source</dt><dd>{{ lead.source?.name || 'No source' }}</dd></div>
                            <div><dt>Came in via</dt><dd>{{ lead.channel }}</dd></div>
                            <div v-if="page.permissions.can_transfer"><dt>Owner</dt><dd>{{ lead.owner?.name || 'Unassigned (Super Admin)' }}</dd></div>
                            <div><dt>Assigned agent</dt><dd>{{ lead.agent?.name || '—' }}</dd></div>
                            <div><dt>Property</dt><dd>{{ lead.property?.title || '—' }}</dd></div>
                            <div><dt>Tags</dt><dd>{{ lead.tags.map((t) => t.name).join(', ') || '—' }}</dd></div>
                        </dl>
                    </div>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="portal-lp-card">
                    <header class="portal-lp-card-head"><h2><span class="portal-lp-card-icon is-warning"><i class="fas fa-chart-line"></i></span>Activity</h2></header>
                    <div class="portal-lp-card-body">
                        <dl class="portal-lead-page-summary mb-0">
                            <div><dt>Received</dt><dd>{{ formatDateTime(lead.created_at) }}</dd></div>
                            <div><dt>Enquiries</dt><dd>{{ lead.enquiry_count }}</dd></div>
                            <div v-if="lead.last_enquired_at"><dt>Last enquiry</dt><dd>{{ formatDateTime(lead.last_enquired_at) }}</dd></div>
                            <div><dt>Emails / phones</dt><dd>{{ page.contacts.emails.length }} / {{ page.contacts.phones.length }}</dd></div>
                            <div><dt>Notes</dt><dd>{{ page.notes.length }}</dd></div>
                            <div><dt>Timeline entries</dt><dd>{{ page.timeline.length }}</dd></div>
                            <div v-if="lead.closed_at"><dt>Closed</dt><dd>{{ formatDateTime(lead.closed_at) }}</dd></div>
                            <div><dt>Last updated</dt><dd>{{ timeAgo(lead.updated_at) }}</dd></div>
                        </dl>
                    </div>
                </section>
            </div>
        </div>

        <!-- ============ Insights — the website visitor's tracked activity ============ -->
        <div v-if="tab === 'insights'">
            <VisitorInsightsPanel v-if="page.has_insights" :base-url="`/leads/${lead.id}/insights`" :lead-name="lead.name" />
            <section v-else class="portal-lp-card">
                <div class="portal-lp-card-body text-center text-muted py-5">
                    <i class="fas fa-chart-line fa-2x mb-3 d-block opacity-50" aria-hidden="true"></i>
                    <div class="fw-semibold mb-1">No website activity for this lead</div>
                    <div class="small">Insights — property views, time spent, searches, favorites, AI chats — appear for leads that came from the website
                        (AI chat, enquiry forms, customer accounts). This one came in via {{ lead.channel }}.</div>
                </div>
            </section>
        </div>

        <!-- Quick actions (floating "+" bottom-right) — contact, note, tag, status, delete. -->
        <div class="portal-lp-fab" :class="{ 'is-open': fabOpen }">
            <div class="portal-lp-fab-backdrop" @click="fabOpen = false"></div>
            <ul class="portal-lp-fab-menu" aria-label="Lead actions">
                <li v-if="lead.email"><span>Send Email</span><a :href="`mailto:${lead.email}`" class="portal-lp-fab-item" aria-label="Send email"><i class="fas fa-envelope"></i></a></li>
                <template v-if="lead.formatted_phone">
                    <li><span>Call</span><a :href="telHref(lead.formatted_phone)" class="portal-lp-fab-item" aria-label="Call"><i class="fas fa-phone"></i></a></li>
                    <li><span>WhatsApp</span><a :href="whatsappHref(lead.formatted_phone)" target="_blank" rel="noopener" class="portal-lp-fab-item" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a></li>
                </template>
                <li><span>Add Note</span><button type="button" class="portal-lp-fab-item" aria-label="Add note" @click="fabNote"><i class="fas fa-clipboard"></i></button></li>
                <li><span>Add Tag</span><button type="button" class="portal-lp-fab-item" aria-label="Add tag" @click="fabTag"><i class="fas fa-tags"></i></button></li>
                <li><span>Mark as {{ ucfirst(nextStatus) }}</span>
                    <button type="button" class="portal-lp-fab-item" :aria-label="`Mark as ${nextStatus}`" @click="fabOpen = false; saveFields({ status: nextStatus })">
                        <i class="fas" :class="nextStatus === 'active' ? 'fa-toggle-on' : 'fa-toggle-off'"></i>
                    </button>
                </li>
                <li v-if="page.permissions.can_delete"><span>Delete Lead</span>
                    <button type="button" class="portal-lp-fab-item portal-lp-fab-item-danger" aria-label="Delete lead" @click="deleteLead"><i class="fas fa-trash-can"></i></button>
                </li>
            </ul>
            <button type="button" class="portal-lp-fab-toggle" :aria-expanded="fabOpen" aria-label="Lead actions" @click="fabOpen = !fabOpen"><i class="fas fa-plus"></i></button>
        </div>
    </div>
</template>

<script setup>
/** The lead screen (resources/views/portal/crm/leads/show) — GET /leads/{id}; each card is edited in place. */
import { computed, h, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import http, { errorMessage } from '../../api/http';
import RemoteSelect from '../../components/RemoteSelect.vue';
import TagMultiSelect from '../../components/TagMultiSelect.vue';
import VisitorInsightsPanel from '../../components/VisitorInsightsPanel.vue';
import PhoneInput from '../../../components/PhoneInput.vue';
import LeadTimelineTab from './components/LeadTimelineTab.vue';
import { useToast } from '../../composables/useToast';
import { useConfirm } from '../../composables/useConfirm';
import { formatDate, formatDateTime, initials, number, telHref, timeAgo, ucfirst, whatsappHref } from '../../utils/format';

const props = defineProps({ id: { type: Number, required: true } });

const route = useRoute();
const router = useRouter();
const { success, error: toastError } = useToast();
const { confirm } = useConfirm();

const page = ref(null);
const loadError = ref('');
const tab = ref('profile');
const editing = ref(null);
const draft = reactive({});
const saving = ref(false);
const fabOpen = ref(false);
const tagsCard = ref(null);
const timelineTab = ref(null);

const lead = computed(() => page.value.lead);
const status = computed(() => lead.value.status || 'inactive');
const nextStatus = computed(() => (lead.value.status === 'active' ? 'inactive' : 'active'));
const leadRoute = (id) => ({ name: 'leads.show', params: { id } });

const tabs = computed(() => [
    { key: 'profile', label: 'Profile', icon: 'fa-id-card' },
    { key: 'timeline', label: 'Timeline', icon: 'fa-stream', count: page.value.timeline.length },
    { key: 'summary', label: 'Summary', icon: 'fa-chart-pie' },
    { key: 'insights', label: 'Insights', icon: 'fa-chart-line' },
]);

const contactGroups = computed(() => [
    { type: 'phone', title: 'Phone Numbers', items: page.value.contacts.phones, icon: 'fa-phone', empty: 'No phone numbers yet.' },
    { type: 'email', title: 'Email Addresses', items: page.value.contacts.emails, icon: 'fa-envelope', empty: 'No email addresses yet.' },
]);

/** Save / Cancel under a card's edit form (resources/views/portal/crm/leads/_show_form_actions). */
const FormActions = (p, { emit }) => h('div', { class: 'd-flex justify-content-end gap-2 mt-2' }, [
    h('button', { type: 'button', class: 'btn btn-sm portal-btn-ghost', onClick: () => emit('cancel') }, 'Cancel'),
    h('button', { type: 'submit', class: 'btn btn-sm btn-portal-primary', disabled: p.saving }, [
        p.saving ? h('span', { class: 'spinner-border spinner-border-sm me-1' }) : null,
        p.saveLabel || 'Save',
    ]),
]);
FormActions.props = ['saving', 'saveLabel'];
FormActions.emits = ['cancel'];

function setTab(key) {
    tab.value = key;
    router.replace({ hash: key === 'profile' ? '' : `#${key}` });
}

function load() {
    return http.get(`/leads/${props.id}`).then((res) => {
        page.value = res.data.data;
        document.title = `${page.value.lead.name || 'Lead'} — Lead - Partner Portal`;
    });
}

function reload() {
    return load().catch((e) => toastError(errorMessage(e)));
}

function edit(card, values) {
    Object.keys(draft).forEach((key) => delete draft[key]);
    Object.assign(draft, values);
    editing.value = card;
}

/** Any card save: the request, then reload so the header, timeline and summary all follow. */
function run(request) {
    saving.value = true;
    return request
        .then((res) => {
            success(res.data.message);
            editing.value = null;
            return reload();
        })
        .catch((e) => toastError(errorMessage(e)))
        .finally(() => {
            saving.value = false;
        });
}

const saveFields = (fields) => run(http.patch(`/leads/${props.id}`, fields));
const act = (method, path, body) => run(http[method](`/leads/${props.id}${path}`, body));
const saveTags = () => run(http.put(`/leads/${props.id}/tags`, { tags: draft.tags.map((t) => t.id) }));

function addContact(type) {
    run(http.post(`/leads/${props.id}/contacts`, {
        type,
        value: draft.value,
        phone_country_code: type === 'phone' ? draft.code : undefined,
    }));
}

async function removeContact(item) {
    if (!(await confirm({ title: 'Remove contact?', message: `Remove ${item.label} from this lead?`, confirmText: 'Remove', tone: 'danger' }))) return;
    act('delete', `/contacts/${item.id}`);
}

async function deleteLead() {
    fabOpen.value = false;
    if (!(await confirm({ title: 'Delete this lead?', message: 'You can restore it later from Deleted Leads.', confirmText: 'Delete', tone: 'danger' }))) return;
    http.delete(`/leads/${props.id}`)
        .then((res) => {
            success(res.data.message);
            router.push({ name: 'leads.index' });
        })
        .catch((e) => toastError(errorMessage(e)));
}

function fabNote() {
    fabOpen.value = false;
    setTab('timeline');
    nextTick(() => timelineTab.value?.focusNote());
}

function fabTag() {
    fabOpen.value = false;
    setTab('profile');
    edit('tags', { tags: [...lead.value.tags] });
    nextTick(() => tagsCard.value?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
}

function init() {
    page.value = null;
    loadError.value = '';
    editing.value = null;
    const hashTab = route.hash.replace('#', '');
    tab.value = ['timeline', 'summary', 'insights'].includes(hashTab) ? hashTab : 'profile';
    load().catch((e) => {
        loadError.value = e.response?.status === 404 ? 'This lead was not found — it may have been deleted.' : errorMessage(e);
    });
}

onMounted(init);
// Previous / next lead reuse this screen.
watch(() => props.id, init);
</script>
