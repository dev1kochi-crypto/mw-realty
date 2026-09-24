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
 * Seeds 20 demo properties (with real galleries, copied from the site's own
 * stock photography) so the CRM property list, agent assignment, and public
 * listing pages all have something realistic to show while testing.
 */
class PropertySeeder extends Seeder
{
    private const LOCATIONS = [
        ['value' => 'downtown-dubai', 'en' => 'Downtown Dubai', 'ar' => 'وسط مدينة دبي'],
        ['value' => 'dubai-marina', 'en' => 'Dubai Marina', 'ar' => 'مرسى دبي'],
        ['value' => 'business-bay', 'en' => 'Business Bay', 'ar' => 'الخليج التجاري'],
        ['value' => 'palm-jumeirah', 'en' => 'Palm Jumeirah', 'ar' => 'نخلة جميرا'],
        ['value' => 'jumeirah-village-circle', 'en' => 'Jumeirah Village Circle', 'ar' => 'قرية جميرا الدائرية'],
        ['value' => 'dubai-hills-estate', 'en' => 'Dubai Hills Estate', 'ar' => 'دبي هيلز استيت'],
        ['value' => 'arabian-ranches', 'en' => 'Arabian Ranches', 'ar' => 'المرابع العربية'],
        ['value' => 'al-furjan', 'en' => 'Al Furjan', 'ar' => 'الفرجان'],
    ];

    private const CATEGORIES = [
        ['value' => 'residential', 'en' => 'Residential', 'ar' => 'سكني'],
        ['value' => 'commercial', 'en' => 'Commercial', 'ar' => 'تجاري'],
    ];

    private const IMAGE_POOL = [
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
        'frontend/assets/images/home/find-villa.jpg',
        'frontend/assets/images/home/find-penthouse-1.jpg',
        'frontend/assets/images/home/find-penthouse-2.jpg',
        'frontend/assets/images/home/find-tall.jpg',
        'frontend/assets/images/home/find-plot.jpg',
        'frontend/assets/images/property-details/gallery-1.jpg',
        'frontend/assets/images/property-details/gallery-2.jpg',
        'frontend/assets/images/property-details/gallery-3.jpg',
        'frontend/assets/images/about/villa.jpg',
        'frontend/assets/images/about/diversity.jpg',
        'frontend/assets/images/about/trends-tall.jpg',
        'frontend/assets/images/about/trends-interior.jpg',
        'frontend/assets/images/about/why-choose.jpg',
    ];

