<?php

namespace App\Http\Controllers\Crm\Concerns;

use App\Models\PortalUser;
use App\Models\Property;
use Illuminate\Support\Facades\Auth;

/**
 * Which listings the signed-in account may see and touch — Super Admin every listing; an
 * Agent/Company its own, plus (view / edit only) the agency listings assigned to an agency agent.
 * Shared by the CRM Properties / Commercial controllers and the portal's Sold Listings screen.
 */
trait ScopesListings
{
    use ScopesPortalOwner;

    protected function viewer(): ?PortalUser
    {
        return Auth::guard('portal')->user();
    }

    /**
     * A menu's listings in display order: every owner's for Super Admin; otherwise the viewer's
     * own — plus, unless $strict, the agency listings assigned to an agency agent. Reordering is
     * always $strict (an agent never renumbers the agency's list).
     */
    protected function orderedListings(string $segment, bool $strict = false)
    {
        return Property::query()
            ->segment($segment)
            // Sold / rented listings move to the Sold Listings menu.
            ->available()
            ->when($this->ownerId(), fn ($q, $ownerId) => $strict
                ? $q->where('portal_user_id', $ownerId)
                : $q->accessibleBy($this->viewer()))
            ->orderBy('order_index')
            ->latest()
            // Tie-breaker: without it rows sharing order_index/created_at come back in an arbitrary
            // order per query, so a listing can repeat on one page and be missing from the next.
            ->orderByDesc('id');
    }

    /** Owner-only (delete, feature, reorder, status) — never an agency agent on the agency's listing. */
    protected function findOwned($id): Property
    {
        return Property::with(['details', 'images', 'floorPlans', 'nearbyPlaces', 'agent'])
            ->when($this->ownerId(), fn ($q, $ownerId) => $q->where('portal_user_id', $ownerId))
            ->findOrFail($id);
    }

    /** View / edit — the owner, or the agency agent the agency assigned this listing to. */
    protected function findAccessible($id): Property
    {
        return Property::with(['details', 'images', 'floorPlans', 'nearbyPlaces', 'agent', 'owner'])
            ->when($this->ownerId(), fn ($q) => $q->accessibleBy($this->viewer()))
            ->findOrFail($id);
    }
}
