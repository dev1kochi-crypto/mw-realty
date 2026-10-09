<template>
    <div class="portal-property-card" data-property-row>
        <div class="portal-property-card__media">
            <div class="form-check portal-property-card__checkbox">
                <input class="form-check-input property-select-checkbox" type="checkbox" :checked="selected" aria-label="Select this property" @change="$emit('select', $event.target.checked)">
            </div>
            <span v-if="canDrag" class="portal-property-card__drag" title="Drag to reorder" aria-label="Drag to reorder" @mousedown="$emit('arm-drag')"><i class="fas fa-grip-vertical"></i></span>
            <img :src="p.thumb || 'https://placehold.co/400x240?text=No+Image'" alt="">
            <span v-if="p.listing_label" class="portal-property-card__badge">{{ p.listing_label }}</span>
            <span v-if="p.featured" class="portal-property-card__badge portal-property-card__badge--featured"><i class="fas fa-star"></i> Premium</span>
            <span v-else-if="p.scheduled" class="portal-property-card__badge portal-property-card__badge--scheduled"><i class="far fa-clock"></i> Scheduled</span>
            <!-- DLD permit review: not live until approved — a chip on the photo + a one-line bar in the Premium slot. -->
            <span v-if="!p.live" class="portal-property-card__review" :class="`pr-tone-${p.compliance_status}`"><i class="fas" :class="p.review.icon"></i>{{ p.compliance_label }}</span>
            <!-- Quality score — hover for the breakdown, click for Listing Performance. -->
            <button type="button" class="pq-ring listing-insights" :class="`is-${p.quality.tone}`" :style="{ '--pq': p.quality.score }" :aria-label="`Quality score ${p.quality.score} of 100 — open listing performance`"
                    @click="$emit('insights')" @mouseenter="$emit('quality-enter', $event.currentTarget)" @mouseleave="$emit('quality-leave')"><span>{{ p.quality.score }}%</span></button>
        </div>
        <div class="portal-property-card__body">
            <h3 class="portal-property-card__title" :title="p.title">{{ p.title }}</h3>
            <p class="portal-property-card__location"><i class="fas fa-map-marker-alt"></i> <span :title="p.address">{{ p.address || '—' }}</span></p>

            <div class="portal-property-card__stats">
                <span v-if="p.bedrooms"><i class="fas fa-bed"></i> {{ p.bedrooms }}</span>
                <span v-if="p.bathrooms"><i class="fas fa-bath"></i> {{ p.bathrooms }}</span>
                <span v-if="p.sqft"><i class="fas fa-ruler-combined"></i> {{ money(p.sqft) }} sq.ft</span>
            </div>

            <div class="portal-property-card__price-row">
                <span class="portal-property-card__price">{{ p.price ? `${money(p.price)} ${p.currency}` : 'Price on request' }}</span>
                <div class="form-check form-switch mb-0 d-flex align-items-center gap-2">
                    <input :id="`statusToggle${p.id}`" class="form-check-input property-status-toggle m-0" type="checkbox" role="switch" :checked="p.status" :disabled="statusBusy" @change="$emit('toggle-status', $event.target)">
                    <label class="form-check-label small fw-semibold status-toggle-label" :class="p.status ? 'text-success' : 'text-secondary'" :for="`statusToggle${p.id}`">{{ p.status ? 'Active' : 'Inactive' }}</label>
                </div>
            </div>

            <!-- Who works this listing: the assigned agent, or the agency itself when none. -->
            <div class="pf-agent">
                <template v-if="p.agent">
                    <img v-if="p.agent.avatar" :src="p.agent.avatar" alt="" class="pf-agent__avatar">
                    <span v-else class="pf-agent__avatar pf-agent__avatar--initials">{{ agentInitials }}</span>
                    <span class="text-truncate"><span class="portal-muted">Agent</span> <strong>{{ p.agent.name }}</strong></span>
                </template>
                <template v-else>
                    <span class="pf-agent__avatar pf-agent__avatar--agency"><i class="fas fa-building"></i></span>
                    <span class="text-truncate"><strong>Agency listing</strong> <span class="portal-muted">· no agent</span></span>
                </template>
            </div>

            <div class="pl-card-perf">
                <button type="button" class="listing-insights" title="Listing performance & leads" @click="$emit('insights')"><i class="fas fa-user-group"></i>Leads <strong>{{ p.leads_count || '-' }}</strong></button>
                <span class="pl-card-perf__q" :class="`is-${p.quality.tone}`"><i class="fas fa-gauge-high"></i>Quality score <strong>{{ p.quality.score }}</strong></span>
            </div>

            <div class="portal-property-card__meta">
                <span>{{ p.type_label || '—' }}</span>
                <span v-if="p.reference_no">Ref: {{ p.reference_no }}</span>
                <span v-if="isAdmin">{{ p.owner_label }}</span>
            </div>

            <RouterLink v-if="!p.live && !p.featured && !p.scheduled" :to="{ name: section.routes.edit, params: { id: p.id }, hash: '#tab-basic' }"
                        class="portal-property-card__feature pr-card-review" :class="`pr-tone-${p.compliance_status}`" :title="p.review.text">
                <span class="text-truncate"><i class="fas me-1" :class="p.review.icon"></i>{{ p.review.text }}</span>
                <span v-if="p.review.cta" class="pr-card-review__cta">{{ p.review.cta }} <i class="fas fa-arrow-right"></i></span>
            </RouterLink>
            <div v-else class="portal-property-card__feature" :class="{ 'is-featured': p.featured, 'is-scheduled': !p.featured && p.scheduled }">
                <template v-if="p.featured">
                    <span><i class="fas fa-star me-1"></i>{{ p.featured_until ? `Premium · ends ${shortDate(p.featured_until, true)}` : 'Premium · no end date' }}</span>
                    <span class="portal-property-card__feature-actions">
                        <button v-if="p.feature_editable" type="button" class="btn btn-link btn-sm p-0" @click="$emit('edit-feature', true)">Edit</button>
                        <button type="button" class="btn btn-link btn-sm p-0" @click="$emit('stop-feature', false)">Stop</button>
                    </span>
                </template>
                <template v-else-if="p.scheduled">
                    <span :title="p.featured_until ? `Ends ${shortDate(p.featured_until, true)}` : 'No end date'"><i class="far fa-clock me-1"></i>Starts {{ shortDate(p.featured_from) }}{{ p.featured_until ? ` → ${shortDate(p.featured_until)}` : '' }}</span>
                    <span class="portal-property-card__feature-actions">
                        <button v-if="p.feature_editable" type="button" class="btn btn-link btn-sm p-0" @click="$emit('edit-feature', false)">Edit</button>
                        <button type="button" class="btn btn-link btn-sm p-0" @click="$emit('stop-feature', true)">Cancel</button>
                    </span>
                </template>
                <button v-else-if="isAdmin || (quota && quota.limit > 0)" type="button" class="btn btn-link btn-sm p-0" :disabled="!isAdmin && !p.status"
                        :title="isAdmin || p.status ? '' : 'Activate the listing to make it premium'" @click="$emit('feature')"><i class="far fa-star me-1"></i>Make premium</button>
                <a v-else href="/portal/plans" class="portal-muted text-decoration-none"><i class="fas fa-lock me-1"></i>Premium needs a paid plan</a>
            </div>

            <div class="portal-property-card__actions">
                <RouterLink :to="{ name: section.routes.show, params: { id: p.id } }" class="portal-btn-ghost btn btn-sm"><i class="fas fa-eye me-1"></i>View</RouterLink>
                <RouterLink :to="{ name: section.routes.edit, params: { id: p.id } }" class="portal-btn-ghost btn btn-sm"><i class="fas fa-edit me-1"></i>Edit</RouterLink>
                <button type="button" class="portal-btn-ghost btn btn-sm portal-property-card__icon-btn listing-insights" title="Listing performance" aria-label="Listing performance" @click="$emit('insights')"><i class="fas fa-chart-line"></i></button>
                <button type="button" class="portal-btn-ghost btn btn-sm portal-property-card__icon-btn" title="Mark as sold / rented" aria-label="Mark as sold or rented" @click="$emit('sold')"><i class="fas fa-handshake"></i></button>
                <button type="button" class="portal-btn-ghost btn btn-sm portal-property-card__icon-btn" title="Delete" aria-label="Delete" @click="$emit('delete')"><i class="fas fa-trash"></i></button>
                <CrmDropdown v-if="totalListings > 1" button-class="portal-btn-ghost btn btn-sm portal-property-card__icon-btn" title="Move to" aria-label="Move to position">
                    <template #toggle><i class="fas fa-sort"></i></template>
                    <li><h6 class="dropdown-header">Move to{{ position ? ` · now #${position} of ${totalListings}` : '' }}</h6></li>
                    <li><button class="dropdown-item" type="button" :disabled="position === 1" @click="$emit('move', 'top')"><i class="fas fa-angle-double-up me-2"></i>Top (position 1)</button></li>
                    <li><button class="dropdown-item" type="button" :disabled="position === totalListings" @click="$emit('move', 'bottom')"><i class="fas fa-angle-double-down me-2"></i>Bottom (position {{ totalListings }})</button></li>
                    <li><button class="dropdown-item" type="button" @click="$emit('move-to')"><i class="fas fa-hashtag me-2"></i>Position&hellip;</button></li>
                </CrmDropdown>
            </div>
        </div>
    </div>
</template>

<script setup>
/** One listing card on the Properties / Commercial grid (resources/views/portal/properties/index). */
import { computed } from 'vue';
import CrmDropdown from '../../../components/CrmDropdown.vue';
import { money } from '../sections';

const props = defineProps({
    p: { type: Object, required: true },
    section: { type: Object, required: true },
    isAdmin: { type: Boolean, default: false },
    quota: { type: Object, default: null },
    selected: { type: Boolean, default: false },
    canDrag: { type: Boolean, default: false },
    position: { type: Number, default: null },
    totalListings: { type: Number, default: 0 },
    statusBusy: { type: Boolean, default: false },
});
defineEmits(['select', 'arm-drag', 'insights', 'quality-enter', 'quality-leave', 'toggle-status', 'feature', 'edit-feature', 'stop-feature', 'sold', 'delete', 'move', 'move-to']);

const agentInitials = computed(() => (props.p.agent?.name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join(''));

/** "2026-11-08" → "08 Nov" (or "08 Nov 2026"). */
function shortDate(iso, withYear = false) {
    if (!iso) return '';
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-GB', withYear ? { day: '2-digit', month: 'short', year: 'numeric' } : { day: '2-digit', month: 'short' });
}
</script>
