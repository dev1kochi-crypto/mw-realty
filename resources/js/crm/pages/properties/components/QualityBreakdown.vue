<template>
    <!-- Quality score breakdown (ListingQualityService::score) — the cards' hover popover. -->
    <div class="pq-breakdown">
        <div v-for="(group, g) in quality.groups" :key="g" class="pq-group">
            <template v-for="(check, i) in group" :key="i">
                <div class="pq-row" :title="check.tip || null">
                    <span class="pq-label">{{ check.label }}</span>
                    <span class="pq-points" :class="check.points >= check.max ? 'is-full' : (check.points > 0 ? 'is-part' : '')">
                        <i class="fas" :class="check.points >= check.max ? 'fa-circle-check' : (check.points > 0 ? 'fa-circle-half-stroke' : 'fa-circle-xmark')"></i>{{ check.points }}/{{ check.max }}
                    </span>
                </div>
                <div v-if="check.tip" class="pq-tip">{{ check.tip }}</div>
            </template>
        </div>
    </div>
</template>

<script setup>
defineProps({
    quality: { type: Object, required: true },
});
</script>
