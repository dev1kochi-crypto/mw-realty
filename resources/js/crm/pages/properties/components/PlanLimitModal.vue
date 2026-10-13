<template>
    <CrmModal :open="open" bare content-class="crx__content" @close="$emit('close')">
        <template #header>
            <div class="crx__head plm__head">
                <span class="crx__head-icon plm__icon"><i class="fas fa-layer-group"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <h5 class="crx__title">{{ usage.agency ? `${usage.agency}'s listings are used up` : "You've used your plan's listings" }}</h5>
                    <p class="crx__subtitle">{{ summary }}</p>
                </div>
                <button type="button" class="btn-close" aria-label="Close" @click="$emit('close')"></button>
            </div>
        </template>

        <!-- Agency agent: the plan and credits are the agency's, so there's nothing to choose here. -->
        <div v-if="usage.agency" class="crx__body">
            <div class="plm__option">
                <span class="plm__option-icon is-plan"><i class="fas fa-building-user"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="plm__option-title">Ask {{ usage.agency }}</div>
                    <div class="plm__option-text">
                        You work on your agency's plan, so your listings count towards it. To add more, ask {{ usage.agency }} to upgrade the plan{{ usage.credits ? ' or add credits' : '' }}.
                    </div>
                </div>
            </div>
            <div v-if="usage.per_month" class="plm__wait"><i class="far fa-calendar me-1"></i> Or wait — the agency's {{ usage.limit }} new listings come back on {{ resets }}.</div>
        </div>

        <div v-else class="crx__body">
            <p class="small text-muted mb-0">You can still add this listing — choose how:</p>

            <div v-if="credits" class="plm__option is-recommended">
                <span class="plm__option-icon"><i class="fas fa-coins"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="plm__option-title">Use credits <span class="plm__badge">Add now</span></div>
                    <div class="plm__option-text">
                        Add it as an extra listing, paid with credits — the cost depends on its category, type and price.
                        You have <strong>{{ num(credits.balance) }}</strong> credits.
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <button v-if="credits.balance > 0" type="button" class="btn crx__submit btn-sm" @click="go({ name: createRoute })">Continue with credits <i class="fas fa-arrow-right ms-1"></i></button>
                        <button v-if="credits.can_buy" type="button" class="btn btn-sm" :class="credits.balance > 0 ? 'btn-portal-light' : 'crx__submit'" @click="go({ name: 'credits.index', query: { buy: 1 } })">
                            <i class="fas fa-plus me-1"></i> Buy credits
                        </button>
                        <button type="button" class="btn btn-link btn-sm px-1" @click="go({ name: 'credits.index' })">See costs</button>
                    </div>
                </div>
            </div>

            <div class="plm__option">
                <span class="plm__option-icon is-plan"><i class="fas fa-rocket"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="plm__option-title">Upgrade your plan</div>
                    <div class="plm__option-text">A bigger plan gives you more listings every month, plus more premium listings.</div>
                    <button type="button" class="btn btn-portal-light btn-sm mt-2" @click="go({ name: 'plans.index' })">Compare plans</button>
                </div>
            </div>

            <div v-if="usage.per_month && resets" class="plm__wait"><i class="far fa-calendar me-1"></i> Or wait — your {{ usage.limit }} new listings come back on {{ resets }}.</div>
        </div>
    </CrmModal>
</template>

<script setup>
/**
 * Shown instead of the Add form when the plan's listing allowance is used up: carry on with
 * credits (an extra listing), buy credits, or upgrade. `credits` = GET /navigation `credits`
 * (null when credits are off — then only upgrading is offered).
 */
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import CrmModal from '../../../components/CrmModal.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    usage: { type: Object, required: true }, // plan_usage {plan, limit, used, per_month, remaining}
    credits: { type: Object, default: null },
    createRoute: { type: String, required: true },
});
const emit = defineEmits(['close']);
const router = useRouter();

const num = (n) => Number(n || 0).toLocaleString('en-US');
const resets = computed(() => props.credits?.resets
    ?? new Date(new Date().getFullYear(), new Date().getMonth() + 1, 1).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }));
const summary = computed(() => {
    const u = props.usage;
    const who = u.agency ? `The agency's ${u.plan} plan` : u.plan;
    return u.per_month
        ? `${who} allows ${u.limit} new listings a month — all ${u.used} are used.`
        : `${who} allows ${u.limit} listings at a time — ${u.agency ? 'the agency has' : 'you have'} ${u.used}.`;
});

function go(to) {
    emit('close');
    router.push(to);
}
</script>
