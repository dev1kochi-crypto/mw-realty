/** Agent Insights formatting shared by the list and the agent page. */

/** Median minutes to the first action → "12 min", "3 h", "2 days". */
export function responseTime(minutes) {
    if (minutes === null || minutes === undefined) return '—';
    if (minutes < 1) return '< 1 min';
    if (minutes < 60) return `${minutes} min`;
    if (minutes < 1440) return `${Math.round(minutes / 60)} h`;
    const d = Math.round(minutes / 1440);
    return `${d} ${d === 1 ? 'day' : 'days'}`;
}

/** Response-rate bar colour (same bands as the agent score). */
export const tone = (percent) => (percent >= 80 ? 'is-good' : percent >= 50 ? 'is-fair' : 'is-poor');

/** Listing quality tone, as ListingQualityService::score(). */
export const qualityTone = (score) => (score >= 80 ? 'good' : score >= 50 ? 'fair' : 'poor');
