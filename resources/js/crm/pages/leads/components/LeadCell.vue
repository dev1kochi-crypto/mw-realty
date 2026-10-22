<template>
    <td :data-column-key="column" :class="{ 'text-muted': dateColumns.includes(column) }">
        <template v-if="column === 'lead'">
            <div class="d-flex align-items-center gap-2">
                <span class="portal-lead-avatar">{{ initials(lead.name, 1) }}</span>
                <div class="min-w-0">
                    <div class="d-flex align-items-center gap-1 flex-nowrap" style="max-width: 200px;">
                        <RouterLink :to="leadLink" class="portal-lead-name-button text-decoration-none text-truncate" style="min-width: 0;" :title="lead.name">{{ lead.name || 'Unknown' }}</RouterLink>
                        <span v-if="lead.enquiry_count > 1" class="badge rounded-pill bg-warning-subtle text-warning-emphasis flex-shrink-0" style="font-size: 0.66rem; padding: 0.2em 0.5em;"
                              :title="`Enquired ${lead.enquiry_count} times${lead.last_enquired_at ? ` — latest ${formatDate(lead.last_enquired_at)}` : ''}`">&times;{{ lead.enquiry_count }}</span>
                    </div>
                    <div class="text-muted text-truncate" style="font-size: 0.78rem; max-width: 180px;">{{ lead.email || lead.formatted_phone || '-' }}</div>
                </div>
            </div>
        </template>

        <span v-else-if="column === 'email'" class="portal-table-email">{{ lead.email || '-' }}</span>
        <template v-else-if="column === 'phone'">{{ lead.formatted_phone || '-' }}</template>
        <template v-else-if="column === 'owner'">{{ lead.owner?.name ?? '-' }}</template>

        <template v-else-if="column === 'agent'">
            <div v-if="lead.agent" class="fw-semibold">{{ lead.agent.name }}</div>
            <span v-else class="badge bg-warning-subtle text-warning-emphasis">Unassigned</span>
            <div v-if="lead.agent && lead.assignment_label" class="text-muted" style="font-size: 0.72rem;">{{ lead.assignment_label }}</div>
        </template>

        <div v-else-if="column === 'stage'" class="portal-inline-stage">
            <RemoteSelect endpoint="/leads/options/stages" :model-value="lead.stage?.id ?? null" :initial="lead.stage" fixed
                          button-class="portal-stage-picker" placeholder="No stage" @change="(stage) => $emit('stage', lead, stage)">
                <template #trigger>
                    <span v-if="lead.stage" class="portal-stage-pill" :style="{ background: `${lead.stage.color}22`, color: lead.stage.color }">
                        <span class="portal-color-dot" :style="{ background: lead.stage.color }"></span>{{ lead.stage.name }}
                    </span>
                    <span v-else class="portal-stage-picker-empty"><i class="fas fa-plus" aria-hidden="true"></i> Set stage</span>
                    <i class="fas fa-chevron-down portal-stage-picker-chevron" aria-hidden="true"></i>
                </template>
            </RemoteSelect>
        </div>

        <span v-else-if="column === 'status'" class="portal-badge-status" :class="`portal-badge-${lead.status}`">{{ ucfirst(lead.status) }}</span>
        <template v-else-if="column === 'source'">{{ lead.source?.name ?? '—' }}</template>

        <div v-else-if="column === 'tags'" class="portal-tags-cell">
            <button type="button" class="portal-tags-picker" title="Manage tags" @click="$emit('tags', lead)">
                <span v-if="lead.tags.length" class="portal-tag-chip" :style="{ background: `${lead.tags[0].color}22`, color: lead.tags[0].color }">{{ lead.tags[0].name }}</span>
                <span v-else class="portal-tags-empty"><i class="fas fa-plus" aria-hidden="true"></i> Add tags</span>
            </button>
            <button v-if="lead.tags.length > 1" type="button" class="portal-tag-overflow" :title="lead.tags.slice(1).map((t) => t.name).join(', ')" @click="$emit('tags', lead)">+{{ lead.tags.length - 1 }}</button>
            <button v-if="lead.tags.length" type="button" class="portal-tags-picker portal-tags-add" title="Add tags" @click="$emit('tags', lead)"><i class="fas fa-plus" aria-hidden="true"></i></button>
        </div>

        <span v-else-if="column === 'message'" class="portal-lead-message-excerpt">{{ truncate(lead.message || '-', 70) }}</span>
        <template v-else-if="column === 'notes'">
            <span v-if="lead.notes_count" class="portal-lead-notes-count"><i class="fas fa-note-sticky" aria-hidden="true"></i>{{ lead.notes_count }}</span>
            <span v-else class="text-muted">-</span>
        </template>
        <template v-else-if="column === 'received'">{{ formatDate(lead.created_at) }}</template>
        <template v-else-if="column === 'company'">{{ lead.company || '-' }}</template>
        <template v-else-if="column === 'country'">{{ lead.country || '-' }}</template>
        <template v-else-if="column === 'property'">
            <span v-if="lead.property" class="d-inline-block text-truncate" style="max-width: 220px;" :title="lead.property.title">{{ lead.property.title }}</span>
            <span v-else class="text-muted">-</span>
        </template>
        <template v-else-if="column === 'campaign'">
            <span v-if="lead.campaign" class="d-inline-block text-truncate" style="max-width: 220px;" :title="lead.campaign"><i class="fab fa-facebook text-primary me-1" aria-hidden="true"></i>{{ lead.campaign }}</span>
            <span v-else class="text-muted">-</span>
        </template>
        <template v-else-if="column === 'enquiries'">{{ lead.enquiry_count }}</template>
        <template v-else-if="column === 'last_enquiry'">{{ formatDate(lead.last_enquired_at ?? lead.created_at) }}</template>
        <template v-else-if="column === 'updated'">{{ formatDate(lead.updated_at) }}</template>
        <template v-else-if="column === 'page'">
            <a v-if="lead.page_url" :href="lead.page_url" target="_blank" rel="noopener" class="d-inline-block text-truncate text-decoration-none" style="max-width: 220px;" :title="lead.page_url">
                {{ lead.page_source || lead.page_url.replace(/^https?:\/\//, '') }}
            </a>
            <span v-else class="text-muted">-</span>
        </template>
        <template v-else-if="column === 'assigned'">{{ lead.assigned_at ? formatDate(lead.assigned_at) : '-' }}</template>
        <template v-else-if="column === 'closed'">{{ lead.closed_at ? formatDate(lead.closed_at) : '-' }}</template>

        <!-- Website insights — each opens the lead's Insights tab. -->
        <template v-else-if="insightColumns.includes(column)">
            <RouterLink v-if="lead.has_insights && insightValue" :to="{ ...leadLink, hash: '#insights' }" class="portal-lead-insights" title="Open website insights">{{ insightValue }}</RouterLink>
            <span v-else class="text-muted">-</span>
        </template>
    </td>
</template>

<script setup>
/** One cell of a lead row — `column` is a LeadTablePreferenceService key (resources/views/portal/crm/leads/_lead_cell). */
import { computed } from 'vue';
import RemoteSelect from '../../../components/RemoteSelect.vue';
import { duration, formatDate, initials, timeAgo, ucfirst } from '../../../utils/format';

const props = defineProps({
    lead: { type: Object, required: true },
    column: { type: String, required: true },
});
defineEmits(['stage', 'tags']);

const dateColumns = ['received', 'last_enquiry', 'updated', 'assigned', 'closed'];
const insightColumns = ['views', 'time_on_site', 'searches', 'chats', 'last_active'];
const leadLink = computed(() => ({ name: 'leads.show', params: { id: props.lead.id } }));

const insightValue = computed(() => {
    const stats = props.lead.insights;
    if (!stats) return null;
    return {
        views: stats.property_views || null,
        time_on_site: stats.seconds ? duration(stats.seconds) : null,
        searches: stats.searches || null,
        chats: stats.chats || null,
        last_active: stats.last_seen ? timeAgo(stats.last_seen) : null,
    }[props.column];
});

const truncate = (text, length) => (text.length > length ? `${text.slice(0, length - 1)}…` : text);
</script>
