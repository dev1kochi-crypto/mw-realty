<template>
    <!-- Migrated screen → in-app route; not yet migrated → its /portal page. -->
    <RouterLink v-if="item.route" :to="{ name: item.route }" class="nav-link" :class="{ active: route.meta.nav === item.key }">
        <i v-if="item.icon" class="fas" :class="item.icon"></i> {{ item.label }}
        <NavBadge :item="item" />
    </RouterLink>
    <a v-else :href="item.legacy_url" class="nav-link">
        <i v-if="item.icon" class="fas" :class="item.icon"></i> {{ item.label }}
        <NavBadge :item="item" />
    </a>
</template>

<script setup>
import { h } from 'vue';
import { useRoute } from 'vue-router';

defineProps({ item: { type: Object, required: true } });

const route = useRoute();

const NavBadge = (props) => {
    if (props.item.locked) {
        return h('span', { class: 'portal-nav-lock', title: props.item.lock_reason }, [h('i', { class: 'fas fa-lock' })]);
    }
    return props.item.badge > 0 ? h('span', { class: 'portal-nav-badge' }, props.item.badge) : null;
};
NavBadge.props = ['item'];
</script>
