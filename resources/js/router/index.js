import { createRouter, createWebHistory } from 'vue-router';
import { nextTick } from 'vue';

const routes = [
    {
        path: '/',
        name: 'home',
        component: () => import('../pages/Home.vue'),
        meta: {
            title: 'MW Realty | Your Trusted Partner in Finding the Best Properties',
            bodyClass: 'home-page',
            activeNav: 'home',
            headerVariant: 'home',
            activeBottom: 'home',
            isHome: true,
        },
    },
    {
        path: '/about',
        name: 'about',
        component: () => import('../pages/About.vue'),
        meta: { title: 'About Us | MW Realty', bodyClass: 'about-page', activeNav: 'about' },
    },
    {
        path: '/commercial',
        name: 'commercial',
        component: () => import('../pages/Commercial.vue'),
        meta: { title: 'Commercial Properties | MW Realty', bodyClass: 'agents-page commercial-page', activeNav: 'commercial' },
    },
    {
        path: '/agents',
        name: 'agents',
        component: () => import('../pages/Agents.vue'),
        meta: { title: 'Our Agent | MW Realty', bodyClass: 'agents-page', activeNav: 'agents' },
    },
    {
        path: '/agent-details/:slug',
        name: 'agent-details',
        component: () => import('../pages/AgentDetails.vue'),
        meta: { title: 'Agent Details | MW Realty', bodyClass: 'agents-page agent-details-page', activeNav: 'agents' },
    },
    { path: '/agent-login', redirect: '/login' },
    { path: '/agent-signup', redirect: '/signup' },
    {
        path: '/agencies',
        name: 'agencies',
        component: () => import('../pages/Agencies.vue'),
        meta: { title: 'Our Agencies | MW Realty', bodyClass: 'agents-page agencies-page', activeNav: 'agencies' },
    },
    {
        path: '/agency-details/:slug',
        name: 'agency-details',
        component: () => import('../pages/AgencyDetails.vue'),
        meta: { title: 'Agency Details | MW Realty', bodyClass: 'agents-page agency-details-page', activeNav: 'agencies' },
    },
    { path: '/agency-login', redirect: '/login' },
    { path: '/agency-signup', redirect: '/signup' },
    {
        path: '/blogs',
        name: 'blogs',
        component: () => import('../pages/Blogs.vue'),
        meta: { title: 'Blog | MW Realty', bodyClass: 'agents-page blogs-page', activeNav: 'blogs' },
    },
    {
        path: '/blog-details/:slug',
        name: 'blog-details',
        component: () => import('../pages/BlogDetails.vue'),
        meta: { title: 'Blog Details | MW Realty', bodyClass: 'agents-page blog-details-page', activeNav: 'blogs' },
    },
    {
        path: '/contact',
        name: 'contact',
        component: () => import('../pages/Contact.vue'),
        meta: { title: 'Contact Us | MW Realty', bodyClass: 'contact-page', activeNav: 'contact' },
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('../pages/Login.vue'),
        meta: { title: 'Login | MW Realty', bodyClass: 'agents-page login-page', activeBottom: 'login' },
    },
    {
        path: '/signup',
        name: 'signup',
        component: () => import('../pages/Signup.vue'),
        meta: { title: 'Sign Up | MW Realty', bodyClass: 'agents-page signup-page' },
    },
    {
        path: '/profile',
        name: 'profile',
        component: () => import('../pages/Profile.vue'),
        meta: { title: 'My Account | MW Realty', bodyClass: 'agents-page profile-page' },
    },
    {
        path: '/properties-dubai',
        name: 'properties-dubai',
        component: () => import('../pages/PropertiesDubai.vue'),
        meta: { title: 'Properties in Dubai | MW Realty', bodyClass: 'agents-page properties-dubai-page' },
    },
    {
        path: '/property-details/:slug',
        name: 'property-details',
        component: () => import('../pages/PropertyDetails.vue'),
        meta: { title: 'Property Details | MW Realty', bodyClass: 'agents-page property-details-page' },
    },
    {
        path: '/terms-and-conditions',
        name: 'terms-and-conditions',
        component: () => import('../pages/LegalPage.vue'),
        meta: { title: 'Terms and Conditions | MW Realty', bodyClass: 'agents-page terms-page', legalKey: 'terms' },
    },
    {
        path: '/privacy-policy',
        name: 'privacy-policy',
        component: () => import('../pages/LegalPage.vue'),
        meta: { title: 'Privacy Policy | MW Realty', bodyClass: 'agents-page terms-page', legalKey: 'privacy' },
    },
    {
        path: '/security-policy',
        name: 'security-policy',
        component: () => import('../pages/LegalPage.vue'),
        meta: { title: 'Security Policy | MW Realty', bodyClass: 'agents-page terms-page', legalKey: 'security' },
    },
    {
        path: '/cookie-settings',
        name: 'cookie-settings',
        component: () => import('../pages/LegalPage.vue'),
        meta: { title: 'Cookie Settings | MW Realty', bodyClass: 'agents-page terms-page', legalKey: 'cookie' },
    },
    {
        path: '/thank-you',
        name: 'thank-you',
        component: () => import('../pages/ThankYou.vue'),
        meta: { title: 'Thank You | MW Realty', bodyClass: 'agents-page thank-you-page' },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('../pages/NotFound.vue'),
        meta: { title: 'Page Not Found | MW Realty', bodyClass: 'agents-page not-found-page' },
    },
];

// The browser's own scroll-restoration remembers the scrollY from before a hard
// reload and jumps straight there as soon as the page repaints — but this is a
// lazily-mounted SPA, so that jump fires before the async page chunk has mounted
// and grown the page to its real height, landing wherever the (still mostly
// empty) page happens to be tall enough at that instant, usually the footer.
// Every load (including a reload) should always land at the top instead, so
// this is disabled outright rather than replaced with our own restoration.
if ('scrollRestoration' in window.history) {
    window.history.scrollRestoration = 'manual';
}

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) return savedPosition;
        if (to.hash) return { el: to.hash };
        return { top: 0 };
    },
});

// assets/js/script.js (sliders, reveal-on-scroll, tabs, dropdowns, galleries…) only
// scans the DOM once on the initial DOMContentLoaded, before this router's lazy-loaded
// page component has mounted — so none of it would ever wire up otherwise. Its
// window.MWRealty.refresh is safe to call again after every navigation.
router.afterEach(() => {
    nextTick(() => {
        window.MWRealty && window.MWRealty.refresh();
    });
});

export default router;
