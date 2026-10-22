<?php

namespace Database\Seeders;

use App\Models\Filter;
use App\Models\FilterValue;
use App\Models\Property;
use App\Models\PropertyFloorPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo data: fills every field of the CRM listing form on a few live listings (3 for sale, 3 for rent —
 * the first ones in the website's display order), so the property detail page shows each section:
 * key features, all specs, price terms, open house days, amenities / easy access / highlights,
 * floor plans with prices, video + 360° tour and the full address. Images, permit and agent are kept.
 * Safe to re-run: the same listings are overwritten and open house dates move to the coming weekends.
 *
 *   php artisan db:seed --class=PropertyFullDataDemoSeeder
 */
class PropertyFullDataDemoSeeder extends Seeder
{
    private const PER_TYPE = 3;

    /** Matterport's public sample space — any 360° tour link (Matterport, Kuula, …) works the same way. */
    private const VIRTUAL_TOUR = 'https://my.matterport.com/show/?m=SxQL3iGyoDo';

    private const PROFILES = [
        [
            'developer' => 'Emaar Properties', 'floor' => '24', 'view' => 'Burj Khalifa View', 'parking' => 2, 'garage' => null,
            'year_built' => 2019, 'furnished' => 'furnished', 'upgraded' => true, 'owner' => 'Yes, owner listed',
            'amenities' => ['balcony', 'central_ac', 'shared_pool', 'shared_gym', 'concierge', 'covered_parking', 'security', 'built_in_wardrobes', 'view_of_landmark'],
            'easy_access' => ['metro_station', 'shopping_mall', 'supermarket', 'highway_access'],
            'attributes' => ['high_floor', 'corner_unit', 'vacant'],
            'features' => [
                'en' => ['Full Burj Khalifa and fountain views', 'Upgraded kitchen with Bosch appliances', 'Floor-to-ceiling windows', 'Walking distance to Dubai Mall', 'Vacant on transfer'],
                'ar' => ['إطلالة كاملة على برج خليفة والنافورة', 'مطبخ مطوّر بأجهزة بوش', 'نوافذ من الأرض حتى السقف', 'على بعد خطوات من دبي مول', 'شاغر عند النقل'],
            ],
            'open_house' => 3,
        ],
        [
            'developer' => 'Nakheel', 'floor' => 'G + 2', 'view' => 'Sea View', 'parking' => 3, 'garage' => 2,
            'year_built' => 2016, 'furnished' => 'semi_furnished', 'upgraded' => false, 'owner' => null,
            'amenities' => ['private_pool', 'private_garden', 'maids_room', 'study', 'barbecue_area', 'walk_in_closet', 'kitchen_appliances', 'pets_allowed', 'view_of_water'],
            'easy_access' => ['beach', 'schools', 'hospital', 'shopping_mall'],
            'attributes' => ['duplex', 'smart_home'],
            'features' => [
                'en' => ['Private beach access', 'Private pool and landscaped garden', 'Maid\'s room and study', 'Smart home system throughout'],
                'ar' => ['وصول خاص إلى الشاطئ', 'مسبح خاص وحديقة منسقة', 'غرفة خادمة ومكتب', 'نظام منزل ذكي في جميع الأنحاء'],
            ],
            'open_house' => 2,
        ],
        [
            'developer' => 'Sobha Realty', 'floor' => '11', 'view' => 'Garden View', 'parking' => 1, 'garage' => null,
            'year_built' => 2023, 'furnished' => 'unfurnished', 'upgraded' => false, 'owner' => null,
            'amenities' => ['balcony', 'shared_pool', 'shared_spa', 'childrens_play_area', 'childrens_pool', 'jogging_track', 'cctv', 'fire_alarm', 'high_speed_internet'],
            'easy_access' => ['schools', 'supermarket', 'mosque', 'bus_stop'],
            'attributes' => ['brand_new', 'low_floor'],
            'features' => [
                'en' => ['Brand new, never lived in', 'Family-friendly community with parks', 'Large balcony overlooking the gardens', 'Chiller free'],
                'ar' => ['جديد تمامًا ولم يُسكن من قبل', 'مجتمع مناسب للعائلات مع حدائق', 'شرفة واسعة تطل على الحدائق', 'تكييف مجاني'],
            ],
            'open_house' => 0, // available immediately
        ],
    ];

    /** Rent terms per profile: rental period, cheques, security deposit (% of rent). */
    private const RENT_TERMS = [
        ['yearly', 4, 5],
        ['yearly', 2, 10],
        ['monthly', 1, 5],
    ];

    public function run(): void
    {
        $options = FilterValue::with('filter')
            ->whereHas('filter', fn ($q) => $q->whereIn('key', ['amenity', 'easy_access', 'property_attribute']))
            ->get()
            ->groupBy(fn ($v) => $v->filter->key)
            ->map(fn ($group) => $group->keyBy('value'));
        $planImages = PropertyFloorPlan::whereNotNull('image')->distinct()->orderBy('image')->pluck('image')->take(12)->values();

        foreach (['sale', 'rent'] as $type) {
            $listings = Property::where('status', true)->where('listing_type', $type)->where('category', 'residential')
                ->whereNull('sold_at')->whereNotNull('image_path')->whereNotNull('latitude')->whereNotNull('longitude')
                ->displayOrder()->take(self::PER_TYPE)->get();

            foreach ($listings as $i => $property) {
                $this->fill($property, $i % count(self::PROFILES), $options, $planImages);
                $this->command?->info("{$type}: /property-details/{$property->slug}");
            }
        }
    }

    private function fill(Property $property, int $profileIndex, $options, $planImages): void
    {
        $profile = self::PROFILES[$profileIndex];
        $rent = $property->listing_type === 'rent';
        [$period, $cheques, $depositPct] = self::RENT_TERMS[$profileIndex];

        $translations = $property->translations ?? [];
        foreach (['en', 'ar'] as $lang) {
            $current = $translations[$lang] ?? [];
            $translations[$lang] = array_merge($current, [
                'key_features' => implode("\n", $profile['features'][$lang]),
                'city' => ($current['city'] ?? null) ?: ($lang === 'ar' ? 'دبي' : 'Dubai'),
                'country' => ($current['country'] ?? null) ?: ($lang === 'ar' ? 'الإمارات العربية المتحدة' : 'United Arab Emirates'),
            ]);
        }

        $property->fill([
            'translations' => $translations,
            'emirate' => $property->emirate ?: 'dubai',
            'rental_period' => $rent ? $period : null,
            'available_dates' => $this->openHouseDates($profile['open_house']),
            'postal_code' => $property->postal_code ?: (string) (10000 + $property->id % 90000),
        ])->save();

        $price = (float) ($property->price ?: 0);
        $property->details()->updateOrCreate([], [
            'developer' => $profile['developer'],
            'floor' => $profile['floor'],
            'view' => $profile['view'],
            'parking' => $profile['parking'],
            'garage' => $profile['garage'],
            'year_built' => $profile['year_built'],
            'furnished' => $profile['furnished'],
            'upgraded' => $profile['upgraded'],
            'direct_from_owner' => $profile['owner'],
            'cheques' => $rent ? $cheques : null,
            'security_deposit' => $rent && $price ? round($price * $depositPct / 100) : null,
            'video_tour_url' => asset('frontend/assets/video/banner.mp4'),
            'virtual_tour_url' => self::VIRTUAL_TOUR,
            'amenities' => $this->optionRows($options->get('amenity'), $profile['amenities']),
            'easy_access' => $this->optionRows($options->get('easy_access'), $profile['easy_access']),
            'property_attributes' => $this->optionRows($options->get('property_attribute'), $profile['attributes']),
        ]);

        // Floor plans (reusing demo drawings already on Cloudinary) — only when the listing has none.
        if ($planImages->isNotEmpty() && !$property->floorPlans()->exists()) {
            $size = (int) ($property->sqft ?: 1200);
            $plans = [['Typical floor', 0.95, 1.0], ['Upper floor', 1.0, 1.1]];
            foreach ($plans as $n => [$label, $from, $to]) {
                $property->floorPlans()->create([
                    'label' => $property->bedrooms ? "{$property->bedrooms} Bedroom — {$label}" : $label,
                    'image' => $planImages[($property->id + $n) % $planImages->count()],
                    'size_from' => (int) round($size * $from),
                    'size_to' => (int) round($size * $to),
                    'price_from' => !$rent && $price ? round($price * $from) : null,
                    'price_to' => !$rent && $price ? round($price * $to) : null,
                    'order_index' => $n + 1,
                ]);
            }
        }

        $property->touch(); // new cache key for the detail page
    }

    /** The next N weekend days (Sat / Sun) as Y-m-d; empty = available immediately. */
    private function openHouseDates(int $count): ?array
    {
        $dates = [];
        for ($day = Carbon::tomorrow(); count($dates) < $count; $day->addDay()) {
            if ($day->isWeekend()) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates ?: null;
    }

    /** Rows in the shape the listing form saves: option key + its icon + label per language. */
    private function optionRows($options, array $keys): array
    {
        return collect($keys)
            ->map(fn ($key) => $options?->get($key))
            ->filter()
            ->map(fn (FilterValue $option) => [
                'key' => $option->value,
                'icon' => $option->icon,
                'label' => collect($option->translations)->map(fn ($t) => $t['label'] ?? null)->filter()->all(),
            ])
            ->values()
            ->all();
    }
}
