<?php

namespace App\Services\Crm;

use App\Models\AgencyAgent;
use App\Models\PortalUser;
use App\Models\Property;
use App\Models\SupportTicket;
use App\Models\Visitors\VisitorLead;
use Illuminate\Support\Facades\Auth;

/**
 * The CRM side menu — which items the signed-in account sees, which are locked (KYC pending /
 * plan) and their badge counts. One definition for both the /crm web app and the mobile app,
 * mirroring resources/views/portal/layouts/app.blade.php's sidebar.
 *
 * `route` = the web app's Vue route name once a module is migrated; until then it's null and
 * `legacy_url` opens the module's /portal screen. Move an item over by setting its route.
 */
class CrmNavigation
{
    public function __construct(private OwnerContext $context)
    {
    }

    public function items(): array
    {
        $owner = $this->context->owner();
        $isSuperAdmin = $this->context->isAdmin();
        $notApproved = $owner && $owner->status !== 'approved';
        $kycLock = $notApproved ? 'Unlocks after KYC approval' : null;

        $items = [
            $this->item('dashboard', 'Dashboard', 'fa-chart-pie', null, null, route: 'dashboard'),

            $this->item('leads', 'Leads', 'fa-address-book', 'Leads', null, route: 'leads.index', lock: $kycLock),
            $this->item('lead-insights', 'Lead Insights', 'fa-chart-line', 'Leads', null, route: 'lead-insights.index', lock: $kycLock),
            $isSuperAdmin ? $this->item('website-leads', 'Website Leads', 'fa-user-clock', 'Leads', null, route: 'website-leads.index',
                badge: VisitorLead::inPool()->where('created_at', '>=', now()->subDays(7))->count()) : null,
            $this->item('master', 'Master', 'fa-layer-group', 'Leads', null, lock: $kycLock, children: array_values(array_filter([
                $this->item('master-stages', 'Stage', null, null, null, route: 'master.stages'),
                $this->item('master-tags', 'Tag', null, null, null, route: 'master.tags'),
                $this->item('master-sources', 'Source', null, null, null, route: 'master.sources'),
                $isSuperAdmin ? $this->item('master-property-options', 'Property Options', null, null, null, route: 'master.property-options') : null,
            ]))),
            $this->item('integrations', 'Integrations', 'fa-plug', 'Leads', null, route: 'integrations.index', lock: $kycLock),

            $this->item('properties', 'Properties', 'fa-building', 'Listings', null, route: 'properties.index', lock: $kycLock,
                badge: $owner && !$notApproved ? Property::accessibleBy($owner)->whereNull('sold_at')
                    ->whereIn('compliance_status', [Property::COMPLIANCE_CHANGES_REQUESTED, Property::COMPLIANCE_EXPIRED])->count() : 0),
            $this->item('commercial', 'Commercial', 'fa-store', 'Listings', null, route: 'commercial.index', lock: $kycLock),
            $isSuperAdmin ? $this->item('listing-approvals', 'Listing Permits', 'fa-file-shield', 'Listings', null, route: 'listing-permits.index') : null,
            $this->item('featured', 'Premium', 'fa-star', 'Listings', null, route: 'premium.index', lock: $kycLock),
            $this->hasSoldListings($owner) ? $this->item('sold', 'Sold Listings', 'fa-handshake', 'Listings', null, route: 'sold-listings.index') : null,
            $isSuperAdmin ? $this->item('marketing', 'Marketing Properties', 'fa-bullhorn', 'Listings', null, route: 'marketing.index') : null,
            $owner?->type === 'agent' ? $this->item('agency', 'My Agency', 'fa-building-user', 'Listings', 'portal.agency.index',
                badge: AgencyAgent::where('agent_id', $owner->id)->where('status', AgencyAgent::INVITED)->count()) : null,
            $isSuperAdmin || $owner?->type === 'company' ? $this->item('agents', 'Agents', 'fa-user-tie', 'Listings', null, route: 'agents.index', lock: $kycLock,
                badge: $owner && !$notApproved ? AgencyAgent::where('agency_id', $owner->id)->where('status', AgencyAgent::REQUESTED)->count() : 0) : null,
            $this->item('nearby-places', 'Nearby Places', 'fa-map-marker-alt', 'Listings', null, route: 'nearby-places.index', lock: $kycLock),
            $this->item('watermark', 'Listing Settings', 'fa-stamp', 'Listings', null, route: 'listing-settings', lock: $kycLock),

            $this->item('reports', 'Reports', 'fa-chart-pie', 'Insights', null, route: 'reports.show',
                lock: $kycLock ?? ($owner && !$owner->hasReportsAccess() ? 'Upgrade your plan to unlock reports' : null)),

            $owner ? $this->item('contact', 'Contact Us', 'fa-headset', 'Support', null, route: 'contact.index',
                badge: SupportTicket::where('portal_user_id', $owner->id)->needsClientReply()->count()) : null,
        ];

        return array_values(array_filter($items));
    }

    /** Account (avatar) menu — profile, security, plans. */
    public function accountItems(): array
    {
        $owner = $this->context->owner();
        if (!$owner) {
            return [$this->item('back-to-admin', 'Back to Admin', 'fa-shield-alt', null, 'cms.dashboard')];
        }

        return [
            $this->item('profile', 'My Profile', 'fa-user-edit', null, null, route: 'profile'),
            $this->item('security', 'Security', 'fa-shield-halved', null, null, route: 'security'),
            $this->item('plans', 'Plans', 'fa-layer-group', null, null, route: 'plans.index',
                lock: $owner->status !== 'approved' ? 'Unlocks after KYC approval' : null),
        ];
    }

    private function item(string $key, string $label, ?string $icon, ?string $section, ?string $legacyRoute, ?string $route = null, ?string $lock = null, int $badge = 0, array $children = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'section' => $section,
            'route' => $route,
            'legacy_url' => $legacyRoute ? route($legacyRoute) : null,
            'locked' => $lock !== null,
            'lock_reason' => $lock,
            'badge' => $badge,
            'children' => $children,
        ];
    }

    private function hasSoldListings(?PortalUser $owner): bool
    {
        if (!$owner && !Auth::guard('cms')->check()) {
            return false;
        }

        return Property::soldOrRented()->when($owner, fn ($q) => $q->accessibleBy($owner))->exists();
    }
}
