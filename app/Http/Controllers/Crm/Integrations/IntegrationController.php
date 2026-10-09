<?php

namespace App\Http\Controllers\Crm\Integrations;

use App\Http\Controllers\Crm\Concerns\ScopesPortalOwner;
use App\Models\FacebookPageConnection;
use App\Services\Integrations\FacebookLeadAds;
use Illuminate\Routing\Controller;

/**
 * @group CRM Integrations
 *
 * The Integrations hub: one card per integration with its status; each opens its own screen
 * (FacebookController, PropertyFinderController). A new integration is a new entry here plus its screen.
 */
class IntegrationController extends Controller
{
    use ScopesPortalOwner;

    /**
     * List integrations
     *
     * @response 200 {"is_admin": false, "integrations": [{"key": "facebook", "name": "Facebook Lead Ads", "category": "Leads", "icon": "fab fa-facebook-f", "color": "#1877f2", "description": "…", "status": {"tone": "ok", "label": "2 pages connected"}}]}
     */
    public function index(FacebookLeadAds $facebook, PropertyFinderController $propertyFinder)
    {
        $isAdmin = $this->isAdmin();
        $facebookCount = FacebookPageConnection::when(!$isAdmin, fn ($q) => $q->where('portal_user_id', $this->ownerId() ?? 0))->count();
        $pf = $propertyFinder->summary();

        return response()->json([
            'is_admin' => $isAdmin,
            'integrations' => [
                [
                    'key' => 'facebook',
                    'name' => 'Facebook Lead Ads',
                    'category' => 'Leads',
                    'icon' => 'fab fa-facebook-f',
                    'color' => '#1877f2',
                    'description' => $isAdmin
                        ? 'Connect Facebook Pages and send each one\'s lead form leads to an agency or agent.'
                        : 'Leads from your Facebook & Instagram lead forms arrive in Leads automatically.',
                    'status' => $this->status(match (true) {
                        !$facebook->configured() => ['off', 'Not set up'],
                        $facebookCount > 0 => ['ok', $facebookCount . ' page' . ($facebookCount === 1 ? '' : 's') . ' connected'],
                        default => ['off', 'Not connected'],
                    }),
                ],
                [
                    'key' => 'property-finder',
                    'name' => 'Property Finder',
                    'category' => 'Listings',
                    'icon' => 'fas fa-house-chimney',
                    'color' => '#ef5e4e',
                    'description' => $isAdmin
                        ? 'Agencies and agents import their Property Finder listings — you review them before they go live.'
                        : 'Import your Property Finder listings as properties — all of them once, then just the new ones.',
                    'status' => $this->status(match (true) {
                        $isAdmin && $pf['pending'] > 0 => ['warn', $pf['pending'] . ' to review'],
                        $isAdmin => ['off', $pf['accounts'] . ' account' . ($pf['accounts'] === 1 ? '' : 's') . ' connected'],
                        $pf['connection']?->syncInProgress() => ['warn', 'Syncing…'],
                        $pf['connection'] !== null => ['ok', 'Connected'],
                        default => ['off', 'Not connected'],
                    }),
                ],
            ],
        ]);
    }

    private function status(array $status): array
    {
        return ['tone' => $status[0], 'label' => $status[1]];
    }
}
