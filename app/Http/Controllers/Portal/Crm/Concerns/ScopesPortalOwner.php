<?php

namespace App\Http\Controllers\Portal\Crm\Concerns;

use Illuminate\Support\Facades\Auth;

/** Shared Super Admin (global) vs Agent/Company (own-data) scoping used across the CRM controllers. */
trait ScopesPortalOwner
{
    /**
     * A portal-guard login always wins, even if a superadmin cms-guard session is
     * also active in the same browser (e.g. testing the portal in a second tab) —
     * otherwise there'd be no way to see your own scoped view without logging out
     * of /admin first.
     */
    protected function isAdmin(): bool
    {
        return !Auth::guard('portal')->check() && (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
    }

    protected function ownerId(): ?int
    {
        return Auth::guard('portal')->check() ? Auth::guard('portal')->user()->id : null;
    }

    protected function owner(): ?\App\Models\PortalUser
    {
        return Auth::guard('portal')->user();
    }
}
