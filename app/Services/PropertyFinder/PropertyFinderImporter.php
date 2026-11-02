<?php

namespace App\Services\PropertyFinder;

use App\Models\PortalUser;
use App\Models\Property;
use App\Models\PropertyDetail;
use App\Models\PropertyFinderConnection;
use App\Models\PropertyFinderImport;
use App\Services\Properties\ListingOptionMatcher;
use App\Services\Properties\RemoteListingImages;
use App\Support\PermitRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Brings an account's live Property Finder listings into MW Realty as its properties.
 *
 *   mode 'all' — every live listing (the first import, or a full re-check).
 *   mode 'new' — newest first, stopping once a whole page is already imported: just the listings
 *                added on Property Finder since the last sync.
 *
 * No duplicates: a listing already imported (property_finder_imports.pf_listing_id, by any account)
 * or whose permit number the account already lists is skipped. Imported properties are created
 * off the website (compliance draft, status off) and wait for Super Admin review
 * (PropertyFinderReview). Plan listing limits apply — the sync stops when the plan is full.
 * Option matching and photo downloads are shared with the spreadsheet import (PropertiesImport).
 */
class PropertyFinderImporter
{
    private const MAX_PAGES = 200;

    /** Why the last sync stopped early (plan full), shown to the account. */
    public ?string $stoppedBecause = null;

    public function __construct(private readonly ListingOptionMatcher $options, private readonly RemoteListingImages $images)
    {
    }

    /**
     * $progress(added, skipped, failed) after each listing. Returns [added, skipped, failed].
     */
    public function sync(PropertyFinderConnection $connection, string $mode = 'all', ?callable $progress = null): array
    {
        $this->stoppedBecause = null;
        $client = $connection->client();
        $owner = $connection->owner;
        $added = $skipped = $failed = 0;

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $result = $client->listings($page);
            $newOnPage = 0;

            foreach ($result['items'] as $raw) {
                $listing = new PropertyFinderListing($raw);
                if ($listing->id() === '' || !$listing->isLive()) {
                    continue;
                }
                // Its property was deleted since (property_id nulled by the FK) → not a duplicate any
                // more: it may come in again. A listing Super Admin rejected stays out for good.
                PropertyFinderImport::where('pf_listing_id', $listing->id())->whereNull('property_id')
                    ->where('review_status', '!=', PropertyFinderImport::REJECTED)->delete();
                if (PropertyFinderImport::where('pf_listing_id', $listing->id())->exists()) {
                    $skipped++;
                    $progress && $progress($added, $skipped, $failed);
                    continue;
                }
                $newOnPage++;

                $owner->unsetRelation('plan');
                if ($owner->remainingPropertySlots() === 0) {
                    // Paid plans get a fresh monthly allowance: the rest import on a sync after an
                    // upgrade or once the month rolls over (duplicates are skipped, so nothing doubles).
                    $this->stoppedBecause = $owner->plan?->listingsPerMonth()
                        ? "You've added all {$owner->plan->property_limit} listings your plan allows this month. Upgrade your plan, or sync again from "
                            . now()->addMonthNoOverflow()->startOfMonth()->format('d M') . ' to import the rest.'
                        : 'Your plan\'s listing limit is reached — upgrade your plan, then sync again to import the rest.';

                    return [$added, $skipped, $failed];
                }

                try {
                    $this->import($connection, $listing) ? $added++ : $skipped++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning("Property Finder listing {$listing->id()} import failed: " . $e->getMessage());
                }
                $progress && $progress($added, $skipped, $failed);
            }

            // 'new': a page with nothing new means everything older is already here.
            if (!$result['hasMore'] || ($mode === 'new' && $newOnPage === 0 && $result['items'])) {
                break;
            }
        }

