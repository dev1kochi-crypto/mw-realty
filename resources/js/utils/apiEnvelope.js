/**
 * The API answers {success, message, data} (see App\Support\ApiEnvelope). The pages were written
 * against the controllers' own shapes, so unwrap back to them: `data` fields are spread beside
 * success/message (res.data.tag), `data` itself stays for single resources / lists (res.data.data),
 * and a paginated data: {items, meta, links} becomes {data: items, meta, links}.
 */
export function unwrap(body) {
    if (!body || typeof body !== 'object' || typeof body.success !== 'boolean' || !('data' in body)) return body;
    const { success, message, data, ...rest } = body;
    if (data && typeof data === 'object' && !Array.isArray(data)) {
        if (Array.isArray(data.items) && 'meta' in data) {
            return { ...rest, ...data, data: data.items, success, message };
        }
        // A body that already had its own `data` key (e.g. {data: [...], counts}) keeps it.
        return { ...rest, ...data, data: 'data' in data ? data.data : data, success, message };
    }
    return { ...rest, data, success, message };
}

/** Axios interceptors that unwrap every /api/* reply (success and error) — pass an axios instance. */
export function installEnvelopeUnwrap(instance) {
    const isApi = (config) => /(^|\/)api\//.test(String(config?.url ?? ''));
    instance.interceptors.response.use(
        (response) => {
            if (isApi(response.config)) response.data = unwrap(response.data);
            return response;
        },
        (error) => {
            if (error.response && isApi(error.config)) error.response.data = unwrap(error.response.data);
            return Promise.reject(error);
        },
    );
}
