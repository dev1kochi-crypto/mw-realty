<template>
    <span v-if="planUsage" class="portal-usage-chip" :class="{ 'is-danger': full }"
          :title="`${planUsage.plan} plan — ${planUsage.remaining === null ? 'unlimited listings' : `${planUsage.used} of ${planUsage.limit} listings used`} (Properties and Commercial combined)${full ? '. Upgrade to add more.' : ''}`">
        <i class="fas fa-layer-group"></i>
        <span class="portal-usage-chip__label">{{ planUsage.plan }}</span>
        <strong>{{ planUsage.remaining === null ? `${planUsage.used} / ∞` : `${planUsage.used} / ${planUsage.limit}` }}</strong>
        <span v-if="planUsage.remaining !== null" class="portal-usage-chip__meter"><span :style="{ width: `${pct}%` }"></span></span>
        <a v-if="full" :href="links.plans">Upgrade</a>
    </span>

    <template v-if="quota">
        <span v-if="quota.limit > 0" class="portal-usage-chip is-featured" :class="{ 'is-full': quota.remaining === 0 }"
              :title="`Premium: ${quota.used} of ${quota.limit} ${quota.per_month ? `used this month (resets ${quota.resets})` : 'live or scheduled — a slot frees up when one ends or you stop it'}. ${quota.max_days ? `Up to ${quota.max_days} days each.` : 'No limit on length.'}`">
            <i class="fas fa-star"></i>
            <span class="portal-usage-chip__label">Premium</span>
            <strong>{{ quota.used }} / {{ quota.limit }}</strong>
            <span class="portal-usage-chip__muted">{{ quota.per_month ? '/ month' : '' }}{{ quota.max_days ? ` · ${quota.max_days}d max` : '' }}</span>
            <RouterLink v-if="showManage" :to="{ name: 'premium.index' }">Manage</RouterLink>
        </span>
        <span v-else class="portal-usage-chip is-featured" title="Premium listings appear in the Premium section on the website. Not included in your plan.">
            <i class="fas fa-star"></i>
            <span class="portal-usage-chip__label">Premium</span>
            <span class="portal-usage-chip__muted">not in plan</span>
            <a :href="links.plans">Upgrade</a>
        </span>
    </template>
</template>

<script setup>
/** Compact plan-usage chips (listings used + premium quota) — the Blade _usage_chips partial. */
import { computed } from 'vue';

const props = defineProps({
    planUsage: { type: Object, default: null }, // {plan, limit, used, remaining}
    quota: { type: Object, default: null }, // FeaturedListingService::quota() + resets
    // The Premium page itself leaves out the chip's "Manage" link.
    showManage: { type: Boolean, default: true },
});

const links = { plans: '/portal/plans' };
const full = computed(() => props.planUsage?.remaining === 0);
const pct = computed(() => (props.planUsage.remaining === null || !props.planUsage.limit ? 0 : Math.min(100, Math.round((props.planUsage.used / props.planUsage.limit) * 100))));
</script>
