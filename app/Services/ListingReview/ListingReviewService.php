<?php

namespace App\Services\ListingReview;

use App\Jobs\ScreenListingPhotos;
use App\Models\ListingReview;
use App\Models\PortalUser;
use App\Models\Property;
use App\Models\PropertyImageHash;
use App\Notifications\ListingReviewNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Listing Review — automated screening of every listing on every save (ListingComplianceService::afterSave).
 *
 *   duplicate  a unit another agency already lists: same location + unit number + sale / rent, or the
 *              same photos (ImageHasher). (The same agency listing a unit twice is refused on save —
 *              PropertyController::assertNotDuplicate.) The OLDEST listing stays on the website;
 *              every newer copy is held off it until Super Admin approves it (both agencies may then
 *              advertise the unit, each with its own permit) or rejects it.
 *   risk       price per sq.ft far off similar listings in the same area · title / type / size / location
 *              that don't add up · advertiser authority (KYC, ORN / BRN, agent not in the agency) — these
 *              leave the listing LIVE with a flag · the ad's price / bedrooms / size differ from the DLD /
 *              ADREC permit record · resubmitted after a rejection — these HOLD it (HOLDING_SIGNALS).
 *
 * Nothing is blocked at submission. A rejected listing stays off the website with the reason; once the
 * agency edits and saves it, it comes back to Listing Review for another check. This only flags for a
 * human — it never claims ownership has been confirmed.
 */
class ListingReviewService
{
    public const PRICE_VARIANCE_PCT = 10;

    private const SIZE_TOLERANCE = 0.03;

    /** Price per sq.ft below 60% or above 200% of similar listings' median is flagged. */
    private const PRICE_LOW_RATIO = 0.6;
    private const PRICE_HIGH_RATIO = 2.0;
    private const MIN_COMPARABLES = 5;

    /* ----------------------------------------------------------------- screening */

    /**
     * $record = count it for the "time saved" measure (a save — not the photo re-check or the one-off
     * `listings:screen` run); $photos = queue the photo fingerprinting when the gallery changed.
     */
    public function screen(Property $property, bool $record = true, bool $photos = true): void
    {
        if ($property->isSold()) {
            return;
        }

        // Rejected, then fixed and saved by the agency: back to Listing Review for another check.
        if (($rejection = $property->reviewRejection()) && Auth::guard('portal')->check()) {
            $metadata = (array) $property->metadata;
            unset($metadata['review_rejection']);
            $property->update(['metadata' => $metadata + ['review_resubmitted' => $rejection['reason'] ?? '']]);
        }

        try {
            DB::transaction(function () use ($property) {
                $this->screenDuplicates($property);
                $this->screenRisks($property);
            });
            $this->syncHold($property->refresh());
            if ($record) {
                DB::table('listing_screenings')->insert(['property_id' => $property->id, 'flagged' => $this->awaitsDecision($property), 'created_at' => now()]);
            }
        } catch (\Throwable $e) {
            // Screening must never break saving a listing.
            Log::error("Listing Review: screening property {$property->id} failed: " . $e->getMessage());
        }

        if ($photos && $property->image_path && ImageHasher::signature($property) !== ($property->metadata['photo_hash_signature'] ?? null)) {
            ScreenListingPhotos::dispatch($property->id)->afterCommit();
        }
    }

    /** What screening would flag, without changing anything (`listings:screen --dry-run`). */
    public function inspect(Property $property): array
    {
        return [
            'duplicates' => $property->isSold() ? [] : $this->duplicateMatches($property),
            'signals' => $property->isSold() ? [] : $this->riskSignals($property),
        ];
    }

