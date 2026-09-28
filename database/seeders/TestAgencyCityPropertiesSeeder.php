<?php

namespace Database\Seeders;

use App\Models\AgencyAgent;
use App\Models\PortalUser;
use App\Models\Property;
use App\Services\Agency\AgencyMembershipService;
use App\Services\Agency\AssignmentActor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Small local demo set for the Home page's city tabs, owned by the Test Company account. */
class TestAgencyCityPropertiesSeeder extends Seeder
{
    private const CITIES = [
        ['tab' => 'Abu Dhabi', 'city' => 'Abu Dhabi', 'lat' => 24.4539, 'lng' => 54.3773, 'areas' => ['Al Reem Island', 'Yas Island', 'Saadiyat Island', 'Al Raha Beach', 'Khalifa City']],
        ['tab' => 'Ajman', 'city' => 'Ajman', 'lat' => 25.4052, 'lng' => 55.5136, 'areas' => ['Ajman Corniche', 'Al Rashidiya', 'Al Zorah', 'Al Nuaimiya', 'Emirates City']],
        ['tab' => 'Ras Al Khaimah', 'city' => 'Ras Al Khaimah', 'lat' => 25.7895, 'lng' => 55.9432, 'areas' => ['Al Marjan Island', 'Mina Al Arab', 'Al Hamra Village', 'Al Nakheel', 'Dafan Al Nakheel']],
        ['tab' => 'Umm Al Quwain', 'city' => 'Umm Al Quwain', 'lat' => 25.5647, 'lng' => 55.5533, 'areas' => ['Al Salamah', 'Al Raas', 'Old Town', 'Al Humrah', 'King Faisal Street']],
        // The home page label is currently "Sharja"; use the correct emirate name in the listing.
        ['tab' => 'Sharja', 'city' => 'Sharjah', 'lat' => 25.3463, 'lng' => 55.4209, 'areas' => ['Al Majaz', 'Muwaileh', 'Al Khan', 'Aljada', 'Tilal City']],
    ];

