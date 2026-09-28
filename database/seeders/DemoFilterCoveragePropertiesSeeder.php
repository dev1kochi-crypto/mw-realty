<?php

namespace Database\Seeders;

use App\Models\AgencyAgent;
use App\Models\CmsKit\CommunityHighlight;
use App\Models\PortalUser;
use App\Models\Property;
use App\Models\PropertyDetail;
use App\Models\PropertyFloorPlan;
use App\Services\Agency\AgencyMembershipService;
use App\Services\Agency\AssignmentActor;
use App\Support\LocationFilter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Local demo data that makes every listing-page filter return results.
 *
 * Every approved agency gets 50 listings (40 residential + 10 commercial), spread round-robin over
 * its approved agents (plus some unassigned); every approved independent agent gets 50 of their own.
 * Within each set of 40 residential listings, every Buy/Rent × property type × bedrooms (1-5+)
 * combination exists once; completion status, location, bathrooms, price, sqft, amenities,
 * furnished and parking rotate across sets so those filters (and their combinations) match too.
 *
 * Idempotent: listings are keyed by slug, so re-running only adds what is missing.
 * Photos are hard links to one shared set of demo images (no extra disk space per listing).
 *
 *   php artisan db:seed --class=DemoFilterCoveragePropertiesSeeder
 */
class DemoFilterCoveragePropertiesSeeder extends Seeder
{
    private const PER_OWNER_RESIDENTIAL = 40;
    private const PER_OWNER_COMMERCIAL = 10;

    private const RESIDENTIAL_TYPES = ['apartment', 'villa', 'townhouse', 'penthouse'];
    private const COMMERCIAL_TYPES = ['office', 'retail-shop', 'warehouse', 'showroom', 'co-working', 'whole-building'];

    /** Dubai communities — `location` is the admin Location filter's value slug. */
    private const DUBAI = [
        ['community' => 'Downtown Dubai', 'location' => 'downtown-dubai', 'lat' => 25.1972, 'lng' => 55.2744],
        ['community' => 'Dubai Marina', 'location' => 'dubai-marina', 'lat' => 25.0805, 'lng' => 55.1403],
        ['community' => 'Business Bay', 'location' => 'business-bay', 'lat' => 25.1851, 'lng' => 55.2651],
        ['community' => 'Palm Jumeirah', 'location' => 'palm-jumeirah', 'lat' => 25.1124, 'lng' => 55.1390],
        ['community' => 'Jumeirah Village Circle', 'location' => 'jumeirah-village-circle', 'lat' => 25.0587, 'lng' => 55.2066],
        ['community' => 'Dubai Hills Estate', 'location' => 'dubai-hills-estate', 'lat' => 25.1106, 'lng' => 55.2463],
        ['community' => 'Arabian Ranches', 'location' => 'arabian-ranches', 'lat' => 25.0551, 'lng' => 55.2711],
        ['community' => 'Al Furjan', 'location' => 'al-furjan', 'lat' => 25.0257, 'lng' => 55.1463],
        ['community' => 'Jumeirah Lake Towers', 'location' => 'jumeirah-lake-towers', 'lat' => 25.0693, 'lng' => 55.1413],
    ];

    /** The other emirates (Home page city tabs). */
    private const OTHER_CITIES = [
        ['city' => 'Abu Dhabi', 'areas' => ['Al Reem Island', 'Yas Island', 'Saadiyat Island', 'Al Raha Beach'], 'lat' => 24.4539, 'lng' => 54.3773],
        ['city' => 'Sharjah', 'areas' => ['Al Majaz', 'Muwaileh', 'Al Khan', 'Aljada'], 'lat' => 25.3463, 'lng' => 55.4209],
        ['city' => 'Ajman', 'areas' => ['Ajman Corniche', 'Al Rashidiya', 'Al Zorah', 'Emirates City'], 'lat' => 25.4052, 'lng' => 55.5136],
        ['city' => 'Ras Al Khaimah', 'areas' => ['Al Marjan Island', 'Mina Al Arab', 'Al Hamra Village', 'Al Nakheel'], 'lat' => 25.7895, 'lng' => 55.9432],
        ['city' => 'Umm Al Quwain', 'areas' => ['Al Salamah', 'Al Raas', 'Old Town', 'Al Humrah'], 'lat' => 25.5647, 'lng' => 55.5533],
    ];

