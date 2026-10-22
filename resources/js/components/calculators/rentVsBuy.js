// Rent vs Buy maths — month-by-month, matching the Property Finder calculator's model.
// Every amount is in AED.

const VAT = 0.05;

/** Fixed Dubai costs and rates the model uses (not shown as inputs). */
export const RVB = {
    // Renting
    moveEveryYears: 3, // a tenant changes home every 3 years on average
    rentAgentRate: 0.05, // agent commission on each move: 5% of the yearly rent + VAT
    ejariRegistration: 120, // every year
    ejariCancellation: 42, // on each move
    // Buying
    agentRate: 0.02, // buying and selling agent commission (+ VAT when buying)
    landDeptRate: 0.04,
    landDeptFixed: 580,
    trusteeFee: 4200,
    mortgageRegistrationRate: 0.0025,
    mortgageRegistrationFixed: 290,
    processingRate: 0.005, // bank processing fee: 0.5% of the loan + VAT
    valuationFee: 3150,
    lifeInsuranceRate: 0.002, // a year, on the loan balance
    propertyInsuranceRate: 0.0006, // a year, on the purchase price
    communityCostPerYear: 15000, // Dubai-wide average service charge
    nocFee: 500, // developer NOC when selling
    earlySettlementRate: 0.01, // on the balance cleared early, capped below
    earlySettlementCap: 10000,
    eibor: 0.049, // 3-month EIBOR, for variable-rate mortgages
};

/** Default inputs (the "More options" values). */
export const RVB_DEFAULTS = {
    mortgageType: 'fixed', // 'fixed' | 'variable'
    interestRate: 4.24, // fixed rate, or the margin over EIBOR for a variable mortgage
    fixedPeriod: 3, // years at the fixed rate
    reversionRate: 3.24, // rate after the fixed period
    rentGrowth: 0, // % a year
    priceGrowth: 0, // % a year
};

function pmt(rate, nper, pv) {
    if (!rate) return pv / nper;
    return pv * rate / (1 - 1 / Math.pow(1 + rate, nper));
}

/**
 * @param {object} i  { rent, price, down, years, mortgageType, interestRate, fixedPeriod, reversionRate, rentGrowth, priceGrowth }
 * Returns the yearly comparison series, the totals and both breakdowns.
 */
