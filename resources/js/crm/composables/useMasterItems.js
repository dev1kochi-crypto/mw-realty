import { ref } from 'vue';
import http from '../api/http';

/** One Master list (stages / tags / sources) — /api/crm/master/{type}. */
export function useMasterItems(type) {
    const items = ref([]);
    const meta = ref(null);
    const loading = ref(false);
    const search = ref('');
    let requestId = 0;

    function fetchItems(page = 1) {
        const id = ++requestId;
        loading.value = true;
        return http.get(`/master/${type}`, { params: { search: search.value || undefined, page } })
            .then((res) => {
                if (id !== requestId) return;
                items.value = res.data.data;
                meta.value = res.data.meta ?? null;
            })
            .finally(() => {
                if (id === requestId) loading.value = false;
            });
    }

    const save = (item, data) => (item?.id
        ? http.put(`/master/${type}/${item.id}`, data)
        : http.post(`/master/${type}`, data));

    const destroy = (item) => http.delete(`/master/${type}/${item.id}`);
    const reorder = (ids) => http.post(`/master/${type}/reorder`, { order: ids });
    const setDefault = (item) => http.post(`/master/${type}/${item.id}/default`);

    return { items, meta, loading, search, fetchItems, save, destroy, reorder, setDefault };
}
