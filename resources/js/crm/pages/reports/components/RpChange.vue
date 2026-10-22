<template>
    <span v-if="pct !== null && pct !== undefined" class="rp-change" :class="pct >= 0 ? 'rp-change--up' : 'rp-change--down'" :title="`vs the previous ${days} days`">
        <i class="fas" :class="pct >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'"></i>{{ Math.abs(pct) }}%
    </span>
</template>

<script setup>
/** "↑ 12%" vs the previous period, on a report KPI. */
defineProps({ pct: { type: Number, default: null }, days: { type: Number, required: true } });
</script>
