<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\PropertyDetail;
use App\Models\PropertyFloorPlan;
use App\Services\CloudinaryMedia;
use App\Support\DemoPropertyMedia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Makes up to 50 listings "complete" for demos: 10–14 features & amenities, a 6–8 photo gallery and
 * 2–3 floor plans each. Only listings still on demo media are touched (an uploaded gallery is
 * never replaced), and only ones not already complete — so it's safe to re-run.
 *
 *   php artisan db:seed --class=DemoRichListingsSeeder
 */
class DemoRichListingsSeeder extends Seeder
{
    private const LIMIT = 50;

    /** Labels chosen to also match the listing page's amenity filter (Pool, Gym, Security, Balcony, …). */
    private const AMENITIES = [
        'Swimming Pool', 'Gym', 'Parking', 'Security Staff', 'Balcony or Terrace', 'Playground', 'Garden',
        'Fire Alarm', 'WiFi', 'CCTV', 'Concierge', 'Central A/C', 'Covered Parking', 'BBQ Area',
        'Kids Play Area', 'Maid’s Room', 'Built-in Wardrobes', 'Metro Access', 'Pets Allowed', 'Jogging Track',
    ];

    private const AMENITIES_AR = [
        'Swimming Pool' => 'مسبح', 'Gym' => 'صالة رياضية', 'Parking' => 'موقف سيارات', 'Security Staff' => 'حراسة أمنية',
        'Balcony or Terrace' => 'شرفة أو تراس', 'Playground' => 'ملعب أطفال', 'Garden' => 'حديقة', 'Fire Alarm' => 'إنذار حريق',
        'WiFi' => 'واي فاي', 'CCTV' => 'كاميرات مراقبة', 'Concierge' => 'خدمة الكونسيرج', 'Central A/C' => 'تكييف مركزي',
        'Covered Parking' => 'موقف مغطى', 'BBQ Area' => 'منطقة شواء', 'Kids Play Area' => 'منطقة ألعاب للأطفال',
        'Maid’s Room' => 'غرفة خادمة', 'Built-in Wardrobes' => 'خزائن مدمجة', 'Metro Access' => 'قريب من المترو',
        'Pets Allowed' => 'يسمح بالحيوانات الأليفة', 'Jogging Track' => 'مسار للركض',
    ];

    public function run(): void
    {
        if (!app()->environment('local')) {
            throw new \RuntimeException('DemoRichListingsSeeder is restricted to the local environment.');
        }

        $media = app(DemoPropertyMedia::class);
        $planImage = null; // one uploaded floor-plan drawing, reused
        $done = 0;

        $candidates = Property::where('status', true)
            ->where('segment', '!=', Property::SEGMENT_COMMERCIAL)
            ->where(fn ($q) => $q->whereNull('image_path')->orWhere('image_path', 'like', '%/demo/%')->orWhere('image_path', 'like', 'properties/PROP%'))
            ->with('details')->withCount('floorPlans')
            ->orderByDesc('featured')->orderBy('id')
            ->get();

        $alreadyRich = $candidates->filter(fn (Property $p) => $this->isRich($p))->count();

        foreach ($candidates as $property) {
            if ($alreadyRich + $done >= self::LIMIT) {
                break;
            }
            if ($this->isRich($property)) {
                continue;
            }
            $n = $property->id;

            // 1) Features & amenities: 10–14 of the list, varied per listing.
            $count = 10 + ($n % 5);
            $labels = collect(self::AMENITIES)->sortBy(fn ($label, $i) => ($i * 7 + $n * 13) % 97)->take($count)->values();
            PropertyDetail::updateOrCreate(['property_id' => $property->id], [
                'amenities' => $labels->map(fn ($label) => ['icon' => null, 'label' => ['en' => $label, 'ar' => self::AMENITIES_AR[$label] ?? $label]])->all(),
            ]);

            // 2) Gallery: 6–8 photos.
            $media->attachGallery($property, $n, 6 + ($n % 3));

            // 3) Floor plans: 2–3 layouts (the only plan drawing available is reused).
            if ($property->floor_plans_count < 2) {
                $planImage ??= $media->floorPlanImage($property);
                $sqft = max(600, (int) ($property->sqft ?: 1200));
                $price = (float) ($property->price ?: 1500000);
                $layouts = $this->layouts((int) ($property->bedrooms ?: 2), $n % 2 === 0 ? 3 : 2);
                PropertyFloorPlan::where('property_id', $property->id)->delete();
                foreach ($layouts as $i => [$label, $factor]) {
                    PropertyFloorPlan::create([
                        'property_id' => $property->id,
                        'label' => $label,
                        'image' => $planImage,
                        'size_from' => (int) round($sqft * $factor),
                        'size_to' => (int) round($sqft * $factor * 1.25),
                        'price_from' => (int) round($price * $factor, -3),
                        'price_to' => (int) round($price * $factor * 1.2, -3),
                        'order_index' => $i + 1,
                    ]);
                }
            }

            $done++;
            $this->command?->line("  {$property->reference_no}: {$count} amenities, " . (6 + ($n % 3)) . ' photos, floor plans ✓');
        }

        Cache::flush(); // listing / detail pages are cached
        $this->command?->info("Listings enriched: {$done} (" . ($alreadyRich + $done) . ' complete in total, target ' . self::LIMIT . ').');
    }

    /** Already has 8+ amenities, 5+ photos and 2+ floor plans. */
    private function isRich(Property $property): bool
    {
        $photos = count(array_filter(explode(',', (string) $property->image_sequence)));

        return $photos >= 5 && $property->floor_plans_count >= 2 && count($property->details?->amenities ?? []) >= 8;
    }

    /** @return array<int, array{0: string, 1: float}> [label, size factor vs the listing] */
    private function layouts(int $beds, int $count): array
    {
        $start = max(1, min($beds - 1, 4));
        $label = fn (int $b) => $b >= 5 ? '5+ Bedroom Residences' : "{$b} Bedroom " . ($b >= 4 ? 'Residences' : 'Apartments');

        return collect(range($start, $start + $count - 1))
            ->map(fn (int $b, int $i) => [$label($b), [0.75, 1.0, 1.35, 1.7][$i] ?? 1.0])
            ->all();
    }
}