    private function screenDuplicates(Property $property): void
    {
        $matches = $this->duplicateMatches($property);
        if (!$matches) {
            return;
        }

        $ids = array_merge(array_keys($matches), [$property->id]);
        $open = ListingReview::open()->where('type', ListingReview::DUPLICATE)
            ->whereHas('properties', fn ($q) => $q->whereIn('properties.id', $ids))
            ->with('properties')->orderBy('id')->get();
        $review = $open->first() ?? ListingReview::create(['type' => ListingReview::DUPLICATE]);

        // The new listing links cases that were separate → one case for the unit.
        foreach ($open->slice(1) as $other) {
            foreach ($other->properties as $member) {
                if (!$this->pivot($review, $member->id)) {
                    $review->properties()->attach($member->id, ['reasons' => $member->pivot->reasons, 'held' => $member->pivot->held, 'outcome' => $member->pivot->outcome, 'note' => $member->pivot->note]);
                }
            }
            $other->delete();
        }

        $refs = Property::whereIn('id', array_keys($matches))->pluck('reference_no', 'id');
        foreach ($matches as $otherId => $reasons) {
            $this->addReasons($review, $otherId, [($property->reference_no ?: "#{$property->id}") . ': ' . implode(', ', $reasons)]);
        }
        $this->addReasons($review, $property->id, collect($matches)->map(fn ($reasons, $id) => ($refs[$id] ?? "#{$id}") . ': ' . implode(', ', $reasons))->values()->all());

        // A rejected listing the agency resubmitted is waiting again.
        if (!$property->reviewRejection()) {
            DB::table('listing_review_property')->where('listing_review_id', $review->id)->where('property_id', $property->id)
                ->where('outcome', 'rejected')->update(['outcome' => null, 'note' => null]);
        }

        // The oldest listing of the unit stays on the website; every newer one still undecided waits.
        $members = $review->properties()->get();
        $original = $members->where('pivot.outcome', '!=', 'rejected')->sortBy(fn ($p) => sprintf('%012d-%012d', $p->created_at?->timestamp ?? 0, $p->id))->first();
        foreach ($members as $member) {
            if ($member->pivot->outcome === null) {
                DB::table('listing_review_property')->where('id', $member->pivot->id)->update(['held' => $member->id !== $original?->id, 'updated_at' => now()]);
            }
        }
        $review->touch();
    }

    /** @return array<int, string[]> other listing id => why it looks like the same unit */
    private function duplicateMatches(Property $property): array
    {
        $photoMatches = $this->photoMatches($property);
        $unit = mb_strtolower(trim((string) $property->details?->unit_number));
        $byUnit = $unit !== '' && $property->location && $property->listing_type;

        $candidates = Property::query()->available()
            ->whereKeyNot($property->id)
            // Another agency's listing (the same agency can't list a unit twice — refused on save).
            ->when($property->portal_user_id === null,
                fn ($q) => $q->whereNotNull('portal_user_id'),
                fn ($q) => $q->where(fn ($o) => $o->whereNull('portal_user_id')->orWhere('portal_user_id', '!=', $property->portal_user_id)))
            ->where(function ($q) use ($property, $photoMatches, $unit, $byUnit) {
                $q->whereIn('id', array_keys($photoMatches) ?: [0]);
                if ($byUnit) {
                    $q->orWhere(fn ($same) => $same
                        ->where('location', $property->location)
                        ->where('listing_type', $property->listing_type)
                        ->whereHas('details', fn ($d) => $d->whereRaw('LOWER(TRIM(unit_number)) = ?', [$unit])));
                }
            })
            ->with('details')
            ->limit(50)
            ->get();

        $approved = $this->approvedWith($property->id, $candidates->pluck('id')->all());
        $matches = [];
        foreach ($candidates as $other) {
            if (in_array($other->id, $approved, true)) {
                continue;
            }
            $reasons = [];
            if ($byUnit && $other->location === $property->location && $other->listing_type === $property->listing_type
                && mb_strtolower(trim((string) $other->details?->unit_number)) === $unit) {
                $reasons[] = 'same unit number (' . trim((string) $property->details->unit_number) . ') in the same location, both for ' . ($property->listing_type === 'rent' ? 'rent' : 'sale');
            }
            if (isset($photoMatches[$other->id])) {
                $reasons[] = $photoMatches[$other->id] . ' matching ' . ($photoMatches[$other->id] === 1 ? 'photo' : 'photos');
            }
            if ($reasons) {
                $matches[$other->id] = $reasons;
            }
        }

        return $matches;
    }

    /** @return array<int, int> other listing id => how many of this listing's photos it also has */
    private function photoMatches(Property $property): array
    {
        $hashes = PropertyImageHash::where('property_id', $property->id)->pluck('hash');
        $counts = [];
        foreach ($hashes as $hash) {
            PropertyImageHash::where('property_id', '!=', $property->id)
                ->whereRaw('BIT_COUNT(hash ^ ?) <= ?', [$hash, ImageHasher::MATCH_BITS])
                ->distinct()->pluck('property_id')
                ->each(function ($id) use (&$counts) {
                    $counts[$id] = ($counts[$id] ?? 0) + 1;
                });
        }

        // Two shared photos (or the only photo) — one stock shot of a building's pool isn't enough.
        return array_filter($counts, fn ($n) => $n >= min(2, $hashes->count()));
    }

