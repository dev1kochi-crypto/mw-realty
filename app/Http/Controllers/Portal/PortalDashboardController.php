<?php

namespace App\Http\Controllers\Portal;

use App\Models\Property;
use App\Models\Lead;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class PortalDashboardController extends Controller
{
    public function index()
    {
        // A portal-guard login always wins over a concurrent cms-guard superadmin
        // session (e.g. testing the portal in a second tab while still admin elsewhere).
        $isAdmin = !Auth::guard('portal')->check() && (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
        $ownerId = Auth::guard('portal')->check() ? Auth::guard('portal')->user()->id : null;

        $propertyQuery = fn () => Property::when($ownerId, fn ($q) => $q->where('portal_user_id', $ownerId));
        $leadQuery = fn () => Lead::when($ownerId, fn ($q) => $q->where('portal_user_id', $ownerId));

        $stats = [
            'total_properties' => $propertyQuery()->count(),
            'active_properties' => $propertyQuery()->where('status', true)->count(),
            'total_leads' => $leadQuery()->count(),
            'new_leads' => $leadQuery()->where('status', 'new')->count(),
        ];

        $recentProperties = $propertyQuery()->latest()->take(5)->get();
        $recentLeads = $leadQuery()->latest()->take(5)->get();

        return view('portal.dashboard', compact('stats', 'recentProperties', 'recentLeads', 'isAdmin'));
    }
}
