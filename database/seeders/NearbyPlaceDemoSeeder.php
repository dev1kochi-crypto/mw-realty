<?php

namespace Database\Seeders;

use App\Models\NearbyPlace;
use App\Models\Property;
use Illuminate\Database\Seeder;

/**
 * Demo data: well-known Dubai landmarks for the newer nearby place types (metro, malls, beaches, ...)
 * as shared places, then tags active listings with the closest places (any type) within a few km —
 * so the property detail page's map has something to show. Coordinates are approximate.
 * Safe to re-run: places are matched by English name, tags are only added, never removed.
 *
 *   php artisan db:seed --class=NearbyPlaceDemoSeeder
 */
class NearbyPlaceDemoSeeder extends Seeder
{
    private const RADIUS_KM = 5;
    private const PER_PROPERTY = 8;
    private const MAX_PROPERTIES = 80;

    private const PLACES = [
        // [type, name, address, lat, lng]
        ['metro_station', 'Burj Khalifa / Dubai Mall Metro Station', 'Sheikh Zayed Road, Downtown Dubai', 25.2010, 55.2696],
        ['metro_station', 'Business Bay Metro Station', 'Sheikh Zayed Road, Business Bay', 25.1913, 55.2603],
        ['metro_station', 'Mall of the Emirates Metro Station', 'Sheikh Zayed Road, Al Barsha', 25.1213, 55.1997],
        ['metro_station', 'DMCC Metro Station', 'Sheikh Zayed Road, Jumeirah Lake Towers', 25.0710, 55.1387],
        ['shopping_mall', 'The Dubai Mall', 'Financial Centre Road, Downtown Dubai', 25.1972, 55.2796],
        ['shopping_mall', 'Mall of the Emirates', 'Sheikh Zayed Road, Al Barsha', 25.1181, 55.2006],
        ['shopping_mall', 'Dubai Marina Mall', 'Sheikh Zayed Road, Dubai Marina', 25.0763, 55.1405],
        ['shopping_mall', 'City Centre Mirdif', 'Sheikh Mohammed Bin Zayed Road, Mirdif', 25.2163, 55.4078],
        ['supermarket', 'Carrefour Mall of the Emirates', 'Mall of the Emirates, Al Barsha', 25.1185, 55.2002],
        ['supermarket', 'Waitrose Dubai Marina', 'Dubai Marina Mall, Dubai Marina', 25.0766, 55.1402],
        ['beach', 'JBR Beach', 'The Walk, Jumeirah Beach Residence', 25.0790, 55.1340],
        ['beach', 'Kite Beach', 'Jumeirah 3, Umm Suqeim', 25.1580, 55.1960],
        ['beach', 'La Mer Beach', 'Jumeirah 1', 25.2290, 55.2560],
        ['park', 'Zabeel Park', 'Sheikh Khalifa Bin Zayed Road, Zabeel', 25.2310, 55.2930],
        ['park', 'Safa Park', 'Al Wasl Road, Al Safa', 25.1860, 55.2430],
        ['park', 'Al Barsha Pond Park', 'Al Barsha 2', 25.1000, 55.2030],
        ['mosque', 'Jumeirah Mosque', 'Jumeirah Beach Road, Jumeirah 1', 25.2340, 55.2660],
        ['mosque', 'Al Farooq Omar Bin Al Khattab Mosque', 'Al Wasl Road, Al Safa', 25.1780, 55.2360],
        ['airport', 'Dubai International Airport (DXB)', 'Al Garhoud', 25.2532, 55.3657],
        ['airport', 'Al Maktoum International Airport (DWC)', 'Dubai South', 24.8960, 55.1610],
        ['university', 'American University in Dubai', 'Sheikh Zayed Road, Dubai Media City', 25.0930, 55.1600],
        ['university', 'Heriot-Watt University Dubai', 'Dubai Knowledge Park', 25.1020, 55.1640],
        ['clinic', 'Aster Clinic JLT', 'Cluster F, Jumeirah Lake Towers', 25.0700, 55.1420],
        ['pharmacy', 'Life Pharmacy Business Bay', 'Bay Square, Business Bay', 25.1860, 55.2650],
        ['gym', 'Fitness First Dubai Marina', 'Dubai Marina', 25.0800, 55.1410],
        ['cafe', 'Arabian Tea House', 'Al Fahidi Historical District, Bur Dubai', 25.2635, 55.2995],
        ['hotel', 'Burj Al Arab', 'Jumeirah Beach Road, Umm Suqeim 3', 25.1412, 55.1853],
        ['hotel', 'Atlantis The Palm', 'Crescent Road, Palm Jumeirah', 25.1304, 55.1171],
    ];

    public function run(): void
    {
        foreach (self::PLACES as [$type, $name, $address, $lat, $lng]) {
            $place = NearbyPlace::whereNull('portal_user_id')->where('translations->en->name', $name)->first() ?? new NearbyPlace();
            $place->fill([
                'portal_user_id' => null,
                'category' => $type,
                'translations' => ['en' => ['name' => $name, 'address' => "{$address}, Dubai"]],
                'latitude' => $lat,
                'longitude' => $lng,
                'status' => true,
            ])->save();
        }

        $places = NearbyPlace::active()->whereNotNull('latitude')->whereNotNull('longitude')->get();

        // Listings with no nearby places yet — leave the ones already tagged by hand alone.
        $properties = Property::where('status', true)->whereNotNull('latitude')->whereNotNull('longitude')
            ->doesntHave('nearbyPlaces')->inRandomOrder()->get();

        $tagged = 0;
        foreach ($properties as $property) {
            $closest = $places
                ->map(fn ($p) => ['id' => $p->id, 'km' => $this->distanceKm((float) $property->latitude, (float) $property->longitude, (float) $p->latitude, (float) $p->longitude)])
                ->filter(fn ($p) => $p['km'] <= self::RADIUS_KM)
                ->sortBy('km')
                ->take(self::PER_PROPERTY);

            if ($closest->count() < 2) {
                continue;
            }
            $property->nearbyPlaces()->syncWithoutDetaching($closest->pluck('id')->all());
            if (++$tagged >= self::MAX_PROPERTIES) {
                break;
            }
        }

        $this->command?->info('Nearby places: ' . count(self::PLACES) . " demo places, {$tagged} listings tagged.");
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
