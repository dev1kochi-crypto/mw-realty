import axios from 'axios';
import { unwrap } from '../../utils/apiEnvelope';

/**
 * The CRM web app's API client — /api/crm/* with this browser's session cookie (Sanctum's
 * stateful SPA mode; axios sends the XSRF-TOKEN cookie back as X-XSRF-TOKEN by itself).
 * The mobile app calls the same endpoints with a Bearer token instead.
 */
const http = axios.create({
    baseURL: window.CrmConfig.apiBase,
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

http.interceptors.response.use(
    (response) => {
        response.data = unwrap(response.data);
        return response;
    },
    (error) => {
        if (error.response) error.response.data = unwrap(error.response.data);
        const status = error.response?.status;
        // Signed out (or the session expired) → the portal's own sign-in page.
        if (status === 401) {
            window.location.href = window.CrmConfig.loginUrl;
        }
        // portal.2fa: two-factor set-up has to be finished first (still a /portal screen).
        if (status === 403 && error.response.data?.redirect) {
            window.location.href = error.response.data.redirect;
        }
        return Promise.reject(error);
    },
);

/** The first validation / error message of a failed request, for a toast or inline alert. */
export function errorMessage(error, fallback = 'Something went wrong — please try again.') {
    const data = error.response?.data;
    const firstFieldError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
    return firstFieldError || data?.message || fallback;
}

export default http;
