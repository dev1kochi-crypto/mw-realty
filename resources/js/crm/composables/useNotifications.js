import { ref } from 'vue';
import http from '../api/http';

/** The bell (GET /api/crm/notifications) — newest first, more on scroll. */
const items = ref([]);
const unread = ref(0);
const nextPage = ref(1);
const loading = ref(false);

function load(reset = false) {
    if (reset) {
        items.value = [];
        nextPage.value = 1;
    }
    if (!nextPage.value || loading.value) return Promise.resolve();
    loading.value = true;
    return http.get('/notifications', { params: { page: nextPage.value } })
        .then((res) => {
            items.value.push(...res.data.data);
            unread.value = res.data.unread_count;
            nextPage.value = res.data.next_page;
        })
        .finally(() => {
            loading.value = false;
        });
}

function markRead(item) {
    if (item.read) return Promise.resolve();
    item.read = true;
    unread.value = Math.max(0, unread.value - 1);
    return http.post(`/notifications/${item.id}/read`).catch(() => {});
}

function markAllRead() {
    items.value.forEach((item) => { item.read = true; });
    unread.value = 0;
    return http.post('/notifications/read-all');
}

export function useNotifications() {
    return { items, unread, nextPage, loading, load, markRead, markAllRead };
}
