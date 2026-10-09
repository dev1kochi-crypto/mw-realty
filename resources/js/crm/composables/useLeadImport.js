import { ref } from 'vue';
import http from '../api/http';

/**
 * A background lead import's live progress (the portal's LeadImportTracker) — polls its
 * status (GET /api/crm/leads/imports/{id}) until it finishes. Shared by the Leads page
 * banner and the Import popup.
 */
const current = ref(null);
let timer = null;

export const isWorking = (progress) => progress && (progress.status === 'queued' || progress.status === 'running');

function poll() {
    clearTimeout(timer);
    if (!isWorking(current.value)) return;
    timer = setTimeout(() => {
        // By id on this app's own API base (status_url is built from APP_URL, which may be another host).
        http.get(`/leads/imports/${current.value.id}`)
            .then((res) => {
                current.value = res.data.import;
            })
            .finally(poll);
    }, current.value.status === 'queued' ? 3000 : 1500);
}

function track(progress) {
    current.value = progress;
    poll();
}

function stop() {
    clearTimeout(timer);
}

function dismiss() {
    stop();
    current.value = null;
}

export function useLeadImport() {
    return { current, track, stop, dismiss };
}
