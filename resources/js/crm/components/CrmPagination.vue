<template>
    <nav v-if="meta && meta.last_page > 1" class="d-flex justify-items-center justify-content-between" aria-label="Pagination Navigation">
        <div class="d-flex justify-content-between flex-fill d-sm-none">
            <ul class="pagination">
                <li class="page-item" :class="{ disabled: meta.current_page <= 1 }"><button type="button" class="page-link" @click="go(meta.current_page - 1)">&laquo; Previous</button></li>
                <li class="page-item" :class="{ disabled: meta.current_page >= meta.last_page }"><button type="button" class="page-link" @click="go(meta.current_page + 1)">Next &raquo;</button></li>
            </ul>
        </div>
        <div class="d-none flex-sm-fill d-sm-flex align-items-sm-center justify-content-sm-between">
            <div>
                <p class="small text-muted">
                    Showing <span class="fw-semibold">{{ meta.from }}</span> to <span class="fw-semibold">{{ meta.to }}</span>
                    of <span class="fw-semibold">{{ meta.total }}</span> results
                </p>
            </div>
            <div>
                <ul class="pagination">
                    <li class="page-item" :class="{ disabled: meta.current_page <= 1 }" aria-label="« Previous">
                        <button type="button" class="page-link" @click="go(meta.current_page - 1)">&lsaquo;</button>
                    </li>
                    <template v-for="(page, i) in pages" :key="i">
                        <li v-if="page === '...'" class="page-item disabled" aria-disabled="true"><span class="page-link">...</span></li>
                        <li v-else-if="page === meta.current_page" class="page-item active" aria-current="page"><span class="page-link">{{ page }}</span></li>
                        <li v-else class="page-item"><button type="button" class="page-link" @click="go(page)">{{ page }}</button></li>
                    </template>
                    <li class="page-item" :class="{ disabled: meta.current_page >= meta.last_page }" aria-label="Next »">
                        <button type="button" class="page-link" @click="go(meta.current_page + 1)">&rsaquo;</button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</template>

<script setup>
/**
 * Laravel's bootstrap-5 paginator links ($paginator->links()), driven by an API page's
 * meta {current_page, last_page, total, from, to}. Emits `change` with the page to load.
 */
import { computed } from 'vue';

const props = defineProps({
    meta: { type: Object, default: null },
    // Page links either side of the current one ($paginator->onEachSide()).
    onEachSide: { type: Number, default: 3 },
});
const emit = defineEmits(['change']);

const range = (from, to) => Array.from({ length: Math.max(0, to - from + 1) }, (_, i) => from + i);

// Laravel's UrlWindow.
const pages = computed(() => {
    const { current_page: current, last_page: last } = props.meta;
    const onEachSide = props.onEachSide;
    const window = onEachSide + 4;
    if (last < onEachSide * 2 + 8) return range(1, last);
    if (current <= window) return [...range(1, window + onEachSide), '...', last - 1, last];
    if (current > last - window) return [1, 2, '...', ...range(last - (window + (onEachSide - 1)), last)];
    return [1, 2, '...', ...range(current - onEachSide, current + onEachSide), '...', last - 1, last];
});

function go(page) {
    if (page >= 1 && page <= props.meta.last_page && page !== props.meta.current_page) emit('change', page);
}
</script>