    private function screenRisks(Property $property): void
    {
        $signals = $this->riskSignals($property);
        $review = ListingReview::open()->where('type', ListingReview::RISK)
            ->whereHas('properties', fn ($q) => $q->where('properties.id', $property->id))->first();

        if (!$signals) {
            // The agency fixed what was flagged (e.g. corrected the price): nothing left to review.
            if ($review) {
                DB::table('listing_review_property')->where('listing_review_id', $review->id)->update(['outcome' => 'approved', 'held' => false, 'updated_at' => now()]);
                $this->resolve($review, 'auto_cleared', 'Resolved by itself — the listing was changed and nothing is flagged any more.');
            }
            return;
        }
        if ($review) {
            $review->update(['signals' => $signals]);
            DB::table('listing_review_property')->where('listing_review_id', $review->id)->whereNull('outcome')
                ->update(['held' => $this->holds($signals), 'updated_at' => now()]);
            return;
        }
        // Super Admin already approved exactly this — don't flag it again on every save.
        if (($property->metadata['review_cleared_signature'] ?? null) === $this->riskSignature($property, $signals)) {
            return;
        }

        $review = ListingReview::create(['type' => ListingReview::RISK, 'signals' => $signals]);
        $review->properties()->attach($property->id, ['reasons' => json_encode(array_column($signals, 'label')), 'held' => $this->holds($signals)]);
    }

    /**
     * Risk flags that keep the listing off the website until reviewed. The others (price far off the
     * area, details that don't add up, advertiser authority) leave it live with a flag for Super Admin.
     */
    private const HOLDING_SIGNALS = ['permit_mismatch', 'resubmitted'];

    private function holds(array $signals): bool
    {
        return array_intersect(array_column($signals, 'code'), self::HOLDING_SIGNALS) !== [];
    }

    /** @return array<int, array{code: string, label: string, detail: string}> */
    private function riskSignals(Property $property): array
    {
        $signals = [];
        $price = (float) $property->price;
        $currency = $property->currency ?: 'AED';

        // Resubmitted after a rejection — unless it is already waiting in a duplicate case.
        if (array_key_exists('review_resubmitted', (array) $property->metadata) && !$this->pendingIn($property, ListingReview::DUPLICATE)) {
            $reason = trim((string) $property->metadata['review_resubmitted']);
            $signals[] = ['code' => 'resubmitted', 'label' => 'Resubmitted after rejection', 'detail' => 'The agency changed the listing and sent it again.' . ($reason !== '' ? " You rejected it because: {$reason}" : '')];
        }

        if ($price > 0 && $property->sqft > 0 && $property->location) {
            $rates = $this->comparables($property)->map(fn (Property $p) => (float) $p->price / $p->sqft)->sort()->values();

            if ($rates->count() >= self::MIN_COMPARABLES) {
                $median = $rates->median();
                $rate = $price / $property->sqft;
                $ratio = $median > 0 ? $rate / $median : 1;
                if ($ratio < self::PRICE_LOW_RATIO || $ratio > self::PRICE_HIGH_RATIO) {
                    $low = $ratio < 1;
                    $signals[] = [
                        'code' => $low ? 'price_low' : 'price_high',
                        'label' => $low ? 'Price far below similar listings' : 'Price far above similar listings',
                        'detail' => sprintf('%s %s / sq.ft — %d%% %s the median of %s %s / sq.ft across %d similar listings in this area.',
                            $currency, number_format($rate), round(abs(1 - $ratio) * 100), $low ? 'below' : 'above', $currency, number_format($median), $rates->count()),
                    ];
                }
            }
        }

        // The ad must match what the permit was issued for (only known when the permit was verified online).
        $permit = $property->permit_data ?? [];
        $differs = [];
        if (isset($permit['price']) && (float) $permit['price'] > 0 && abs($price - (float) $permit['price']) > (float) $permit['price'] * 0.01) {
            $differs[] = 'price ' . number_format((float) $permit['price']) . ' on the permit vs ' . number_format($price) . ' in the ad';
        }
        if (isset($permit['bedrooms']) && $permit['bedrooms'] !== '' && $property->bedrooms !== null && (string) $permit['bedrooms'] !== (string) $property->bedrooms) {
            $differs[] = "bedrooms {$permit['bedrooms']} vs {$property->bedrooms}";
        }
        if (isset($permit['sqft']) && (float) $permit['sqft'] > 0 && $property->sqft && abs($property->sqft - (float) $permit['sqft']) > (float) $permit['sqft'] * self::SIZE_TOLERANCE) {
            $differs[] = 'size ' . number_format((float) $permit['sqft']) . ' vs ' . number_format($property->sqft) . ' sq.ft';
        }
        if ($differs) {
            $signals[] = ['code' => 'permit_mismatch', 'label' => 'Ad differs from the permit record', 'detail' => ucfirst(implode('; ', $differs)) . '.'];
        }

        if ($issues = $this->consistencyIssues($property)) {
            $signals[] = ['code' => 'inconsistent', 'label' => "Listing details don't add up", 'detail' => implode(' ', $issues)];
        }
        if ($issues = $this->advertiserIssues($property)) {
            $signals[] = ['code' => 'advertiser', 'label' => 'Advertiser authority not confirmed', 'detail' => implode(' ', $issues)];
        }

        return $signals;
    }