export function calculateRentVsBuy(i) {
    const years = Math.max(1, Math.round(i.years));
    const months = years * 12;
    const loan = Math.max(0, i.price - i.down);
    const monthlyGrowth = Math.pow(1 + i.priceGrowth / 100, 1 / 12) - 1;
    const rentGrowth = i.rentGrowth / 100;
    const aprFor = (year) => (i.mortgageType === 'variable'
        ? RVB.eibor + i.interestRate / 100
        : (year <= i.fixedPeriod ? i.interestRate : i.reversionRate) / 100);

    // One-off purchase costs (month 1)
    const buyAgent = i.price * RVB.agentRate * (1 + VAT);
    const landDept = i.price * RVB.landDeptRate + RVB.landDeptFixed;
    const trustee = RVB.trusteeFee;
    const processing = loan * RVB.processingRate * (1 + VAT);
    const mortgageRegistration = loan * RVB.mortgageRegistrationRate + RVB.mortgageRegistrationFixed;
    const valuation = RVB.valuationFee;
    const purchaseCosts = buyAgent + landDept + trustee + processing + mortgageRegistration + valuation;

    // Renting: a move (agent commission) in month 1 and after every N-th year; Ejari every year.
    const yearOf = (p) => Math.ceil(p / 12);
    const monthOf = (p) => (p % 12 === 0 ? 12 : p % 12);
    const endsTenancy = (p) => yearOf(p) !== 1 && monthOf(p) === 12 && yearOf(p) % RVB.moveEveryYears === 0;
    const startsTenancy = (p) => p === 1 || endsTenancy(p - 1);
    const yearlyRent = (p) => i.rent * Math.pow(1 + rentGrowth, yearOf(p) - 1);

    const rent = { payments: 0, agent: 0, ejari: 0 };
    const buy = { principal: 0, interest: 0, insurance: 0, community: 0 };
    let balance = loan;
    let yearStartBalance = loan;
    let instalment = 0;
    let cumRent = 0;
    let cumBuy = 0;
    let unrecoverable = 0; // interest + insurance + community + fees: money buying never gets back
    let breakevenMonth = 0;
    let prevGain = 0;
    let firstMonth = null;
    const series = [{ year: 0, saved: 0, lost: 0 }];

    for (let p = 1; p <= months; p++) {
        const year = yearOf(p);
        const month = monthOf(p);
        const apr = aprFor(year);

        // Renting this month
        const rentPaid = month === 1 ? yearlyRent(p) : 0;
        const agent = startsTenancy(p) && month === 1 ? yearlyRent(p) * RVB.rentAgentRate * (1 + VAT) : 0;
        const ejari = (month === 1 ? RVB.ejariRegistration : 0) + (endsTenancy(p) ? RVB.ejariCancellation : 0);
        rent.payments += rentPaid;
        rent.agent += agent;
        rent.ejari += ejari;
        cumRent += rentPaid + agent + ejari;

        // Mortgage this month (instalment re-set each year, as the rate can change)
        if (month === 1) {
            yearStartBalance = balance;
            instalment = pmt(apr / 12, (years - (year - 1)) * 12, yearStartBalance);
        }
        const live = balance > 10; // loan repaid: nothing more to pay
        const interest = live ? balance * apr / 12 : 0;
        const principal = live ? instalment - balance * apr / 12 : 0;
        const life = live ? balance * RVB.lifeInsuranceRate / 12 : 0;
        const building = live && balance > 1 ? RVB.propertyInsuranceRate / 12 * i.price : 0;
        const community = month === 12 ? RVB.communityCostPerYear : 0;
        if (p === 1) firstMonth = { principal, interest, insurance: life + building };
        const monthStartBalance = balance;
        balance -= instalment - balance * apr / 12;

        buy.principal += principal;
        buy.interest += interest;
        buy.insurance += life + building;
        buy.community += community;
        cumBuy += principal + interest + life + building + community + (p === 1 ? i.down + purchaseCosts : 0);
        unrecoverable += interest + life + building + community + (p === 1 ? purchaseCosts : 0);

        // If you sold at the end of this month
        const value = i.price * Math.pow(1 + monthlyGrowth, p);
        const early = balance;
        const settlement = early / monthStartBalance > 0.2 && RVB.earlySettlementRate * monthStartBalance > RVB.earlySettlementCap
            ? RVB.earlySettlementCap : early * RVB.earlySettlementRate;
        const saleIncome = value - value * RVB.agentRate - RVB.nocFee - settlement - early;
        const gain = cumRent + saleIncome - cumBuy;
        if (!breakevenMonth && gain >= 0 && prevGain < 0) breakevenMonth = p;
        prevGain = gain;

        if (month === 12) series.push({ year, saved: gain, lost: unrecoverable - cumRent });
    }

    rent.payments = Math.ceil(rent.payments);
    const rentInitial = rent.agent + rent.ejari;
    const rentTotal = rentInitial + rent.payments;

    const salePrice = i.price * Math.pow(1 + monthlyGrowth, months);
    const sellingFees = salePrice * RVB.agentRate + RVB.nocFee;
    const buyInitial = i.down + purchaseCosts;
    const buyRecurring = buy.principal + buy.interest + buy.insurance + buy.community;
    const settleFinal = balance * RVB.earlySettlementRate; // ~0: the loan is repaid by the end
    const buyTotal = buyInitial + buyRecurring - (salePrice - sellingFees - balance - settleFinal);
    const saved = rentTotal - buyTotal;

    return {
        series,
        saved, // "Saved if buying"
        lost: saved > 0 ? -rentTotal : rentTotal, // "Lost if renting"
        // No crossing but buying ends ahead: it was ahead from the first month.
        breakevenYears: breakevenMonth ? Math.ceil(breakevenMonth / 12) : (saved > 0 ? 1 : 0),
        monthly: { rent: Math.ceil(i.rent / 12), ...firstMonth, buy: firstMonth.principal + firstMonth.interest + firstMonth.insurance },
        rent: { agent: rent.agent, government: rent.ejari, payments: rent.payments, initial: rentInitial, total: rentTotal },
        buy: {
            down: i.down,
            agent: buyAgent,
            government: landDept + trustee,
            bank: processing + valuation + mortgageRegistration,
            initial: buyInitial,
            principal: buy.principal,
            interest: buy.interest,
            insurance: buy.insurance,
            community: buy.community,
            recurring: buyRecurring,
            salePrice,
            sellingFees,
            netSale: salePrice - sellingFees,
            total: buyTotal,
        },
    };
}
