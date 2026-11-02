<?php

namespace App\Imports;

use App\Models\CmsKit\Language;
use App\Models\PortalUser;
use App\Models\Property;
use App\Models\PropertyDetail;
use App\Services\AutoTranslator;
use App\Services\ListingComplianceService;
use App\Services\Properties\ListingCopywriter;
use App\Services\Properties\ListingOptionMatcher;
use App\Services\Properties\ListingTextNormalizer;
use App\Services\Properties\RemoteListingImages;
use App\Support\PermitRules;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bulk-creates an account's listings from a spreadsheet (any agency's own inventory export — the
 * columns are mapped to our fields when uploading, see FIELDS / suggestMapping()).
 *
 * Every row is checked on its own and gets a status in $results, which becomes the status column
 * of the result file:
 *  - Added          — listing created, nothing missing beyond validating its permit.
 *  - Needs details  — listing created, but photos / map pin / size / permit … are still missing
 *                     (listed in the remarks, and on the listing's form as usual).
 *  - Duplicate      — the account already has it (same permit, same agency reference, or the same
 *                     type, community, bedrooms, size and price), or an earlier row of the file does.
 *  - Skipped        — a required value is missing or unreadable, or the plan's listing limit is reached.
 *
 * Text is standardised (ListingTextNormalizer); optionally a missing description is written by AI
 * (ListingCopywriter) and the text translated into the other site languages (AutoTranslator).
 * Like Property Finder imports, listings are created off the website: they go live once their
 * permit is validated (ListingComplianceService). Option values are matched the same way too
 * (ListingOptionMatcher). Plan limits apply; credits are never spent by an import.
 */
class PropertiesImport
{
    public const STATUS_ADDED = 'Added';
    public const STATUS_NEEDS_INFO = 'Needs details';
    public const STATUS_DUPLICATE = 'Duplicate';
    public const STATUS_SKIPPED = 'Skipped';

    public const MAX_ROWS = 2000;

    /**
     * Import field => [label, required, header aliases (as LeadImportReader::key), hint].
     * Order = the order of the mapping screen and of the template's columns.
     */
    public const FIELDS = [
        'reference' => ['Your reference', false, ['reference', 'ref', 'ref_no', 'reference_no', 'reference_number', 'listing_ref', 'listing_reference', 'external_id', 'listing_id', 'property_ref'], 'Your own listing ID — used to spot duplicates'],
        'title' => ['Title', false, ['title', 'listing_title', 'headline', 'property_title', 'title_en'], 'Written from the details when empty'],
        'listing_type' => ['Purpose (sale / rent)', true, ['purpose', 'offering', 'offering_type', 'listing_type', 'sale_rent', 'sale_or_rent', 'for', 'transaction_type', 'ad_type'], 'Sale, Buy, Rent, Lease…'],
        'property_type' => ['Property type', true, ['property_type', 'type', 'unit_type', 'property_sub_type', 'subtype', 'type_of_property'], 'Apartment, Villa, Townhouse, Office…'],
        'price' => ['Price (AED)', true, ['price', 'price_aed', 'amount', 'asking_price', 'rent', 'rent_aed', 'annual_rent', 'selling_price'], '1,250,000 · 1.25M · 95k'],
        'community' => ['Community / area', true, ['community', 'area', 'location', 'neighbourhood', 'neighborhood', 'district', 'locality', 'project_area'], 'e.g. Dubai Marina, JVC'],
        'sub_community' => ['Building / sub-community', false, ['sub_community', 'subcommunity', 'building', 'tower', 'project', 'building_name', 'project_name', 'address', 'street'], 'Shown before the community in the address'],
        'city' => ['City', false, ['city', 'town'], 'Dubai, Abu Dhabi, Al Ain, Sharjah…'],
        'emirate' => ['Emirate', false, ['emirate', 'state', 'region', 'province'], 'Read from the city / community when empty'],
        'category' => ['Category', false, ['category', 'segment', 'property_category', 'usage'], 'Residential or Commercial — from this page when empty'],
        'completion_status' => ['Completion', false, ['completion', 'completion_status', 'ready_off_plan', 'project_status', 'construction_status'], 'Ready or Off-plan (default Ready)'],
        'rental_period' => ['Rent frequency', false, ['rental_period', 'rent_frequency', 'frequency', 'period', 'rent_period', 'price_period'], 'Yearly, Monthly… (rentals, default Yearly)'],
        'bedrooms' => ['Bedrooms', false, ['bedrooms', 'beds', 'bedroom', 'bed', 'br', 'no_of_bedrooms', 'rooms'], 'Number, or Studio'],
        'bathrooms' => ['Bathrooms', false, ['bathrooms', 'baths', 'bathroom', 'bath', 'no_of_bathrooms'], ''],
        'sqft' => ['Size (sq.ft)', false, ['size', 'sqft', 'size_sqft', 'area_sqft', 'bua', 'built_up_area', 'builtup_area', 'size_sq_ft', 'plot_size'], ''],
        'sqm' => ['Size (sq.m)', false, ['sqm', 'size_sqm', 'area_sqm', 'size_m2', 'area_m2'], 'Converted to sq.ft'],
        'furnished' => ['Furnishing', false, ['furnishing', 'furnished', 'furniture', 'furnishing_status'], 'Furnished, Semi, Unfurnished, Yes / No'],
        'description' => ['Description', false, ['description', 'desc', 'details', 'listing_description', 'description_en', 'property_description', 'remarks'], 'Plain text or HTML'],
        'key_features' => ['Key features', false, ['key_features', 'features', 'highlights', 'selling_points'], 'One per line, or separated by ;'],
        'amenities' => ['Amenities', false, ['amenities', 'facilities', 'amenity', 'private_amenities', 'commercial_amenities'], 'Comma separated: Pool, Gym, Parking…'],
        'image_urls' => ['Photo URLs', false, ['images', 'image_urls', 'photos', 'photo_urls', 'pictures', 'image_links', 'photo_links', 'image_url'], 'Links separated by commas, spaces or new lines'],
        'permit_number' => ['Permit number', false, ['permit', 'permit_number', 'permit_no', 'rera_permit', 'trakheesi', 'trakheesi_permit', 'dld_permit', 'adrec_permit', 'advertising_permit'], 'DLD (Trakheesi) / ADREC permit'],
        'permit_expires_at' => ['Permit expiry date', false, ['permit_expiry', 'permit_expires_at', 'permit_expiry_date', 'permit_expires'], ''],
        'latitude' => ['Latitude', false, ['latitude', 'lat', 'geo_lat'], 'Needed for the map pin'],
        'longitude' => ['Longitude', false, ['longitude', 'lng', 'lon', 'long', 'geo_lng'], ''],
        'agent' => ['Agent (email or name)', false, ['agent', 'agent_email', 'agent_name', 'listing_agent', 'assigned_agent', 'broker_email'], 'Matched to your agents'],
        'view' => ['View', false, ['view', 'views'], ''],
        'parking' => ['Parking spaces', false, ['parking', 'parking_spaces', 'parkings', 'car_parks'], ''],
        'developer' => ['Developer', false, ['developer', 'developer_name', 'builder'], ''],
        'unit_number' => ['Unit number', false, ['unit_number', 'unit', 'unit_no', 'apartment_number', 'villa_number'], ''],
        'floor' => ['Floor', false, ['floor', 'floor_number', 'level'], ''],
        'year_built' => ['Year built', false, ['year_built', 'built_year', 'completion_year', 'handover_year', 'year'], ''],
    ];

    private const COMMERCIAL_TYPES = ['office', 'retail-shop', 'showroom', 'warehouse', 'whole-building', 'co-working', 'plot'];

    public int $added = 0;
    public int $complete = 0;
    public int $duplicates = 0;
    public int $skipped = 0;
    public int $aiWritten = 0;
    public int $translated = 0;

    /** Why rows stopped being imported (plan full), for the summary. */
    public ?string $stoppedBecause = null;

    /** @var string[] */
    public array $headers = [];

    /** @var array<int, string[]> */
    public array $rows = [];

    /** @var array<int, array{status:string, remarks:string}> Per row of $rows, same index. */
    public array $results = [];

    /** In-file duplicate keys => file row number of the first row that used it. */
    private array $seen = [];

    /** Other site languages to translate into (when the option is on and translation is set up). */
    private array $targets = [];

    private ListingOptionMatcher $options;
    private ListingTextNormalizer $text;

    /**
     * @param array<string, int|null> $mapping import field => file column index
     * @param array{translate?: bool, ai_descriptions?: bool} $settings
     */
    public function __construct(
        private readonly PortalUser $owner,
        private readonly ?PortalUser $uploader,
        private readonly string $segment,
        private readonly array $mapping,
        private readonly array $settings = [],
        private readonly ?int $importId = null,
    ) {
        $this->options = app(ListingOptionMatcher::class);
        $this->text = app(ListingTextNormalizer::class);
    }

    /** The mapping screen's field list. */
    public static function fieldList(): array
    {
        return collect(self::FIELDS)->map(fn ($f, $key) => ['key' => $key, 'label' => $f[0], 'required' => $f[1], 'hint' => $f[3]])->values()->all();
    }

    /** Field => column index for the headings we recognise (each column used once). */
    public static function suggestMapping(array $headers): array
    {
        $keys = array_map([LeadImportReader::class, 'key'], $headers);
        $used = [];
        $mapping = [];
        foreach (self::FIELDS as $field => [, , $aliases]) {
            foreach ($aliases as $alias) {
                $index = array_search($alias, $keys, true);
                if ($index !== false && !isset($used[$index])) {
                    $mapping[$field] = $used[$index] = $index;
                    break;
                }
            }
        }

        return $mapping;
    }

    /** Problems with the file or the mapping as a whole, else null. */
    public function prepare(array $headers, array $rows): ?string
    {
        $this->headers = $headers;
        $this->rows = $rows;

        $missing = collect(self::FIELDS)->filter(fn ($f, $key) => $f[1] && !is_int($this->mapping[$key] ?? null))->map(fn ($f) => $f[0]);
        if ($missing->isNotEmpty()) {
            return 'Choose the column for: ' . $missing->implode(', ') . '.';
        }
        foreach ($this->mapping as $field => $index) {
            if ($index !== null && (!isset(self::FIELDS[$field]) || $index < 0 || $index >= count($headers))) {
                return 'The column mapping doesn\'t match this file — please map the columns again.';
            }
        }
        if (!array_filter($rows, fn ($row) => !LeadImportReader::isBlank($row))) {
            return 'The uploaded file has no data rows.';
        }
        if (count($rows) > self::MAX_ROWS) {
            return 'This file has ' . number_format(count($rows)) . ' rows — import at most ' . number_format(self::MAX_ROWS) . ' listings per file (split it into several files).';
        }

        return null;
    }

    /** $progress(processed) after each row. */
    public function process(?callable $progress = null): void
    {
        if (($this->settings['translate'] ?? false) && AutoTranslator::configured()) {
            $this->targets = Language::active()->pluck('code')->reject(fn ($c) => $c === $this->sourceLang())->values()->all();
        }

        foreach ($this->rows as $index => $row) {
            try {
                $this->results[$index] = $this->importRow($row, $index + 2);
            } catch (\Throwable $e) {
                Log::warning("Property import row " . ($index + 2) . ' failed: ' . $e->getMessage());
                $this->results[$index] = ['status' => self::STATUS_SKIPPED, 'remarks' => 'Could not be saved — please check this row and import it again.'];
            }
            match ($this->results[$index]['status']) {
                self::STATUS_ADDED => [$this->added++, $this->complete++],
                self::STATUS_NEEDS_INFO => $this->added++,
                self::STATUS_DUPLICATE => $this->duplicates++,
                default => $this->skipped++,
            };
            $progress && $progress($index + 1);
        }
    }

    /** @return array{status:string, remarks:string} */
    private function importRow(array $row, int $rowNumber): array
    {
        if (LeadImportReader::isBlank($row)) {
            return $this->skip('Empty row.');
        }
        $v = fn (string $field) => is_int($this->mapping[$field] ?? null) ? trim((string) ($row[$this->mapping[$field]] ?? '')) : '';

        // ---- Required values
        $errors = [];
        $listingType = $this->listingType($v('listing_type'));
        if (!$listingType) {
            $errors[] = $v('listing_type') === '' ? 'purpose (sale / rent) is empty' : "purpose \"{$v('listing_type')}\" isn't sale or rent";
        }
        $propertyType = $this->options->propertyType($v('property_type'));
        if (!$propertyType) {
            $errors[] = $v('property_type') === '' ? 'property type is empty' : "property type \"{$v('property_type')}\" isn't one of ours";
        }
        $price = $this->number($v('price'));
        if (!$price || $price <= 0) {
            $errors[] = $v('price') === '' ? 'price is empty' : "price \"{$v('price')}\" isn't a number";
        }
        $community = $this->text->title($v('community'));
        if ($community === '') {
            $errors[] = 'community / area is empty';
        }
        $emirate = $this->options->match('emirate', [$v('emirate')]) ?? $this->options->emirateFromNames([$v('emirate'), $v('city'), $community, $v('sub_community')]);
        if (!$emirate) {
            $errors[] = 'emirate unknown — add an Emirate or City column';
        }
        if ($errors) {
            return $this->skip(Str::ucfirst(implode('; ', $errors)) . '.');
        }

        // ---- Everything else (an unreadable optional value is left empty and mentioned)
        $notes = [];
        $category = $this->options->match('category', [$v('category')])
            ?? (in_array($propertyType, self::COMMERCIAL_TYPES, true) ? 'commercial' : ($this->segment === Property::SEGMENT_COMMERCIAL ? 'commercial' : 'residential'));
        $completion = $this->options->match('completion_status', [str_replace(['offplan', 'under construction'], ['off plan', 'off plan'], Str::lower($v('completion_status')))]) ?? 'ready';
        $bedrooms = preg_match('/studio/i', $v('bedrooms')) ? 0 : $this->integer($v('bedrooms'));
        $bathrooms = $this->integer($v('bathrooms'));
        $sqft = $this->number($v('sqft'));
        if ($sqft === null && ($sqm = $this->number($v('sqm')))) {
            $sqft = $sqm * 10.7639;
        }
        $sqft = $sqft ? (int) round($sqft) : null;
        [$lat, $lng] = [$this->number($v('latitude')), $this->number($v('longitude'))];
        if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180 || ($lat == 0 && $lng == 0)) {
            [$lat, $lng] = [null, null];
        }
        $permit = strtoupper(preg_replace('/\s+/', '', $v('permit_number')));
        if ($permit !== '' && !preg_match('/^[A-Za-z0-9\-\/]{3,64}$/', $permit)) {
            $notes[] = "permit \"{$v('permit_number')}\" left out (letters, numbers, - and / only)";
            $permit = '';
        }
        $reference = Str::limit($v('reference'), 100, '');
        $city = $this->cityName($v('city'), $emirate);
        $location = $this->options->match('location', [$community, $v('sub_community')]) ?? $this->options->addLocation($community);
        $permitType = PermitRules::resolve($emirate, 'rera', preg_match('/al ain/i', "{$city} {$community}") ? 'al_ain' : 'other');
        $amenities = $this->options->amenities(array_filter(array_map('trim', preg_split('/[,;|\n]/', $v('amenities')))));

        // ---- Duplicates: against the account's listings, then earlier rows of this file
        $fingerprint = implode('|', [$listingType, $propertyType, $location, $bedrooms, $sqft ? round($sqft / 25) : '', round($price / 1000)]);
        if ($duplicate = $this->existingDuplicate($permit, $reference, $listingType, $propertyType, $location, $bedrooms, $sqft, $price)) {
            return $duplicate;
        }
        foreach (array_filter(['permit' => $permit, 'reference' => $reference, 'listing' => $fingerprint]) as $kind => $key) {
            if ($first = $this->seen["{$kind}:" . Str::lower($key)] ?? null) {
                return ['status' => self::STATUS_DUPLICATE, 'remarks' => "Same {$kind} as row {$first} of this file."];
            }
        }
        foreach (array_filter(['permit' => $permit, 'reference' => $reference, 'listing' => $fingerprint]) as $kind => $key) {
            $this->seen["{$kind}:" . Str::lower($key)] = $rowNumber;
        }

        // ---- Plan limit (checked per row: a plan can be upgraded while a long file runs)
        $this->owner->unsetRelation('plan');
        if ($this->owner->remainingPropertySlots() === 0) {
            $this->stoppedBecause ??= 'Your plan\'s listing limit was reached — upgrade your plan, then import the file again to add the rest (rows already added are recognised as duplicates).';

            return $this->skip('Plan listing limit reached.');
        }

        // ---- Text: standardised, AI-written when missing, translated
        $typeLabel = Str::headline(str_replace('-', ' ', $propertyType));
        $title = $this->text->title($v('title'));
        $autoTitle = $title === '';
        if ($autoTitle) {
            $rooms = $bedrooms === null ? '' : ($bedrooms === 0 ? ($propertyType === 'apartment' ? 'Studio ' : '') : "{$bedrooms} Bed ");
            $title = trim("{$rooms}{$typeLabel} for " . ($listingType === 'rent' ? 'Rent' : 'Sale') . " in {$community}");
        }
        $description = $this->text->description($v('description'));
        $keyFeatures = $this->text->keyFeatures($v('key_features'));
        $furnished = $this->furnishing($v('furnished'));

        if ($description === '' && ($this->settings['ai_descriptions'] ?? false)) {
            $copy = app(ListingCopywriter::class)->write([
                'listing_type' => $listingType, 'property_type' => $propertyType, 'category' => $category, 'completion_status' => $completion,
                'bedrooms' => $bedrooms, 'bathrooms' => $bathrooms, 'sqft' => $sqft, 'price' => $price, 'currency' => 'AED',
                'community' => $community, 'address' => $v('sub_community'), 'city' => $city, 'emirate' => $emirate, 'furnished' => $furnished,
                'view' => $v('view'), 'floor' => $v('floor'), 'parking' => $v('parking'), 'year_built' => $v('year_built'), 'developer' => $v('developer'),
                'amenities' => $amenities, 'notes' => $v('key_features'),
            ], $this->sourceLang());
            if ($copy) {
                $description = $copy['description'];
                $keyFeatures = $keyFeatures ?: $copy['key_features'];
                $title = $autoTitle ? $copy['title'] : $title;
                $this->aiWritten++;
            } else {
                $notes[] = 'AI description not available right now';
            }
        }

        $source = $this->sourceLang();
        $translations = [$source => array_filter([
            'title' => $title,
            'key_features' => $keyFeatures,
            'description' => $description,
            'address' => $this->text->title($v('sub_community')) ?: $community,
            'community' => $community,
            'city' => $city,
            'country' => 'United Arab Emirates',
        ], fn ($x) => $x !== '' && $x !== null)];
        $translatedTo = $this->translate($translations);

        // ---- Create
        $agentId = $this->agentId($v('agent'));
        $property = DB::transaction(function () use ($translations, $title, $listingType, $completion, $propertyType, $category, $location, $emirate, $permitType,
            $permit, $bedrooms, $bathrooms, $sqft, $price, $lat, $lng, $reference, $agentId, $amenities, $furnished, $v) {
            $referenceNo = Property::nextReferenceNo();
            $property = Property::create([
                'portal_user_id' => $this->owner->id,
                'agent_id' => $agentId,
                'created_by_type' => $this->owner->isAgency() ? 'agency' : 'agent',
                'created_by_id' => $this->uploader?->id ?? $this->owner->id,
                'translations' => $translations,
                'slug' => Property::makeSlug($title, $referenceNo),
                'reference_no' => $referenceNo,
                'listing_type' => $listingType,
                'rental_period' => $listingType === 'rent' ? ($this->options->match('rental_period', [$v('rental_period')]) ?? 'yearly') : null,
                'completion_status' => $completion,
                'property_type' => $propertyType,
                'category' => $category,
                'segment' => $category === 'commercial' ? Property::SEGMENT_COMMERCIAL : Property::SEGMENT_RESIDENTIAL,
                'location' => $location,
                'emirate' => $emirate,
                'permit_type' => $permitType,
                'permit_city' => $emirate === PermitRules::NORTHERN ? ($permitType === 'adrec' ? 'al_ain' : 'other') : null,
                'permit_number' => $permit ?: null,
                'permit_expires_at' => $this->date($v('permit_expires_at')),
                'latitude' => $lat,
                'longitude' => $lng,
                'bedrooms' => $bedrooms,
                'bathrooms' => $bathrooms,
                'sqft' => $sqft,
                'price' => round($price, 2),
                'currency' => 'AED',
                'featured' => false,
                // Off the website until the permit is validated (ListingComplianceService::afterSave below).
                'status' => false,
                'order_index' => 0,
                'metadata' => array_filter(['source' => 'spreadsheet', 'import_id' => $this->importId, 'import_reference' => $reference ?: null]),
            ]);

            PropertyDetail::create([
                'property_id' => $property->id,
                'amenities' => $amenities,
                'easy_access' => [],
                'property_attributes' => [],
                'furnished' => $furnished ?? PropertyDetail::UNFURNISHED,
                'view' => Str::limit($v('view'), 255, '') ?: null,
                'parking' => $this->integer($v('parking')),
                'developer' => Str::limit($v('developer'), 255, '') ?: null,
                'unit_number' => Str::limit($v('unit_number'), 100, '') ?: null,
                'floor' => Str::limit($v('floor'), 255, '') ?: null,
                'year_built' => ($year = $this->integer($v('year_built'))) && $year >= 1800 && $year <= 2200 ? $year : null,
            ]);

            return $property;
        });

        // Photos, then the permit state / duplicate screening — outside the transaction, like Property Finder.
        $urls = array_values(array_filter(preg_split('/[\s,|;]+/', $v('image_urls')), fn ($u) => filter_var($u, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $u)));
        $photos = app(RemoteListingImages::class)->store($property, $urls);
        if ($urls && $photos < count($urls)) {
            $notes[] = ($photos ? count($urls) - $photos . ' of ' . count($urls) : 'the') . ' photos could not be downloaded';
        }
        $compliance = app(ListingComplianceService::class);
        $compliance->afterSave($property, null);

        // ---- What the listing still needs
        $missing = array_keys(array_filter([
            'photos' => !$photos,
            'description' => $description === '',
            'map pin (latitude / longitude)' => $lat === null,
            'size' => !$sqft,
            'bedrooms' => $bedrooms === null && $category !== 'commercial',
            'amenities' => !$amenities,
        ]));
        foreach (Language::active()->pluck('code') as $code) {
            if ($code !== $source && !in_array($code, $translatedTo, true)) {
                $missing[] = Str::upper($code) . ' title';
            }
        }
        $missing = [...$missing, ...$compliance->missingItems($property->refresh())];

        $remarks = $property->reference_no
            . ($agentId ? '' : ($this->owner->isAgency() && $v('agent') !== '' ? ' · agent "' . $v('agent') . '" not found — no agent assigned' : ''))
            . ($missing ? ' · still needed: ' . implode(', ', $missing) : ' · ready — validate the permit to publish')
            . ($notes ? ' · ' . implode('; ', $notes) : '');

        return ['status' => $missing ? self::STATUS_NEEDS_INFO : self::STATUS_ADDED, 'remarks' => $remarks];
    }

    /** The same listing already on the account: permit, the agency's own reference, or the same unit at the same price. */
    private function existingDuplicate(string $permit, string $reference, string $listingType, string $propertyType, ?string $location, ?int $bedrooms, ?int $sqft, float $price): ?array
    {
        if ($permit !== '' && ($other = Property::where('permit_number', $permit)->first(['id', 'portal_user_id', 'reference_no']))) {
            return $other->portal_user_id === $this->owner->id
                ? ['status' => self::STATUS_DUPLICATE, 'remarks' => "Already listed as {$other->reference_no} (same permit)."]
                : $this->skip('This permit number is already used by another listing on MW Realty — each advertising permit covers one listing.');
        }
        $mine = Property::where('portal_user_id', $this->owner->id);
        if ($reference !== '' && ($other = (clone $mine)->where('metadata->import_reference', $reference)->value('reference_no'))) {
            return ['status' => self::STATUS_DUPLICATE, 'remarks' => "Already listed as {$other} (same reference \"{$reference}\")."];
        }
        $other = (clone $mine)->where('listing_type', $listingType)->where('property_type', $propertyType)->where('location', $location)
            ->when($bedrooms === null, fn ($q) => $q->whereNull('bedrooms'), fn ($q) => $q->where('bedrooms', $bedrooms))
            ->when($sqft, fn ($q) => $q->whereBetween('sqft', [(int) floor($sqft * 0.97), (int) ceil($sqft * 1.03)]))
            ->whereBetween('price', [$price * 0.98, $price * 1.02])
            ->value('reference_no');

        return $other ? ['status' => self::STATUS_DUPLICATE, 'remarks' => "Looks like {$other} — same type, community, bedrooms, size and price."] : null;
    }

    /** Fills the other site languages from the source text; returns the languages filled. */
    private function translate(array &$translations): array
    {
        $source = $this->sourceLang();
        if (!$this->targets) {
            return [];
        }
        $translator = app(AutoTranslator::class);
        $text = array_diff_key($translations[$source], array_flip(['description', 'country']));
        $html = array_intersect_key($translations[$source], array_flip(['description']));
        $done = [];
        foreach ($this->targets as $target) {
            $t = $translator->translateMany($text, $target, $source, 'text');
            $h = $html ? $translator->translateMany($html, $target, $source, 'html') : [];
            if ($t === null || $h === null || empty($t['title'])) {
                continue;
            }
            $translations[$target] = array_filter($t + $h + ['country' => $translator->translate('United Arab Emirates', $target, $source) ?? 'United Arab Emirates']);
            $done[] = $target;
        }
        if ($done) {
            $this->translated++;
        }

        return $done;
    }

    private function agentId(string $agent): ?int
    {
        // An agency agent's upload is theirs; an independent agent's listings are always their own.
        if (!$this->owner->isAgency()) {
            return $this->owner->id;
        }
        if ($agent !== '') {
            $query = fn () => $this->owner->eligibleAgentsQuery();
            $id = str_contains($agent, '@')
                ? $query()->whereRaw('LOWER(portal_users.email) = ?', [Str::lower($agent)])->value('portal_users.id')
                : $query()->whereRaw('LOWER(TRIM(portal_users.name)) = ?', [Str::lower($agent)])->value('portal_users.id');
            if ($id) {
                return $id;
            }
        }

        return $this->uploader && $this->uploader->id !== $this->owner->id ? $this->uploader->id : null;
    }

    private function listingType(string $value): ?string
    {
        $v = Str::lower($value);

        return match (true) {
            $v === '' => null,
            (bool) preg_match('/rent|lease|\blet\b/', $v) => 'rent',
            (bool) preg_match('/sale|sell|buy|resale|off.?plan/', $v) => 'sale',
            default => $this->options->match('listing_type', [$value]),
        };
    }

    private function furnishing(string $value): ?string
    {
        $v = Str::lower($value);

        return match (true) {
            $v === '' => null,
            str_contains($v, 'semi'), str_contains($v, 'part') => 'semi_furnished',
            str_contains($v, 'unfurn'), in_array($v, ['no', 'n', '0', 'false'], true) => 'unfurnished',
            str_contains($v, 'furn'), in_array($v, ['yes', 'y', '1', 'true'], true) => 'furnished',
            default => $this->options->match('furnishing', [$value]),
        };
    }

    private function cityName(string $city, string $emirate): string
    {
        $city = $this->text->title($city);

        return $city !== '' ? $city : match ($emirate) {
            PermitRules::DUBAI => 'Dubai',
            PermitRules::ABU_DHABI => 'Abu Dhabi',
            default => 'Sharjah',
        };
    }

    /** "AED 1,250,000" · "1.25M" · "95k" → float; null when unreadable. */
    private function number(string $value): ?float
    {
        $v = str_replace([',', ' ', 'aed', 'sqft', 'sq.ft', 'sqm'], '', Str::lower($value));
        if (!preg_match('/^(\d+(?:\.\d+)?)(k|m|mn|million)?$/', $v, $m)) {
            return is_numeric($v) ? (float) $v : null;
        }

        return (float) $m[1] * match ($m[2] ?? '') { 'k' => 1e3, 'm', 'mn', 'million' => 1e6, default => 1 };
    }

    private function integer(string $value): ?int
    {
        $n = $this->number($value);

        return $n === null ? null : (int) round($n);
    }

    private function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        try {
            // Excel serial dates come through as plain numbers.
            return is_numeric($value)
                ? Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString()
                : Carbon::parse(str_replace('/', '-', $value))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function sourceLang(): string
    {
        return config('app.fallback_locale', 'en');
    }

    private function skip(string $reason): array
    {
        return ['status' => self::STATUS_SKIPPED, 'remarks' => $reason];
    }
}
