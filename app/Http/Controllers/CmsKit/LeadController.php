<?php

namespace App\Http\Controllers\CmsKit;

use App\Models\Lead;
use App\Models\PortalUser;
use App\Notifications\NewLeadNotification;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Admin-side view of leads that have no agent/company assigned (Property::portal_user_id is
 * null when the lead was captured) — every other lead lives entirely in the portal CRM, scoped
 * to its own agent/company. This screen exists purely to hand an unassigned one off to whoever
 * should actually work it.
 */
class LeadController extends Controller
{
    public function index()
    {
        $leads = Lead::whereNull('portal_user_id')->with('property')->latest()->paginate(20);
        $agents = PortalUser::approved()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'company_name', 'type']);

        return view('cms-kit::leads.unassigned', compact('leads', 'agents'));
    }

    public function assign(Request $request, Lead $lead)
    {
        $request->validate([
            'portal_user_id' => ['required', 'integer', 'exists:portal_users,id'],
        ]);

        $owner = PortalUser::findOrFail($request->input('portal_user_id'));
        $lead->update(['portal_user_id' => $owner->id]);

        try {
            $owner->notify(new NewLeadNotification($lead));
        } catch (\Throwable) {
            // Non-fatal — the assignment itself already succeeded.
        }

        return redirect()->route('cms.unassigned-leads.index')->with('success', "Lead assigned to {$owner->displayName()}.");
    }
}
