/** The confirm dialog for cancelling (not started yet) or stopping (live) an ad — AdBookingService::cancel(). */
export function cancelAdPrompt(ad) {
    if (ad.state === 'live') {
        return {
            title: 'Stop this ad now?',
            message: `“${ad.name}” comes off the website right away. The time it already ran isn't refunded.`,
            confirmText: 'Stop ad',
            cancelText: 'Keep it running',
            tone: 'warning',
        };
    }

    return {
        title: 'Cancel this ad?',
        message: `“${ad.name}” won't run. ${ad.paid_with === 'credits' ? `Your ${Number(ad.credits).toLocaleString('en-US')} credits are refunded.` : 'It no longer counts toward your plan\'s ads this month.'}`,
        confirmText: 'Cancel ad',
        cancelText: 'Keep it',
        tone: 'warning',
    };
}
