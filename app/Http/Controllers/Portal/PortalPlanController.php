<?php

namespace App\Http\Controllers\Portal;

use App\Models\CmsKit\Admin;
use App\Models\Plan;
use App\Models\PlanUpgradeRequest;
use App\Notifications\PlanUpgradeRequestedNotification;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PortalPlanController extends Controller
{
    public function index()
    {
        $owner = Auth::guard('portal')->user();
        $plans = Plan::active()->orderBy('order_index')->get();
        $pendingRequest = $owner->planUpgradeRequests()->pending()->latest()->first();

        return view('portal.plans.index', compact('plans', 'owner', 'pendingRequest'));
    }

    public function request(Request $request)
    {
        $owner = Auth::guard('portal')->user();

        $request->validate([
            'plan_id' => 'required|integer|exists:plans,id',
        ]);

        abort_if($owner->hasPendingPlanUpgradeRequest(), 422, 'You already have a pending plan request.');
        abort_if((int) $request->input('plan_id') === (int) $owner->plan_id, 422, 'You are already on this plan.');

        $upgradeRequest = PlanUpgradeRequest::create([
            'portal_user_id' => $owner->id,
            'plan_id' => $request->input('plan_id'),
            'current_plan_id' => $owner->plan_id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        try {
            Admin::role('superadmin')->get()->each(
                fn (Admin $admin) => $admin->notify(new PlanUpgradeRequestedNotification($upgradeRequest))
            );
        } catch (\Throwable $e) {
            Log::error('Failed to create plan-upgrade-requested bell notification: ' . $e->getMessage());
        }

        return back()->with('success', 'Upgrade request submitted — Super Admin will review it shortly.');
    }
}
