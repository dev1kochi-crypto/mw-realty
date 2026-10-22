import http from '../api/http';

/** Downloads a file from the CRM API (e.g. POST /leads/export) and saves it with its server-given name. */
export function download(method, url, data) {
    return http.request({ method, url, data, responseType: 'blob' }).then((res) => {
        const disposition = res.headers['content-disposition'] || '';
        const name = decodeURIComponent((disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i) || [])[1] || 'download');
        const link = document.createElement('a');
        link.href = URL.createObjectURL(res.data);
        link.download = name;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    });
}