    /** Base price per type: [sale at 1 bed, sale per extra bed, yearly rent at 1 bed, rent per extra bed]. */
    private const PRICING = [
        'apartment' => [700000, 550000, 45000, 35000],
        'townhouse' => [1400000, 600000, 110000, 45000],
        'villa' => [2200000, 1100000, 160000, 80000],
        'penthouse' => [3500000, 2000000, 250000, 150000],
    ];

    /** Sqft per type: [at 1 bed, per extra bed]. */
    private const SIZES = [
        'apartment' => [650, 450],
        'townhouse' => [1500, 500],
        'villa' => [2400, 900],
        'penthouse' => [2800, 1100],
    ];

    /** Rotated per owner so each type/bed/listing combination gets a wide spread of prices. */
    private const PRICE_FACTORS = [0.6, 0.85, 1.0, 1.25, 1.6, 2.2, 0.75, 1.1, 1.4, 1.9, 0.95, 2.6];

    /** Amenity label sets — the listing filter matches these labels (Pool, Gym, Security, ...). */
    private const AMENITY_SETS = [
        ['Community Pool', 'Gym', '24/7 Security', 'Balcony'],
        ['Private Pool', 'Concierge', 'Security', 'Covered Parking'],
        ['Gym', 'Balcony', 'Near Metro', 'Children\'s Play Area'],
        ['Community Pool', 'Concierge', 'Private Garden', 'Security'],
        ['Private Pool', 'Gym', 'Balcony', 'Near Metro', 'Concierge'],
        ['Security', 'Community Pool', 'BBQ Area'],
    ];
    private const COMMERCIAL_AMENITY_SETS = [
        ['Pantry', 'Meeting Rooms', 'Central A/C', 'Near Metro'],
        ['Loading Bay', 'Central A/C', '24/7 Security'],
        ['Meeting Rooms', 'Pantry', 'Reception', 'High-Speed Internet'],
        ['Central A/C', 'Near Metro', 'Loading Bay', 'Security'],
    ];

    private const ADJECTIVES = ['Modern', 'Spacious', 'Luxury', 'Elegant', 'Bright', 'Premium', 'Family', 'Stylish'];
    private const VIEWS = ['Sea View', 'Skyline View', 'Community View', 'Garden View', 'Pool View', 'Golf Course View'];

    private const DEMO_IMAGES = [
        'frontend/assets/images/home/project-card-1.jpg',
        'frontend/assets/images/home/project-card-2.jpg',
        'frontend/assets/images/home/project-card-3.jpg',
        'frontend/assets/images/home/project-card-4.jpg',
        'frontend/assets/images/home/project-card-5.jpg',
        'frontend/assets/images/home/project-card-6.jpg',
        'frontend/assets/images/home/luxury-card-1.jpg',
        'frontend/assets/images/home/luxury-card-2.jpg',
        'frontend/assets/images/home/luxury-card-3.jpg',
        'frontend/assets/images/home/realty-card-1.jpg',
        'frontend/assets/images/home/realty-card-2.jpg',
        'frontend/assets/images/home/realty-card-3.jpg',
        'frontend/assets/images/home/realty-card-4.jpg',
    ];
    private const FLOOR_PLAN_IMAGE = 'frontend/assets/images/property-details/floorplan.png';
    private const MASTER_FOLDER = 'properties/_demo-masters';

    private int $nextReference;
    private int $nextOrder;
    private array $masters = [];
    private int $created = 0;

