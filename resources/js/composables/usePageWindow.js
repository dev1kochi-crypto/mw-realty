import { computed, unref } from 'vue';

/**
 * Page numbers for a "Previous 1 2 3 4 5 Next" bar: at most `size` consecutive pages, kept
 * around the current page (so page 8 of 22 shows 6 7 8 9 10), instead of one button per page.
 * `current` and `last` may be refs, computeds or plain numbers.
 */
export function usePageWindow(current, last, size = 5) {
    return computed(() => {
        const total = Math.max(0, Number(unref(last)) || 0);
        if (!total) return [];
        const page = Math.min(Math.max(1, Number(unref(current)) || 1), total);
        const count = Math.min(size, total);
        const start = Math.min(Math.max(1, page - Math.floor(count / 2)), total - count + 1);
        return Array.from({ length: count }, (_, i) => start + i);
    });
}
