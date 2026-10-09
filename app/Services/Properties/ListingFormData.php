<?php

namespace App\Services\Properties;

use App\Models\CmsKit\Language;
use App\Models\Filter;
use App\Models\NearbyPlace;
use App\Models\PortalUser;
use App\Models\Property;
use App\Services\Crm\OwnerContext;
use App\Support\PermitRules;
use CMS\SiteManager\Services\StaticTranslationService;
use Illuminate\Support\Arr;

/**
 * Everything the CRM property form (Properties / Commercial — web app and mobile app) needs besides
 * the listing itself: languages, the managed dropdowns with a label per language, the Amenities /
 * Easy Access / Attributes lists, agent / agency choices, the permit rules and licenses, and which
 * fields the viewer may not change.
 */
class ListingFormData
{
    /** Managed dropdowns on the form (options from Master › Property Options or CMS filters). */
    private const SELECTS = ['listing_type', 'completion_status', 'property_type', 'category', 'location', Filter::FURNISHING_KEY, Filter::EMIRATE_KEY, Filter::RENTAL_PERIOD_KEY];

    /**
     * Fixed value sets whose label falls back to the static-texts JSON (lang/cms-static/{code}.json)
     * when Super Admin hasn't named the option; the rest use the option's own translation.
     */
    private const STATIC_LABEL_FIELDS = ['property_type', 'listing_type', 'completion_status', 'category'];

    /** Must match the DLD permit, so read-only once it's approved (Super Admin can still change them). */
    public const PERMIT_LOCKED_FIELDS = ['emirate', 'permit_type', 'permit_city', 'category', 'listing_type', 'property_type', 'location', 'bedrooms', 'sqft', 'permit_number'];

    public function __construct(private readonly OwnerContext $context)
    {
    }

    /** The form's option data. $property = the listing being edited (null on create). */
    public function for(?Property $property): array
    {
        $languages = Language::where('status', true)->get();
        $fallback = config('app.fallback_locale', 'en');
        $statics = app(StaticTranslationService::class);
        $staticLabels = $languages->mapWithKeys(fn ($lang) => [$lang->code => $statics->flatten($statics->read($lang->code))])->all();

        $filters = Filter::whereIn('key', self::SELECTS)->where('type', 'select')->with(['activeValues'])->get()->keyBy('key');
        $selects = collect(self::SELECTS)->mapWithKeys(fn ($key) => [$key => ($filters[$key]?->activeValues ?? collect())->map(fn ($option) => [
            'value' => (string) $option->value,
            'labels' => $languages->mapWithKeys(fn ($lang) => [$lang->code => $this->optionLabel($key, $option, $lang->code, $staticLabels, $fallback)])->all(),
        ])->values()]);

        $optionLists = Filter::whereIn('key', array_keys(Filter::ICON_LISTS))
            ->with(['values' => fn ($q) => $q->orderBy('order_index')->orderBy('id')])->get()->keyBy('key');

        return [
            'is_admin' => $this->context->isAdmin(),
            'languages' => $languages->map(fn ($lang) => ['code' => $lang->code, 'name' => $lang->name])->values(),
            'auto_translate' => \App\Services\AutoTranslator::configured(),
            'selects' => $selects,
            'option_lists' => collect(Filter::ICON_LISTS)->mapWithKeys(fn ($column, $key) => [$key => [
                'column' => $column,
                'options' => ($optionLists[$key]?->values ?? collect())->map(fn ($option) => [
                    'value' => (string) $option->value,
                    'label' => $option->getTranslation('label'),
                    'search' => mb_strtolower(collect($option->translations)->pluck('label')->implode(' ')),
                    'icon' => media_url($option->icon),
                    'status' => (bool) $option->status,
                ])->values(),
            ]]),
            'nearby_place_types' => (Filter::where('key', NearbyPlace::FILTER_KEY)->with('activeValues')->first()?->activeValues ?? collect())
                ->map(fn ($option) => ['value' => (string) $option->value, 'label' => $option->getTranslation('label')])->values(),
            'agents' => $this->agentAssignment(),
            'permit' => [
                'types' => collect(PermitRules::TYPES)->map(fn ($t, $key) => [
                    'label' => $t['label'],
                    'number_label' => $t['number_label'],
                    'license_label' => $t['license_label'],
                    'validates' => $t['validates'],
                    'needs_approval' => PermitRules::needsApproval($key),
                ]),
                'dubai_types' => PermitRules::DUBAI_TYPES,
                'northern_cities' => PermitRules::NORTHERN_CITIES,
                'licenses' => $this->permitLicenses($this->permitOwner($property)),
            ],
            'locked_fields' => $this->lockedFields($property),
            'remaining_slots' => $this->context->isAdmin() ? null : $this->context->owner()?->listingOwner()->remainingPropertySlots(),
            'links' => [
                'contact' => route('crm.app', 'contact'),
                'agents' => route('crm.app', 'agents'),
                'nearby_create' => route('crm.app', 'nearby-places/create'),
                'nearby_index' => route('crm.app', 'nearby-places'),
            ],
        ];
    }

    private function optionLabel(string $field, $option, string $lang, array $staticLabels, string $fallback): string
    {
        if (in_array($field, self::STATIC_LABEL_FIELDS, true)) {
            // Super Admin's label (Master › Property Options) wins; the static-texts JSON is the fallback.
            return ($option->translations[$lang]['label'] ?? null)
                ?: ($staticLabels[$lang]["{$field}.{$option->value}"]
                ?? $staticLabels[$fallback]["{$field}.{$option->value}"]
                ?? (string) $option->getTranslation('label', $lang));
        }

        return (string) ($option->getTranslation('label', $lang) ?: $option->getTranslation('label', $fallback));
    }

