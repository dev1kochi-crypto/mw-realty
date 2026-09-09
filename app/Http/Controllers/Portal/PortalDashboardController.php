<?php

namespace App\Http\Controllers\Portal;

use App\Models\Property;
use App\Models\CmsKit\Enquiry;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class PortalDashboardController extends Controller
{
    public function index()
    {
        $isAdmin = (bool) Auth::guard('cms')->user()?->hasRole('superadmin');
        $ownerId = $isAdmin ? null : Auth::guard('portal')->user()->id;

        $propertyQuery = fn () => Property::when($ownerId, fn ($q) => $q->where('portal_user_id', $ownerId));
        $enquiryQuery = fn () => Enquiry::when($ownerId, fn ($q) => $q->where('portal_user_id', $ownerId));

        $stats = [
            'total_properties' => $propertyQuery()->count(),
            'active_properties' => $propertyQuery()->where('status', true)->count(),
            'total_enquiries' => $enquiryQuery()->count(),
            'new_enquiries' => $enquiryQuery()->where('status', 'new')->count(),
        ];

        $recentProperties = $propertyQuery()->latest()->take(5)->get();
        $recentEnquiries = $enquiryQuery()->latest()->take(5)->get();

        return view('portal.dashboard', compact('stats', 'recentProperties', 'recentEnquiries', 'isAdmin'));
    }
}
