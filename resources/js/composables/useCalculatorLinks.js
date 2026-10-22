import { computed } from 'vue';
import { useStaticText } from './useStaticText';
import { useCalculatorMenu } from './useCalculatorContent';

const PATHS = {
    mortgage: '/mortgage-calculator',
    eligibility: '/eligibility-calculator',
    'rent-vs-buy': '/rent-vs-buy-calculator',
    refinance: '/refinance-calculator',
};

/**
 * The calculator pages — shared by the header's Calculators menu and the tabs on each calculator
 * page. Titles, order and which ones show come from Admin › Calculators; until that loads (or if
 * it fails) the built-in list is used.
 */
export function useCalculatorLinks() {
    const { t } = useStaticText();
    const { menu } = useCalculatorMenu();

    const fallback = computed(() => [
        { key: 'mortgage', title: t('nav.mortgage_calculator', 'Mortgage Calculator') },
        { key: 'eligibility', title: t('nav.eligibility_calculator', 'Eligibility Calculator') },
        { key: 'rent-vs-buy', title: t('nav.rent_vs_buy_calculator', 'Rent vs Buy Calculator') },
        { key: 'refinance', title: t('nav.refinance_calculator', 'Refinance Calculator') },
    ]);

    const calculators = computed(() => {
        const titles = Object.fromEntries(fallback.value.map((c) => [c.key, c.title]));
        const list = menu.value?.length ? menu.value.filter((c) => PATHS[c.key]) : fallback.value;
        return list.map((c) => ({ key: c.key, to: PATHS[c.key], title: c.title || titles[c.key] }));
    });

    // Every calculator, shown in the menu or not — a page reached by direct link still finds itself.
    const all = computed(() => fallback.value.map((c) => ({ ...c, to: PATHS[c.key] })));

    return { calculators, all };
}