    /**
     * Agent / agency for the form: a locked (read-only) agent and/or agency for an Agent or Company
     * login, or full picker lists for Super Admin (choosing an agency narrows the agent list).
     */
    private function agentAssignment(): array
    {
        $user = $this->context->owner();

        if ($user && $user->type === 'agent') {
            return ['locked_agent' => $user->name, 'locked_agency' => $user->company?->displayName() ?? '—', 'agent_options' => [], 'agency_options' => []];
        }
        if ($user && $user->type === 'company') {
            // Only active, approved agents of this agency can be picked; "no agent" is always allowed.
            return [
                'locked_agent' => null,
                'locked_agency' => $user->displayName(),
                'agent_options' => $user->eligibleAgentsQuery()->reorder('portal_users.name')->get()->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'company_id' => $a->company_id, 'company' => null])->values(),
                'agency_options' => [],
            ];
        }

        return [
            'locked_agent' => null,
            'locked_agency' => null,
            'agent_options' => PortalUser::where('type', 'agent')->with('company')->orderBy('name')->get()
                ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'company_id' => $a->company_id, 'company' => $a->company?->displayName()])->values(),
            'agency_options' => PortalUser::where('type', 'company')->orderBy('name')->get()
                ->map(fn ($a) => ['id' => $a->id, 'name' => $a->displayName()])->values(),
        ];
    }

    /**
     * Fields the current user can't change on this listing (Super Admin can change everything):
     *  - once DLD / ADREC verified the permit: the emirate / permit choice, the permit number and every
     *    field the permit filled in (PermitVerifier::PERMIT_FIELDS) — they must stay as the permit says;
     *  - once the listing is approved: all PERMIT_LOCKED_FIELDS.
     * A field still empty stays editable, so a listing approved before it existed (e.g. Emirate) can fill it in.
     */
    public function lockedFields(?Property $property): array
    {
        if (!$property || $this->context->isAdmin()) {
            return [];
        }
        // The permit has to be replaced: taken down by Super Admin, expired, or about to expire
        // (renewal) — everything is editable again until the new permit is validated.
        if (in_array($property->compliance_status, [Property::COMPLIANCE_CHANGES_REQUESTED, Property::COMPLIANCE_EXPIRED], true)
            || ($property->permit_expires_at && $property->permit_expires_at->lte(today()->addDays(30)))) {
            return [];
        }
        $fields = [];
        if ($property->permit_verified_at && in_array($property->permit_verified_via, ['dld', 'adrec'], true)) {
            $fromPermit = array_keys(array_filter(Arr::only($property->permit_data ?? [], \App\Services\Permits\PermitVerifier::PERMIT_FIELDS), 'filled'));
            $fields = ['emirate', 'permit_type', 'permit_city', 'permit_number', ...$fromPermit];
        }
        if ($property->compliance_status === Property::COMPLIANCE_APPROVED) {
            $fields = [...$fields, ...self::PERMIT_LOCKED_FIELDS];
        }

        return array_values(array_unique(array_filter($fields, fn ($field) => filled($property->{$field}))));
    }

    /** Whose license a listing's permit is issued under: its owner (null = MW Realty house listing). */
    public function permitOwner(?Property $property): ?PortalUser
    {
        if ($property) {
            return $property->owner;
        }
        $viewer = $this->context->owner();

        return $viewer && $viewer->isAgencyAgent() ? $viewer->company : $viewer;
    }

    /** The licenses the form shows per permit type ("Real estate company license" / "Broker license"). */
    private function permitLicenses(?PortalUser $owner): array
    {
        $licenses = [
            'rera' => PermitRules::license('rera', $owner),
            'adrec' => PermitRules::license('adrec', $owner),
        ];

        // When a license is missing: who has to add it, and where (shown instead of the license).
        $holder = $owner && $owner->isAgent() && $owner->company ? $owner->company : $owner;
        $viewer = $this->context->owner();
        $missing = [];
        foreach (['rera' => 'RERA ORN', 'adrec' => 'ADREC brokerage registration number'] as $type => $what) {
            if ($licenses[$type]) {
                continue;
            }
            $missing[$type] = match (true) {
                !$holder => $this->context->isAdmin()
                    ? ['text' => "MW Realty's {$what} isn't set yet. Add it in CMS › Site Information to validate permits of MW Realty listings.", 'url' => route('cms.site-information.index'), 'link' => 'Open Site Information']
                    : ['text' => "MW Realty's {$what} isn't set yet.", 'url' => null, 'link' => null],
                $holder->isAgency() && $viewer?->id === $holder->id
                    => ['text' => "Your company's {$what} isn't in your profile yet. Add it under Profile › Corporate licenses to validate this permit.", 'url' => route('crm.app', 'profile'), 'link' => 'Open profile'],
                $holder->isAgency()
                    => ['text' => "{$holder->displayName()} hasn't added its {$what} yet — ask your agency to add it in their profile (Corporate licenses).", 'url' => null, 'link' => null],
                default => ['text' => 'Permits are issued to a brokerage, so an independent agent can\'t validate one here. Join your brokerage under My Agency, or save the listing and MW Realty will check the permit.', 'url' => route('portal.agency.index'), 'link' => 'My Agency'],
            };
            $missing[$type]['text'] .= ' You can still save the listing — MW Realty checks the permit before it goes live.';
        }

        return $licenses + ['missing' => $missing];
    }
}
