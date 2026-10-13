<template>
    <div class="crm-credits" :class="{ 'is-low': low }">
        <div class="crm-credits__top">
            <RouterLink :to="{ name: 'credits.index' }" class="crm-credits__label" title="Credits — usage history"><i class="fas fa-coins"></i> Credits</RouterLink>
            <RouterLink :to="{ name: 'credits.index' }" class="crm-credits__value" :title="`${num(credits.balance)} credits left`">
                {{ num(credits.balance) }}<small v-if="credits.active">/{{ num(credits.active) }}</small>
            </RouterLink>
        </div>
        <!-- Limit tag → Plans (upgrade / see the allowance); the full sentence is in its tooltip. Own line: the sidebar is narrow. -->
        <RouterLink v-if="tag" :to="{ name: 'plans.index' }" class="crm-credits__tag" :class="{ 'is-full': fullLimit }" :title="hint">
            <i class="fas fa-circle-exclamation me-1"></i>{{ tag }}<i class="fas fa-arrow-right ms-1"></i>
        </RouterLink>
        <template v-if="credits.active">
            <div class="crm-credits__bar" role="progressbar" :aria-valuenow="credits.percent" aria-valuemin="0" aria-valuemax="100" :aria-label="`${credits.percent}% of your credits left`">
                <span :style="{ width: `${credits.percent}%` }"></span>
            </div>
            <div class="crm-credits__flow">
                <span>{{ num(used) }} used</span>
                <span>{{ num(credits.balance) }} left</span>
            </div>
        </template>
        <RouterLink v-if="button" :to="button.to" class="crm-credits__btn">{{ button.label }}</RouterLink>
    </div>
</template>

<script setup>
/**
 * Sidebar credits card (compact) — balance out of the credits added (still valid), how many are used,
 * a tag when the plan's allowance is nearly / fully used (opens Plans), and one action. Details live
 * on the Credits page. Shown only when credits matter (CrmNavigation::credits()).
 */
import { computed } from 'vue';

const props = defineProps({ credits: { type: Object, required: true } });

const num = (n) => Number(n || 0).toLocaleString('en-US');
const used = computed(() => Math.max(0, (props.credits.active || 0) - (props.credits.balance || 0)));
const low = computed(() => props.credits.balance > 0 && (props.credits.percent <= 25 || (props.credits.expiring?.days ?? 99) <= 3));
const fullLimit = computed(() => props.credits.limits.listings || props.credits.limits.premium);
const tag = computed(() => (fullLimit.value ? 'Plan limit reached' : (props.credits.listings_left ? `${props.credits.listings_left} listings left` : '')));
const hint = computed(() => {
    const { listings, premium } = props.credits.limits;
    const what = listings && premium ? 'listings and premium' : (listings ? 'listings' : (premium ? 'premium listings' : null));
    if (what) return `Your plan's ${what} are used up${props.credits.resets ? ` until ${props.credits.resets}` : ''}. Click to see plans, or use credits to keep going.`;
    return `Only ${props.credits.listings_left} listings left on your plan. Click to see plans.`;
});
// One action: use the credits you have when listings are full, otherwise buy (none while buying is switched off).
const button = computed(() => {
    if (props.credits.limits.listings && props.credits.balance > 0) return { to: { name: 'properties.create' }, label: 'Add listing with credits' };
    return props.credits.can_buy ? { to: { name: 'credits.index', query: { buy: 1 } }, label: 'Buy credits' } : null;
});
</script>
