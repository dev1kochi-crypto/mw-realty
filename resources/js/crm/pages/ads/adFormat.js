/** Advertisements: bookings are made and shown in Dubai time, whatever the browser's own zone is. */

export const AD_TZ = 'Asia/Dubai';

const parts = (iso, options) => (iso ? new Date(iso).toLocaleString('en-GB', { timeZone: AD_TZ, ...options }) : '—');

/** "14 Oct, 18:00" */
export const adDateTime = (iso) => parts(iso, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', hour12: false });

/** "14 Oct 2026" */
export const adDate = (iso) => parts(iso, { day: '2-digit', month: 'short', year: 'numeric' });

/** "18:00" */
export const adTime = (iso) => parts(iso, { hour: '2-digit', minute: '2-digit', hour12: false });

/** "Tue 14 Oct" */
export const adDay = (iso) => parts(iso, { weekday: 'short', day: '2-digit', month: 'short' });

/** 6 → "6 hours", 24 → "1 day", 72 → "3 days" (Plan::adDurationLabel). */
export function durationLabel(hours) {
    if (hours >= 24 && hours % 24 === 0) return `${hours / 24} day${hours === 24 ? '' : 's'}`;
    return `${hours} hour${hours === 1 ? '' : 's'}`;
}

/** Status chip per ad state (Ad::state()). */
export const AD_STATES = {
    pending: { label: 'In review', icon: 'fa-hourglass-half', tone: 'amber' },
    scheduled: { label: 'Approved · upcoming', icon: 'fa-calendar-check', tone: 'blue' },
    live: { label: 'Live', icon: 'fa-circle', tone: 'green' },
    ended: { label: 'Ended', icon: 'fa-flag-checkered', tone: 'grey' },
    rejected: { label: 'Not approved', icon: 'fa-circle-xmark', tone: 'red' },
    expired: { label: 'Not reviewed in time', icon: 'fa-hourglass-end', tone: 'grey' },
    cancelled: { label: 'Cancelled', icon: 'fa-ban', tone: 'grey' },
    off: { label: 'Paused', icon: 'fa-pause', tone: 'grey' },
};

export const number = (n) => Number(n || 0).toLocaleString('en-US');
