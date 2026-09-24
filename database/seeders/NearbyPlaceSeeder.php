<?php

namespace Database\Seeders;

use App\Models\NearbyPlace;
use App\Models\Property;
use Illuminate\Database\Seeder;

/**
 * Seeds shared (admin-managed) Dubai landmarks — a school, hospital, restaurant and attraction per
 * community used by PropertySeeder, plus a few city-wide attractions — and tags every property
 * with the places in its own community. Matching is by the property's `location` slug rather than
 * distance, since demo properties' coordinates are placeholders. Safe to re-run: places are
 * matched on their English name and tags are synced without detaching existing ones.
 */
class NearbyPlaceSeeder extends Seeder
{
    /** community slug => [category, en name, ar name, en address, lat, lng] */
    private const PLACES = [
        'downtown-dubai' => [
            ['school', 'Dubai International Academy - Downtown', 'أكاديمية دبي الدولية - وسط المدينة', 'Downtown Dubai', 25.1920, 55.2780],
            ['hospital', 'Mediclinic Dubai Mall', 'ميديكلينيك دبي مول', 'The Dubai Mall, Downtown Dubai', 25.1985, 55.2796],
            ['restaurant', 'Armani Ristorante', 'مطعم أرماني', 'Burj Khalifa, Downtown Dubai', 25.1972, 55.2744],
            ['attraction', 'Dubai Fountain', 'نافورة دبي', 'Burj Lake, Downtown Dubai', 25.1953, 55.2753],
        ],
        'dubai-marina' => [
            ['school', 'Emirates International School - Meadows', 'مدرسة الإمارات الدولية - الميدوز', 'Near Dubai Marina', 25.0700, 55.1580],
            ['hospital', 'Saudi German Hospital Dubai', 'المستشفى السعودي الألماني دبي', 'Al Barsha, near Dubai Marina', 25.0970, 55.1820],
            ['restaurant', 'Pier 7 Dining', 'بير 7', 'Dubai Marina Walk', 25.0773, 55.1397],
            ['attraction', 'Dubai Marina Walk', 'ممشى مرسى دبي', 'Dubai Marina', 25.0780, 55.1400],
        ],
        'business-bay' => [
            ['school', 'Nord Anglia International School', 'مدرسة نورد أنجليا الدولية', 'Al Barsha South, near Business Bay', 25.1050, 55.2300],
            ['hospital', 'King\'s College Hospital Clinic Business Bay', 'عيادة مستشفى كينغز كوليدج الخليج التجاري', 'Business Bay', 25.1860, 55.2650],
            ['restaurant', 'Bay Avenue Restaurants', 'مطاعم باي أفينيو', 'Bay Avenue, Business Bay', 25.1880, 55.2630],
            ['attraction', 'Dubai Water Canal Boardwalk', 'ممشى قناة دبي المائية', 'Business Bay', 25.1850, 55.2600],
        ],
        'palm-jumeirah' => [
            ['school', 'Dubai College', 'كلية دبي', 'Al Sufouh, near Palm Jumeirah', 25.1020, 55.1690],
            ['hospital', 'Emirates Hospital Clinic - Palm', 'عيادة مستشفى الإمارات - النخلة', 'Palm Jumeirah', 25.1120, 55.1390],
            ['restaurant', 'The Pointe Waterfront Dining', 'مطاعم ذا بوينت', 'The Pointe, Palm Jumeirah', 25.1480, 55.1400],
            ['attraction', 'Atlantis Aquaventure', 'أكوافنتشر أتلانتس', 'Crescent Road, Palm Jumeirah', 25.1310, 55.1170],
        ],
        'jumeirah-village-circle' => [
            ['school', 'JSS International School', 'مدرسة جي إس إس الدولية', 'Al Barsha South, near JVC', 25.0600, 55.2150],
            ['hospital', 'Aster Clinic JVC', 'عيادة أستر قرية جميرا الدائرية', 'Jumeirah Village Circle', 25.0590, 55.2090],
            ['restaurant', 'Circle Mall Food Court', 'ردهة طعام سيركل مول', 'Circle Mall, JVC', 25.0580, 55.2130],
            ['attraction', 'JVC Community Park', 'حديقة قرية جميرا الدائرية', 'Jumeirah Village Circle', 25.0610, 55.2100],
        ],
        'dubai-hills-estate' => [
            ['school', 'GEMS Wellington Academy - Al Khail', 'أكاديمية جيمس ويلينغتون - الخيل', 'Dubai Hills', 25.1050, 55.2540],
            ['hospital', 'King\'s College Hospital Dubai', 'مستشفى كينغز كوليدج دبي', 'Dubai Hills Estate', 25.1100, 55.2480],
            ['restaurant', 'Dubai Hills Mall Dining', 'مطاعم دبي هيلز مول', 'Dubai Hills Mall', 25.1020, 55.2400],
            ['attraction', 'Dubai Hills Park', 'حديقة دبي هيلز', 'Dubai Hills Estate', 25.1080, 55.2450],
        ],
        'arabian-ranches' => [
            ['school', 'Jumeira Baccalaureate School', 'مدرسة جميرا البكالوريا', 'Near Arabian Ranches', 25.0500, 55.2700],
            ['hospital', 'Mediclinic Arabian Ranches', 'ميديكلينيك المرابع العربية', 'Arabian Ranches', 25.0540, 55.2690],
            ['restaurant', 'Ranches Souk Cafes', 'مقاهي سوق المرابع', 'Arabian Ranches Community Centre', 25.0560, 55.2670],
            ['attraction', 'Dubai Polo & Equestrian Club', 'نادي دبي للبولو والفروسية', 'Arabian Ranches', 25.0450, 55.2630],
        ],
        'al-furjan' => [
            ['school', 'Dubai British School Jumeirah Park', 'مدرسة دبي البريطانية جميرا بارك', 'Near Al Furjan', 25.0450, 55.1600],
            ['hospital', 'NMC Royal Hospital DIP', 'مستشفى إن إم سي رويال', 'Dubai Investments Park, near Al Furjan', 25.0100, 55.1650],
            ['restaurant', 'Al Furjan Pavilion Dining', 'مطاعم جناح الفرجان', 'Al Furjan', 25.0290, 55.1430],
            ['attraction', 'Ibn Battuta Mall', 'مول ابن بطوطة', 'Sheikh Zayed Road, near Al Furjan', 25.0440, 55.1170],
        ],
    ];