    private const LISTINGS = [
        ['type' => 'apartment', 'listing' => 'sale', 'status' => 'ready', 'bed' => 1, 'bath' => 1, 'sqft' => 720, 'price' => 850000, 'title_en' => 'Modern 1 Bedroom Apartment', 'title_ar' => 'شقة عصرية غرفة نوم واحدة'],
        ['type' => 'apartment', 'listing' => 'sale', 'status' => 'ready', 'bed' => 2, 'bath' => 2, 'sqft' => 1150, 'price' => 1450000, 'title_en' => 'Spacious 2BR Apartment with Balcony', 'title_ar' => 'شقة واسعة بغرفتي نوم مع شرفة'],
        ['type' => 'apartment', 'listing' => 'rent', 'status' => 'ready', 'bed' => 1, 'bath' => 1, 'sqft' => 680, 'price' => 65000, 'title_en' => 'Cozy Studio Apartment for Rent', 'title_ar' => 'استوديو مريح للإيجار'],
        ['type' => 'apartment', 'listing' => 'sale', 'status' => 'off_plan', 'bed' => 3, 'bath' => 3, 'sqft' => 1620, 'price' => 2350000, 'title_en' => 'Premium 3BR Apartment, Off-Plan', 'title_ar' => 'شقة مميزة 3 غرف نوم على الخارطة'],
        ['type' => 'villa', 'listing' => 'sale', 'status' => 'ready', 'bed' => 4, 'bath' => 5, 'sqft' => 3800, 'price' => 5200000, 'title_en' => 'Elegant 4 Bedroom Villa with Private Pool', 'title_ar' => 'فيلا أنيقة 4 غرف نوم مع مسبح خاص'],
        ['type' => 'villa', 'listing' => 'sale', 'status' => 'ready', 'bed' => 5, 'bath' => 6, 'sqft' => 4600, 'price' => 8900000, 'title_en' => 'Luxury 5 Bedroom Villa with Garden', 'title_ar' => 'فيلا فاخرة 5 غرف نوم مع حديقة'],
        ['type' => 'villa', 'listing' => 'rent', 'status' => 'ready', 'bed' => 4, 'bath' => 4, 'sqft' => 3500, 'price' => 220000, 'title_en' => 'Family Villa for Rent, Landscaped Garden', 'title_ar' => 'فيلا عائلية للإيجار مع حديقة منسقة'],
        ['type' => 'villa', 'listing' => 'sale', 'status' => 'off_plan', 'bed' => 4, 'bath' => 5, 'sqft' => 4100, 'price' => 6100000, 'title_en' => 'Contemporary Villa, Handover 2027', 'title_ar' => 'فيلا عصرية تسليم 2027'],
        ['type' => 'townhouse', 'listing' => 'sale', 'status' => 'ready', 'bed' => 3, 'bath' => 3, 'sqft' => 2100, 'price' => 2650000, 'title_en' => '3 Bedroom Townhouse in Gated Community', 'title_ar' => 'تاون هاوس 3 غرف نوم في مجتمع مسور'],
        ['type' => 'townhouse', 'listing' => 'sale', 'status' => 'ready', 'bed' => 4, 'bath' => 4, 'sqft' => 2450, 'price' => 3200000, 'title_en' => 'Corner Townhouse with Extra Storage', 'title_ar' => 'تاون هاوس زاوية مع مساحة تخزين إضافية'],
        ['type' => 'townhouse', 'listing' => 'rent', 'status' => 'ready', 'bed' => 3, 'bath' => 3, 'sqft' => 2000, 'price' => 130000, 'title_en' => 'Townhouse for Rent Near Community Park', 'title_ar' => 'تاون هاوس للإيجار قرب الحديقة العامة'],
        ['type' => 'townhouse', 'listing' => 'sale', 'status' => 'off_plan', 'bed' => 3, 'bath' => 3, 'sqft' => 2250, 'price' => 2900000, 'title_en' => 'New Launch Townhouse, Flexible Payment Plan', 'title_ar' => 'تاون هاوس إطلاق جديد بخطة سداد مرنة'],
        ['type' => 'penthouse', 'listing' => 'sale', 'status' => 'ready', 'bed' => 4, 'bath' => 5, 'sqft' => 4200, 'price' => 12500000, 'title_en' => 'Sky Penthouse with Panoramic Views', 'title_ar' => 'بنتهاوس بإطلالة بانورامية'],
        ['type' => 'penthouse', 'listing' => 'rent', 'status' => 'ready', 'bed' => 3, 'bath' => 4, 'sqft' => 3300, 'price' => 380000, 'title_en' => 'Duplex Penthouse for Rent, Fully Furnished', 'title_ar' => 'بنتهاوس دوبلكس للإيجار مفروش بالكامل'],
        ['type' => 'penthouse', 'listing' => 'sale', 'status' => 'ready', 'bed' => 5, 'bath' => 6, 'sqft' => 5400, 'price' => 18900000, 'title_en' => 'Signature Penthouse with Private Terrace', 'title_ar' => 'بنتهاوس مميز مع تراس خاص'],
        ['type' => 'apartment', 'listing' => 'sale', 'status' => 'ready', 'bed' => 2, 'bath' => 2, 'sqft' => 1080, 'price' => 1650000, 'title_en' => 'Waterfront 2BR Apartment with Marina View', 'title_ar' => 'شقة غرفتي نوم على الواجهة المائية بإطلالة على المرسى'],
        ['type' => 'apartment', 'listing' => 'rent', 'status' => 'ready', 'bed' => 2, 'bath' => 2, 'sqft' => 1200, 'price' => 95000, 'title_en' => '2 Bedroom Apartment for Rent, High Floor', 'title_ar' => 'شقة غرفتي نوم للإيجار في طابق مرتفع'],
        ['type' => 'villa', 'listing' => 'sale', 'status' => 'ready', 'bed' => 3, 'bath' => 4, 'sqft' => 3100, 'price' => 4350000, 'title_en' => '3 Bedroom Villa, Quiet Cul-de-Sac', 'title_ar' => 'فيلا 3 غرف نوم في نهاية طريق هادئ'],
        ['type' => 'apartment', 'listing' => 'sale', 'status' => 'off_plan', 'bed' => 1, 'bath' => 1, 'sqft' => 750, 'price' => 980000, 'title_en' => 'Smart 1BR Apartment, Branded Residence', 'title_ar' => 'شقة ذكية غرفة نوم واحدة ضمن مقيم بعلامة تجارية'],
        ['type' => 'townhouse', 'listing' => 'sale', 'status' => 'ready', 'bed' => 4, 'bath' => 4, 'sqft' => 2600, 'price' => 3450000, 'title_en' => 'End-Unit Townhouse with Roof Terrace', 'title_ar' => 'تاون هاوس بوحدة طرفية مع تراس على السطح'],
    ];