    /** Smallest believable size (sq.ft) per property type — below it the size or the type is likely wrong. */
    private const MIN_SQFT = ['villa' => 1000, 'townhouse' => 900, 'penthouse' => 800, 'apartment' => 250, 'condo' => 250, 'warehouse' => 500, 'office' => 100];

    /** A type word in the title → the property types it fits. */
    private const TITLE_TYPES = [
        'villa' => ['villa'],
        'townhouse' => ['townhouse', 'villa'],
        'penthouse' => ['penthouse', 'apartment', 'condo'],
        'apartment' => ['apartment', 'condo', 'penthouse', 'residential'],
        'flat' => ['apartment', 'condo', 'penthouse', 'residential'],
        'warehouse' => ['warehouse'],
        'office' => ['office', 'co-working'],
    ];

    /** Does the listing agree with itself? Title vs bedrooms / type / location, and size vs type. */
    private function consistencyIssues(Property $property): array
    {
        $title = (string) $property->getTranslation('title', config('app.fallback_locale', 'en'));
        $issues = [];
        $beds = $property->bedrooms;

        if (preg_match('/\b(\d{1,2})\s*-?\s*(?:br|b\/r|bhk|beds?|bedrooms?)\b/i', $title, $m) && is_numeric($beds) && (int) $m[1] !== (int) $beds) {
            $issues[] = "The title says {$m[1]} bedrooms, the listing says {$beds}.";
        } elseif (preg_match('/\bstudio\b/i', $title) && is_numeric($beds) && (int) $beds > 0) {
            $issues[] = "The title says studio, the listing says {$beds} bedrooms.";
        }

        foreach (self::TITLE_TYPES as $word => $fits) {
            if (preg_match('/\b' . $word . 's?\b/i', $title)) {
                if ($property->property_type && in_array($property->property_type, array_merge(...array_values(self::TITLE_TYPES)), true) && !in_array($property->property_type, $fits, true)) {
                    $issues[] = "The title says {$word}, the property type is " . ($property->filterLabel('property_type') ?: $property->property_type) . '.';
                }
                break;
            }
        }

        $min = self::MIN_SQFT[$property->property_type] ?? null;
        if ($min && $property->sqft > 0 && $property->sqft < $min) {
            $issues[] = 'A ' . strtolower($property->filterLabel('property_type') ?: $property->property_type) . ' of ' . number_format($property->sqft) . " sq.ft is unusually small (under {$min}) — check the size or the type.";
        }

        // The title names a different community than the listing's location ("… in Dubai Marina" for a JVC listing).
        $labels = $this->locationLabels();
        $own = $labels[$property->location] ?? null;
        if ($own && !preg_match('/\b' . preg_quote($own, '/') . '\b/i', $title)) {
            foreach ($labels as $value => $label) {
                if ($value !== $property->location && preg_match('/\b(?:in|at)\s+' . preg_quote($label, '/') . '\b/i', $title)) {
                    $issues[] = "The title says {$label}, the location is {$own}.";
                    break;
                }
            }
        }

        return $issues;
    }

