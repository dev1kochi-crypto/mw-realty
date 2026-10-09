import { reactive } from 'vue';

/**
 * Styled replacement for window.confirm() (ConfirmDialog.vue) — the portal's portalConfirm():
 *   if (!(await confirm({ title: 'Delete lead?', message: '…', confirmText: 'Delete', tone: 'danger' }))) return;
 * tone: danger (red, deletes) | warning (amber, stop / turn off) | primary.
 */
const state = reactive({ open: false, title: '', message: '', confirmText: 'Confirm', cancelText: 'Cancel', tone: 'danger', resolve: null });

function confirm(options) {
    const opts = typeof options === 'string' ? { message: options } : (options || {});
    state.resolve?.(false);
    Object.assign(state, {
        open: true,
        title: opts.title || 'Are you sure?',
        message: opts.message || '',
        confirmText: opts.confirmText || 'Confirm',
        cancelText: opts.cancelText || 'Cancel',
        tone: ['danger', 'warning', 'primary'].includes(opts.tone) ? opts.tone : 'danger',
    });
    return new Promise((resolve) => {
        state.resolve = resolve;
    });
}

function settle(answer) {
    state.open = false;
    state.resolve?.(answer);
    state.resolve = null;
}

export function useConfirm() {
    return { confirm, settle, state };
}
