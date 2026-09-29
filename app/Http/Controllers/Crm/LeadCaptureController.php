<?php

namespace App\Http\Controllers\Crm;

use App\Models\Lead;
use App\Models\PortalUser;
use App\Models\Property;
use App\Rules\RecaptchaRule;
use App\Services\Crm\LeadCreationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Public, unauthenticated endpoints the website's enquiry forms POST to. Each one only
 * validates and maps its form to lead attributes — owner routing, duplicate handling,
 * assignment and notifications all happen in LeadCreationService::create(). A property with
 * no assigned agent/company still captures the lead (no owner) rather than rejecting the
 * enquirer — admin gets notified instead, and can transfer it to an agent later.
 */
class LeadCaptureController extends Controller
{
    public function __construct(private readonly LeadCreationService $leadCreation)
    {
    }

    /** Capture a property-search request from an agent or agency profile and route it to that CRM. */
    public function storeProfileRequest(Request $request)
    {
        $data = $request->validate([
            'profile_type' => 'required|in:agent,agency',
            'profile_slug' => 'required|string|max:255',
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

        $profile = PortalUser::query()
            ->where('slug', $data['profile_slug'])
            ->where('type', $data['profile_type'] === 'agency' ? 'company' : 'agent')
            ->approved()->where('is_active', true)->firstOrFail();
        $agencyOwner = $data['profile_type'] === 'agent' && $profile->company_id
            ? PortalUser::find($profile->company_id)
            : null;
        $agentIsAgencyMember = $agencyOwner?->hasEligibleAgent($profile->id) ?? false;

        $this->leadCreation->create(
            [
                'agent_id' => $data['profile_type'] === 'agent' && !$agentIsAgencyMember ? $profile->id : null,
                'user_id' => Auth::guard('web')->id(),
                'name' => trim($data['first_name'] . ' ' . ($data['last_name'] ?? '')),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'message' => $this->requestSummary($data),
                'page_url' => $request->header('referer'),
                'page_source' => $data['profile_type'] . '-profile-request',
                'status' => 'active',
                'extra_fields' => $this->requestExtraFields($request),
            ],
            ownerId: $agentIsAgencyMember ? $agencyOwner->id : $profile->id,
            // An agency agent's own profile → that agent works it within the agency.
            preferredAgentId: $data['profile_type'] === 'agent' && $agentIsAgencyMember ? $profile->id : null,
        );

        return response()->json(['message' => 'Thanks — your request has been sent to ' . $profile->displayName() . '.']);
    }

    /** Record a lead before revealing the listing brochure URL. */
    public function downloadBrochure(Request $request)
    {
        $data = $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'recaptcha_token' => ['nullable', new RecaptchaRule()],
        ]);
        $property = Property::where('status', true)->findOrFail($data['property_id']);
        abort_unless($property->brochure_path, 404);

        // Captured directly rather than via store(): reCAPTCHA tokens are single-use, so
        // re-validating the same token there would always fail as a duplicate.
        $request->merge([
            'message' => 'Requested the property brochure for ' . ($property->getTranslation('title') ?: $property->reference_no),
            'page_source' => 'brochure-download',
        ]);
        $this->capturePropertyLead($request, $property);

        return response()->json([
            'message' => 'Your brochure is ready to download.',
            'brochure_url' => media_url($property->brochure_path),
        ]);
    }

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
        $this->capturePropertyLead($request, $property);

        $message = 'Thanks — your enquiry has been received and we\'ll be in touch soon.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    /** A property enquiry — routed to the listing's owner. Expects an already-validated request. */
    private function capturePropertyLead(Request $request, Property $property): Lead
    {
        return $this->leadCreation->create([
            'property_id' => $property->id,
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
    }

    /**
     * "Custom Request" — a buyer describes a property they couldn't find in the listing
     * (properties listing page toolbar). There's no property to route this to, so it's always
     * an unassigned Lead: superadmin gets notified and picks it up from the Unassigned Leads
     * screen, same as a property lead whose listing has no agent/company assigned.
     */
    public function storeCustomRequest(Request $request)
    {
        $data = $request->validate([
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

        $this->leadCreation->create([
            'user_id' => Auth::guard('web')->id(),
            'name' => trim($data['first_name'] . ' ' . ($data['last_name'] ?? '')),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'message' => $this->requestSummary($data),
            'page_url' => $request->header('referer'),
            'page_source' => 'custom-request',
            'status' => 'active',
            'extra_fields' => $this->requestExtraFields($request),
        ]);

        $message = "Thanks — your request has been received and our team will reach out soon.";

        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    /** One-line summary of a property-search request (profile / custom request forms). */
    private function requestSummary(array $data): string
    {
        $details = array_filter([
            !empty($data['property_category']) ? 'Category: ' . $data['property_category'] : null,
            !empty($data['specification']) ? 'Specification: ' . $data['specification'] : null,
            !empty($data['price_range']) ? 'Budget: ' . $data['price_range'] : null,
            !empty($data['area']) ? 'Area: ' . $data['area'] : null,
            !empty($data['preferred_location']) ? 'Location: ' . $data['preferred_location'] : null,
            !empty($data['move_in_timeline']) ? 'Move-in: ' . $data['move_in_timeline'] : null,
            !empty($data['furnishing_status']) ? 'Furnishing: ' . $data['furnishing_status'] : null,
            !empty($data['additional_details']) ? 'Notes: ' . $data['additional_details'] : null,
        ]);

        return $details ? implode(' | ', $details) : 'Custom property request submitted.';
    }

    private function requestExtraFields(Request $request): array
    {
        return [
            'property_category' => $request->input('property_category'),
            'specification' => $request->input('specification'),
            'price_range' => $request->input('price_range'),
            'area' => $request->input('area'),
            'preferred_location' => $request->input('preferred_location'),
            'move_in_timeline' => $request->input('move_in_timeline'),
            'furnishing_status' => $request->input('furnishing_status'),
            'additional_details' => $request->input('additional_details'),
            'whatsapp_consent' => $request->boolean('whatsapp_consent'),
        ];
    }
}
