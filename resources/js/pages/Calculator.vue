<template>
    <main>
        <div class="mw-about-progress" aria-hidden="true"><span></span></div>

        <section class="mw-about-hero">
            <div class="mw-about-hero__band">
                <div class="container-ctn">
                    <h1 class="mw-about-title" data-reveal>{{ current.title }}</h1>
                </div>
            </div>
            <nav class="mw-about-crumb" :aria-label="t('calculators.breadcrumb_aria', 'Breadcrumb')">
                <div class="container-ctn">
                    <p><router-link to="/">{{ t('calculators.breadcrumb_home', 'Home') }}</router-link><span class="mw-about-crumb__sep"> / </span><span>{{ current.title }}</span></p>
                </div>
            </nav>
        </section>

        <section class="mw-calc-page">
            <div class="container-ctn">
                <nav class="mw-calc-page__tabs" :aria-label="t('nav.calculators', 'Calculators')">
                    <router-link v-for="item in calculators" :key="item.key" :to="item.to" class="mw-calc-page__tab" :class="{ 'is-active': item.key === current.key }">{{ item.title }}</router-link>
                </nav>

                <div class="mw-calc-page__intro">
                    <h2 class="mw-calc-page__heading">{{ current.heading }}</h2>
                    <p class="mw-calc-page__lead">{{ current.lead }}</p>
                </div>

                <MortgageCalculator v-if="current.key === 'mortgage'" ref="calculator" :price="Number(route.query.price) || 1200000">
                    <template #actions="{ priceAed }">
                        <router-link :to="{ path: '/properties', query: { listing_type: 'sale', max_price: Math.round(priceAed) } }" class="mc__btn mc__btn--solid">{{ t('mortgage_page.browse_cta', 'Browse properties in budget') }}</router-link>
                        <router-link :to="{ hash: '#calculator-enquiry' }" class="mc__btn mc__btn--ghost">{{ t('mortgage_page.contact_cta', 'Talk to an advisor') }}</router-link>
                    </template>
                </MortgageCalculator>
                <EligibilityCalculator v-else-if="current.key === 'eligibility'" ref="calculator" />
                <RentVsBuyCalculator v-else-if="current.key === 'rent-vs-buy'" ref="calculator" />
                <RefinanceCalculator v-else-if="current.key === 'refinance'" ref="calculator" :settings="content?.settings" />

                <CalculatorFaqs :title="content?.faq_title || t('calculators.faq_title', 'Frequently asked questions')" :faqs="content?.faqs || []" />

                <CalculatorEnquiry :title="current.formTitle" :eyebrow="content?.form?.eyebrow || ''" :text="content?.form?.text || ''" :points="content?.form?.points || []" :summary="() => calculator?.summary" />
            </div>
        </section>
    </main>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useStaticText } from '../composables/useStaticText';
import { useCalculatorLinks } from '../composables/useCalculatorLinks';
import { useCalculatorContent } from '../composables/useCalculatorContent';
import CalculatorFaqs from '../components/calculators/CalculatorFaqs.vue';
import MortgageCalculator from '../components/calculators/MortgageCalculator.vue';
import EligibilityCalculator from '../components/calculators/EligibilityCalculator.vue';
import RentVsBuyCalculator from '../components/calculators/RentVsBuyCalculator.vue';
import RefinanceCalculator from '../components/calculators/RefinanceCalculator.vue';
import CalculatorEnquiry from '../components/calculators/CalculatorEnquiry.vue';

// One page for every calculator — the route's meta.calculator picks which one (see router/index.js).
// Copy, FAQs and SEO come from Admin › Calculators; the texts below are the fallback until it loads.
const { t } = useStaticText();
const route = useRoute();
const { calculators, all } = useCalculatorLinks();
const { pages, load } = useCalculatorContent();

const COPY = {
    mortgage: ['mortgage_page.title', 'Estimate Your Monthly Payments', 'mortgage_page.description', 'Adjust the property price, down payment, interest rate and loan term to see what your home loan could cost each month, plus the upfront fees to budget for in the UAE.'],
    eligibility: ['calculators.eligibility.heading', 'How Much Can You Borrow?', 'calculators.eligibility.lead', 'Find the most a UAE bank is likely to lend you and the highest property price you can aim for, based on your income, existing debts and the Central Bank’s 50% debt burden rule.'],
    'rent-vs-buy': ['calculators.rent_vs_buy.heading', 'Should You Rent or Buy?', 'calculators.rent_vs_buy.lead', 'Compare the real cost of renting against buying over the years you plan to stay — including fees, service charges, property growth and what your savings could earn instead.'],
    refinance: ['calculators.refinance.heading', 'Refinance Your Mortgage', 'calculators.refinance.lead', 'Refinance now to lower your monthly payments and lock in better rates. Tell us about your current loan, your job and what you need — we will show how much you could save.'],
};

// Heading of the enquiry form under each calculator.
const FORM_TITLES = {
    mortgage: ['calculators.enquiry.title_mortgage', 'Get a mortgage quote'],
    eligibility: ['calculators.enquiry.title_eligibility', 'Get pre-approved for a mortgage'],
    'rent-vs-buy': ['calculators.enquiry.title_rent_vs_buy', 'Ready to buy? Talk to an advisor'],
    refinance: ['calculators.enquiry.title_refinance', 'Find a better mortgage rate'],
};

const currentKey = computed(() => (COPY[route.meta.calculator] ? route.meta.calculator : 'mortgage'));
const content = computed(() => pages.value[currentKey.value] || null);
watch(currentKey, (key) => load(key), { immediate: true });

const current = computed(() => {
    const item = calculators.value.find((c) => c.key === currentKey.value) || all.value.find((c) => c.key === currentKey.value);
    const [headingKey, heading, leadKey, lead] = COPY[item.key];
    const page = content.value || {};
    return {
        ...item,
        title: page.title || item.title,
        heading: page.heading || t(headingKey, heading),
        lead: page.lead || t(leadKey, lead),
        formTitle: page.form?.title || t(...FORM_TITLES[item.key]),
    };
});

// The calculator on screen — its exposed `summary` (the figures worked out) travels with the enquiry.
const calculator = ref(null);
</script>

<style scoped>
.mw-calc-page { padding: clamp(28px, 3vw, 56px) 0 clamp(56px, 6vw, 110px); font-family: "Plus Jakarta Sans", sans-serif; }
.mw-calc-page__tabs { display: flex; gap: 8px; margin-bottom: clamp(24px, 2.4vw, 44px); padding-bottom: 4px; overflow-x: auto; scrollbar-width: none; }
.mw-calc-page__tabs::-webkit-scrollbar { display: none; }
.mw-calc-page__tab { flex-shrink: 0; padding: 10px 18px; border: 1px solid #d6dce8; border-radius: 50px; background: #fff; color: #244373; font-size: 14px; font-weight: 700; text-decoration: none; white-space: nowrap; transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease; }
.mw-calc-page__tab:hover { border-color: #244373; color: #244373; }
.mw-calc-page__tab.is-active { border-color: transparent; background: linear-gradient(90deg, #c92844 0%, #244373 100%); color: #fff; }
.mw-calc-page__intro { max-width: 760px; margin-bottom: clamp(24px, 2.4vw, 44px); }
.mw-calc-page__heading { margin: 0; color: #244373; font-family: "Playfair Display", Georgia, serif; font-size: clamp(28px, 2.6vw, 46px); font-weight: 600; line-height: 1.15; }
.mw-calc-page__lead { margin: 12px 0 0; color: #35373c; font-size: clamp(15px, 1vw, 17px); line-height: 1.65; }
</style>
