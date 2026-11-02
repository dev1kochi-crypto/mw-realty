// Labels for the automated assessment (PropertyInsightService::assessment) — headline badge and signals.

const BADGES = {
    strong_investment: 'Strong investment choice',
    high_yield: 'High yield potential',
    below_market: 'Priced below market',
    strong_growth: 'Strong capital growth',
    above_market: 'Priced above market',
    low_yield: 'Lower rental yield',
    steady_yield: 'Steady rental yield',
    falling_prices: 'Prices softening',
    fair_price: 'In line with the market',
    in_line: 'In line with the market',
    good_value_rent: 'Good value rent',
    above_market_rent: 'Above market rent',
    fair_rent: 'Fair market rent',
};

const SIGNALS = {
    below_market: 'Priced {value}% below the area median per sq.ft',
    above_market: 'Priced {value}% above the area median per sq.ft',
    fair_price: 'Priced close to the area median per sq.ft',
    good_value_rent: 'Rent is {value}% below the area median per sq.ft',
    above_market_rent: 'Rent is {value}% above the area median per sq.ft',
    high_yield: 'Estimated gross rental yield of {value}%',
    steady_yield: 'Estimated gross rental yield of {value}%',
    low_yield: 'Estimated gross rental yield of only {value}%',
    strong_growth: 'Prices per sq.ft up {value}% year on year',
    falling_prices: 'Prices per sq.ft down {value}% year on year',
};

/** Which estimate (insights.estimates.*) each signal was worked out from — for its source tag. */
export const SIGNAL_ESTIMATE = {
    below_market: 'price_ratio', above_market: 'price_ratio', fair_price: 'price_ratio',
    good_value_rent: 'price_ratio', above_market_rent: 'price_ratio',
    high_yield: 'gross_yield', steady_yield: 'gross_yield', low_yield: 'gross_yield',
    strong_growth: 'growth', falling_prices: 'growth',
};

export const badgeLabel = (t, key) => t(`property_details.insights.badge.${key}`, BADGES[key] || key);

export const signalText = (t, signal) => t(`property_details.insights.signal.${signal.key}`, SIGNALS[signal.key] || signal.key)
    .replace('{value}', Math.abs(signal.value));