    private const LISTING_VARIANTS = [
        ['type' => 'apartment', 'listing' => 'sale', 'bedrooms' => 2, 'bathrooms' => 2, 'sqft' => 1250, 'price' => 1850000],
        ['type' => 'apartment', 'listing' => 'rent', 'bedrooms' => 1, 'bathrooms' => 1, 'sqft' => 780, 'price' => 80000],
        ['type' => 'villa', 'listing' => 'sale', 'bedrooms' => 4, 'bathrooms' => 4, 'sqft' => 3100, 'price' => 3200000],
        ['type' => 'townhouse', 'listing' => 'sale', 'bedrooms' => 3, 'bathrooms' => 3, 'sqft' => 2200, 'price' => 2100000],
        ['type' => 'apartment', 'listing' => 'rent', 'bedrooms' => 2, 'bathrooms' => 2, 'sqft' => 1400, 'price' => 110000],
    ];

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
    ];

    public function run(): void
    {
        if (!app()->environment('local')) {
            throw new \RuntimeException('TestAgencyCityPropertiesSeeder is restricted to the local environment.');
        }

        $agency = PortalUser::where('type', 'company')
            ->where(fn ($query) => $query->where('name', 'Test Company')->orWhere('company_name', 'Test Company'))
            ->first();

        if (!$agency) {
            throw new \RuntimeException('The local Test Company agency account was not found.');
        }

        DB::transaction(function () use ($agency) {
            $agents = $this->ensureDemoAgents($agency);
            $nextReference = Property::where('reference_no', 'like', 'PROP%')
                ->pluck('reference_no')->map(fn ($ref) => (int) substr($ref, 4))->max() + 1;
            $nextOrder = (int) Property::max('order_index') + 1;

            foreach (self::CITIES as $cityIndex => $city) {
                $currentCount = Property::where('portal_user_id', $agency->id)
                    ->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(translations, '$.en.city'))) LIKE ?", ['%' . Str::lower($city['tab']) . '%'])
                    ->count();
                $capacity = max(0, 10 - $currentCount);

                foreach (array_slice($city['areas'], 0, min(5, $capacity)) as $index => $area) {
                    $variant = self::LISTING_VARIANTS[$index];
                    $title = ucfirst($variant['type']) . ' in ' . $area;
                    $slug = Str::slug('test-company-' . $city['city'] . '-' . $title);
                    $agentId = match ($index) {
                        0, 4 => $agents[0]->id,
                        2 => $agents[1]->id,
                        default => null,
                    };

                    $property = Property::firstOrCreate(
                        ['slug' => $slug],
                        [
                            'portal_user_id' => $agency->id,
                            'agent_id' => $agentId,
                            'created_by_type' => 'agency',
                            'created_by_id' => $agency->id,
                            'translations' => [
                                'en' => [
                                    'title' => $title,
                                    'key_features' => $variant['bedrooms'] . ' Bedrooms, ' . $variant['bathrooms'] . ' Bathrooms, ' . number_format($variant['sqft']) . ' sqft',
                                    'description' => $title . ' in ' . $city['city'] . ', United Arab Emirates. Close to local shops, schools and transport links.',
                                    'address' => $area . ', ' . $city['city'] . ', United Arab Emirates',
                                    'community' => $area,
                                    'city' => $city['city'],
                                    'country' => 'United Arab Emirates',
                                ],
                            ],
                            'reference_no' => 'PROP' . str_pad((string) $nextReference++, 3, '0', STR_PAD_LEFT),
                            'listing_type' => $variant['listing'],
                            'completion_status' => 'ready',
                            'property_type' => $variant['type'],
                            'segment' => Property::SEGMENT_RESIDENTIAL,
                            'latitude' => $city['lat'] + (($index - 2) * 0.001),
                            'longitude' => $city['lng'] + (($index - 2) * 0.001),
                            'bedrooms' => $variant['bedrooms'],
                            'bathrooms' => $variant['bathrooms'],
                            'sqft' => $variant['sqft'],
                            'price' => $variant['price'],
                            'currency' => 'AED',
                            'featured' => false,
                            'status' => true,
                            'published_at' => now(),
                            'order_index' => $nextOrder++,
                        ]
                    );

                    if (!$property->image_path) {
                        $this->attachDemoGallery($property, ($cityIndex * 5) + $index);
                    }

                    if ($property->wasRecentlyCreated) {
                        app(AgencyMembershipService::class)->recordPropertyChange(
                            $property,
                            'created',
                            null,
                            $agency->id,
                            null,
                            $agentId,
                            AssignmentActor::system(),
                            'Local city-tab demo listing.',
                        );
                    }
                }
            }
        });
    }

    private function attachDemoGallery(Property $property, int $seedIndex): void
    {
        $folder = 'properties/' . $property->reference_no;
        $numbers = [];

        for ($number = 1; $number <= 3; $number++) {
            $imageIndex = ($seedIndex * 3 + $number - 1) % count(self::DEMO_IMAGES);
            $source = public_path(self::DEMO_IMAGES[$imageIndex]);
            if (!is_file($source)) {
                continue;
            }

            Storage::disk('public')->put($folder . '/' . $property->reference_no . '-' . $number . '.jpeg', file_get_contents($source));
            $numbers[] = $number;
        }

        if ($numbers) {
            $property->update([
                'image_path' => $folder,
                'image_sequence' => implode(',', $numbers),
                'image_next_number' => max($numbers),
            ]);
        }
    }

    /** @return array{0: PortalUser, 1: PortalUser} */
    private function ensureDemoAgents(PortalUser $agency): array
    {
        $agents = [];
        foreach ([
            ['email' => 'test-agency-agent-one@example.test', 'name' => 'Test Agency Agent One', 'phone' => '+971500000811'],
            ['email' => 'test-agency-agent-two@example.test', 'name' => 'Test Agency Agent Two', 'phone' => '+971500000812'],
        ] as $details) {
            $agent = PortalUser::firstOrCreate(
                ['email' => $details['email']],
                [
                    'type' => 'agent',
                    'name' => $details['name'],
                    'phone' => $details['phone'],
                    'company_id' => $agency->id,
                    'password' => Hash::make(Str::random(40)),
                    'status' => 'approved',
                    'status_changed_at' => now(),
                    'is_active' => true,
                ]
            );

            if (!$agent->isAgent() || (int) $agent->company_id !== (int) $agency->id) {
                throw new \RuntimeException("Demo agent {$details['email']} is already attached to another account or agency.");
            }

            AgencyAgent::firstOrCreate(
                ['agency_id' => $agency->id, 'agent_id' => $agent->id],
                [
                    'status' => AgencyAgent::APPROVED,
                    'initiated_by' => AssignmentActor::AGENCY,
                    'approved_at' => now(),
                    'joined_at' => now(),
                ]
            );
            $agents[] = $agent;
        }

        return $agents;
    }
}
