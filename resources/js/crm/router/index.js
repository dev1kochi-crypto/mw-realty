import { createRouter, createWebHistory } from 'vue-router';

/**
 * CRM web app screens. A module that hasn't moved here yet is still a /portal page — the menu
 * (CrmNavigation, GET /api/crm/navigation) links there until its `route` is set to one of these.
 * meta: title (browser tab), nav (menu item key), help (help guide topic for the top bar's "!").
 */
const routes = [
    { path: '/', redirect: { name: 'dashboard' } },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('../pages/dashboard/DashboardIndex.vue'),
        meta: { title: 'Dashboard', nav: 'dashboard', help: 'dashboard' },
    },
    {
        path: '/leads',
        name: 'leads.index',
        component: () => import('../pages/leads/LeadsIndex.vue'),
        meta: { title: 'Leads', nav: 'leads', help: 'leads' },
    },
    {
        path: '/leads/deleted',
        name: 'leads.trashed',
        component: () => import('../pages/leads/LeadsTrashed.vue'),
        meta: { title: 'Deleted Leads', nav: 'leads', help: 'leads' },
    },
    {
        path: '/leads/:id(\\d+)',
        name: 'leads.show',
        component: () => import('../pages/leads/LeadShow.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'Lead', nav: 'leads', help: 'leads' },
    },
    {
        path: '/lead-insights',
        name: 'lead-insights.index',
        component: () => import('../pages/lead-insights/LeadInsightsIndex.vue'),
        meta: { title: 'Lead Insights', nav: 'lead-insights', help: 'lead-insights' },
    },
    // Properties / Commercial — the same screens over one table, split by segment (pages/properties/sections.js).
    ...[['properties', 'residential', 'Properties', 'Property', 'properties'], ['commercial', 'commercial', 'Commercial', 'Commercial Property', 'commercial']]
        .flatMap(([base, segment, title, item, help]) => [
            {
                path: `/${base}`,
                name: `${base}.index`,
                component: () => import('../pages/properties/PropertiesIndex.vue'),
                meta: { title, nav: base, segment, help },
            },
            {
                path: `/${base}/create`,
                name: `${base}.create`,
                component: () => import('../pages/properties/PropertyForm.vue'),
                meta: { title: `Add ${item}`, nav: base, segment, help: 'property-form' },
            },
            {
                path: `/${base}/:id(\\d+)`,
                name: `${base}.show`,
                component: () => import('../pages/properties/PropertyShow.vue'),
                props: (route) => ({ id: Number(route.params.id) }),
                meta: { title, nav: base, segment, help },
            },
            {
                path: `/${base}/:id(\\d+)/edit`,
                name: `${base}.edit`,
                component: () => import('../pages/properties/PropertyForm.vue'),
                props: (route) => ({ id: Number(route.params.id) }),
                meta: { title: `Edit ${item}`, nav: base, segment, help: 'property-form' },
            },
        ]),
    {
        path: '/premium',
        name: 'premium.index',
        component: () => import('../pages/premium/PremiumIndex.vue'),
        meta: { title: 'Premium', nav: 'featured', help: 'premium' },
    },
    {
        path: '/sold-listings',
        name: 'sold-listings.index',
        component: () => import('../pages/sold/SoldIndex.vue'),
        meta: { title: 'Sold Listings', nav: 'sold', help: 'sold' },
    },
    // Marketing Properties — Super Admin only (the API answers 403 for anyone else).
    {
        path: '/marketing-properties',
        name: 'marketing.index',
        component: () => import('../pages/marketing/MarketingIndex.vue'),
        meta: { title: 'Marketing Properties', nav: 'marketing', help: 'marketing' },
    },
    // Agents — an agency's roster; Super Admin sees every agent.
    {
        path: '/agents',
        name: 'agents.index',
        component: () => import('../pages/agents/AgentsIndex.vue'),
        meta: { title: 'Agents', nav: 'agents', help: 'agents' },
    },
    {
        path: '/agents/create',
        name: 'agents.create',
        component: () => import('../pages/agents/AgentCreate.vue'),
        meta: { title: 'Add Agent', nav: 'agents', help: 'agents' },
    },
    {
        path: '/agents/:id(\\d+)',
        name: 'agents.show',
        component: () => import('../pages/agents/AgentShow.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'Agent', nav: 'agents', help: 'agents' },
    },
    {
        path: '/nearby-places',
        name: 'nearby-places.index',
        component: () => import('../pages/nearby-places/NearbyPlacesIndex.vue'),
        meta: { title: 'Nearby Places', nav: 'nearby-places', help: 'nearby-places' },
    },
    {
        path: '/nearby-places/create',
        name: 'nearby-places.create',
        component: () => import('../pages/nearby-places/NearbyPlaceForm.vue'),
        meta: { title: 'Add Nearby Place', nav: 'nearby-places', help: 'nearby-places' },
    },
    {
        path: '/nearby-places/:id(\\d+)/edit',
        name: 'nearby-places.edit',
        component: () => import('../pages/nearby-places/NearbyPlaceForm.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'Edit Nearby Place', nav: 'nearby-places', help: 'nearby-places' },
    },
    {
        path: '/listing-settings',
        name: 'listing-settings',
        component: () => import('../pages/listing-settings/WatermarkSettings.vue'),
        meta: { title: 'Listing Settings', nav: 'watermark', help: 'watermark' },
    },
    // Reports — one screen, a tab per report; the Sales report used to be called Revenue.
    { path: '/reports/revenue', redirect: (to) => ({ name: 'reports.show', params: { report: 'sales' }, query: to.query }) },
    {
        path: '/reports/:report(leads|properties|sales|agents)?',
        name: 'reports.show',
        component: () => import('../pages/reports/ReportsIndex.vue'),
        meta: { title: 'Reports', nav: 'reports', help: 'reports' },
    },
    // Contact Us — the account's support tickets.
    {
        path: '/contact',
        name: 'contact.index',
        component: () => import('../pages/contact/ContactIndex.vue'),
        meta: { title: 'Contact Us', nav: 'contact', help: 'support' },
    },
    {
        path: '/contact/tickets/create',
        name: 'contact.create',
        component: () => import('../pages/contact/TicketCreate.vue'),
        meta: { title: 'Raise a Ticket', nav: 'contact', help: 'support' },
    },
    {
        path: '/contact/tickets/:id(\\d+)',
        name: 'contact.show',
        component: () => import('../pages/contact/TicketShow.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'Support Ticket', nav: 'contact', help: 'support' },
    },
    // Account menu — agent / company accounts (the API answers 403 for a Super Admin).
    {
        path: '/profile',
        name: 'profile',
        component: () => import('../pages/account/ProfileIndex.vue'),
        meta: { title: 'My Profile', help: 'profile' },
    },
    {
        path: '/security',
        name: 'security',
        component: () => import('../pages/account/SecurityIndex.vue'),
        meta: { title: 'Security', help: 'security' },
    },
    // Also where portal.2fa sends an account that must set up 2FA (web route crm.two-factor).
    {
        path: '/security/two-factor',
        name: 'two-factor.setup',
        component: () => import('../pages/account/TwoFactorSetup.vue'),
        meta: { title: 'Two-Factor Authentication', help: 'security' },
    },
    {
        path: '/plans',
        name: 'plans.index',
        component: () => import('../pages/account/PlansIndex.vue'),
        meta: { title: 'Plans', help: 'plans' },
    },
    {
        path: '/plans/checkout',
        name: 'plans.checkout',
        component: () => import('../pages/account/PlanCheckout.vue'),
        meta: { title: 'Checkout', help: 'plans' },
    },
    {
        path: '/plans/payments',
        name: 'plans.payments',
        component: () => import('../pages/account/PlanPayments.vue'),
        meta: { title: 'Payment History', help: 'plans' },
    },
    {
        path: '/plans/payments/:id(\\d+)/invoice',
        name: 'plans.invoice',
        component: () => import('../pages/account/PlanInvoice.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'Invoice', help: 'plans' },
    },
    // Listing Permits — Super Admin only (the API answers 403 for anyone else).
    {
        path: '/listing-permits',
        name: 'listing-permits.index',
        component: () => import('../pages/listing-permits/ListingPermitsIndex.vue'),
        meta: { title: 'Listing Permits', nav: 'listing-approvals', help: 'listing-approvals' },
    },
    {
        path: '/listing-permits/:id(\\d+)',
        name: 'listing-permits.show',
        component: () => import('../pages/listing-permits/ListingPermitShow.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'Listing Permit', nav: 'listing-approvals', help: 'listing-approvals' },
    },
    // Website Leads — Super Admin only (the API answers 403 for anyone else).
    {
        path: '/website-leads',
        name: 'website-leads.index',
        component: () => import('../pages/website-leads/WebsiteLeadsIndex.vue'),
        meta: { title: 'Website Leads', nav: 'website-leads', help: 'website-leads' },
    },
    {
        path: '/website-leads/:id(\\d+)',
        name: 'website-leads.show',
        component: () => import('../pages/website-leads/WebsiteLeadShow.vue'),
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'Website Lead', nav: 'website-leads', help: 'website-leads' },
    },
    // Integrations — hub + one screen per integration.
    {
        path: '/integrations',
        name: 'integrations.index',
        component: () => import('../pages/integrations/IntegrationsIndex.vue'),
        meta: { title: 'Integrations', nav: 'integrations', help: 'integrations' },
    },
    {
        path: '/integrations/facebook',
        name: 'integrations.facebook',
        component: () => import('../pages/integrations/FacebookIntegration.vue'),
        meta: { title: 'Facebook Lead Ads', nav: 'integrations', help: 'facebook' },
    },
    {
        path: '/integrations/property-finder',
        name: 'integrations.property-finder',
        component: () => import('../pages/integrations/PropertyFinderIntegration.vue'),
        meta: { title: 'Property Finder', nav: 'integrations', help: 'property-finder' },
    },
    {
        path: '/integrations/property-finder/review',
        name: 'integrations.property-finder.review',
        component: () => import('../pages/integrations/PropertyFinderReview.vue'),
        meta: { title: 'Property Finder review', nav: 'integrations', help: 'property-finder' },
    },
    // Master › Stage / Tag / Source — one screen, configured per type (pages/master/masterTypes.js).
    {
        path: '/master/stages',
        name: 'master.stages',
        component: () => import('../pages/master/MasterIndex.vue'),
        meta: { title: 'Master - Stages', nav: 'master-stages', masterType: 'stages', help: 'stages' },
    },
    {
        path: '/master/tags',
        name: 'master.tags',
        component: () => import('../pages/master/MasterIndex.vue'),
        meta: { title: 'Master - Tags', nav: 'master-tags', masterType: 'tags', help: 'tags' },
    },
    {
        path: '/master/sources',
        name: 'master.sources',
        component: () => import('../pages/master/MasterIndex.vue'),
        meta: { title: 'Master - Sources', nav: 'master-sources', masterType: 'sources', help: 'sources' },
    },
    // Master › Property Options — Super Admin only (the API answers 403 for anyone else).
    {
        path: '/master/property-options',
        name: 'master.property-options',
        component: () => import('../pages/master/PropertyOptionsIndex.vue'),
        meta: { title: 'Master - Property Options', nav: 'master-property-options', help: 'property-options' },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('../pages/NotFound.vue'),
        meta: { title: 'Page not found' },
    },
];

const router = createRouter({
    history: createWebHistory(window.CrmConfig.basePath),
    routes,
    scrollBehavior: (to, from, saved) => saved ?? (to.hash ? { el: to.hash } : { top: 0 }),
});

router.afterEach((to) => {
    document.title = `${to.meta.title ?? 'CRM'} - Partner Portal`;
});

export default router;
