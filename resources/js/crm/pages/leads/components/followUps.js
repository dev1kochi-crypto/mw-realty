/** Lead follow-ups — shared by the lead screen's Follow-ups card and the Follow-ups page (LeadFollowUp::TYPES). */
export const TYPES = { call: 'Call', whatsapp: 'WhatsApp', email: 'Email' };
export const TYPE_ICONS = { call: 'fa-phone', whatsapp: 'fa-comment-dots', email: 'fa-envelope' };

/**
 * A "YYYY-MM-DDTHH:mm" in UAE time (what the follow-up form sends), `hoursAhead` from now —
 * at `atHour`:00 when given (e.g. tomorrow 10:00), else rounded up to the next quarter hour.
 */
export function uaeLocal(hoursAhead = 0, atHour = null) {
    const at = new Date(Date.now() + hoursAhead * 3600 * 1000);
    // sv-SE formats as "2026-10-27 14:05:00".
    let [date, time] = at.toLocaleString('sv-SE', { timeZone: 'Asia/Dubai', hour12: false }).split(' ');
    let [hour, minute] = time.split(':').map(Number);
    if (atHour !== null) {
        hour = atHour;
        minute = 0;
    } else if (hoursAhead > 0) {
        minute = Math.ceil(minute / 15) * 15;
        if (minute === 60) {
            minute = 45; // keep it simple: stay inside the same hour rather than rolling the date
        }
    }
    return `${date}T${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
}
