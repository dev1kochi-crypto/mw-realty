<?php

namespace App\Http\Controllers\Crm;

use App\Models\Lead;
use App\Models\PortalUser;
use App\Models\Property;
use App\Rules\PhoneNumber;
use App\Rules\RecaptchaRule;
use App\Models\Visitors\VisitorLead;
use App\Services\Crm\LeadCreationService;
use App\Services\Visitors\VisitorTracker;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public, unauthenticated endpoints the website's enquiry forms POST to. Each one only
 * validates and maps its form to lead attributes — owner routing, duplicate handling,
 * assignment and notifications all happen in LeadCreationService::create(). A property with
 * no assigned agent/company still captures the lead (no owner) rather than rejecting the
 * enquirer — admin gets notified instead, and can transfer it to an agent later.
 *
 * @group Leads & Enquiries
 */
class LeadCaptureController extends Controller
{
    public function __construct(
        private readonly LeadCreationService $leadCreation,
        private readonly VisitorTracker $tracker,
    ) {
    }

    /**
     * Request from an agent/agency profile
     *
     * "Find me a property" form on an agent's or agency's profile — sent to that agent/agency.
     *
     * Capture a property-search request from an agent or agency profile and route it to that CRM.
     *
     * @bodyParam profile_type string required agent or agency. Example: agent
     * @bodyParam profile_slug string required Example: sruthi-raveendran-m
     * @bodyParam first_name string required Example: Sara
     * @bodyParam email string required Example: buyer@example.com
     * @bodyParam phone string Example: 501234567
     * @bodyParam phone_country_code string Example: +971
     * @bodyParam recaptcha_token string See "Forms & reCAPTCHA" in the introduction. No-example
     *
     * @response 200 {"message": "Thanks — your request has been sent to Sruthi Raveendran."}
     */
    public function storeProfileRequest(Request $request)
    {
        $data = $request->validate([
            'profile_type' => 'required|in:agent,agency',
            'profile_slug' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => PhoneNumber::emailRules(),
            'phone' => PhoneNumber::rules(),
            'phone_country_code' => PhoneNumber::countryCodeRules(),
            'property_category' => 'nullable|string|max:100',
            'specification' => 'nullable|string|max:255',
            'price_range' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:100',
            'preferred_location' => 'nullable|string|max:255',
            'additional_details' => 'nullable|string|max:2000',
            'move_in_timeline' => 'nullable|string|max:100',
            'furnishing_status' => 'nullable|string|max:100',
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
        $profileOwnerId = $agentIsAgencyMember ? $agencyOwner->id : $profile->id;
        // One person, one account: someone already another account's lead stays with it.
        $assignedOwnerId = $this->tracker->assignedOwnerIdFor($request, $data['email'], $data['phone'] ?? null);
        $keepsOwner = $assignedOwnerId && $assignedOwnerId !== $profileOwnerId;

        $lead = $this->leadCreation->create(
            [
                'agent_id' => !$keepsOwner && $data['profile_type'] === 'agent' && !$agentIsAgencyMember ? $profile->id : null,
                'user_id' => $request->user('sanctum')?->id, // website session or app Bearer token
                'name' => trim($data['first_name'] . ' ' . ($data['last_name'] ?? '')),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'phone_country_code' => !empty($data['phone']) ? ($data['phone_country_code'] ?? null) : null,
                'message' => $this->requestSummary($data),
                'page_url' => $request->header('referer'),
                'page_source' => $data['profile_type'] . '-profile-request',
                'status' => 'active',
                'extra_fields' => $this->requestExtraFields($request),
            ],
            ownerId: $keepsOwner ? $assignedOwnerId : $profileOwnerId,
            // An agency agent's own profile → that agent works it within the agency.
            preferredAgentId: !$keepsOwner && $data['profile_type'] === 'agent' && $agentIsAgencyMember ? $profile->id : null,
        );
        $this->trackEnquiry($request, $lead, ucfirst($data['profile_type']) . ' profile request');

        return response()->json(['message' => 'Thanks — your request has been sent to ' . $profile->displayName() . '.']);
    }

    /**
     * Download brochure
     *
     * Records the lead, then returns a 15-minute signed `download_url` for the listing's
     * brochure (open it in the browser / download manager — no token needed). 404 when the
     * listing has no brochure.
     *
     * Record a lead before revealing the listing brochure URL.
     *
     * @bodyParam property_id integer required Example: 150651
     * @bodyParam name string required Example: Sara Ahmed
     * @bodyParam email string required Example: buyer@example.com
     * @bodyParam phone string required Example: 501234567
     * @bodyParam phone_country_code string Example: +971
     * @bodyParam recaptcha_token string See "Forms & reCAPTCHA" in the introduction. No-example
     *
     * @response 200 {"message": "Your brochure is ready to download.", "download_url": "https://example.com/downloads/property/150651/brochure?expires=1760000000&signature=..."}
     */
    public function downloadBrochure(Request $request)
    {
        $property = $this->validatedDownloadProperty($request);
        abort_unless($property->brochure_path, 404);
        $this->captureDownloadLead($request, $property, 'property brochure', 'brochure-download');

        return response()->json([
            'message' => 'Your brochure is ready to download.',
            'download_url' => $this->signedDownloadUrl($property, 'brochure'),
        ]);
    }

    /**
     * Download floor plan
     *
     * Same as the brochure download, for the listing's floor plan file.
     *
     * Record a lead before revealing the listing's downloadable floor plan file.
     *
     * @bodyParam property_id integer required Example: 111
     * @bodyParam name string required Example: Sara Ahmed
     * @bodyParam email string required Example: buyer@example.com
     * @bodyParam phone string required Example: 501234567
     * @bodyParam phone_country_code string Example: +971
     * @bodyParam recaptcha_token string See "Forms & reCAPTCHA" in the introduction. No-example
     *
     * @response 200 {"message": "Your floor plan is ready to download.", "download_url": "https://example.com/downloads/property/111/floor-plan?expires=1760000000&signature=..."}
     */
    public function downloadFloorPlan(Request $request)
    {
        $property = $this->validatedDownloadProperty($request);
        $file = $property->details?->floor_plan_file;
        abort_unless($file, 404);
        $this->captureDownloadLead($request, $property, 'floor plan', 'floor-plan-download');

        return response()->json([
            'message' => 'Your floor plan is ready to download.',
            'download_url' => $this->signedDownloadUrl($property, 'floor-plan'),
        ]);
    }

    /** Same-origin, 15-minute link to PropertyFileDownloadController — the file itself is never exposed. */
    private function signedDownloadUrl(Property $property, string $kind): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute('property-files.download', now()->addMinutes(15), [
            'property' => $property->id,
            'kind' => $kind,
        ]);
    }

    private function validatedDownloadProperty(Request $request): Property
    {
        $data = $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
            'name' => 'required|string|max:255',
            'email' => PhoneNumber::emailRules(),
            'phone' => PhoneNumber::rules(true),
            'phone_country_code' => PhoneNumber::countryCodeRules(),
            'recaptcha_token' => ['nullable', new RecaptchaRule()],
        ]);

