/** Display helpers shared by the CRM screens (dates come from the API as ISO 8601). */

const toDate = (iso) => (iso ? new Date(iso) : null);

/** "09 Oct 2026" */
export function formatDate(iso) {
    const date = toDate(iso);
    return date ? date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
}

/** "09 Oct 2026, 14:20" */
export function formatDateTime(iso) {
    const date = toDate(iso);
    return date
        ? `${formatDate(iso)}, ${date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false })}`
        : '—';
}

/** "14:20" */
export function formatTime(iso) {
    const date = toDate(iso);
    return date ? date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false }) : '';
}

/** "3 hours ago" / "in 2 days"; `short` → "3h". */
export function timeAgo(iso, short = false) {
    const date = toDate(iso);
    if (!date) return '—';
    const seconds = Math.round((date.getTime() - Date.now()) / 1000);
    const units = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60], ['second', 1]];
    const [unit, size] = units.find(([, s]) => Math.abs(seconds) >= s) ?? ['second', 1];
    const value = Math.round(seconds / size);
    if (short) {
        const abbreviations = { year: 'y', month: 'mo', week: 'w', day: 'd', hour: 'h', minute: 'm', second: 's' };
        return `${Math.abs(value)}${abbreviations[unit]}`;
    }
    return new Intl.RelativeTimeFormat('en', { numeric: 'auto' }).format(value, unit);
}

/** 75 → "1m 15s", 3900 → "1h 5m" (VisitorInsights::duration). */
export function duration(seconds) {
    const s = Math.max(0, Math.round(seconds || 0));
    if (s < 60) return `${s}s`;
    if (s < 3600) return `${Math.floor(s / 60)}m ${String(s % 60).padStart(2, '0')}s`;
    return `${Math.floor(s / 3600)}h ${Math.floor((s % 3600) / 60)}m`;
}

export function number(value) {
    return Number(value || 0).toLocaleString('en');
}

/** plural('lead', 2) → "leads"; handles the few irregular words the CRM uses. */
export function plural(word, count) {
    if (count === 1) return word;
    if (word.endsWith('y') && !/[aeiou]y$/.test(word)) return `${word.slice(0, -1)}ies`;
    return `${word}s`;
}

/** "Sara Ahmed" → "SA" */
export function initials(name, letters = 2) {
    const parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    const picked = letters === 1 ? [parts[0]] : [parts[0], parts.length > 1 ? parts[parts.length - 1] : null].filter(Boolean);
    return picked.map((part) => part[0]).join('').toUpperCase();
}

export const ucfirst = (value) => (value ? value.charAt(0).toUpperCase() + value.slice(1) : '');

export const telHref = (label) => `tel:${String(label || '').replace(/[^\d+]/g, '')}`;
export const whatsappHref = (label) => `https://wa.me/${String(label || '').replace(/\D+/g, '')}`;
