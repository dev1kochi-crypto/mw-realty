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
    /** KYC (and 2FA) locks shut the screen entirely, so those items can't be opened; plan locks stay clickable for the "Unlock …" card. */
    public const KYC_LOCK = 'Unlocks after KYC approval';

    public function __construct(private OwnerContext $context)
    {
    }

    public function items(): array
    {
        $owner = $this->context->owner();
        $isSuperAdmin = $this->context->isAdmin();
        $notApproved = $owner && $owner->status !== 'approved';
        $kycLock = $notApproved ? self::KYC_LOCK : null;
        // Sections only paid plans include (Plan::GATED_FEATURES): padlock icon; the page shows the "Unlock …" card.
        $planLock = fn (string ...$features) => $kycLock
            ?? ($owner && !collect($features)->contains(fn ($f) => $owner->planAllows($f)) ? 'Upgrade your plan to unlock this' : null);

        $items = [
            $this->item('dashboard', 'Dashboard', 'fa-chart-pie', null, null, route: 'dashboard'),

            $this->item('leads', 'Leads', 'fa-address-book', 'Leads', null, route: 'leads.index', lock: $kycLock),
            // Badge = overdue follow-ups on the leads this account can see.
            $this->item('follow-ups', 'Follow-ups', 'fa-calendar-check', 'Leads', null, route: 'follow-ups.index', lock: $kycLock,
                badge: $kycLock ? 0 : app(LeadFollowUpService::class)->visibleTo($this->context->ownerId())->open()->where('due_at', '<', now())->count()),
            $this->item('lead-insights', 'Lead Insights', 'fa-chart-line', 'Leads', null, route: 'lead-insights.index', lock: $planLock('lead_insights')),
            $isSuperAdmin ? $this->item('website-leads', 'Website Leads', 'fa-user-clock', 'Leads', null, route: 'website-leads.index',
                badge: VisitorLead::inPool()->where('created_at', '>=', now()->subDays(7))->count()) : null,
            $this->item('master', 'Master', 'fa-layer-group', 'Leads', null, lock: $kycLock, children: array_values(array_filter([
                $this->item('master-stages', 'Stage', null, null, null, route: 'master.stages'),
                $this->item('master-tags', 'Tag', null, null, null, route: 'master.tags'),
                $this->item('master-sources', 'Source', null, null, null, route: 'master.sources'),
                $isSuperAdmin ? $this->item('master-property-options', 'Property Options', null, null, null, route: 'master.property-options') : null,
            ]))),
            $this->item('integrations', 'Integrations', 'fa-plug', 'Leads', null, route: 'integrations.index',
                lock: $planLock('facebook', 'property_finder')),

            $this->item('properties', 'Properties', 'fa-building', 'Listings', null, route: 'properties.index', lock: $kycLock,
                badge: $owner && !$notApproved ? Property::accessibleBy($owner)->whereNull('sold_at')
                    ->whereIn('compliance_status', [Property::COMPLIANCE_CHANGES_REQUESTED, Property::COMPLIANCE_EXPIRED])->count() : 0),
            $this->item('commercial', 'Commercial', 'fa-store', 'Listings', null, route: 'commercial.index', lock: $kycLock),
            $isSuperAdmin ? $this->item('listing-approvals', 'Listing Permits', 'fa-file-shield', 'Listings', null, route: 'listing-permits.index') : null,
            $this->item('featured', 'Premium', 'fa-star', 'Listings', null, route: 'premium.index', lock: $kycLock),
            // Website ads belong to the account that owns the plan — not shown to an agency's agents.
            // Super Admin manages MW Realty's own ads here and reviews clients' (badge = waiting for review).
            $isSuperAdmin ? $this->item('ads', 'Advertisements', 'fa-rectangle-ad', 'Listings', null, route: 'ads.index',
                badge: \App\Models\CmsKit\Ad::client()->where('review_status', \App\Models\CmsKit\Ad::PENDING)->whereNull('cancelled_at')->where('ends_at', '>', now())->count()) : null,
            $owner && $owner->listingOwner()->id === $owner->id ? $this->item('ads', 'Advertisements', 'fa-rectangle-ad', 'Listings', null, route: 'ads.index',
                lock: $kycLock ?? (!app(\App\Services\AdBookingService::class)->includesAds($owner) ? 'Upgrade your plan to advertise on the website' : null)) : null,
            $this->hasSoldListings($owner) ? $this->item('sold', 'Sold Listings', 'fa-handshake', 'Listings', null, route: 'sold-listings.index') : null,
            $isSuperAdmin ? $this->item('marketing', 'Marketing Properties', 'fa-bullhorn', 'Listings', null, route: 'marketing.index') : null,
            $owner?->type === 'agent' ? $this->item('agency', 'My Agency', 'fa-building-user', 'Listings', 'portal.agency.index',
                badge: AgencyAgent::where('agent_id', $owner->id)->where('status', AgencyAgent::INVITED)->count()) : null,
            $isSuperAdmin || $owner?->type === 'company' ? $this->item('agents', 'Agents', 'fa-user-tie', 'Listings', null, route: 'agents.index', lock: $kycLock,
                badge: $owner && !$notApproved ? AgencyAgent::where('agency_id', $owner->id)->where('status', AgencyAgent::REQUESTED)->count() : 0) : null,
            $this->item('nearby-places', 'Nearby Places', 'fa-map-marker-alt', 'Listings', null, route: 'nearby-places.index', lock: $kycLock),
            $this->item('watermark', 'Listing Settings', 'fa-stamp', 'Listings', null, route: 'listing-settings', lock: $planLock('listing_settings')),

            $this->item('reports', 'Reports', 'fa-chart-pie', 'Insights', null, route: 'reports.show',
                lock: $kycLock ?? ($owner && !$owner->hasReportsAccess() ? 'Upgrade your plan to unlock reports' : null)),

            $owner ? $this->item('contact', 'Contact Us', 'fa-headset', 'Support', null, route: 'contact.index',
                badge: SupportTicket::where('portal_user_id', $owner->id)->needsClientReply()->count()) : null,
            $this->item('help-desk', 'Help Desk', 'fa-circle-question', 'Support', null, route: 'help-desk.index'),
        ];

        return $this->lockUntilTwoFactor(array_values(array_filter($items)));
    }

    /**
     * Held at 2FA set-up (EnsurePortalTwoFactor): every item gets a padlock and no count, except
     * the ones in $open — nothing behind them would open yet, and counts would leak data.
     */
    private function lockUntilTwoFactor(array $items, array $open = []): array
    {
        if (!$this->context->owner()?->mustSetUpTwoFactor()) {
            return $items;
        }

        $lock = fn (array $item) => in_array($item['key'], $open, true) ? $item : [
            'locked' => true,
            'lock_reason' => 'Set up two-factor authentication first',
            'disabled' => true,
            'badge' => 0,
            'children' => array_map(fn ($child) => ['locked' => true, 'lock_reason' => 'Set up two-factor authentication first', 'disabled' => true, 'badge' => 0] + $child, $item['children']),
        ] + $item;

        return array_map($lock, $items);
    }

    /**
     * The sidebar's credits card — only when credits matter: the plan's listing or premium
     * allowance is used up (`limits` says which, so the card can suggest credits), or the account
     * already holds credits. Null otherwise, and for Super Admin, accounts awaiting KYC, or when
     * credits are off and the account has none left.
     */
    public function credits(): ?array
    {
        $owner = $this->context->owner();
        // An agency agent has no credits of their own (the agency's), so no card; none either until 2FA is set up.
        if (!\App\Services\CreditService::usableBy($owner) || $owner->mustSetUpTwoFactor()) {
            return null;
        }
        $credits = app(\App\Services\CreditService::class);
        $wallet = $credits->wallet($owner);
        $summary = $credits->summary($wallet);

        $premium = app(\App\Services\FeaturedListingService::class)->quota($owner);
        $plan = $owner->listingOwner()->plan;
        $left = $owner->listingOwner()->remainingPropertySlots(); // null = unlimited
        $limits = [
            'listings' => $left === 0,
            'premium' => $premium['limit'] > 0 && $premium['remaining'] === 0,
        ];
        // Nearly used up (10% or less of the allowance left, at least 2): show the card early as a heads-up.
        $listingsLeft = $left !== null && $left > 0 && $left <= max(2, (int) ceil((int) $plan?->property_limit * 0.1)) ? $left : null;
        if (!$summary['balance'] && !$summary['active'] && !in_array(true, $limits, true) && $listingsLeft === null) {
            return null;
        }

        return $summary + [
            'account' => $wallet->id !== $owner->id ? $wallet->displayName() : null,
            'limits' => $limits,
            'listings_left' => $listingsLeft,
            // Credits switched off: the card still shows what's left, but no "Buy credits".
            'can_buy' => \App\Services\CreditService::canBuy($owner),
            // Paid plans' allowances are monthly: when they come back.
            'resets' => $owner->listingOwner()->plan?->listingsPerMonth() ? now()->addMonthNoOverflow()->startOfMonth()->format('d M') : null,
        ];
    }

    /** Account (avatar) menu — profile, security, plans. */
    public function accountItems(): array
    {
        $owner = $this->context->owner();
        if (!$owner) {
            return [$this->item('back-to-admin', 'Back to Admin', 'fa-shield-alt', null, 'cms.dashboard')];
        }

        $kycLock = $owner->status !== 'approved' ? self::KYC_LOCK : null;

        // Security stays open while 2FA set-up is pending — it's where set-up happens.
        return $this->lockUntilTwoFactor(array_values(array_filter([
            $this->item('profile', 'My Profile', 'fa-user-edit', null, null, route: 'profile'),
            $this->item('security', 'Security', 'fa-shield-halved', null, null, route: 'security'),
            $this->item('plans', 'Plans', 'fa-layer-group', null, null, route: 'plans.index', lock: $kycLock),
            // Credits — add-ons next to the plan (extra listings, Premium), so they sit right under Plans.
            \App\Services\CreditService::usableBy($owner) || ($kycLock && \App\Services\CreditService::enabled() && $owner->listingOwner()->id === $owner->id)
                ? $this->item('credits', 'Credits', 'fa-coins', null, null, route: 'credits.index', lock: $kycLock) : null,
        ])), ['security']);
    }

    private function item(string $key, string $label, ?string $icon, ?string $section, ?string $legacyRoute, ?string $route = null, ?string $lock = null, int $badge = 0, array $children = []): array
    {
        $disabled = $lock === self::KYC_LOCK;
        if ($disabled) {
            $children = array_map(fn ($child) => ['disabled' => true] + $child, $children);
        }

        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'section' => $section,
            'route' => $route,
            'legacy_url' => $legacyRoute ? route($legacyRoute) : null,
            'locked' => $lock !== null,
            'lock_reason' => $lock,
            'disabled' => $disabled,
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
