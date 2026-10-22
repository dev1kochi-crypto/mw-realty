// Refinance maths for the 3-step Refinance calculator — a port of Property Finder's Mortgage Finder
// refinance calculator (mortgagefinder.ae/en/refinance-calculator), so the same answers give the
// same figures. Every amount is in AED.
import { EARLY_SETTLEMENT_CAP, TERM_MAX } from './finance.js';

// Used until Admin › Calculators' lender list loads (same list as CalculatorPageSeeder::lenders()).
export const DEFAULT_LENDERS = [
    'Abu Dhabi Commercial Bank (ADCB)', 'Abu Dhabi Islamic Bank (ADIB)', 'Ajman Bank', 'Al Hilal Bank', 'Arab Bank',
    'Commercial Bank of Dubai (CBD)', 'Dubai Islamic Bank (DIB)', 'Emirates Islamic', 'Emirates NBD',
    'First Abu Dhabi Bank (FAB)', 'FAB Islamic', 'HSBC', 'Mashreq', 'National Bank of Fujairah (NBF)', 'RAKBANK',
    'Sharjah Islamic Bank', 'Standard Chartered', 'United Arab Bank', 'Other',
];

// Employment status → the client type banks price by (Mortgage Finder's codes).
export const EMPLOYMENT_TYPES = [
    { value: 'STL', label: 'Employed Salary Transfer' },
    { value: 'NSTL', label: 'Employed No Salary Transfer' },
    { value: 'SE', label: 'Self Employed' },
    { value: 'NR', label: 'Non Resident' },
];

// --- Market rates (Mortgage Finder's bank data, EIBOR published 2026-10-13) -------------------
export const EIBOR = { 1: 4.07423, 3: 4.44348, 6: 4.61651, 12: 4.99523 };
// Lowest refinance rate on offer per client type — without / with an equity release.
export const BEST_RATES = {
    refinance: { STL: 3.75, NSTL: 3.99, SE: 4.19, NR: 4.19 },
    equity: { STL: 3.89, NSTL: 3.99, SE: 4.19, NR: 4.44 },
};
// What the current loan moves to when its fixed rate ends: the lender's margin over EIBOR (tenor in months).
const LENDER_REVERSION = [
    { match: /\bADIB\b|abu dhabi islamic/i, margin: 1.6, eibor: 1 },
    { match: /\bENBD\b|emirates nbd/i, margin: 1.99, eibor: 1 },
    { match: /\bEIB\b|emirates islamic/i, margin: 1.99, eibor: 3 },
    { match: /\bFAB\b.*islamic|fab islamic/i, margin: 1.5, eibor: 3 },
    { match: /\bFAB\b|first abu dhabi/i, margin: 1.5, eibor: 3 },
    { match: /\bNBF\b|fujairah/i, margin: 1.49, eibor: 3 },
    { match: /\bSCB\b|standard chartered/i, margin: 1.1, eibor: 1 },
    { match: /\bSIB\b|sharjah islamic/i, margin: 0, eibor: 6 },
    { match: /\bCBD\b|commercial bank of dubai/i, margin: 1.99, eibor: 3 },
    { match: /mashreq/i, margin: 2.99, eibor: 3 },
    { match: /\bUAB\b|united arab bank/i, margin: 1.89, eibor: 3 },
    { match: /hsbc/i, margin: 0.99, eibor: 3 },
    { match: /rak\s?bank/i, margin: 1.69, eibor: 3 },
    { match: /ajman/i, margin: 1.59, eibor: 3 },
    { match: /\bDIB\b|dubai islamic/i, margin: 1.5, eibor: 3 },
    { match: /arab bank/i, margin: 1.99, eibor: 6 },
    { match: /nomo/i, margin: 7.5, eibor: 1 },
];
const REVERSION_FALLBACK = { margin: 1.6, eibor: 3 }; // a lender Mortgage Finder has no data for (ADCB, Other…)

export const VALUATION_FEE = 3150; // property valuation for the new bank (AED 3,000 + VAT)
export const REGISTRATION_TRANSFER_FEES = 290 + 4200 + 580; // mortgage registration, release and transfer
export const MAX_AGE = 65; // the new loan must end by this age
export const MIN_AGE = 18;
export const MIN_LOAN = 100000; // validation rules as on Mortgage Finder's refinance calculator
export const MIN_PROPERTY_VALUE = 300000;
export const MIN_INCOME = 10000;
export const MIN_DATE = '1950-01-01';
export const SAVING_YEARS = 3;