    /** Tagged on every property (and the only tags for a property without a community). */
    private const CITY_WIDE = [
        ['attraction', 'Burj Khalifa', 'برج خليفة', 'Downtown Dubai', 25.1972, 55.2744],
        ['attraction', 'The Dubai Mall', 'دبي مول', 'Downtown Dubai', 25.1985, 55.2796],
        ['hospital', 'Rashid Hospital', 'مستشفى راشد', 'Oud Metha, Dubai', 25.2330, 55.3180],
    ];

    public function run(): void
    {
        $byCommunity = [];
        $order = 0;
        foreach (self::PLACES as $community => $rows) {
            foreach ($rows as $row) {
                $byCommunity[$community][] = $this->place($row, $order++)->id;
            }
        }
        $cityWide = array_map(fn ($row) => $this->place($row, $order++)->id, self::CITY_WIDE);

        Property::query()->each(function (Property $property) use ($byCommunity, $cityWide) {
            $ids = array_merge($byCommunity[$property->location] ?? [], $cityWide);
            $property->nearbyPlaces()->syncWithoutDetaching($ids);
        });
    }

    private function place(array $row, int $order): NearbyPlace
    {
        [$category, $en, $ar, $address, $lat, $lng] = $row;

        $place = NearbyPlace::whereNull('portal_user_id')->where('translations->en->name', $en)->first()
            ?? new NearbyPlace(['portal_user_id' => null]);

        $place->fill([
            'category' => $category,
            'translations' => [
                'en' => ['name' => $en, 'address' => $address . ', Dubai'],
                'ar' => ['name' => $ar, 'address' => 'دبي'],
            ],
            'latitude' => $lat,
            'longitude' => $lng,
            'order_index' => $order,
            'status' => true,
        ])->save();

        return $place;
    }
}
