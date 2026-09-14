<?php

namespace App\Services\Crm;

use App\Models\PortalUser;
use Illuminate\Support\Facades\Auth;

/**
 * The single place that resolves "who is acting as the CRM owner right now" —
 * a real portal-guard agent/company, or the shared Admin owner (see
 * AdminOwnerResolver) when a CMS Super Admin is managing their own data.
 * Used by the ScopesPortalOwner controller trait and directly by
 * FormRequests/Services/Imports that need the same answer outside a
 * controller's request lifecycle, so this logic exists in exactly one place.
 */
class OwnerContext
{
    /**
     * A portal-guard login always wins, even if a superadmin cms-guard session
     * is also active in the same browser (e.g. testing the portal in a second
     * tab) — otherwise there'd be no way to see your own scoped view without
     * logging out of /admin first.
     */
    public function isAdmin(): bool
    {
        return !Auth::guard('portal')->check() && (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
    }

    public function ownerId(): ?int
    {
        return Auth::guard('portal')->check() ? Auth::guard('portal')->user()->id : null;
    }

    public function owner(): ?PortalUser
    {
        return Auth::guard('portal')->user();
    }

    /**
     * Like ownerId(), but Super Admin resolves to the shared "Admin" owner row
     * instead of null — used wherever Admin manages/creates their own
     * records directly (Stage/Tag/Source master data, and their own Leads),
     * as opposed to Leads' global cross-tenant listing where null intentionally
     * means "every owner".
     */
    public function effectiveOwnerId(): ?int
    {
        return $this->ownerId() ?? ($this->isAdmin() ? AdminOwnerResolver::resolve()->id : null);
    }

    public function effectiveOwner(): ?PortalUser
    {
        return $this->owner() ?? ($this->isAdmin() ? AdminOwnerResolver::resolve() : null);
    }

    /**
     * Display name of whoever is actually signed in right now — the real
     * agent/company on the portal guard, or the CMS admin's own name (not the
     * shared "Admin" owner row) when acting as Super Admin. Used to attribute
     * activity-history entries (Lead notes, and later Follow-ups/Calls/etc.)
     * to a real person rather than the tenant record.
     */
    public function actorName(): string
    {
        if ($owner = $this->owner()) {
            return $owner->displayName();
        }

        return Auth::guard('cms')->user()?->name ?? 'Admin';
    }
}
