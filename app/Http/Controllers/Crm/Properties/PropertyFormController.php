<?php

namespace App\Http\Controllers\Crm\Properties;

use App\Http\Controllers\Crm\Concerns\ScopesListings;
use App\Models\AgencyAgent;
use App\Models\CmsKit\Language;
use App\Models\NearbyPlace;
use App\Models\PortalUser;
use App\Services\AutoTranslator;
use App\Services\Permits\PermitVerifier;
use App\Services\Properties\ListingFormData;
use App\Support\PermitRules;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * @group CRM Properties — Form helpers
 *
 * Lookups the property form and the listing filters use: the permit Validate button, auto-translate,
 * the Nearby Places type → place picker and the agent filter.
 */
class PropertyFormController extends Controller
{
    use ScopesListings;

    /** Translatable property fields; description is rich text (TinyMCE HTML). */
    private const TRANSLATE_FIELDS = ['title', 'key_features', 'description', 'address', 'community', 'city', 'country'];
    private const HTML_FIELDS = ['description'];

    /**
     * Validate a permit
     *
     * Checks the permit with DLD / ADREC. Returns the status, the details to fill in and a token the
     * form posts back on save (`permit_token`, see PermitVerifier).
     *
     * @bodyParam emirate string required Example: dubai
     * @bodyParam permit_type string rera | dtcm | none (Dubai). Example: rera
     * @bodyParam permit_city string al_ain | other (Northern Emirates).
     * @bodyParam permit_number string required Example: 7112345678
     * @bodyParam property_id integer The listing being edited.
     */
    public function validatePermit(Request $request, ListingFormData $form)
    {
        $input = $request->validate([
            'emirate' => 'required|string|max:100',
            'permit_type' => 'nullable|string|max:20',
            'permit_city' => 'nullable|string|max:30',
            'permit_number' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'property_id' => 'nullable|integer',
        ], ['permit_number.regex' => 'The permit number may only contain letters, numbers, dashes and slashes.']);

        $property = !empty($input['property_id']) ? $this->findAccessible($input['property_id']) : null;
        $type = PermitRules::resolve($input['emirate'], $input['permit_type'] ?? null, $input['permit_city'] ?? null);
        $result = app(PermitVerifier::class)->validate((string) $type, $form->permitOwner($property), $input['permit_number'], $property?->id);
        $check = $result['check'];

        return response()->json([
            'status' => $check->status,
            'message' => $check->message,
            'token' => $result['token'],
            'license' => $result['license'],
            // Only the listing fields the form fills in and locks; the raw response stays server-side.
            'fields' => $check->isVerified() ? Arr::only($check->data, [...PermitVerifier::PERMIT_FIELDS, 'expires_at', 'zone_name']) : [],
        ]);
    }

    /**
     * Auto-translate
     *
     * The source-language title / key features / description / location translated into the other
     * site languages (Google Cloud Translation — see AutoTranslator). 503 when not set up.
     *
     * @bodyParam source string required Example: en
     * @bodyParam targets string[] required Example: ["ar"]
     * @bodyParam fields object required Example: {"title": "Sea view apartment"}
     */
    public function translate(Request $request, AutoTranslator $translator)
    {
        $codes = Language::active()->pluck('code')->all();
        $data = $request->validate([
            'source' => ['required', Rule::in($codes)],
            'targets' => 'required|array|min:1',
            'targets.*' => [Rule::in($codes), 'different:source'],
            'fields' => 'required|array',
            'fields.*' => 'nullable|string|max:20000',
        ]);

        if (!AutoTranslator::configured()) {
            return response()->json(['message' => 'Auto-translate isn\'t set up yet (GOOGLE_TRANSLATE_API_KEY). You can still type each language by hand.'], 503);
        }

        $fields = array_intersect_key($data['fields'], array_flip(self::TRANSLATE_FIELDS));
        $html = array_intersect_key($fields, array_flip(self::HTML_FIELDS));
        $text = array_diff_key($fields, $html);

        $result = [];
        foreach (array_unique($data['targets']) as $target) {
            $translatedText = $text ? $translator->translateMany($text, $target, $data['source'], 'text') : [];
            $translatedHtml = $html ? $translator->translateMany($html, $target, $data['source'], 'html') : [];
            if ($translatedText === null || $translatedHtml === null) {
                return response()->json(['message' => 'Translation failed — please try again, or type this language by hand.'], 502);
            }
            $result[$target] = $translatedText + $translatedHtml;
        }

        return response()->json(['translations' => $result]);
    }

    /**
     * Nearby places of a type
     *
     * The form's Nearby Places picker: every active place of the type (shared or anyone's own).
     *
     * @queryParam type string required A Nearby Place Type option value. Example: school
     */
    public function nearbyPlaces(Request $request)
    {
        $places = NearbyPlace::active()
            ->when($request->input('type'), fn ($q, $type) => $q->where('category', $type))
            ->get(['id', 'translations', 'portal_user_id'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->getTranslation('name'), 'own' => !$p->isShared()]);

        return response()->json(['places' => $places]);
    }

    /**
     * Agent filter options
     *
     * The agency's agents (every agent for Super Admin) — searched on the server, 20 a page.
     *
     * @queryParam search string Example: sara
     * @queryParam page integer Example: 1
     */
    public function agentOptions(Request $request)
    {
        abort_unless($this->isAdmin() || $this->viewer()?->isAgency(), 403);
        $term = mb_substr(trim((string) $request->input('search', $request->input('q', ''))), 0, 100);

        $page = ($this->isAdmin()
                ? PortalUser::where('type', 'agent')
                : PortalUser::where('type', 'agent')->whereIn('id', AgencyAgent::where('agency_id', $this->viewer()->id)
                    ->whereIn('status', AgencyAgent::MEMBER_STATUSES)->select('agent_id')))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(20, ['id', 'name'], 'page', max(1, (int) $request->input('page', 1)));

        return response()->json([
            'data' => collect($page->items())->map(fn ($agent) => ['id' => $agent->id, 'name' => $agent->name]),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }
}
