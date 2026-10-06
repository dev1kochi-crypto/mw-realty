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
    public function __construct(
        private readonly LeadCreationService $leadCreation,
        private readonly VisitorTracker $tracker,
    ) {
    }

    /** Capture a property-search request from an agent or agency profile and route it to that CRM. */
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
                'user_id' => Auth::guard('web')->id(),
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

    /** Record a lead before revealing the listing brochure URL. */
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

    /** Record a lead before revealing the listing's downloadable floor plan file. */
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

        if ($request->wantsJson()) {
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
     * "Book a viewing" on the property page — a property lead (same routing as an enquiry) with the
     * requested day + time slot. A listing with open house days only offers those days; otherwise
     * any day in the next 60 days.
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

        return $request->wantsJson() ? response()->json(['message' => $message, 'when' => $when]) : back()->with('success', $message);
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
            'user_id' => Auth::guard('web')->id(),
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
            'user_id' => Auth::guard('web')->id(),
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
        ];
    }
}
