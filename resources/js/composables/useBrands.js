import { ref } from 'vue';

const brands = ref([]);
let loaded = false;

export function useBrands() {
    if (!loaded) {
        loaded = true;
        window.axios.get('/api/brands').then((res) => {
            brands.value = res.data;
        }).catch(() => {
            brands.value = [];
        });
    }

    return { brands };
}
