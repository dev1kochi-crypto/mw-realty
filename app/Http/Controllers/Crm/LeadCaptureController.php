<?php

namespace App\Http\Controllers\Crm;

use App\Mail\NewLeadReceived;
use App\Models\CmsKit\Admin;
use App\Models\CmsKit\SiteInformation;
use App\Models\Lead;
use App\Models\Property;
use App\Notifications\NewLeadNotification;
use App\Rules\RecaptchaRule;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Public, unauthenticated endpoint any property-detail page can POST to.
 * Stamps the lead with the property's owning company/agent so it shows up
 * in that owner's CRM — this is what distinguishes a Lead from a general
 * contact-us Enquiry. A property with no assigned agent/company still
 * captures the lead (portal_user_id stays null) rather than rejecting the
 * enquirer — admin gets notified instead, and can transfer it to an agent later.
 */
class LeadCaptureController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'message' => 'required|string|max:2000',
            'page_source' => 'nullable|string|max:100',
            'recaptcha_token' => ['nullable', new RecaptchaRule()],
        ]);

        $property = Property::findOrFail($request->input('property_id'));

        $lead = Lead::create([
            'property_id' => $property->id,
            'portal_user_id' => $property->portal_user_id,
            'user_id' => Auth::guard('web')->id(),
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'company' => $request->input('company'),
            'country' => $request->input('country'),
            'message' => $request->input('message'),
            'page_url' => $request->header('referer'),
            'page_source' => $request->input('page_source', 'property-detail'),
            'status' => 'active',
        ]);

        if ($property->owner) {
            try {
                $property->owner->notify(new NewLeadNotification($lead));
            } catch (\Throwable $e) {
                Log::error('Failed to create new-lead bell notification: ' . $e->getMessage());
            }

            if ($property->owner->email) {
                Mail::to($property->owner->email)->queue((new NewLeadReceived($lead))->afterCommit());
            }
        } else {
            $this->notifyAdminOfUnassignedLead($lead);
        }

        $message = 'Thanks — your enquiry has been received and we\'ll be in touch soon.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    /**
     * No agent/company is assigned to this property, so there's no CRM owner to route the
     * lead to — notify every superadmin instead (bell + email) so it can be picked up and
     * transferred to an agent from the admin side.
     */
    private function notifyAdminOfUnassignedLead(Lead $lead): void
    {
        try {
            Admin::role('superadmin')->get()->each(
                fn (Admin $admin) => $admin->notify(new NewLeadNotification($lead))
            );
        } catch (\Throwable $e) {
            Log::error('Failed to create unassigned-lead bell notification: ' . $e->getMessage());
        }

        $adminEmail = SiteInformation::notificationEmail();
        if ($adminEmail) {
            Mail::to($adminEmail)->queue((new NewLeadReceived($lead))->afterCommit());
        }
    }
}
