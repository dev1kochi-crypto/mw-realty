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
        path: '/agent-details',
        name: 'agent-details',
        component: () => import('../pages/AgentDetails.vue'),
        meta: { title: 'Jayme Craig | MW Realty', bodyClass: 'agents-page agent-details-page', activeNav: 'agents' },
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
        path: '/agency-details',
        name: 'agency-details',
        component: () => import('../pages/AgencyDetails.vue'),
        meta: { title: 'DXB Dubai Properties | MW Realty', bodyClass: 'agents-page agency-details-page', activeNav: 'agencies' },
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
        path: '/property-details',
        name: 'property-details',
        component: () => import('../pages/PropertyDetails.vue'),
        meta: { title: 'Elegant Apartment | MW Realty', bodyClass: 'agents-page property-details-page' },
    },
    {
        path: '/terms-and-conditions',
        name: 'terms-and-conditions',
        component: () => import('../pages/TermsAndConditions.vue'),
        meta: { title: 'Terms and Conditions | MW Realty', bodyClass: 'agents-page terms-page' },
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
// Disabling the native restore and doing it ourselves after the page has
// actually mounted (below) fixes that while keeping the same end behavior:
// paint at the top first, then settle back where the reader left off.
if ('scrollRestoration' in window.history) {
    window.history.scrollRestoration = 'manual';
}

const SCROLL_KEY_PREFIX = 'mw-scroll:';
let scrollSaveScheduled = false;
window.addEventListener(
    'scroll',
    () => {
        if (scrollSaveScheduled) return;
        scrollSaveScheduled = true;
        requestAnimationFrame(() => {
            sessionStorage.setItem(SCROLL_KEY_PREFIX + window.location.pathname, String(window.scrollY));
            scrollSaveScheduled = false;
        });
    },
    { passive: true },
);

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) return savedPosition;

        // Vue Router gives the very first navigation a "from" with no matched
        // route at all — a reliable, order-independent way to tell "this is a
        // fresh page load" apart from a later in-app navigation (which should
        // just go to { top: 0 } like a normal new page).
        const isInitialLoad = from.matched.length === 0;
        if (isInitialLoad) {
            const saved = sessionStorage.getItem(SCROLL_KEY_PREFIX + to.path);
            if (saved !== null) {
                // Wait for the lazy-loaded page to actually mount and lay out —
                // nextTick alone can still fire before images/late content have
                // settled the page's final height, so give layout one more frame.
                return nextTick().then(
                    () =>
                        new Promise((resolve) => {
                            requestAnimationFrame(() => {
                                resolve({ top: Number(saved) });
                            });
                        }),
                );
            }
        }

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