    public function run(): void
    {
        $locationFilterId = Filter::where('key', 'location')->value('id');
        $categoryFilterId = Filter::where('key', 'category')->value('id');

        if ($locationFilterId) {
            foreach (self::LOCATIONS as $i => $loc) {
                FilterValue::firstOrCreate(
                    ['filter_id' => $locationFilterId, 'value' => $loc['value']],
                    ['translations' => ['en' => ['label' => $loc['en']], 'ar' => ['label' => $loc['ar']]], 'order_index' => $i, 'status' => true]
                );
            }
        }

        if ($categoryFilterId) {
            foreach (self::CATEGORIES as $i => $cat) {
                FilterValue::firstOrCreate(
                    ['filter_id' => $categoryFilterId, 'value' => $cat['value']],
                    ['translations' => ['en' => ['label' => $cat['en']], 'ar' => ['label' => $cat['ar']]], 'order_index' => $i, 'status' => true]
                );
            }
        }

        $companies = PortalUser::where('type', 'company')->where('status', 'approved')->get();
        $agents = PortalUser::where('type', 'agent')->where('status', 'approved')->get();

        $startNumber = ((int) (Property::where('reference_no', 'like', 'PROP%')
            ->pluck('reference_no')
            ->map(fn ($ref) => (int) substr($ref, 4))
            ->max())) + 1;

        foreach (self::LISTINGS as $i => $listing) {
            $number = $startNumber + $i;
            $referenceNo = 'PROP' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $location = self::LOCATIONS[$i % count(self::LOCATIONS)];
            $category = self::CATEGORIES[$i % count(self::CATEGORIES)];
            $slug = Str::slug($listing['title_en'] . '-' . $referenceNo);

            $translations = [
                'en' => [
                    'title' => $listing['title_en'],
                    'key_features' => "{$listing['bed']} Bedrooms, {$listing['bath']} Bathrooms, {$listing['sqft']} sqft",
                    'description' => "A {$listing['title_en']} located in {$location['en']}, Dubai. Featuring {$listing['bed']} bedrooms and {$listing['bath']} bathrooms across {$listing['sqft']} sqft of well-planned living space, close to key amenities, retail and transport links.",
                    'address' => $location['en'] . ', Dubai, United Arab Emirates',
                    'community' => $location['en'],
                    'city' => 'Dubai',
                    'country' => 'United Arab Emirates',
                ],
                'ar' => [
                    'title' => $listing['title_ar'],
                    'key_features' => "{$listing['bed']} غرف نوم، {$listing['bath']} حمامات، {$listing['sqft']} قدم مربع",
                    'description' => "{$listing['title_ar']} يقع في {$location['ar']}، دبي. يضم {$listing['bed']} غرف نوم و{$listing['bath']} حمامات على مساحة {$listing['sqft']} قدم مربع، بالقرب من أهم الخدمات والمرافق ووسائل النقل.",
                    'address' => $location['ar'] . '، دبي، الإمارات العربية المتحدة',
                    'community' => $location['ar'],
                    'city' => 'دبي',
                    'country' => 'الإمارات العربية المتحدة',
                ],
            ];

            $company = $companies->isNotEmpty() ? $companies[$i % $companies->count()] : null;
            $agent = $agents->isNotEmpty() ? $agents[$i % $agents->count()] : null;

            $property = Property::create([
                'portal_user_id' => $company?->id,
                'agent_id' => $agent?->id,
                'translations' => $translations,
                'slug' => $slug,
                'reference_no' => $referenceNo,
                'rera_id' => 'RERA' . random_int(10000, 99999),
                'listing_type' => $listing['listing'],
                'completion_status' => $listing['status'],
                'property_type' => $listing['type'],
                'category' => $categoryFilterId ? $category['value'] : null,
                'location' => $locationFilterId ? $location['value'] : null,
                'postal_code' => '00000',
                'latitude' => 25.0 + ($i * 0.01),
                'longitude' => 55.1 + ($i * 0.01),
                'bedrooms' => $listing['bed'],
                'bathrooms' => $listing['bath'],
                'sqft' => $listing['sqft'],
                'price' => $listing['price'],
                'currency' => 'AED',
                'featured' => $i < 4,
                'status' => true,
                'published_at' => now()->subDays(20 - $i),
                'order_index' => $i,
            ]);

            PropertyDetail::create([
                'property_id' => $property->id,
                'year_built' => 2018 + ($i % 7),
                'floor' => $listing['type'] === 'apartment' || $listing['type'] === 'penthouse' ? (string) (($i % 25) + 1) : 'Ground',
                'parking' => 1 + ($i % 3),
                'garage' => $listing['type'] === 'villa' || $listing['type'] === 'townhouse' ? 1 : 0,
                'furnished' => $i % 2 === 0,
                'direct_from_owner' => $i % 4 === 0 ? 'Yes' : 'No',
                'view' => $i % 2 === 0 ? 'Community View' : 'Skyline View',
            ]);

            $this->attachGallery($property, $referenceNo, $i);
        }
    }