    public function run(): void
    {
        if (!app()->environment('local')) {
            throw new \RuntimeException('DemoFilterCoveragePropertiesSeeder is restricted to the local environment.');
        }

        $this->prepareMasterImages();
        $this->nextReference = (int) Property::where('reference_no', 'like', 'PROP%')
            ->pluck('reference_no')->map(fn ($ref) => (int) substr($ref, 4))->max() + 1;
        $this->nextOrder = (int) Property::max('order_index') + 1;

        $agencies = PortalUser::where('type', 'company')->where('status', 'approved')->orderBy('id')->get();
        $independentAgents = PortalUser::where('type', 'agent')->where('status', 'approved')
            ->whereNull('company_id')->orderBy('id')->get();

        $set = 0;
        foreach ($agencies as $agency) {
            $agentIds = AgencyAgent::where('agency_id', $agency->id)->where('status', AgencyAgent::APPROVED)
                ->pluck('agent_id')->all();
            // null = listing kept on the agency itself, not assigned to an agent.
            $this->seedOwner($agency, 'agency', array_merge($agentIds, [null]), $set++);
        }
        foreach ($independentAgents as $agent) {
            $this->seedOwner($agent, 'agent', [$agent->id], $set++);
        }
        $this->seedHomeCommunities($agencies);

        $this->command?->info("Demo listings created: {$this->created} (for {$agencies->count()} agencies, {$independentAgents->count()} independent agents).");
    }

    /** @param  array<int, int|null>  $agentPool  agent ids to assign round-robin (null = unassigned) */
    private function seedOwner(PortalUser $owner, string $ownerType, array $agentPool, int $set): void
    {
        DB::transaction(function () use ($owner, $ownerType, $agentPool, $set) {
            for ($i = 0; $i < self::PER_OWNER_RESIDENTIAL; $i++) {
                $this->createListing($owner, $ownerType, $agentPool[$i % count($agentPool)], $set, $i, $this->residentialSpec($set, $i));
            }
            for ($i = 0; $i < self::PER_OWNER_COMMERCIAL; $i++) {
                $n = self::PER_OWNER_RESIDENTIAL + $i;
                $this->createListing($owner, $ownerType, $agentPool[$n % count($agentPool)], $set, $n, $this->commercialSpec($set, $i));
            }
        });
    }

    /**
     * The Home page's "Popular Properties in Dubai Communities" rows link to the listing page per
     * community + tab (For Sale / For Rent / Off Plan). Every community the admin lists there that
     * has no listing yet gets COMMUNITY_LISTINGS of them — a third of each kind — spread over the
     * agencies and their agents.
     */
    private const COMMUNITY_LISTINGS = 9;

    private function seedHomeCommunities($agencies): void
    {
        if ($agencies->isEmpty()) {
            return;
        }
        $communities = CommunityHighlight::where('status', true)->get()
            ->map(fn ($item) => trim((string) $item->getTranslation('title', 'en')))->filter()->unique()->values();

        foreach ($communities as $ci => $community) {
            // Same free-text match the listing page uses for ?location= (also keeps re-runs idempotent).
            if (LocationFilter::apply(Property::where('status', true)->residential(), null, null, $community)->exists()) {
                continue;
            }

            DB::transaction(function () use ($agencies, $community, $ci) {
                for ($k = 0; $k < self::COMMUNITY_LISTINGS; $k++) {
                    $agency = $agencies[($ci + $k) % $agencies->count()];
                    $agentIds = AgencyAgent::where('agency_id', $agency->id)->where('status', AgencyAgent::APPROVED)
                        ->orderBy('agent_id')->pluck('agent_id')->all();
                    $pool = array_merge($agentIds, [null]);
                    $kind = $k % 3; // 0 = for sale (ready), 1 = for rent, 2 = off-plan (for sale)
                    $i = 2 * (($k * 7 + $ci * 3) % 20) + ($kind === 1 ? 1 : 0);

                    $spec = $this->residentialSpec($ci, $i);
                    $spec['completion'] = $kind === 2 ? 'off_plan' : 'ready';
                    $spec['place'] = ['city' => 'Dubai', 'community' => $community, 'location' => null, 'lat' => 25.12, 'lng' => 55.22];

                    // n ≥ 100 keeps these slugs apart from the per-owner 0..49 set.
                    $n = 100 + $ci * 10 + $k;
                    $this->createListing($agency, 'agency', $pool[$k % count($pool)], $ci, $n, $spec);
                }
            });
        }
    }