    /** location value => English label (Filter "location"), longest first so "Dubai Marina" wins over "Dubai". */
    private function locationLabels(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('listing-review:location-labels', 3600, fn () => \App\Models\FilterValue::query()
            ->whereHas('filter', fn ($q) => $q->where('key', 'location'))
            ->get()
            ->mapWithKeys(fn ($v) => [$v->value => $v->getTranslation('label', 'en') ?: \Illuminate\Support\Str::headline($v->value)])
            ->sortByDesc(fn ($label) => mb_strlen($label))
            ->all());
    }

    /**
     * Who advertises it: an approved (KYC) account, with its RERA numbers for a Dubai listing, and an
     * agent who really belongs to the agency. MW Realty's own listings are skipped.
     */
    private function advertiserIssues(Property $property): array
    {
        $owner = $property->owner;
        if (!$owner) {
            return [];
        }
        $issues = [];
        $dubai = $property->emirate === 'dubai';

        if (!$owner->isApproved()) {
            $issues[] = "{$owner->displayName()}'s account isn't approved (KYC).";
        }
        if ($dubai && $owner->isAgency() && !$owner->orn_number) {
            $issues[] = "{$owner->displayName()} has no agency ORN (RERA office number) on its account.";
        } elseif ($dubai && !$owner->isAgency() && !$owner->brn_number) {
            $issues[] = "{$owner->displayName()} has no BRN (RERA broker number) on the account.";
        }

        $agent = $property->agent_id && $property->agent_id !== $owner->id ? $property->agent : null;
        if ($agent) {
            if ($dubai && !$agent->brn_number) {
                $issues[] = "The agent {$agent->name} has no BRN (RERA broker number).";
            }
            // The agent's brokerage (company_id — PortalUser::listingOwner) or an active My Agency membership.
            $member = $agent->company_id === $owner->id
                || \App\Models\AgencyAgent::where('agency_id', $owner->id)->where('agent_id', $agent->id)->whereIn('status', \App\Models\AgencyAgent::MEMBER_STATUSES)->exists();
            if ($owner->isAgency() && !$member) {
                $issues[] = "The agent {$agent->name} isn't an active member of {$owner->displayName()}.";
            }
        }

        return $issues;
    }

    /** Live listings the price is compared with: same purpose, type and location, with a size and price. */
    private function comparables(Property $property)
    {
        return Property::query()->available()
            ->where('status', true)
            ->whereKeyNot($property->id)
            ->where('listing_type', $property->listing_type)
            ->where('property_type', $property->property_type)
            ->where('location', $property->location)
            ->where('sqft', '>', 0)->where('price', '>', 0)
            ->limit(500)
            ->get();
    }

    /**
     * The price check shown on a risk case, so Super Admin can verify it by hand: the similar listings
     * it was compared with (cheapest per sq.ft first), their median, and where this listing sits.
     */
    public function priceComparison(Property $property): ?array
    {
        if ((float) $property->price <= 0 || $property->sqft <= 0 || !$property->location) {
            return null;
        }
        $comparables = $this->comparables($property)->load('owner')
            ->sortBy(fn (Property $p) => (float) $p->price / $p->sqft)->values();
        if ($comparables->count() < self::MIN_COMPARABLES) {
            return null;
        }
        $currency = $property->currency ?: 'AED';
        $median = $comparables->map(fn (Property $p) => (float) $p->price / $p->sqft)->median();
        $rate = (float) $property->price / $property->sqft;
        $row = fn (Property $p) => [
            'id' => $p->id,
            'segment' => $p->segment,
            'reference_no' => $p->reference_no,
            'title' => $p->getTranslation('title') ?: 'Property',
            'owner' => $p->owner?->displayName() ?? 'MW Realty',
            'size' => number_format($p->sqft) . ' sq.ft',
            'bedrooms' => $p->bedrooms,
            'price' => $currency . ' ' . number_format((float) $p->price),
            'rate_value' => round((float) $p->price / $p->sqft),
            'rate' => $currency . ' ' . number_format((float) $p->price / $p->sqft),
        ];

        return [
            'type' => $property->filterLabel('property_type') ?: $property->property_type,
            'purpose' => $property->listing_type === 'rent' ? 'rent' : 'sale',
            'location' => $property->getTranslation('community') ?: $property->location,
            'median' => $currency . ' ' . number_format($median),
            'median_value' => round($median),
            'rate' => $currency . ' ' . number_format($rate),
            'percent' => (int) round(($rate / $median - 1) * 100),
            'low' => $currency . ' ' . number_format($median * self::PRICE_LOW_RATIO),
            'high' => $currency . ' ' . number_format($median * self::PRICE_HIGH_RATIO),
            'self' => $row($property),
            'listings' => $comparables->map($row)->values(),
        ];
    }