        return [$added, $skipped, $failed];
    }

    /** Creates the property for one listing. False when it's a duplicate of one the account already has. */
    public function import(PropertyFinderConnection $connection, PropertyFinderListing $listing): bool
    {
        $owner = $connection->owner;
        $permit = $listing->permitNumber();
        // The account already lists this permit (added by hand, or from another feed) → not again.
        if ($permit && Property::where('portal_user_id', $owner->id)->where('permit_number', $permit)->exists()) {
            return false;
        }

        $place = $this->placeFields($connection, $listing);
        $title = $listing->title() ?? trim(($listing->type() ?? 'Property') . ' in ' . ($place['names'][0] ?? 'UAE'));
        $translations = ['en' => array_filter([
            'title' => Str::limit($title, 255, ''),
            'description' => $listing->description(),
            'country' => 'United Arab Emirates',
        ] + $place['translation'])];
        if ($arTitle = $listing->title('ar')) {
            $translations['ar'] = array_filter(['title' => Str::limit($arTitle, 255, ''), 'description' => $listing->description('ar')]);
        }

        $property = DB::transaction(function () use ($connection, $owner, $listing, $place, $title, $translations, $permit) {
            // Claim the listing first: two syncs racing can't both import it (unique pf_listing_id).
            $import = PropertyFinderImport::create([
                'property_finder_connection_id' => $connection->id,
                'portal_user_id' => $owner->id,
                'pf_listing_id' => $listing->id(),
                'pf_reference' => $listing->reference(),
                'review_status' => PropertyFinderImport::PENDING,
                'pf_created_at' => $listing->createdAt(),
            ]);

            $segment = $listing->category() === 'commercial' ? Property::SEGMENT_COMMERCIAL : Property::SEGMENT_RESIDENTIAL;
            $referenceNo = Property::nextReferenceNo();
            $property = Property::create([
                'portal_user_id' => $owner->id,
                'agent_id' => $this->agentId($owner, $listing),
                'created_by_type' => $owner->isAgency() ? 'agency' : 'agent',
                'created_by_id' => $owner->id,
                'translations' => $translations,
                'slug' => Property::makeSlug($title, $referenceNo),
                'reference_no' => $referenceNo,
                'listing_type' => $listing->purpose(),
                'rental_period' => $listing->purpose() === 'rent' ? 'yearly' : null,
                'completion_status' => $listing->completionStatus(),
                'property_type' => $this->options->propertyType($listing->type()),
                'category' => $listing->category(),
                'segment' => $segment,
                ...$place['columns'],
                'permit_number' => $permit,
                'bedrooms' => $listing->bedrooms(),
                'bathrooms' => $listing->bathrooms(),
                'sqft' => $listing->size(),
                'price' => $listing->price(),
                'currency' => 'AED',
                'featured' => false,
                // Off the website until Super Admin approves the import (and the permit rules allow).
                'status' => false,
                // With a permit number it only needs validating ("Not verified"); without one, the permit is still to add.
                'compliance_status' => $permit ? Property::COMPLIANCE_PENDING : Property::COMPLIANCE_DRAFT,
                'compliance_submitted_at' => $permit ? now() : null,
                'order_index' => 0,
                'metadata' => [
                    'source' => 'property_finder', 'property_finder_id' => $listing->id(), 'property_finder_reference' => $listing->reference(),
                    'property_finder_review' => 'pending', // cleared when Super Admin approves the import
                ],
            ]);

            PropertyDetail::create([
                'property_id' => $property->id,
                'amenities' => $this->options->amenities($listing->amenities()),
                'easy_access' => [],
                'property_attributes' => [],
                'furnished' => $listing->furnishing() ?? PropertyDetail::UNFURNISHED,
            ]);

            $import->update(['property_id' => $property->id]);
            $connection->increment('imported_count');

            return $property;
        });

        // Photos last, outside the transaction: a slow or broken image never loses the listing.
        $this->images->store($property, $listing->images());

        return true;
    }

    /**
     * Where the listing is: property columns (location option, emirate, permit type, lat / lng), the
     * address / community / city translation text, and the location names (most specific first).
     */
    public function placeFields(PropertyFinderConnection $connection, PropertyFinderListing $listing): array
    {
        $location = $this->location($connection, $listing);
        $names = $location['names'];
        $emirate = $this->emirate($listing->emirate(), $names);
        $isAlAin = collect($names)->contains(fn ($n) => Str::lower($n) === 'al ain');

        $community = count($names) > 1 ? $names[count($names) - 2] : null;

        return [
            'names' => $names,
            'columns' => [
                // The community picked in "Property location" (Master › Property Options) — added to
                // that list when it isn't there yet, so an imported listing never has it empty.
                'location' => $this->options->match('location', $names) ?? $this->options->addLocation($community),
                'emirate' => $emirate,
                'permit_type' => PermitRules::resolve($emirate, 'rera', $isAlAin ? 'al_ain' : 'other'),
                'latitude' => $location['coordinates'][0] ?? null,
                'longitude' => $location['coordinates'][1] ?? null,
            ],
            // Names run building / sub-community → community → city. The address is just the part
            // before the community (pages show "address, community, city").
            'translation' => [
                'address' => count($names) > 2 ? implode(', ', array_slice($names, 0, -2)) : ($names[0] ?? null),
                'community' => count($names) > 1 ? $names[count($names) - 2] : null,
                'city' => $this->cityName($emirate, $names),
            ],
        ];
    }

    /** ['names' => most specific first, 'coordinates' => [lat, lng] | null]. */
    private function location(PropertyFinderConnection $connection, PropertyFinderListing $listing): array
    {
        $inline = is_array($listing->raw['location'] ?? null) ? $listing->raw['location'] : null;
        $names = $listing->inlineLocationNames();
        $coordinates = PropertyFinderListing::coordinatesOf($inline);

        // Listings usually carry just the location id — look it up (cached) for names / coordinates.
        if ((count($names) < 2 || !$coordinates) && ($id = $listing->locationId())) {
            try {
                if ($found = $connection->client()->location($id)) {
                    $names = $names ?: PropertyFinderListing::locationNames($found);
                    if (count($names) < 2) {
                        $names = array_values(array_unique([...$names, ...PropertyFinderListing::locationNames($found)]));
                    }
                    $coordinates ??= PropertyFinderListing::coordinatesOf($found);
                }
            } catch (\Throwable $e) {
                Log::info("Property Finder location {$id} lookup failed: " . $e->getMessage());
            }
        }

        return ['names' => $names, 'coordinates' => $coordinates];
    }

    /** Property Finder's uaeEmirate when sent, else read from the location names. */
    private function emirate(?string $uaeEmirate, array $names): ?string
    {
        if ($uaeEmirate) {
            return match ($uaeEmirate) {
                'dubai' => PermitRules::DUBAI,
                'abu_dhabi', 'al_ain' => PermitRules::ABU_DHABI,
                default => PermitRules::NORTHERN,
            };
        }

        return $this->options->emirateFromNames($names);
    }

    private function cityName(?string $emirate, array $names): ?string
    {
        foreach (array_reverse($names) as $name) {
            if (preg_match('/^(dubai|abu dhabi|al ain|sharjah|ajman|ras al khaimah|umm al quwain|fujairah)$/i', $name)) {
                return $name;
            }
        }

        return match ($emirate) {
            PermitRules::DUBAI => 'Dubai',
            PermitRules::ABU_DHABI => 'Abu Dhabi',
            default => end($names) ?: null,
        };
    }

    /**
     * An agency's listing goes to its agent who is the Property Finder agent (same email, else same
     * name); none → no agent (the agency assigns one). An independent agent's listing is theirs.
     */
    private function agentId(PortalUser $owner, PropertyFinderListing $listing): ?int
    {
        if (!$owner->isAgency()) {
            return $owner->id;
        }
        if ($email = $listing->agentEmail()) {
            $id = $owner->eligibleAgentsQuery()->whereRaw('LOWER(portal_users.email) = ?', [$email])->value('portal_users.id');
            if ($id) {
                return $id;
            }
        }
        $name = $listing->agentName();

        return $name ? $owner->eligibleAgentsQuery()->whereRaw('LOWER(TRIM(portal_users.name)) = ?', [Str::lower(trim($name))])->value('portal_users.id') : null;
    }
}
