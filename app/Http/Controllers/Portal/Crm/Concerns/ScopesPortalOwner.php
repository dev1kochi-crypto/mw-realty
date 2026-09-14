<?php

namespace App\Http\Controllers\Portal\Crm\Concerns;

use App\Services\Crm\OwnerContext;

/**
 * Shared Super Admin (global) vs Agent/Company (own-data) scoping used across
 * the CRM controllers. Thin delegation to OwnerContext, the single source of
 * truth for this resolution — also used directly by FormRequests/Imports that
 * need the same answer outside a controller.
 */
trait ScopesPortalOwner
{
    protected function ownerContext(): OwnerContext
    {
        return app(OwnerContext::class);
    }

    protected function isAdmin(): bool
    {
        return $this->ownerContext()->isAdmin();
    }

    protected function ownerId(): ?int
    {
        return $this->ownerContext()->ownerId();
    }

    protected function owner(): ?\App\Models\PortalUser
    {
        return $this->ownerContext()->owner();
    }

    protected function effectiveOwnerId(): ?int
    {
        return $this->ownerContext()->effectiveOwnerId();
    }

    protected function effectiveOwner(): ?\App\Models\PortalUser
    {
        return $this->ownerContext()->effectiveOwner();
    }

    protected function actorName(): string
    {
        return $this->ownerContext()->actorName();
    }
}