    private function residentialSpec(int $set, int $i): array
    {
        // i = 0..39 → every listing × type × bedrooms combination exactly once per owner.
        $listing = $i % 2 === 0 ? 'sale' : 'rent';
        $type = self::RESIDENTIAL_TYPES[intdiv($i, 2) % 4];
        $bedIndex = intdiv($i, 8) % 5; // 0..4 → 1, 2, 3, 4, 5+
        $beds = $bedIndex === 4 ? 5 + (($set + $i) % 2) : $bedIndex + 1;
        $baths = max(1, min(6, $beds + [0, 1, -1][($set + $i) % 3]));

        [$saleBase, $salePerBed, $rentBase, $rentPerBed] = self::PRICING[$type];
        $factor = self::PRICE_FACTORS[($set + $i) % count(self::PRICE_FACTORS)];
        $price = $listing === 'sale' ? ($saleBase + $salePerBed * ($beds - 1)) : ($rentBase + $rentPerBed * ($beds - 1));
        $price = min(30000000, max(20000, (int) round($price * $factor / 5000) * 5000));

        [$sizeBase, $sizePerBed] = self::SIZES[$type];
        $sqft = (int) round(($sizeBase + $sizePerBed * ($beds - 1)) * (0.85 + (($set * 7 + $i) % 7) * 0.05));

        return [
            'segment' => Property::SEGMENT_RESIDENTIAL,
            'category' => 'residential',
            'listing' => $listing,
            'type' => $type,
            'beds' => $beds,
            'baths' => $baths,
            'price' => $price,
            'sqft' => $sqft,
            // ~1 in 3 off-plan, shifting per owner so each combination gets both states.
            'completion' => ($set + intdiv($i, 2)) % 3 === 0 ? 'off_plan' : 'ready',
            'place' => $this->place($set, $i, $i % 5 === 4),
            'amenities' => self::AMENITY_SETS[($set + $i) % count(self::AMENITY_SETS)],
            // Not tied to the amenity rotation's parity, so e.g. Gym + Furnished also occurs.
            'furnished' => intdiv($set + $i, 2) % 2 === 1,
            'parking' => ($set + $i) % 4 === 0 ? 0 : 1 + ($beds > 3 ? 1 : 0),
        ];
    }

    private function commercialSpec(int $set, int $i): array
    {
        $type = self::COMMERCIAL_TYPES[($set + $i) % count(self::COMMERCIAL_TYPES)];
        $listing = ($set + $i) % 2 === 0 ? 'sale' : 'rent';
        $sqft = [800, 1500, 3000, 6000, 12000, 20000][($set * 3 + $i) % 6];
        $price = $listing === 'sale' ? $sqft * [1400, 1800, 2400][($set + $i) % 3] : $sqft * [90, 130, 180][($set + $i) % 3];

        return [
            'segment' => Property::SEGMENT_COMMERCIAL,
            'category' => 'commercial',
            'listing' => $listing,
            'type' => $type,
            'beds' => null,
            'baths' => 1 + ($i % 3),
            'price' => min(30000000, (int) round($price / 5000) * 5000),
            'sqft' => $sqft,
            'completion' => ($set + $i) % 4 === 0 ? 'off_plan' : 'ready',
            'place' => $this->place($set, $i + 3, $i % 4 === 3),
            'amenities' => self::COMMERCIAL_AMENITY_SETS[($set + $i) % count(self::COMMERCIAL_AMENITY_SETS)],
            'furnished' => $i % 2 === 0,
            'parking' => 1 + ($i % 5),
        ];
    }

