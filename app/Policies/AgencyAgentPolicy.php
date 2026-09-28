<?php

namespace App\Policies;

use App\Models\AgencyAgent;
use App\Models\PortalUser;

/**
 * Portal-side authorization for agency ⇄ agent memberships (auto-discovered for AgencyAgent).
 * Controllers also look memberships up already scoped to the signed-in account, so a guessed or
 * edited id from another agency 404s before it ever reaches here — this is the second check.
 */
class AgencyAgentPolicy
{
    /** The agency side: view the agent, suspend/reactivate/remove, answer join requests, cancel invites. */
    public function manage(PortalUser $user, AgencyAgent $membership): bool
    {
        return $user->isAgency() && $membership->agency_id === $user->id;
    }

    /** The agent side: answer invitations, cancel own requests, leave. */
    public function respond(PortalUser $user, AgencyAgent $membership): bool
    {
        return $user->isAgent() && $membership->agent_id === $user->id;
    }
}
