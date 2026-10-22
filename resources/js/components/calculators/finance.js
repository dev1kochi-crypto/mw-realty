// Shared home-finance maths for the calculators. Every amount is in AED.

export const TERM_MAX = 25; // UAE Central Bank cap on home-loan tenure (years)
export const RATE_MIN = 1;
export const RATE_MAX = 10;
export const DEFAULT_RATE = 3.75;
export const DBR_LIMIT = 0.5; // Debt burden ratio: all monthly debt repayments ≤ 50% of income
export const HIGH_VALUE_PRICE = 5000000; // Above this, banks need a bigger down payment
export const EARLY_SETTLEMENT_CAP = 10000; // Early settlement fee: 1% of the balance, max AED 10,000

/**
 * Per residency: minimum down payment (%) — UAE Central Bank loan-to-value limits for a first home,
 * up to AED 5M / above AED 5M, and for a second or later home (non-residents 40% in every case) —
 * and the typical interest rate (%) the calculators start from when that status is picked.
 */
export const RESIDENCY = {
    national: { minDown: 15, highDown: 25, secondDown: 35, rate: 4.24 },
    resident: { minDown: 20, highDown: 30, secondDown: 35, rate: 3.75 },
    'non-resident': { minDown: 40, highDown: 40, secondDown: 40, rate: 4.19 },
};
export function minDownPct(residency, price, firstHome = true) {
    const rule = RESIDENCY[residency] || RESIDENCY.resident;
    if (!firstHome) return rule.secondDown;
    return price > HIGH_VALUE_PRICE ? rule.highDown : rule.minDown;
}

/** Banks count 5% of every credit card limit as a monthly debt, used or not. */
export const CARD_LIMIT_SHARE = 0.05;

/** Fixed monthly instalment for a loan. */
export function monthlyPayment(loan, ratePct, years) {
    const n = Math.round(years * 12);
    const r = ratePct / 100 / 12;
    if (!(loan > 0) || !n) return 0;
    if (!r) return loan / n;
    const f = Math.pow(1 + r, n);
    return loan * r * f / (f - 1);
}

/** Largest loan a monthly instalment can repay over the term. */
export function loanForPayment(payment, ratePct, years) {
    const n = Math.round(years * 12);
    const r = ratePct / 100 / 12;
    if (!(payment > 0) || !n) return 0;
    if (!r) return payment * n;
    return payment * (1 - Math.pow(1 + r, -n)) / r;
}

/** Outstanding balance after `months` instalments. */
export function balanceAfter(loan, ratePct, years, months) {
    const n = Math.round(years * 12);
    const m = Math.min(months, n);
    const r = ratePct / 100 / 12;
    const pmt = monthlyPayment(loan, ratePct, years);
    if (!r) return Math.max(0, loan - pmt * m);
    const f = Math.pow(1 + r, m);
    return Math.max(0, loan * f - pmt * (f - 1) / r);
}

const VAT = 1.05;

/** Mortgage registration at the Land Department: 0.25% of the loan + AED 10. */
export const mortgageRegistrationFee = (loan) => (loan > 0 ? loan * 0.0025 + 10 : 0);

/**
 * One-off purchase costs in Dubai, in the order buyers usually see them. Mortgage-only items
 * (registration, bank arrangement, valuation) apply only when there is a loan.
 * Returns each item (for the breakdown) and the total.
 */
export function purchaseFees(price, loan) {
    const items = [
        { key: 'land_department', label: 'Land department fee (4% + AED 580)', amount: price * 0.04 + 580 },
        { key: 'trustee', label: 'Registration trustee fee (AED 4,000 + VAT)', amount: 4000 * VAT },
    ];
    if (loan > 0) items.push({ key: 'mortgage_registration', label: 'Mortgage registration fee (0.25% + AED 10)', amount: mortgageRegistrationFee(loan) });
    items.push({ key: 'agency', label: 'Real estate agency fee (2% + VAT)', amount: price * 0.02 * VAT });
    if (loan > 0) {
        items.push(
            { key: 'bank_arrangement', label: 'Bank arrangement fee (1% + VAT)', amount: loan * 0.01 * VAT },
            { key: 'valuation', label: 'Mortgage valuation fee (AED 3,000 + VAT)', amount: 3000 * VAT },
        );
    }
    return { items, total: items.reduce((sum, item) => sum + item.amount, 0) };
}

/** "AED 1,234,567" — for lead summaries, which are always stored in AED whatever the visitor's currency. */
export const aed = (n) => `AED ${Math.round(Number(n) || 0).toLocaleString('en-US')}`;

export const clamp =(n, min, max) => Math.min(max, Math.max(min, Number(n) || min));