/** Whole months from `from` to `to` (dates or YYYY-MM-DD), never negative. */
export function monthsBetween(from, to = new Date()) {
    const a = from instanceof Date ? from : new Date(from);
    const b = to instanceof Date ? to : new Date(to);
    if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime())) return 0;
    let months = (b.getFullYear() - a.getFullYear()) * 12 + (b.getMonth() - a.getMonth());
    if (b.getDate() < a.getDate()) months -= 1;
    return Math.max(0, months);
}

/** Age in years (365.25-day years, as Mortgage Finder counts it). */
export function ageYears(dob, today = new Date()) {
    const d = new Date(dob);
    return Number.isNaN(d.getTime()) ? 0 : (today.getTime() - d.getTime()) / 315576e5;
}

/** Months left on the current loan: full term minus the days since it started, in 30-day months. */
export function remainingMonths(termMonths, startDate, today = new Date()) {
    const midnight = (d) => { const x = new Date(d); x.setHours(0, 0, 0, 0); return x; };
    return termMonths - (midnight(today) - midnight(startDate)) / 864e5 / 30;
}

const annuity = (principal, ratePct, months) => {
    const j = ratePct / 100 / 12;
    if (!(months > 0)) return NaN;
    if (j === 0) return principal / months;
    const q = Math.pow(1 + j, months);
    return (j * principal * q) / (q - 1);
};

/** Balance the original loan would have today if every instalment was paid on time (step-3 suggestion). */
export function estimatedBalance({ originalLoan, currentRate, termMonths, startDate }, today = new Date()) {
    if (!(originalLoan > 0) || !termMonths || !startDate) return 0;
    const j = currentRate / 100 / 12;
    const k = Math.min(monthsBetween(startDate, today), termMonths);
    if (!j) return originalLoan * (1 - k / termMonths);
    const pay = annuity(originalLoan, currentRate, termMonths);
    return originalLoan * Math.pow(1 + j, k) - pay * ((Math.pow(1 + j, k) - 1) / j);
}

export function lenderReversion(lender) {
    return LENDER_REVERSION.find((l) => l.match.test(lender || '')) || REVERSION_FALLBACK;
}

/**
 * @param i  { lender, termMonths, startDate, employment (STL|NSTL|SE|NR), dob, balance, equityRelease }
 *           — original loan, valuation, fixed rate/period, income and tenure are asked (and sent with
 *           the lead) but, as on Mortgage Finder, do not change the figures.
 */
export function calculateRefinance(i, today = new Date()) {
    // Current loan, once its fixed rate ends: lender margin + EIBOR, on the balance over the months left.
    const reversion = lenderReversion(i.lender);
    const currentRate = reversion.margin + (EIBOR[reversion.eibor] || 0);
    const remaining = remainingMonths(i.termMonths, i.startDate, today);
    const currentMonthly = annuity(i.balance, currentRate, remaining);

    // Refinance: the best rate for the client type, on balance + equity, over up to 25 years (ending by 65).
    const equity = i.equityRelease || 0;
    const rate = (equity ? BEST_RATES.equity : BEST_RATES.refinance)[i.employment] || BEST_RATES.refinance.STL;
    const newTermYears = Math.max(0, Math.min(TERM_MAX, MAX_AGE - ageYears(i.dob, today)));
    const newLoan = i.balance + equity;
    const newMonthly = annuity(newLoan, rate, newTermYears * 12);

    const monthlySaving = currentMonthly - newMonthly;

    // Cost of switching: early settlement (1% of the balance, max AED 10,000, + 5% VAT), valuation, registration & transfer.
    const settlementFee = Math.min(i.balance * 0.01, EARLY_SETTLEMENT_CAP) * 1.05;
    const switchCosts = settlementFee + VALUATION_FEE + REGISTRATION_TRANSFER_FEES;

    return {
        currentRate,
        rate,
        remaining,
        newTerm: newTermYears * 12,
        termLimitedByAge: newTermYears < TERM_MAX,
        currentMonthly,
        newLoan,
        equity,
        newMonthly,
        monthlySaving,
        firstYearSaving: monthlySaving * 12,
        totalSaving: monthlySaving * 12 * SAVING_YEARS,
        settlementFee,
        registrationFee: REGISTRATION_TRANSFER_FEES,
        valuationFee: VALUATION_FEE,
        switchCosts,
    };
}
