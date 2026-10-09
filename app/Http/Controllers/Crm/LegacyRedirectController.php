<?php

namespace App\Http\Controllers\Crm;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * The old /portal addresses of screens that moved to the CRM web app (/crm). Their route names
 * (portal.dashboard, portal.crm.leads.show…) are kept on these redirects, so every link that
 * still uses them — emails already sent, saved notifications, CMS shortcuts, other portal
 * screens — lands on the new screen. The query string is carried over (filters, ?import=…).
 */
class LegacyRedirectController extends Controller
{
    public function dashboard(Request $request)
    {
        return $this->to('dashboard', $request);
    }

    public function leads(Request $request)
    {
        return $this->to('leads', $request);
    }

    public function lead(Request $request, $id)
    {
        return $this->to('leads/' . (int) $id, $request);
    }

    public function trashedLeads(Request $request)
    {
        return $this->to('leads/deleted', $request);
    }

    public function leadInsights(Request $request)
    {
        return $this->to('lead-insights', $request);
    }

    public function websiteLeads(Request $request)
    {
        return $this->to('website-leads', $request);
    }

    public function websiteLead(Request $request, $id)
    {
        return $this->to('website-leads/' . (int) $id, $request);
    }

    public function properties(Request $request)
    {
        return $this->to('properties', $request);
    }

    public function propertyCreate(Request $request)
    {
        return $this->to('properties/create', $request);
    }

    public function commercial(Request $request)
    {
        return $this->to('commercial', $request);
    }

    public function commercialCreate(Request $request)
    {
        return $this->to('commercial/create', $request);
    }

    /** A listing's page — the CRM app moves a commercial listing to its own menu's URL. */
    public function property(Request $request, $id)
    {
        return $this->to('properties/' . (int) $id, $request);
    }

    public function propertyEdit(Request $request, $id)
    {
        return $this->to('properties/' . (int) $id . '/edit', $request);
    }

    public function soldListings(Request $request)
    {
        return $this->to('sold-listings', $request);
    }

    public function listingPermits(Request $request)
    {
        return $this->to('listing-permits', $request);
    }

    public function listingPermit(Request $request, $id)
    {
        return $this->to('listing-permits/' . (int) $id, $request);
    }

    public function premium(Request $request)
    {
        return $this->to('premium', $request);
    }

    public function marketingProperties(Request $request)
    {
        return $this->to('marketing-properties', $request);
    }

    public function agents(Request $request)
    {
        return $this->to('agents', $request);
    }

    public function agentCreate(Request $request)
    {
        return $this->to('agents/create', $request);
    }

    public function agent(Request $request, $id)
    {
        return $this->to('agents/' . (int) $id, $request);
    }

    public function nearbyPlaces(Request $request)
    {
        return $this->to('nearby-places', $request);
    }

    public function nearbyPlaceCreate(Request $request)
    {
        return $this->to('nearby-places/create', $request);
    }

    public function nearbyPlaceEdit(Request $request, $id)
    {
        return $this->to('nearby-places/' . (int) $id . '/edit', $request);
    }

    public function watermark(Request $request)
    {
        return $this->to('listing-settings', $request);
    }

    /** The Sales report used to be called Revenue — old links land on Sales. */
    public function reports(Request $request, ?string $report = null)
    {
        return $this->to('reports/' . ($report === 'revenue' ? 'sales' : ($report ?? 'leads')), $request);
    }

    public function contact(Request $request)
    {
        return $this->to('contact', $request);
    }

    public function contactCreate(Request $request)
    {
        return $this->to('contact/tickets/create', $request);
    }

    public function contactTicket(Request $request, $ticket)
    {
        return $this->to('contact/tickets/' . (int) $ticket, $request);
    }

    public function profile(Request $request)
    {
        return $this->to('profile', $request);
    }

    public function security(Request $request)
    {
        return $this->to('security', $request);
    }

    /** ?onboarding=1 is carried over — the set-up screen then offers "Skip for now". */
    public function twoFactorSetup(Request $request)
    {
        return $this->to('security/two-factor', $request);
    }

    /** Also the old Stripe Checkout success URL — ?session_id= is carried over and finished on the Plans page. */
    public function plans(Request $request)
    {
        return $this->to('plans', $request);
    }

    public function planCheckout(Request $request)
    {
        return $this->to('plans/checkout', $request);
    }

    public function planPayments(Request $request)
    {
        return $this->to('plans/payments', $request);
    }

    /** Invoice links in emails (web page and PDF) open the invoice in the CRM app. */
    public function planInvoice(Request $request, $id)
    {
        return $this->to('plans/payments/' . (int) $id . '/invoice', $request);
    }

    public function propertyOptions(Request $request)
    {
        return $this->to('master/property-options', $request);
    }

    public function integrations(Request $request)
    {
        return $this->to('integrations', $request);
    }

    public function facebookIntegration(Request $request)
    {
        return $this->to('integrations/facebook', $request);
    }

    public function propertyFinder(Request $request)
    {
        return $this->to('integrations/property-finder', $request);
    }

    public function propertyFinderReview(Request $request)
    {
        return $this->to('integrations/property-finder/review', $request);
    }

    private function to(string $path, Request $request)
    {
        $query = $request->getQueryString();

        return redirect()->to(route('crm.app', $path) . ($query ? '?' . $query : ''));
    }
}