    /** Dubai community (with Location filter slug) or another emirate. */
    private function place(int $set, int $i, bool $otherEmirate): array
    {
        if ($otherEmirate) {
            $city = self::OTHER_CITIES[($set + intdiv($i, 5)) % count(self::OTHER_CITIES)];
            $area = $city['areas'][($set + $i) % count($city['areas'])];

            return ['city' => $city['city'], 'community' => $area, 'location' => null, 'lat' => $city['lat'], 'lng' => $city['lng']];
        }
        $d = self::DUBAI[($i * 7 + $set * 3) % count(self::DUBAI)];

        return ['city' => 'Dubai', 'community' => $d['community'], 'location' => $d['location'], 'lat' => $d['lat'], 'lng' => $d['lng']];
    }

    private function createListing(PortalUser $owner, string $ownerType, ?int $agentId, int $set, int $n, array $spec): void
    {
        $place = $spec['place'];
        $typeLabel = Str::of($spec['type'])->replace('-', ' ')->title();
        $adjective = self::ADJECTIVES[($set + $n) % count(self::ADJECTIVES)];
        $title = $spec['beds']
            ? "{$adjective} {$spec['beds']} Bedroom {$typeLabel} in {$place['community']}"
            : "{$adjective} {$typeLabel} in {$place['community']}";
        $slug = Str::slug("demo-{$owner->id}-{$n}-{$title}");

        // Already seeded: only refresh the filterable details, so fixes here apply on a re-run.
        if ($existing = Property::where('slug', $slug)->first()) {
            PropertyDetail::where('property_id', $existing->id)->update([
                'amenities' => json_encode(array_map(fn ($label) => ['icon' => null, 'label' => ['en' => $label]], $spec['amenities'])),
                'parking' => $spec['parking'],
                'furnished' => $spec['furnished'],
            ]);

            return;
        }

        $reference = 'PROP' . str_pad((string) $this->nextReference++, 3, '0', STR_PAD_LEFT);
        $features = $spec['beds']
            ? "{$spec['beds']} Bedrooms, {$spec['baths']} Bathrooms, " . number_format($spec['sqft']) . ' sqft'
            : "{$spec['baths']} Washrooms, " . number_format($spec['sqft']) . ' sqft';
        $purpose = $spec['listing'] === 'sale' ? 'for sale' : 'for rent';
        $featured = $n % 12 === 0;

        $property = Property::create([
            'portal_user_id' => $owner->id,
            'agent_id' => $agentId,
            'created_by_type' => $ownerType,
            'created_by_id' => $owner->id,
            'translations' => [
                'en' => [
                    'title' => $title,
                    'key_features' => $features,
                    'description' => "{$title}, {$place['city']} — {$purpose}. {$features}, with "
                        . Str::lower(implode(', ', $spec['amenities'])) . '. Close to shops, schools and main roads.',
                    'address' => "{$place['community']}, {$place['city']}, United Arab Emirates",
                    'community' => $place['community'],
                    'city' => $place['city'],
                    'country' => 'United Arab Emirates',
                ],
            ],
            'slug' => $slug,
            'reference_no' => $reference,
            'listing_type' => $spec['listing'],
            'completion_status' => $spec['completion'],
            'property_type' => $spec['type'],
            'category' => $spec['category'],
            'segment' => $spec['segment'],
            'location' => $place['location'],
            'latitude' => round($place['lat'] + ((($n * 13) % 21) - 10) * 0.0012, 6),
            'longitude' => round($place['lng'] + ((($n * 17) % 21) - 10) * 0.0012, 6),
            'bedrooms' => $spec['beds'],
            'bathrooms' => $spec['baths'],
            'sqft' => $spec['sqft'],
            'price' => $spec['price'],
            'currency' => 'AED',
            'featured' => $featured,
            'featured_from' => $featured ? now()->subDay() : null,
            'featured_until' => $featured ? now()->addMonths(3) : null,
            'status' => true,
            'published_at' => now()->subDays(($set * 3 + $n) % 90)->subMinutes($n),
            'order_index' => $this->nextOrder++,
        ]);

        PropertyDetail::create([
            'property_id' => $property->id,
            'amenities' => array_map(fn ($label) => ['icon' => null, 'label' => ['en' => $label]], $spec['amenities']),
            'year_built' => $spec['completion'] === 'off_plan' ? (int) now()->year + 1 + ($n % 3) : 2008 + (($set + $n) % 17),
            'floor' => in_array($spec['type'], ['villa', 'townhouse', 'warehouse'], true) ? 'G' : (string) (1 + ($n * 3) % 40),
            'parking' => $spec['parking'],
            'garage' => in_array($spec['type'], ['villa', 'townhouse'], true) ? 1 : 0,
            'furnished' => $spec['furnished'],
            'direct_from_owner' => $n % 5 === 0 ? 'Yes' : 'No',
            'view' => self::VIEWS[($set + $n) % count(self::VIEWS)],
        ]);

        $this->attachGallery($property, $set * 50 + $n);

        // Every 5th listing gets a floor plan (the "Floor plans" filter).
        if ($n % 5 === 0) {
            $planPath = "properties/{$reference}/floor-plan-1.png";
            $this->linkFile(self::MASTER_FOLDER . '/floorplan.png', $planPath);
            PropertyFloorPlan::create([
                'property_id' => $property->id,
                'label' => $spec['beds'] ? "{$spec['beds']} Bedroom" : 'Typical Floor',
                'image' => $planPath,
                'size_from' => $spec['sqft'],
                'size_to' => (int) round($spec['sqft'] * 1.1),
                'price_from' => $spec['price'],
                'price_to' => (int) round($spec['price'] * 1.1),
                'order_index' => 1,
            ]);
        }

        app(AgencyMembershipService::class)->recordPropertyChange(
            $property, 'created', null, $ownerType === 'agency' ? $owner->id : null, null, $agentId,
            AssignmentActor::system(), 'Local filter-coverage demo listing.',
        );

        $this->created++;
    }