    private function riskSignature(Property $property, array $signals): string
    {
        // Price flags by code (their numbers move with the market); the others by what they say, so a
        // different inconsistency / advertiser problem is flagged again after an approval.
        $parts = collect($signals)->reject(fn ($s) => $s['code'] === 'resubmitted')
            ->map(fn ($s) => str_starts_with($s['code'], 'price_') ? $s['code'] : $s['code'] . ':' . $s['detail']);

        return md5($parts->implode(',') . '|' . (float) $property->price . '|' . (int) $property->sqft . '|' . $property->bedrooms);
    }

    /* ----------------------------------------------------------------- Super Admin actions */

    /**
     * Approve one listing: it may be on the website. In a duplicate case both agencies then advertise
     * the unit (each with its own permit) and the pair is never flagged again.
     */
    public function approve(ListingReview $review, int $propertyId, ?string $note = null): void
    {
        $property = $this->undecided($review, $propertyId);

        DB::transaction(function () use ($review, $property, $note) {
            if ($review->type === ListingReview::DUPLICATE) {
                foreach ($review->properties()->get()->where('pivot.outcome', '!=', 'rejected') as $other) {
                    $this->rememberApproved($property->id, $other->id);
                }
            }
            $metadata = (array) $property->metadata;
            unset($metadata['review_resubmitted']);
            if ($review->type === ListingReview::RISK) {
                $metadata['review_cleared_signature'] = $this->riskSignature($property, $review->signals ?? []);
            }
            $property->update(['metadata' => $metadata]);
            $this->setOutcome($review, $property->id, 'approved', $note);
            $this->settle($review);
        });

        $this->syncHold($property->refresh());
    }

    /** Reject one listing: off the website with the reason, until the agency fixes it and saves it again. */
    public function reject(ListingReview $review, int $propertyId, string $reason): void
    {
        $property = $this->undecided($review, $propertyId);

        DB::transaction(function () use ($review, $property, $reason) {
            $metadata = (array) $property->metadata;
            unset($metadata['review_resubmitted']);
            $property->update(['metadata' => $metadata + ['review_rejection' => ['reason' => $reason, 'at' => now()->toIso8601String()]]]);
            $this->setOutcome($review, $property->id, 'rejected', $reason);
            $this->settle($review);
        });

        $this->syncHold($property->refresh(), quiet: true);
        $this->notify($property, 'rejected', $reason);
    }

    /** Approves the listing in every open case it waits in — Super Admin approving it on Listing Permits. */
    public function approveEverywhere(Property $property, ?string $note = null): int
    {
        $cases = $this->openCasesFor([$property->id])->get($property->id, collect())->where('waiting', true);
        foreach ($cases as $case) {
            $this->approve(ListingReview::findOrFail($case['id']), $property->id, $note);
        }

        return $cases->count();
    }

    /**
     * Open cases for these listings (Listing Permits shows them next to the permit):
     * property id => [{id, type, waiting, label, signals, others: [{reference_no, owner, price, live, first}]}].
     */
    public function openCasesFor(array $propertyIds)
    {
        if (!$propertyIds) {
            return collect();
        }
        $cases = ListingReview::open()
            ->whereHas('properties', fn ($q) => $q->whereIn('properties.id', $propertyIds))
            ->with(['properties' => fn ($q) => $q->with('owner')->orderBy('properties.created_at')->orderBy('properties.id')])
            ->get();

        $byProperty = collect();
        foreach ($cases as $case) {
            $first = $case->type === ListingReview::DUPLICATE ? $case->properties->first(fn ($p) => $p->pivot->outcome === null && !$p->pivot->held) : null;
            foreach ($case->properties->whereIn('id', $propertyIds) as $member) {
                $others = $case->properties->where('id', '!=', $member->id)->where('pivot.outcome', '!=', 'rejected');
                $byProperty[$member->id] = ($byProperty[$member->id] ?? collect())->push([
                    'id' => $case->id,
                    'type' => $case->type,
                    'waiting' => $member->pivot->outcome === null && ($member->pivot->held || $case->type === ListingReview::RISK),
                    'held' => $member->pivot->outcome === null && (bool) $member->pivot->held,
                    'label' => $case->type === ListingReview::DUPLICATE
                        ? 'Same unit as ' . $others->pluck('reference_no')->join(', ')
                        : collect($case->signals ?? [])->pluck('label')->join(' · '),
                    'signals' => $case->signals ?? [],
                    'others' => $others->map(fn (Property $p) => [
                        'reference_no' => $p->reference_no,
                        'owner' => $p->owner?->displayName() ?? 'MW Realty',
                        'price' => ($p->currency ?: 'AED') . ' ' . number_format((float) $p->price),
                        'live' => (bool) $p->status,
                        'first' => $first?->id === $p->id,
                    ])->values(),
                ]);
            }
        }

        return $byProperty;
    }