    private function attachGallery(Property $property, string $referenceNo, int $index): void
    {
        $gallery = app(\App\Services\PropertyGallery::class);
        $folder = $gallery->folderValue('properties/' . $referenceNo); // Cloudinary folder URL when configured
        $count = 3;
        $sequence = [];

        for ($n = 1; $n <= $count; $n++) {
            $sourceRelative = self::IMAGE_POOL[($index * $count + $n - 1) % count(self::IMAGE_POOL)];
            $sourcePath = public_path($sourceRelative);
            if (!is_file($sourcePath)) {
                continue;
            }

            $jpeg = $this->toJpeg($sourcePath);
            if (!$jpeg) {
                continue;
            }

            $gallery->put($folder, "{$referenceNo}-{$n}.jpeg", $jpeg);
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

    private function toJpeg(string $path): ?string
    {
        $mime = @getimagesize($path)['mime'] ?? null;
        $source = match ($mime) {
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => @imagecreatefromjpeg($path),
        };
        if (!$source) {
            $source = @imagecreatefromstring(file_get_contents($path));
        }
        if (!$source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $flattened = imagecreatetruecolor($width, $height);
        imagefill($flattened, 0, 0, imagecolorallocate($flattened, 255, 255, 255));
        imagecopy($flattened, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        ob_start();
        imagejpeg($flattened, null, 85);
        $data = ob_get_clean();
        imagedestroy($flattened);

        return $data ?: null;
    }
}
