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
     * "Custom Request" — a buyer describes a property they couldn't find in the listing
     * (properties listing page toolbar). There's no property to route this to, so it's always
     * an unassigned Lead: superadmin gets notified and picks it up from the Unassigned Leads
     * screen, same as a property lead whose listing has no agent/company assigned.
     */
    public function storeCustomRequest(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'property_category' => 'nullable|string|max:100',
            'specification' => 'nullable|string|max:255',
            'price_range' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:100',
            'preferred_location' => 'nullable|string|max:255',
            'additional_details' => 'nullable|string|max:2000',
            'move_in_timeline' => 'nullable|string|max:100',
            'furnishing_status' => 'nullable|string|max:100',
            'whatsapp_consent' => 'nullable|boolean',
            'recaptcha_token' => ['nullable', new RecaptchaRule()],
        ]);

        $name = trim($request->input('first_name').' '.$request->input('last_name', ''));

        $summary = array_filter([
            $request->filled('property_category') ? 'Category: '.$request->input('property_category') : null,
            $request->filled('specification') ? 'Specification: '.$request->input('specification') : null,
            $request->filled('price_range') ? 'Budget: '.$request->input('price_range') : null,
            $request->filled('area') ? 'Area: '.$request->input('area') : null,
            $request->filled('preferred_location') ? 'Location: '.$request->input('preferred_location') : null,
            $request->filled('move_in_timeline') ? 'Move-in: '.$request->input('move_in_timeline') : null,
            $request->filled('furnishing_status') ? 'Furnishing: '.$request->input('furnishing_status') : null,
            $request->filled('additional_details') ? 'Notes: '.$request->input('additional_details') : null,
        ]);

        $lead = Lead::create([
            'user_id' => Auth::guard('web')->id(),
            'name' => $name,
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'message' => $summary ? implode(' | ', $summary) : 'Custom property request submitted.',
            'page_url' => $request->header('referer'),
            'page_source' => 'custom-request',
            'status' => 'active',
            'extra_fields' => [
                'property_category' => $request->input('property_category'),
                'specification' => $request->input('specification'),
                'price_range' => $request->input('price_range'),
                'area' => $request->input('area'),
                'preferred_location' => $request->input('preferred_location'),
                'move_in_timeline' => $request->input('move_in_timeline'),
                'furnishing_status' => $request->input('furnishing_status'),
                'additional_details' => $request->input('additional_details'),
                'whatsapp_consent' => $request->boolean('whatsapp_consent'),
            ],
        ]);

        $this->notifyAdminOfUnassignedLead($lead);

        $message = "Thanks — your request has been received and our team will reach out soon.";

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