    /* ----------------------------------------------------------------- measures */

    public function metrics(): array
    {
        $available = Property::query()->available()->count();
        $waiting = DB::table('listing_review_property')
            ->join('listing_reviews', 'listing_reviews.id', '=', 'listing_review_property.listing_review_id')
            ->where('listing_reviews.type', ListingReview::DUPLICATE)
            ->where('listing_reviews.status', ListingReview::OPEN)
            ->distinct()->count('listing_review_property.property_id');
        $resolved = ListingReview::where('status', ListingReview::RESOLVED)->where('resolution', '!=', 'auto_cleared')
            ->where('resolved_at', '>=', now()->subDays(90))->get(['created_at', 'resolved_at']);

        // Time saved: every listing screened on save that didn't need a person, at the manual-check time.
        $minutes = max(0, (int) config('listing_review.minutes_per_check', 10));
        $screened = DB::table('listing_screenings')->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('COUNT(*) as total, SUM(flagged) as flagged')->first();
        $cleared = (int) $screened->total - (int) $screened->flagged;

        return [
            'screened_30d' => (int) $screened->total,
            'screened_clear_30d' => $cleared,
            'minutes_per_check' => $minutes,
            'hours_saved_30d' => round($cleared * $minutes / 60, 1),
            'duplicate_rate' => $available ? round($waiting * 100 / $available, 1) : 0,
            'duplicate_listings' => $waiting,
            'suspicious_30d' => ListingReview::where('created_at', '>=', now()->subDays(30))->count(),
            'verification_rate' => $available ? round(Property::query()->available()->where('compliance_status', Property::COMPLIANCE_APPROVED)->count() * 100 / $available, 1) : 0,
            'avg_review_hours' => $resolved->count() ? round($resolved->avg(fn ($r) => $r->created_at->diffInMinutes($r->resolved_at)) / 60, 1) : null,
            'resolved_90d' => $resolved->count(),
            'auto_resolved_90d' => ListingReview::where('resolution', 'auto_cleared')->where('resolved_at', '>=', now()->subDays(90))->count(),
        ];
    }

    /* ----------------------------------------------------------------- helpers */

    /** Off the website while waiting in an open case or rejected; back to how it was once neither. */
    public function syncHold(Property $property, bool $quiet = false): void
    {
        $held = $property->reviewRejection() !== null || $this->pendingIn($property);
        if ($held === (bool) $property->review_hold) {
            return;
        }

        $metadata = (array) $property->metadata;
        if ($held) {
            $property->update(['review_hold' => true, 'status' => false, 'metadata' => $metadata + ['status_before_review' => (bool) $property->status]]);
            if (!$quiet) {
                $this->notify($property, 'held');
            }
            return;
        }

        $wasOn = $metadata['status_before_review'] ?? true;
        unset($metadata['status_before_review']);
        $property->update(['review_hold' => false, 'metadata' => $metadata]);
        $property->update(['status' => $wasOn && $property->canGoLive()]);
        if (!$quiet) {
            $this->notify($property, 'approved');
        }
    }

    /** Needs Super Admin's decision in some open case — held off the website or only flagged (risk). */
    private function awaitsDecision(Property $property): bool
    {
        return DB::table('listing_review_property')
            ->join('listing_reviews', 'listing_reviews.id', '=', 'listing_review_property.listing_review_id')
            ->where('listing_review_property.property_id', $property->id)
            ->where('listing_reviews.status', ListingReview::OPEN)
            ->whereNull('listing_review_property.outcome')
            ->where(fn ($q) => $q->where('listing_review_property.held', true)->orWhere('listing_reviews.type', ListingReview::RISK))
            ->exists();
    }

