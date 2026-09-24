<?php

namespace Database\Seeders;

use App\Models\Filter;
use App\Models\FilterValue;
use App\Models\PortalUser;
use App\Models\Property;
use App\Models\PropertyDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds genuinely commercial listings (office/retail/warehouse/showroom) for the /commercial
 * page. PropertySeeder's demo properties are split ~50/50 between category=residential and
 * category=commercial, but every one of them is still an apartment/villa/townhouse — there was
 * no listing whose property_type is actually commercial-shaped, which is what /commercial
 * should be showing.
 */
class CommercialPropertySeeder extends Seeder
{
    private const LOCATIONS = [
        ['value' => 'business-bay', 'en' => 'Business Bay', 'ar' => 'الخليج التجاري'],
        ['value' => 'downtown-dubai', 'en' => 'Downtown Dubai', 'ar' => 'وسط مدينة دبي'],
        ['value' => 'dubai-marina', 'en' => 'Dubai Marina', 'ar' => 'مرسى دبي'],
        ['value' => 'al-furjan', 'en' => 'Al Furjan', 'ar' => 'الفرجان'],
        ['value' => 'dubai-hills-estate', 'en' => 'Dubai Hills Estate', 'ar' => 'دبي هيلز استيت'],
        ['value' => 'arabian-ranches', 'en' => 'Arabian Ranches', 'ar' => 'المرابع العربية'],
    ];

    private const PROPERTY_TYPES = [
        ['value' => 'office', 'en' => 'Office', 'ar' => 'مكتب'],
        ['value' => 'retail-shop', 'en' => 'Retail Shop', 'ar' => 'محل تجاري'],
        ['value' => 'warehouse', 'en' => 'Warehouse', 'ar' => 'مستودع'],
        ['value' => 'showroom', 'en' => 'Showroom', 'ar' => 'صالة عرض'],
    ];

    private const IMAGE_POOL = [
        'frontend/assets/images/home/realty-card-1.jpg',
        'frontend/assets/images/home/realty-card-2.jpg',
        'frontend/assets/images/home/realty-card-3.jpg',
        'frontend/assets/images/home/realty-card-4.jpg',
        'frontend/assets/images/home/project-card-3.jpg',
        'frontend/assets/images/home/project-card-5.jpg',
        'frontend/assets/images/property-details/gallery-2.jpg',
        'frontend/assets/images/property-details/gallery-3.jpg',
        'frontend/assets/images/about/diversity.jpg',
    ];

    private const LISTINGS = [
        ['type' => 'office', 'listing' => 'sale', 'location' => 'business-bay', 'sqft' => 1200, 'price' => 1850000, 'title_en' => 'Fitted Office Space in Business Bay Tower', 'title_ar' => 'مكتب مجهز في برج الخليج التجاري', 'floor' => 12, 'furnished' => true],
        ['type' => 'retail-shop', 'listing' => 'rent', 'location' => 'downtown-dubai', 'sqft' => 850, 'price' => 180000, 'title_en' => 'Ground Floor Retail Shop in Downtown Dubai', 'title_ar' => 'محل تجاري بالطابق الأرضي في وسط مدينة دبي', 'floor' => 0, 'furnished' => false],
        ['type' => 'warehouse', 'listing' => 'sale', 'location' => 'al-furjan', 'sqft' => 8000, 'price' => 3200000, 'title_en' => 'Logistics Warehouse with Loading Bay in Al Furjan', 'title_ar' => 'مستودع لوجستي مع رصيف تحميل في الفرجان', 'floor' => 0, 'furnished' => false],
        ['type' => 'showroom', 'listing' => 'rent', 'location' => 'dubai-marina', 'sqft' => 2200, 'price' => 420000, 'title_en' => 'Ground Floor Auto Showroom on Marina Walk', 'title_ar' => 'صالة عرض سيارات بالطابق الأرضي في مرسى دبي', 'floor' => 0, 'furnished' => false],
        ['type' => 'office', 'listing' => 'rent', 'location' => 'dubai-hills-estate', 'sqft' => 950, 'price' => 145000, 'title_en' => 'Grade A Office Suite in Dubai Hills Estate', 'title_ar' => 'مكتب فئة أولى في دبي هيلز استيت', 'floor' => 6, 'furnished' => true],
        ['type' => 'retail-shop', 'listing' => 'sale', 'location' => 'arabian-ranches', 'sqft' => 650, 'price' => 950000, 'title_en' => 'Community Retail Unit in Arabian Ranches', 'title_ar' => 'وحدة تجارية مجتمعية في المرابع العربية', 'floor' => 0, 'furnished' => false],
        ['type' => 'warehouse', 'listing' => 'rent', 'location' => 'business-bay', 'sqft' => 5500, 'price' => 385000, 'title_en' => 'Mid-Size Storage Warehouse near Business Bay', 'title_ar' => 'مستودع تخزين متوسط الحجم قرب الخليج التجاري', 'floor' => 0, 'furnished' => false],
        ['type' => 'office', 'listing' => 'sale', 'location' => 'downtown-dubai', 'sqft' => 1800, 'price' => 3650000, 'title_en' => 'Full-Floor Office in Downtown Dubai Business Tower', 'title_ar' => 'مكتب طابق كامل في برج أعمال وسط مدينة دبي', 'floor' => 21, 'furnished' => true],
    ];

