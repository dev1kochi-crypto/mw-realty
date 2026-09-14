<?php

namespace App\Http\Controllers\Portal;

use App\Models\Property;
use App\Services\Crm\LeadService;
use App\Services\Crm\OwnerContext;
use Illuminate\Routing\Controller;

class PortalDashboardController extends Controller
{
    public function __construct(
        private readonly OwnerContext $ownerContext,
        private readonly LeadService $leadService,
    ) {
    }

    public function index()
    {
        $isAdmin = $this->ownerContext->isAdmin();
        $ownerId = $this->ownerContext->ownerId();

        $propertyQuery = fn () => Property::when($ownerId, fn ($q) => $q->where('portal_user_id', $ownerId));

        $leadStats = $this->leadService->getLeadStatistics($ownerId);

        $stats = [
            'total_properties' => $propertyQuery()->count(),
            'active_properties' => $propertyQuery()->where('status', true)->count(),
            'total_leads' => $leadStats['total'],
            'new_leads' => $leadStats['active'],
        ];

        $recentProperties = $propertyQuery()->latest()->take(5)->get();
        $recentLeads = $this->leadService->filteredQuery($ownerId)->take(5)->get();

        return view('portal.dashboard', compact('stats', 'recentProperties', 'recentLeads', 'isAdmin'));
    }
}