    /** Held off the website by an open case (of $type, or any). */
    private function pendingIn(Property $property, ?string $type = null): bool
    {
        return DB::table('listing_review_property')
            ->join('listing_reviews', 'listing_reviews.id', '=', 'listing_review_property.listing_review_id')
            ->where('listing_review_property.property_id', $property->id)
            ->where('listing_reviews.status', ListingReview::OPEN)
            ->when($type, fn ($q) => $q->where('listing_reviews.type', $type))
            ->whereNull('listing_review_property.outcome')
            ->where('listing_review_property.held', true)
            ->exists();
    }

    private function undecided(ListingReview $review, int $propertyId): Property
    {
        $property = $review->properties()->wherePivotNull('outcome')->find($propertyId);
        abort_unless($review->status === ListingReview::OPEN && $property, 422, 'This listing has already been decided.');

        return $property;
    }

    /** Closed once no listing in it is waiting any more. */
    private function settle(ListingReview $review): void
    {
        // A risk case's listing waits for a decision even while it stays live (a flag that doesn't hold).
        $query = $review->properties()->wherePivotNull('outcome');
        if ($review->type === ListingReview::DUPLICATE) {
            $query->wherePivot('held', true);
        }
        $waiting = $query->exists();
        if (!$waiting) {
            $rejected = $review->properties()->wherePivot('outcome', 'rejected')->exists();
            $this->resolve($review, $rejected ? 'rejected' : 'approved', null);
        }
    }

    private function resolve(ListingReview $review, string $resolution, ?string $note): void
    {
        $review->update([
            'status' => ListingReview::RESOLVED,
            'resolution' => $resolution,
            'resolution_note' => $note,
            'resolved_by' => $resolution === 'auto_cleared' ? null : Auth::guard('cms')->id(),
            'resolved_at' => now(),
        ]);
    }

    private function setOutcome(ListingReview $review, int $propertyId, string $outcome, ?string $note): void
    {
        DB::table('listing_review_property')->where('listing_review_id', $review->id)->where('property_id', $propertyId)
            ->update(['outcome' => $outcome, 'note' => $note, 'held' => false, 'updated_at' => now()]);
    }

    private function pivot(ListingReview $review, int $propertyId): ?object
    {
        return DB::table('listing_review_property')->where('listing_review_id', $review->id)->where('property_id', $propertyId)->first();
    }

    /** Adds to a member's "why it matched" lines (attaching it to the case first if needed). */
    private function addReasons(ListingReview $review, int $propertyId, array $reasons): void
    {
        $row = $this->pivot($review, $propertyId);
        if (!$row) {
            $review->properties()->attach($propertyId, ['reasons' => json_encode($reasons), 'held' => false]);
            return;
        }
        $merged = collect(json_decode((string) $row->reasons, true) ?: [])
            // One line per other listing: a re-save replaces its own earlier line.
            ->reject(fn ($line) => collect($reasons)->contains(fn ($new) => strtok($new, ':') === strtok($line, ':')))
            ->merge($reasons)->values()->all();
        DB::table('listing_review_property')->where('id', $row->id)->update(['reasons' => json_encode($merged), 'updated_at' => now()]);
    }

    private function rememberApproved(int $a, int $b): void
    {
        if ($a && $b && $a !== $b) {
            DB::table('listing_distinct_pairs')->insertOrIgnore(['low_id' => min($a, $b), 'high_id' => max($a, $b), 'created_at' => now()]);
        }
    }

    /** Of $ids, those Super Admin already approved side by side with $id. */
    private function approvedWith(int $id, array $ids): array
    {
        if (!$ids) {
            return [];
        }

        return DB::table('listing_distinct_pairs')
            ->where(fn ($q) => $q->where('low_id', $id)->whereIn('high_id', $ids))
            ->orWhere(fn ($q) => $q->where('high_id', $id)->whereIn('low_id', $ids))
            ->get()
            ->map(fn ($pair) => (int) ((int) $pair->low_id === $id ? $pair->high_id : $pair->low_id))
            ->all();
    }

    private function notify(Property $property, string $event, ?string $reason = null): void
    {
        try {
            PortalUser::whereIn('id', array_filter([$property->portal_user_id, $property->agent_id]))->get()
                ->each(fn (PortalUser $user) => $user->notify(new ListingReviewNotification($property, $event, $reason)));
        } catch (\Throwable $e) {
            Log::error('Failed to send listing review bell: ' . $e->getMessage());
        }
    }
}