    public function run(): void
    {
        $locationFilterId = Filter::where('key', 'location')->value('id');
        $typeFilterId = Filter::where('key', 'property_type')->value('id');
        $categoryFilterId = Filter::where('key', 'category')->value('id');

        foreach (self::LOCATIONS as $i => $loc) {
            if ($locationFilterId) {
                FilterValue::firstOrCreate(
                    ['filter_id' => $locationFilterId, 'value' => $loc['value']],
                    ['translations' => ['en' => ['label' => $loc['en']], 'ar' => ['label' => $loc['ar']]], 'order_index' => $i, 'status' => true]
                );
            }
        }

        foreach (self::PROPERTY_TYPES as $i => $type) {
            if ($typeFilterId) {
                FilterValue::firstOrCreate(
                    ['filter_id' => $typeFilterId, 'value' => $type['value']],
                    ['translations' => ['en' => ['label' => $type['en']], 'ar' => ['label' => $type['ar']]], 'order_index' => 100 + $i, 'status' => true]
                );
            }
        }

        $company = PortalUser::where('type', 'company')->where('status', 'approved')->get();
        $agent = PortalUser::where('type', 'agent')->where('status', 'approved')->get();

        $startNumber = ((int) (Property::where('reference_no', 'like', 'COMM%')
            ->pluck('reference_no')
            ->map(fn ($ref) => (int) substr($ref, 4))
            ->max())) + 1;

        foreach (self::LISTINGS as $i => $listing) {
            $number = $startNumber + $i;
            $referenceNo = 'COMM' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $location = collect(self::LOCATIONS)->firstWhere('value', $listing['location']);
            $slug = Str::slug($listing['title_en'] . '-' . $referenceNo);

            $translations = [
                'en' => [
                    'title' => $listing['title_en'],
                    'key_features' => number_format($listing['sqft']) . ' sqft commercial unit',
                    'description' => "A {$listing['title_en']} located in {$location['en']}, Dubai — {$listing['sqft']} sqft of commercial space close to major roads and business amenities.",
                    'address' => $location['en'] . ', Dubai, United Arab Emirates',
                    'community' => $location['en'],
                    'city' => 'Dubai',
                    'country' => 'United Arab Emirates',
                ],
                'ar' => [
                    'title' => $listing['title_ar'],
                    'key_features' => number_format($listing['sqft']) . ' قدم مربع وحدة تجارية',
                    'description' => "{$listing['title_ar']} يقع في {$location['ar']}، دبي، بمساحة {$listing['sqft']} قدم مربع بالقرب من الطرق الرئيسية والمرافق التجارية.",
                    'address' => $location['ar'] . '، دبي، الإمارات العربية المتحدة',
                    'community' => $location['ar'],
                    'city' => 'دبي',
                    'country' => 'الإمارات العربية المتحدة',
                ],
            ];

            $property = Property::create([
                'portal_user_id' => $company->isNotEmpty() ? $company[$i % $company->count()]->id : null,
                'agent_id' => $agent->isNotEmpty() ? $agent[$i % $agent->count()]->id : null,
                'translations' => $translations,
                'slug' => $slug,
                'reference_no' => $referenceNo,
                'rera_id' => 'RERA-COMM-' . random_int(10000, 99999),
                'listing_type' => $listing['listing'],
                'completion_status' => 'ready',
                'property_type' => $listing['type'],
                'category' => $categoryFilterId ? 'commercial' : null,
                'location' => $locationFilterId ? $listing['location'] : null,
                'postal_code' => '00000',
                'latitude' => 25.05 + ($i * 0.01),
                'longitude' => 55.15 + ($i * 0.01),
                'bedrooms' => null,
                'bathrooms' => $listing['type'] === 'office' ? 1 : null,
                'sqft' => $listing['sqft'],
                'price' => $listing['price'],
                'currency' => 'AED',
                'featured' => $i < 3,
                'status' => true,
                'published_at' => now()->subDays(10 - $i),
                'order_index' => 1000 + $i,
            ]);

            PropertyDetail::create([
                'property_id' => $property->id,
                'floor' => (string) $listing['floor'],
                'parking' => 1,
                'furnished' => $listing['furnished'],
                'direct_from_owner' => 'No',
                'view' => 'Street View',
                'amenities' => [
                    ['icon' => null, 'label' => ['en' => 'High-Speed Internet', 'ar' => 'إنترنت عالي السرعة']],
                    ['icon' => null, 'label' => ['en' => 'Reception', 'ar' => 'استقبال']],
                    ['icon' => null, 'label' => ['en' => '24/7 Security', 'ar' => 'أمن على مدار الساعة']],
                ],
            ]);

            $this->attachGallery($property, $referenceNo, $i);
        }
    }

    private function attachGallery(Property $property, string $referenceNo, int $index): void
    {
        $folder = 'properties/' . $referenceNo;
        $count = 3;
        $sequence = [];

        for ($n = 1; $n <= $count; $n++) {
            $sourceRelative = self::IMAGE_POOL[($index * $count + $n - 1) % count(self::IMAGE_POOL)];
            $sourcePath = public_path($sourceRelative);
            if (!is_file($sourcePath)) {
                continue;
            }

            $gallery = app(\App\Services\PropertyGallery::class);
            $folder = $gallery->folderValue($folder); // Cloudinary folder URL when configured
            $gallery->put($folder, "{$referenceNo}-{$n}.jpeg", file_get_contents($sourcePath));
            $sequence[] = $n;
        }

        if (empty($sequence)) {
            return;
        }

        $property->update([
            'image_path' => $folder,
            'image_sequence' => implode(',', $sequence),
            'image_next_number' => max($sequence),
        ]);
    }
}
