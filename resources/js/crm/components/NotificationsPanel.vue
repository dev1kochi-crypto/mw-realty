<template>
    <button type="button" class="notif-bell-btn" aria-label="Notifications" @click="toggle">
        <i class="fas fa-bell"></i>
        <span v-if="unread > 0" class="notif-bell-badge">{{ unread > 9 ? '9+' : unread }}</span>
    </button>

    <Teleport to="body">
        <div class="offcanvas offcanvas-end notif-offcanvas" :class="{ show: open }" tabindex="-1" aria-labelledby="crmNotifTitle"
             :style="{ visibility: open ? 'visible' : 'hidden' }">
            <div class="offcanvas-header">
                <h5 id="crmNotifTitle" class="offcanvas-title">Notifications</h5>
                <button type="button" class="btn-close" aria-label="Close" @click="open = false"></button>
            </div>
            <div class="offcanvas-body p-0 d-flex flex-column">
                <div v-if="unread > 0" class="notif-offcanvas-actions">
                    <button type="button" class="btn btn-sm btn-link p-0" @click="markAllRead">Mark all read</button>
                </div>
                <div class="notif-list flex-grow-1" @scroll="onScroll">
                    <a v-for="item in items" :key="item.id" :href="hrefFor(item)" class="notif-item" :class="{ 'is-unread': !item.read }" @click.prevent="openItem(item)">
                        <span class="notif-icon" :class="`tone-${item.tone}`"><i class="fas" :class="item.icon"></i></span>
                        <span class="notif-body">
                            <span class="notif-title d-block">{{ item.title }}</span>
                            <span class="notif-message d-block">{{ item.message }}</span>
                            <span class="notif-time">{{ timeAgo(item.created_at) }}</span>
                        </span>
                    </a>
                    <div v-if="loading" class="text-center py-3"><span class="spinner-border spinner-border-sm text-muted"></span></div>
                    <div v-else-if="!items.length" class="notif-empty"><i class="fas fa-bell-slash mb-2 d-block" style="font-size: 1.5rem;"></i>You're all caught up.</div>
                </div>
            </div>
        </div>
        <div v-if="open" class="offcanvas-backdrop fade show" @click="open = false"></div>
    </Teleport>
</template>

<script setup>
/** Top bar bell + its side panel. A notification opens its CRM screen in-app, else its own page. */
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useNotifications } from '../composables/useNotifications';
import { timeAgo } from '../utils/format';

const router = useRouter();
const { items, unread, nextPage, loading, load, markRead, markAllRead } = useNotifications();
const open = ref(false);

function toggle() {
    open.value = !open.value;
    if (open.value) load(true);
}

function onScroll(event) {
    const el = event.target;
    if (nextPage.value && el.scrollTop + el.clientHeight >= el.scrollHeight - 40) load();
}

function hrefFor(item) {
    return item.route ? router.resolve(item.route).href : (item.url || '#');
}

function openItem(item) {
    markRead(item);
    open.value = false;
    if (item.route) router.push(item.route);
    else if (item.url) window.location.href = item.url;
}

// Just the unread count up front; the list loads when the panel opens.
onMounted(() => load(true));
</script>
