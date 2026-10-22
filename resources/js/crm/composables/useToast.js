import { ref } from 'vue';

/** Top-right toasts (ToastHost.vue) — same look as the portal's window.portalToast(). */
const toasts = ref([]);
let nextId = 1;

function dismiss(id) {
    const toast = toasts.value.find((t) => t.id === id);
    if (!toast || toast.leaving) return;
    toast.leaving = true;
    setTimeout(() => {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    }, 300);
}

/** type: success | danger | info */
function toast(type, message, duration) {
    const kind = ['success', 'danger', 'info'].includes(type) ? type : 'info';
    const id = nextId++;
    const ms = duration || (kind === 'danger' ? 6000 : 3500);
    toasts.value.push({ id, type: kind, message, duration: ms, shown: false, leaving: false });
    requestAnimationFrame(() => {
        const added = toasts.value.find((t) => t.id === id);
        if (added) added.shown = true;
    });
    setTimeout(() => dismiss(id), ms);
}

export function useToast() {
    return {
        toasts,
        dismiss,
        toast,
        success: (message) => toast('success', message),
        error: (message) => toast('danger', message),
        info: (message) => toast('info', message),
    };
}
