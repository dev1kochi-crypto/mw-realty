import { ref } from 'vue';

/**
 * The /compare page's unlock token (from POST /api/contact, source=compare). Until the visitor has
 * one, GET /api/compare leaves out the money & investment figures. Kept in localStorage so the
 * comparison stays unlocked in this browser; the server decides whether it is still valid.
 */
const STORAGE_KEY = 'mw-compare-unlock';
export const UNLOCK_HEADER = 'X-Compare-Unlock';

function read() {
    try { return window.localStorage.getItem(STORAGE_KEY) || ''; } catch { return ''; }
}

const token = ref(read());

export function useCompareUnlock() {
    function setToken(value) {
        token.value = value || '';
        try {
            if (token.value) window.localStorage.setItem(STORAGE_KEY, token.value);
            else window.localStorage.removeItem(STORAGE_KEY);
        } catch { /* private mode etc. */ }
    }

    /** Request headers for the compare endpoints. */
    const headers = () => (token.value ? { [UNLOCK_HEADER]: token.value } : {});

    return { token, setToken, headers };
}