        return Property::where('status', true)->findOrFail($data['property_id']);
    }

    private function captureDownloadLead(Request $request, Property $property, string $what, string $pageSource): void
    {
        // Captured directly rather than via store(): reCAPTCHA tokens are single-use, so
        // re-validating the same token there would always fail as a duplicate.
        $request->merge([
            'message' => "Requested the {$what} for " . ($property->getTranslation('title') ?: $property->reference_no),
            'page_source' => $pageSource,
        ]);
        $this->capturePropertyLead($request, $property);
    }

    /**
     * Property enquiry
     *
     * "Contact agent" form on a listing — goes to the listing's agent/agency CRM. If a customer
     * token is sent, the enquiry also appears under their account.
     *
     * @bodyParam property_id integer required Example: 111
     * @bodyParam name string required Example: Sara Ahmed
     * @bodyParam email string Example: buyer@example.com
     * @bodyParam phone string Example: 501234567
     * @bodyParam phone_country_code string Example: +971
     * @bodyParam message string required Example: Is this still available? I'd like to view it this week.
     * @bodyParam page_source string Where in the app it was sent from. Example: mobile-app
     * @bodyParam recaptcha_token string See "Forms & reCAPTCHA" in the introduction. No-example
     *
     * @response 200 {"message": "Thanks — your enquiry has been received and we'll be in touch soon."}
     */
    public function store(Request $request)
    {
        $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
            'name' => 'required|string|max:255',
            'email' => PhoneNumber::emailRules(false),
            'phone' => PhoneNumber::rules(),
            'phone_country_code' => PhoneNumber::countryCodeRules(),
            'company' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'message' => 'required|string|max:2000',
            'page_source' => 'nullable|string|max:100',
            'recaptcha_token' => ['nullable', new RecaptchaRule()],
        ]);

        $property = Property::findOrFail($request->input('property_id'));
        $this->capturePropertyLead($request, $property);

        $message = 'Thanks — your enquiry has been received and we\'ll be in touch soon.';

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    /** Time slots a visitor can pick when booking a viewing (key => label). */
    public const VIEWING_SLOTS = [
        'morning' => 'Morning (9 AM – 12 PM)',
        'afternoon' => 'Afternoon (12 PM – 4 PM)',
        'evening' => 'Evening (4 PM – 7 PM)',
    ];

    /**
     * Book a viewing
     *
     * A property lead (same routing as an enquiry) with the requested day + time slot. If the
     * listing has open house days (`available_dates` on the property details), only those days
     * are accepted; otherwise any day from today up to 60 days ahead.
     *
     * @bodyParam property_id integer required Example: 111
     * @bodyParam name string required Example: Sara Ahmed
     * @bodyParam email string Example: buyer@example.com
     * @bodyParam phone string Example: 501234567
     * @bodyParam phone_country_code string Example: +971
     * @bodyParam viewing_date string required Y-m-d. Example: 2026-10-15
     * @bodyParam viewing_time string required morning (9–12), afternoon (12–4) or evening (4–7). Example: afternoon
     * @bodyParam note string Example: Please call before coming.
     * @bodyParam recaptcha_token string See "Forms & reCAPTCHA" in the introduction. No-example
     *
     * @response 200 {"message": "Thanks — your viewing request for Thursday, 15 Oct 2026, Afternoon (12 PM – 4 PM) has been sent. The agent will confirm with you shortly.", "when": "Thursday, 15 Oct 2026, Afternoon (12 PM – 4 PM)"}
     * @response 422 {"message": "Please pick one of the open house days for this property.", "errors": {"viewing_date": ["Please pick one of the open house days for this property."]}}
     */
    public function storeViewing(Request $request)
    {
        $data = $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
            'name' => 'required|string|max:255',
            'email' => PhoneNumber::emailRules(false),
            'phone' => PhoneNumber::rules(),
            'phone_country_code' => PhoneNumber::countryCodeRules(),
            'viewing_date' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:' . today()->addDays(60)->toDateString(),
            'viewing_time' => ['required', \Illuminate\Validation\Rule::in(array_keys(self::VIEWING_SLOTS))],
            'note' => 'nullable|string|max:1000',
            'recaptcha_token' => ['nullable', new RecaptchaRule()],
        ]);

        $property = Property::findOrFail($data['property_id']);
        $openHouseDays = collect($property->available_dates ?? [])->filter(fn ($d) => is_string($d) && $d >= today()->toDateString());
        if ($openHouseDays->isNotEmpty() && !$openHouseDays->contains($data['viewing_date'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['viewing_date' => 'Please pick one of the open house days for this property.']);
        }

        $when = \Illuminate\Support\Carbon::parse($data['viewing_date'])->format('l, j M Y') . ', ' . self::VIEWING_SLOTS[$data['viewing_time']];
        $title = $property->getTranslation('title') ?: $property->reference_no;
        $request->merge([
            'message' => "Viewing request for \"{$title}\" — {$when}." . (!empty($data['note']) ? "\n\n" . $data['note'] : ''),
            'page_source' => 'book-viewing',
        ]);
        $lead = $this->capturePropertyLead($request, $property);
        $lead->forceFill(['extra_fields' => array_merge((array) $lead->extra_fields, [
            'viewing_date' => $data['viewing_date'],
            'viewing_time' => $data['viewing_time'],
        ])])->save();

        $message = "Thanks — your viewing request for {$when} has been sent. The agent will confirm with you shortly.";

        return $request->wantsJson() || $request->is('api/*') ? response()->json(['message' => $message, 'when' => $when]) : back()->with('success', $message);
    }

    /**
     * A property enquiry — routed to the listing's owner, unless the person is already another
     * account's lead: then it updates that lead (one person, one account). Expects a validated request.
     */
    private function capturePropertyLead(Request $request, Property $property): Lead
    {
        $assignedOwnerId = $this->tracker->assignedOwnerIdFor($request, $request->input('email'), $request->input('phone'));

        $lead = $this->leadCreation->create([
            'property_id' => $property->id,
            'user_id' => $request->user('sanctum')?->id, // website session or app Bearer token
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'phone_country_code' => $request->filled('phone') ? $request->input('phone_country_code') : null,
            'company' => $request->input('company'),
            'country' => $request->input('country'),
            'message' => $request->input('message'),
            'page_url' => $request->header('referer'),
            'page_source' => $request->input('page_source', 'property-detail'),
            'status' => 'active',
        ], ownerId: $assignedOwnerId);
        $this->trackEnquiry($request, $lead, match ($request->input('page_source', 'property-detail')) {
            'ai-chatbot' => 'AI chat property enquiry',
            'book-viewing' => 'Viewing request',
            'brochure-download' => 'Brochure download',
            'floor-plan-download' => 'Floor plan download',
            default => 'Property enquiry',
        });

        return $lead;
    }

    /** The enquirer becomes / stays this browser's website lead; the CRM lead links to their activity. */
    private function trackEnquiry(Request $request, Lead $lead, string $form): void
    {
        $this->tracker->captureEnquiry(
            $request,
            ['name' => $lead->name] + $request->only(['email', 'phone', 'phone_country_code']),
            $request->input('page_source') === 'ai-chatbot' ? VisitorLead::SOURCE_CHATBOT : VisitorLead::SOURCE_PROPERTY_ENQUIRY,
            ['form' => $form, 'message' => \Illuminate\Support\Str::limit((string) $request->input('message', $lead->message), 500)],
            $lead,
        );
    }

    /**
     * Custom property request
     *
     * "Can't find what you're looking for?" — the buyer describes the property they want; the
     * team picks it up.
     *
     * There's no property to route this to, so it's always an unassigned Lead: superadmin gets
     * notified and picks it up from the Unassigned Leads screen.
     *
     * @bodyParam first_name string required Example: Sara
     * @bodyParam last_name string Example: Ahmed
     * @bodyParam email string required Example: buyer@example.com
     * @bodyParam phone string Example: 501234567
     * @bodyParam phone_country_code string Example: +971
     * @bodyParam property_category string Example: Apartment
     * @bodyParam price_range string Example: AED 1M – 2M
     * @bodyParam preferred_location string Example: Dubai Marina
     * @bodyParam additional_details string Example: Sea view, high floor.
     * @bodyParam recaptcha_token string See "Forms & reCAPTCHA" in the introduction. No-example
     *
     * @response 200 {"message": "Thanks — your request has been received and our team will reach out soon."}
     */
    public function storeCustomRequest(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => PhoneNumber::emailRules(),
            'phone' => PhoneNumber::rules(),
            'phone_country_code' => PhoneNumber::countryCodeRules(),
            'property_category' => 'nullable|string|max:100',
            'specification' => 'nullable|string|max:255',
            'price_range' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:100',
            'preferred_location' => 'nullable|string|max:255',
            'additional_details' => 'nullable|string|max:2000',
            'move_in_timeline' => 'nullable|string|max:100',
            'furnishing_status' => 'nullable|string|max:100',
            'recaptcha_token' => ['nullable', new RecaptchaRule()],
        ]);

        $lead = $this->leadCreation->create([
            'user_id' => $request->user('sanctum')?->id, // website session or app Bearer token
            'name' => trim($data['first_name'] . ' ' . ($data['last_name'] ?? '')),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'phone_country_code' => !empty($data['phone']) ? ($data['phone_country_code'] ?? null) : null,
            'message' => $this->requestSummary($data),
            'page_url' => $request->header('referer'),
            'page_source' => 'custom-request',
            'status' => 'active',
            'extra_fields' => $this->requestExtraFields($request),
        // Already an agency's / agent's lead → it updates that lead; otherwise Super Admin's unassigned pool.
        ], ownerId: $this->tracker->assignedOwnerIdFor($request, $data['email'], $data['phone'] ?? null));
        $this->trackEnquiry($request, $lead, 'Custom property request');

        $message = "Thanks — your request has been received and our team will reach out soon.";

        if ($request->wantsJson() || $request->is('api/*')) {
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
        ];
    }
}