    private function attachGallery(Property $property, int $seed): void
    {
        $folder = 'properties/' . $property->reference_no;
        $numbers = [];
        for ($number = 1; $number <= 3; $number++) {
            $master = $this->masters[($seed * 3 + $number - 1) % count($this->masters)];
            $this->linkFile($master, "{$folder}/{$property->reference_no}-{$number}.jpeg");
            $numbers[] = $number;
        }

        $property->update([
            'image_path' => $folder,
            'image_sequence' => implode(',', $numbers),
            'image_next_number' => max($numbers),
        ]);
    }

    /** One copy of each demo image on the public disk; listing galleries hard-link to these. */
    private function prepareMasterImages(): void
    {
        $disk = Storage::disk('public');
        foreach (self::DEMO_IMAGES as $index => $image) {
            $target = self::MASTER_FOLDER . '/demo-' . ($index + 1) . '.jpeg';
            if (!$disk->exists($target) && is_file(public_path($image))) {
                $disk->put($target, file_get_contents(public_path($image)));
            }
            if ($disk->exists($target)) {
                $this->masters[] = $target;
            }
        }
        if (!$this->masters) {
            throw new \RuntimeException('No demo images found under public/frontend/assets/images/home.');
        }
        if (!$disk->exists(self::MASTER_FOLDER . '/floorplan.png')) {
            $disk->put(self::MASTER_FOLDER . '/floorplan.png', file_get_contents(public_path(self::FLOOR_PLAN_IMAGE)));
        }
    }

    /** Hard link (no extra disk space); falls back to a copy where links aren't supported. */
    private function linkFile(string $from, string $to): void
    {
        $disk = Storage::disk('public');
        if ($disk->exists($to)) {
            return;
        }
        $disk->makeDirectory(dirname($to));
        if (!@link($disk->path($from), $disk->path($to))) {
            $disk->copy($from, $to);
        }
    }
}
