<?php

namespace App\Http\Controllers\Crm;

use App\Mail\NewLeadReceived;
use App\Models\Lead;
use App\Models\Property;
use App\Notifications\NewLeadNotification;
use App\Rules\RecaptchaRule;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Public, unauthenticated endpoint any property-detail page can POST to.
 * Stamps the lead with the property's owning company/agent so it shows up
 * in that owner's CRM — this is what distinguishes a Lead from a general
 * contact-us Enquiry.
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
        abort_if(!$property->portal_user_id, 422, 'This property is not currently listed by an agent or company and cannot receive leads.');

        $lead = Lead::create([
            'property_id' => $property->id,
            'portal_user_id' => $property->portal_user_id,
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

        try {
            $property->owner->notify(new NewLeadNotification($lead));
        } catch (\Throwable $e) {
            Log::error('Failed to create new-lead bell notification: ' . $e->getMessage());
        }

        if ($property->owner->email) {
            Mail::to($property->owner->email)->queue((new NewLeadReceived($lead))->afterCommit());
        }

        $message = 'Thanks — your enquiry has been sent to the listing agent.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }
}
