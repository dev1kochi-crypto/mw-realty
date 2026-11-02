import { ref } from 'vue';
import http from '../api/http';
import { isWorking } from './useLeadImport';

/**
 * A background listing spreadsheet import's live progress — polls GET /api/crm/properties/imports/{id}
 * until it finishes. Shared by the Properties page banner and the Import popup (like useLeadImport).
 */
const current = ref(null);
let timer = null;

function poll() {
    clearTimeout(timer);
    if (!isWorking(current.value)) return;
    timer = setTimeout(() => {
        http.get(`/properties/imports/${current.value.id}`)
            .then((res) => {
                current.value = res.data.import;
            })
            .finally(poll);
    }, current.value.status === 'queued' ? 3000 : 2000);
}

function track(progress) {
    current.value = progress;
    poll();
}

/** The latest import (or ?import=id from the "finished" notification), shown when the page opens. */
function restore(id) {
    return http.get('/properties/imports/latest', { params: id ? { id } : {} })
        .then((res) => {
            if (res.data.import) track(res.data.import);
        })
        .catch(() => {});
}

function stop() {
    clearTimeout(timer);
}

function dismiss() {
    stop();
    current.value = null;
}

export function usePropertyImport() {
    return { current, track, restore, stop, dismiss };
}
