// Buy-to-let maths for the Property Details "Investment" section. Every amount is in AED.
import { balanceAfter, monthlyPayment, purchaseFees } from '../calculators/finance';

/**
 * One financing scenario for a buy-to-let:
 *   price, downPct, rate, years          — the purchase and the loan (downPct 100 = cash)
 *   rent                                 — expected annual rent
 *   serviceCharges                       — annual building / community service charges
 *   maintenancePct, vacancyPct           — % of the price per year · % of the year the unit is empty
 *   holdYears                            — how long it is kept, for the total cost of ownership
 */
export function investment({ price, downPct, rate, years, rent, serviceCharges, maintenancePct, vacancyPct, holdYears }) {
    const loan = Math.max(0, price * (1 - downPct / 100));
    const down = price - loan;
    const fees = purchaseFees(price, loan);
    const upfront = down + fees.total;

    const monthlyLoan = loan > 0 ? monthlyPayment(loan, rate, years) : 0;
    const effectiveRent = rent * (1 - vacancyPct / 100);
    const maintenance = price * maintenancePct / 100;
    const runningCosts = serviceCharges + maintenance; // a year, before the mortgage
    const netOperatingIncome = effectiveRent - runningCosts;
    const annualCashFlow = netOperatingIncome - monthlyLoan * 12;

    // Over the holding period: what is paid in, what the rent brings back, and what is still owed.
    const loanMonths = Math.min(holdYears, years) * 12;
    const loanPaid = monthlyLoan * loanMonths;
    const loanBalance = loan > 0 ? balanceAfter(loan, rate, years, holdYears * 12) : 0;
    const totalCost = upfront + loanPaid + runningCosts * holdYears;
    const rentIncome = effectiveRent * holdYears;

    return {
        loan,
        down,
        fees,
        upfront,
        monthlyLoan,
        effectiveRent,
        maintenance,
        runningCosts,
        grossYield: price > 0 ? rent / price * 100 : 0,
        netYield: price + fees.total > 0 ? netOperatingIncome / (price + fees.total) * 100 : 0,
        monthlyCashFlow: annualCashFlow / 12,
        cashOnCash: upfront > 0 ? annualCashFlow / upfront * 100 : 0,
        loanPaid,
        loanInterest: Math.max(0, loanPaid - (loan - loanBalance)),
        loanBalance,
        totalCost,
        rentIncome,
        netCost: totalCost - rentIncome,
    };
}

/** Rental period → periods per year (matches PropertyInsightService). No period = yearly. */
export const PERIODS_PER_YEAR = { yearly: 1, monthly: 12, weekly: 52, daily: 365 };
export const annualRent = (price, period) => price * (PERIODS_PER_YEAR[period] || 1);

const VAT = 1.05;

/**
 * Moving-in costs for a long-term rental in the UAE, in the order a tenant pays them. `annual` is
 * the yearly rent; `deposit` the listing's own security deposit when it states one.
 * Housing fee: Dubai Municipality 5% of the rent (billed monthly with DEWA), Abu Dhabi 3%.
 */
export function rentalCosts({ annual, agencyPct, depositPct, deposit, emirate, villa }) {
    const items = [
        { key: 'security_deposit', label: 'Security deposit (refundable)', amount: deposit > 0 ? deposit : annual * depositPct / 100, refundable: true },
        { key: 'agency', label: 'Agency fee ({pct}% + VAT)', amount: annual * agencyPct / 100 * VAT },
        { key: 'registration', label: 'Tenancy contract registration (Ejari / Tawtheeq)', amount: 220 },
        { key: 'utility_deposit', label: 'Utility connection deposit (refundable)', amount: villa ? 4000 : 2000, refundable: true },
    ];
    const housingPct = { dubai: 5, abu_dhabi: 3 }[emirate] ?? 0;
    const housingFee = annual * housingPct / 100;
    const moveIn = items.reduce((sum, item) => sum + item.amount, 0);
    const refundable = items.filter((item) => item.refundable).reduce((sum, item) => sum + item.amount, 0);

    return { items, moveIn, refundable, housingPct, housingFee, yearOne: annual + housingFee + moveIn - refundable };
}
