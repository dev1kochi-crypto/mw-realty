<?php

namespace Database\Seeders;

use App\Models\Filter;
use App\Models\FilterValue;
use App\Models\NearbyPlace;
use App\Models\PropertyDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds every list under CRM › Master › Property Options (property form dropdowns, Amenities /
 * Easy Access / Attributes with icons, nearby place types). Safe to re-run: only missing options
 * are added — names, icons, order and on/off that Super Admin changed are left alone.
 *
 * Also links listings saved before these lists existed: their free-text amenity / easy access /
 * attribute rows are matched to an option (by name or a known alias) and given its key; a name
 * nothing matches becomes a new option, so no listing loses anything.
 *
 *   php artisan db:seed --class=PropertyOptionsSeeder
 */
class PropertyOptionsSeeder extends Seeder
{
    /** Prefix for a default icon below ("icon:pets.svg" → ICON_URLS['pets.svg']). */
    private const ICONS = 'icon:';

    /** Default option icons, already on Cloudinary (MW/property-options/icons) — no local copies are kept. */
    private const ICON_URLS = [
        'ac.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845220/MW/property-options/icons/WpCf6nKnn8I3jm1gYvtcJDKyTMFuIxLAKEoG9rx2.svg',
        'airport.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845299/MW/property-options/icons/soZQGoDceMgMJl90xAKYnYjYzGw0dr6VxpzDRr1N.svg',
        'amenity-balcony.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845212/MW/property-options/icons/wy9ifMFP2r1gZkEKNHuSwkoxhvf6Bzn25yf5aGz8.svg',
        'amenity-cctv.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845281/MW/property-options/icons/of7A4rj89ZPmeOQ8R26m6H9J6zXvilzuF8aPtI57.svg',
        'amenity-fire-alarm.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845283/MW/property-options/icons/tJOjqrsjh4hpImWt1sPpaaPoFchhbKzS5OiIqWpG.svg',
        'amenity-garden.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845237/MW/property-options/icons/z58zFmohdYOWvnr1WydxxoSXttCTYqxko9W5UilH.svg',
        'amenity-gym.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845258/MW/property-options/icons/pO0KwNc09PqNoBeERu2TBUOZ52d3Vl7KGN1L10z6.svg',
        'amenity-parking.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845222/MW/property-options/icons/lf2PeLcFieXOBK6S2xHq61M3lPVofZCs18OFpbVu.svg',
        'amenity-playground.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845269/MW/property-options/icons/GNLfNj7OeXIHA8vl2buTbUe4oRk4aJjirtMkDHEZ.svg',
        'amenity-pool.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845240/MW/property-options/icons/w2ToZX9vF4DvNUciQ8DtPftgUveneNpwUEKdBP9P.svg',
        'amenity-security.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845250/MW/property-options/icons/0DDAMTS58CQXu93Ej9C7qzO5Lzz8hpuBdlDTbQ9I.svg',
        'amenity-wifi.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845279/MW/property-options/icons/CueKkSVl3dBtMRVJijhY5zu1z0S2pzABMz1VpzQL.svg',
        'bbq.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845215/MW/property-options/icons/Z17A0EtXTjX5QjeNtlafd4HRJ5dNe3pVaZFPyJR1.svg',
        'beach.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845310/MW/property-options/icons/DrPuyRLD42vepDD2iim9yHBOSwbfG8q733TcvnHQ.svg',
        'brand-new.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845288/MW/property-options/icons/hvcnYdqMbGdkDfgQXKdonXroTAYWREX0PGfizotd.svg',
        'bus.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845293/MW/property-options/icons/6dXlH2s5pPMe8VKC9ZHVCko0P2wFmpBIqAPMzPMu.svg',
        'concierge.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845253/MW/property-options/icons/7WJC0cFBfzwbIevEFdS8Qan0R4g5G8BnICPvTQlo.svg',
        'corner.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845316/MW/property-options/icons/TNGCOcL3PWAh4STpRj4apTk45saI3dBX1k6wc60o.svg',
        'default.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845286/MW/property-options/icons/Z7BVaOwPvkfx8waKUbbFhS36AKMd85bnmKzuIBAE.svg',
        'duplex.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845322/MW/property-options/icons/odoeRBuMsGRxSpJGOVJWgcR6q40ln2HAtxrdTuE6.svg',
        'high-floor.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845318/MW/property-options/icons/tpNL5TbtOTqaIE7Y7wFYCuZPAs2FpBC46DQN67x8.svg',
        'highway.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845296/MW/property-options/icons/2Ny9wajz8os5fUEFsfKKIM2hUhwZNF8uO9mx6WF1.svg',
        'hospital.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845308/MW/property-options/icons/9qf4CU3dslONVjmYfLwyHKBMSTHmtpP8sEcU619h.svg',
        'jacuzzi.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845227/MW/property-options/icons/Uph51oDrgszvoXRJC6SOFIwjz65wbkVHaU3I3osK.svg',
        'kids-pool.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845274/MW/property-options/icons/5Sic8zv6wUOJzGBCdrcwSUuidhR9Be6ksWq2qK5q.svg',
        'kitchen.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845229/MW/property-options/icons/Lf8OHCPGcFphiwaGXD8uPGnnODLeZzSB4qkF1iPT.svg',
        'landmark-view.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845266/MW/property-options/icons/Q45gFxSXwgmDcv1Xl4ltnVsXfnBl6aosb31GG5vW.svg',
        'lobby.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845271/MW/property-options/icons/mAEkz4q4UEXEbvhT1tDjKTfRZJlJY9WCSzVojaS8.svg',
        'low-floor.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845320/MW/property-options/icons/cq4VSYpmw1uE6evhQVpNcWAb4ueryRuJ3xe371bn.svg',
        'maid-room.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845232/MW/property-options/icons/LJvXI7PIQ0TN3B4URpg4dHOTVvABUCj0cPxLeo1t.svg',
        'maid-service.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845261/MW/property-options/icons/20PPmagsbwrXxJGOBti66vxnspVXonk5JAZJI4PL.svg',
        'mall.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845301/MW/property-options/icons/BAkTpgaFXCO2NQsRJKHZFDZVAzW2R0zRcE5Qqc2B.svg',
        'metro.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845291/MW/property-options/icons/cd4KGreBUbm8Mt0Q7cccJ1olbVHhcPLk6hXtpHra.svg',
        'mosque.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845313/MW/property-options/icons/iCjjR7epLQBG07crxVnhaSA3CPgMNWhtcEWchANH.svg',
        'penthouse.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845325/MW/property-options/icons/JZ2kAeseq0s7EOMwT0g6GMeCWKjQgcRqZzH1UH1T.svg',
        'pets.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845234/MW/property-options/icons/FGV41vx04yLccgKWEo7y90xQ0I6qQJVpz8ujvlaj.svg',
        'private-gym.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845225/MW/property-options/icons/iUeGSe5wKFkw0RbtzJmlPT9EjrDyLp0W5j2aJWm5.svg',
        'school.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845305/MW/property-options/icons/4s5iUhyqIluHQrXA7OCg5hz2hEwzeK5Wxg3jZRX6.svg',
        'shared-pool.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845243/MW/property-options/icons/ZuMWVwD3Ew87fHM8a9h7p1VQhqH5CvRQEZMdZtYY.svg',
        'smart-home.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845333/MW/property-options/icons/0f0d8FEaL9nvFnfRSu5LHDM8spHICUpaIUf3vGUQ.svg',
        'spa.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845255/MW/property-options/icons/42gXZCWt28E4UL7FfkAJK1K3fgmGaKe7ad96NR8u.svg',
        'study.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845245/MW/property-options/icons/WmyPC7QOSLbG8kn6POfw7a1eCaXpOjLR6GF10R6P.svg',
        'supermarket.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845303/MW/property-options/icons/XmShfV0Qk6gFhKadDP0h0mutnvDX3ug4b0rhRuOB.svg',
        'tenanted.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845330/MW/property-options/icons/GXmwsXU7fxF1JMUjXOrr9QljyEMwHxmjCvFbE1jc.svg',
        'vacant.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845328/MW/property-options/icons/mBkxWVpkVI6h8wbdCr06xv0wCDfh0K31RVvzND9d.svg',
        'vastu.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845276/MW/property-options/icons/S1xCp2woJ5o2KfkonNXf845xWpOvYS8sDsRTys3y.svg',
        'walk-in-closet.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845263/MW/property-options/icons/Fyu51Mdy6LUkkPE0czmUoaV0avfnjAAzosYQeYky.svg',
        'wardrobe.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845217/MW/property-options/icons/8FuCeOm9Jx5b3t2I85VAKqxu0Tf6bZkhem8o16DP.svg',
        'water-view.svg' => 'https://res.cloudinary.com/dvgxfjqjj/image/upload/v1790845248/MW/property-options/icons/KbZpiNkekyXkgQotRHDNbIQUtOJSvps0AdJ9TfDQ.svg',
    ];

    /** key => [label (filter name), [value => [en, ar, icon file?]]] */
    private function lists(): array
    {
        $o = self::ICONS;

        return [
            'property_type' => ['Property Type', [
                'apartment' => ['Apartment', 'شقة'], 'villa' => ['Villa', 'فيلا'], 'townhouse' => ['Townhouse', 'تاون هاوس'],
                'penthouse' => ['Penthouse', 'بنتهاوس'], 'condo' => ['Condo', 'كوندو'], 'residential' => ['Residential Building', 'مبنى سكني'],
                'whole-building' => ['Whole Building', 'مبنى كامل'], 'plot' => ['Plot', 'قطعة أرض'], 'office' => ['Office', 'مكتب'],
                'co-working' => ['Co-working Space', 'مساحة عمل مشتركة'], 'retail-shop' => ['Retail Shop', 'محل تجاري'],
                'showroom' => ['Showroom', 'صالة عرض'], 'warehouse' => ['Warehouse', 'مستودع'],
            ]],
            'listing_type' => ['Listing Type', ['rent' => ['Rent', 'إيجار'], 'sale' => ['Sale', 'بيع']]],
            'category' => ['Category', ['residential' => ['Residential', 'سكني'], 'commercial' => ['Commercial', 'تجاري']]],
            'completion_status' => ['Completion Status', ['ready' => ['Ready', 'جاهز'], 'off_plan' => ['Off-Plan', 'على الخريطة']]],
            Filter::FURNISHING_KEY => ['Furnishing', [
                'unfurnished' => ['Unfurnished', 'غير مفروش'], 'semi_furnished' => ['Semi furnished', 'مفروش جزئياً'], 'furnished' => ['Furnished', 'مفروش'],
            ]],
            Filter::EMIRATE_KEY => ['Emirate', [
                'dubai' => ['Dubai', 'دبي'], 'abu_dhabi' => ['Abu Dhabi', 'أبوظبي'], 'northern_emirates' => ['Northern Emirates', 'الإمارات الشمالية'],
            ]],
            Filter::RENTAL_PERIOD_KEY => ['Rental Period', [
                'yearly' => ['Per year', 'سنوياً'], 'monthly' => ['Per month', 'شهرياً'], 'weekly' => ['Per week', 'أسبوعياً'], 'daily' => ['Per day', 'يومياً'],
            ]],
            'amenity' => ['Amenities', [
                'balcony' => ['Balcony', 'شرفة', $o . 'amenity-balcony.svg'],
                'barbecue_area' => ['Barbecue Area', 'منطقة شواء', $o . 'bbq.svg'],
                'built_in_wardrobes' => ['Built in Wardrobes', 'خزائن مدمجة', $o . 'wardrobe.svg'],
                'central_ac' => ['Central A/C', 'تكييف مركزي', $o . 'ac.svg'],
                'covered_parking' => ['Covered Parking', 'موقف مغطى', $o . 'amenity-parking.svg'],
                'private_gym' => ['Private Gym', 'صالة رياضية خاصة', $o . 'private-gym.svg'],
                'private_jacuzzi' => ['Private Jacuzzi', 'جاكوزي خاص', $o . 'jacuzzi.svg'],
                'kitchen_appliances' => ['Kitchen Appliances', 'أجهزة المطبخ', $o . 'kitchen.svg'],
                'maids_room' => ['Maids Room', 'غرفة خادمة', $o . 'maid-room.svg'],
                'pets_allowed' => ['Pets Allowed', 'يسمح بالحيوانات الأليفة', $o . 'pets.svg'],
                'private_garden' => ['Private Garden', 'حديقة خاصة', $o . 'amenity-garden.svg'],
                'private_pool' => ['Private Pool', 'مسبح خاص', $o . 'amenity-pool.svg'],
                'shared_pool' => ['Shared Pool', 'مسبح مشترك', $o . 'shared-pool.svg'],
                'study' => ['Study', 'غرفة دراسة', $o . 'study.svg'],
                'view_of_water' => ['View of Water', 'إطلالة مائية', $o . 'water-view.svg'],
                'security' => ['Security', 'أمن', $o . 'amenity-security.svg'],
                'concierge' => ['Concierge', 'كونسيرج', $o . 'concierge.svg'],
                'shared_spa' => ['Shared Spa', 'سبا مشترك', $o . 'spa.svg'],
                'shared_gym' => ['Shared Gym', 'صالة رياضية مشتركة', $o . 'amenity-gym.svg'],
                'maid_service' => ['Maid Service', 'خدمة التنظيف', $o . 'maid-service.svg'],
                'walk_in_closet' => ['Walk-in Closet', 'غرفة ملابس', $o . 'walk-in-closet.svg'],
                'view_of_landmark' => ['View of Landmark', 'إطلالة على معلم', $o . 'landmark-view.svg'],
                'childrens_play_area' => ["Children's Play Area", 'منطقة لعب للأطفال', $o . 'amenity-playground.svg'],
                'lobby_in_building' => ['Lobby in Building', 'ردهة في المبنى', $o . 'lobby.svg'],
                'childrens_pool' => ["Children's Pool", 'مسبح أطفال', $o . 'kids-pool.svg'],
                'vastu_compliant' => ['Vastu-compliant', 'متوافق مع فاستو', $o . 'vastu.svg'],
                // Commercial listings
                'high_speed_internet' => ['High-Speed Internet', 'إنترنت عالي السرعة', $o . 'amenity-wifi.svg'],
                'cctv' => ['CCTV', 'كاميرات مراقبة', $o . 'amenity-cctv.svg'],
                'fire_alarm' => ['Fire Alarm', 'إنذار حريق', $o . 'amenity-fire-alarm.svg'],
                'reception' => ['Reception', 'استقبال', $o . 'concierge.svg'],
                'meeting_rooms' => ['Meeting Rooms', 'غرف اجتماعات', $o . 'lobby.svg'],
                'pantry' => ['Pantry', 'مطبخ صغير', $o . 'kitchen.svg'],
                'loading_bay' => ['Loading Bay', 'منطقة تحميل', $o . 'default.svg'],
                'jogging_track' => ['Jogging Track', 'مسار للجري', $o . 'brand-new.svg'],
            ]],
            'easy_access' => ['Easy Access', [
                'metro_station' => ['Metro Station', 'محطة مترو', $o . 'metro.svg'],
                'bus_stop' => ['Bus Stop', 'موقف حافلات', $o . 'bus.svg'],
                'highway_access' => ['Highway Access', 'سهولة الوصول للطريق السريع', $o . 'highway.svg'],
                'airport' => ['Airport', 'مطار', $o . 'airport.svg'],
                'shopping_mall' => ['Shopping Mall', 'مركز تسوق', $o . 'mall.svg'],
                'supermarket' => ['Supermarket', 'سوبرماركت', $o . 'supermarket.svg'],
                'schools' => ['Schools', 'مدارس', $o . 'school.svg'],
                'hospital' => ['Hospital', 'مستشفى', $o . 'hospital.svg'],
                'beach' => ['Beach', 'شاطئ', $o . 'beach.svg'],
                'mosque' => ['Mosque', 'مسجد', $o . 'mosque.svg'],
            ]],
            'property_attribute' => ['Attributes', [
                'corner_unit' => ['Corner Unit', 'وحدة زاوية', $o . 'corner.svg'],
                'high_floor' => ['High Floor', 'طابق مرتفع', $o . 'high-floor.svg'],
                'low_floor' => ['Low Floor', 'طابق منخفض', $o . 'low-floor.svg'],
                'duplex' => ['Duplex', 'دوبلكس', $o . 'duplex.svg'],
                'penthouse' => ['Penthouse Level', 'طابق البنتهاوس', $o . 'penthouse.svg'],
                'brand_new' => ['Brand New', 'جديد كلياً', $o . 'brand-new.svg'],
                'vacant' => ['Vacant', 'شاغر', $o . 'vacant.svg'],
                'tenanted' => ['Tenanted', 'مؤجر', $o . 'tenanted.svg'],
                'smart_home' => ['Smart Home', 'منزل ذكي', $o . 'smart-home.svg'],
            ]],
            NearbyPlace::FILTER_KEY => ['Nearby Place Type', collect(NearbyPlace::DEFAULT_TYPES)->map(fn ($l) => [$l['en'], $l['ar']])->all()],
        ];
    }

    /** Older free-text names => [list, value]. A target in another list moves the row there (e.g. "Near Metro"). */
    private const ALIASES = [
        'gym' => ['amenity', 'shared_gym'], 'community pool' => ['amenity', 'shared_pool'], 'swimming pool' => ['amenity', 'shared_pool'],
        '24/7 security' => ['amenity', 'security'], 'security staff' => ['amenity', 'security'], 'bbq area' => ['amenity', 'barbecue_area'],
        'kids play area' => ['amenity', 'childrens_play_area'], 'playground' => ['amenity', 'childrens_play_area'],
        "maid's room" => ['amenity', 'maids_room'], 'maid’s room' => ['amenity', 'maids_room'], 'built-in wardrobes' => ['amenity', 'built_in_wardrobes'],
        'parking' => ['amenity', 'covered_parking'], 'balcony or terrace' => ['amenity', 'balcony'], 'garden' => ['amenity', 'private_garden'],
        'wifi' => ['amenity', 'high_speed_internet'],
        'near metro' => ['easy_access', 'metro_station'], 'metro access' => ['easy_access', 'metro_station'],
        'metro station - 5 min' => ['easy_access', 'metro_station'], 'shopping mall - 10 min' => ['easy_access', 'shopping_mall'],
    ];

    public function run(): void
    {
        $order = (int) Filter::max('order_index');
        foreach ($this->lists() as $key => [$name, $values]) {
            $filter = Filter::firstOrCreate(['key' => $key], [
                'type' => 'select',
                'translations' => ['en' => ['label' => $name]],
                // Only the website search filters are shown on the search bar; the rest feed the form.
                'show_on' => in_array($key, ['property_type', 'listing_type', 'category', 'completion_status'], true) ? ['home', 'listing'] : [],
                'order_index' => ++$order,
                'status' => true,
            ]);

            $existing = $filter->values()->get()->keyBy('value');
            $next = (int) $filter->values()->max('order_index');
            foreach ($values as $value => $row) {
                [$en, $ar] = $row;
                $iconFile = $row[2] ?? null;
                if ($option = $existing->get($value)) {
                    // Options that predate icons, or still point at a local file: give them the Cloudinary one.
                    if ($iconFile && (!$option->icon || !preg_match('#^https?://#', $option->icon))) {
                        $option->update(['icon' => $this->iconUrl($iconFile)]);
                    }
                    continue;
                }
                $icon = $iconFile ? $this->iconUrl($iconFile) : null;
                FilterValue::create([
                    'filter_id' => $filter->id, 'value' => $value, 'icon' => $icon, 'order_index' => ++$next, 'status' => true,
                    'translations' => ['en' => ['label' => $en], 'ar' => ['label' => $ar]],
                ]);
            }
        }

        $linked = $this->linkExistingListings();
        $refreshed = $this->refreshListingCopies();
        $this->command?->info("Property options seeded; {$linked} listing(s) linked, {$refreshed} listing(s) given the current option icons/names.");
    }

    /** Cloudinary URL of a default icon ("icon:pets.svg"); unknown names fall back to the generic one. */
    private function iconUrl(string $icon): string
    {
        return self::ICON_URLS[substr($icon, strlen(self::ICONS))] ?? self::ICON_URLS['default.svg'];
    }

    /** Listing rows keep a copy of the option's icon + name; bring those copies up to date. */
    private function refreshListingCopies(): int
    {
        $options = Filter::whereIn('key', array_keys(Filter::ICON_LISTS))->with('values')->get()
            ->mapWithKeys(fn ($f) => [$f->key => $f->values->keyBy('value')]);
        $count = 0;
        PropertyDetail::query()->each(function (PropertyDetail $detail) use ($options, &$count) {
            $dirty = false;
            foreach (Filter::ICON_LISTS as $key => $column) {
                $rows = collect($detail->{$column})->map(function ($row) use ($options, $key, &$dirty) {
                    $option = isset($row['key']) ? $options[$key]->get($row['key']) : null;
                    if ($option && ($row['icon'] ?? null) !== $option->icon) {
                        $row['icon'] = $option->icon;
                        $row['label'] = collect($option->translations)->map(fn ($t) => $t['label'] ?? '')->filter()->all();
                        $dirty = true;
                    }
                    return $row;
                })->all();
                $detail->{$column} = $rows;
            }
            if ($dirty) {
                $detail->save();
                $count++;
            }
        });

        return $count;
    }

    /** Gives every keyless amenity / easy access / attribute row on existing listings an option key. */
    private function linkExistingListings(): int
    {
        $filters = Filter::whereIn('key', array_keys(Filter::ICON_LISTS))->with('values')->get()->keyBy('key');
        $byName = [];
        foreach ($filters as $key => $filter) {
            foreach ($filter->values as $v) {
                foreach ($v->translations ?? [] as $t) {
                    if (!empty($t['label'])) {
                        $byName[$key][$this->norm($t['label'])] = $v->value;
                    }
                }
            }
        }

        $linked = 0;
        PropertyDetail::query()->each(function (PropertyDetail $detail) use ($filters, &$byName, &$linked) {
            $rows = [];
            foreach (Filter::ICON_LISTS as $key => $column) {
                $rows[$key] = collect($detail->{$column} ?? [])->filter(fn ($r) => !empty($r['key']))->values()->all();
            }
            $changed = false;
            foreach (Filter::ICON_LISTS as $key => $column) {
                foreach ($detail->{$column} ?? [] as $row) {
                    if (!empty($row['key'])) {
                        continue;
                    }
                    $labels = (array) ($row['label'] ?? []);
                    $name = $labels['en'] ?? collect($labels)->first();
                    if (!$name) {
                        continue;
                    }
                    [$targetKey, $value] = self::ALIASES[$this->norm($name)] ?? [$key, $byName[$key][$this->norm($name)] ?? null];
                    if (!$value) {
                        // Nothing matches: keep it as a new option of this list (with the listing's own icon, if any).
                        $value = Str::slug($name, '_') ?: 'option_' . Str::random(6);
                        $filters[$key]->values()->firstOrCreate(['value' => $value], [
                            'translations' => collect($labels)->filter()->map(fn ($l) => ['label' => $l])->all(),
                            'icon' => $row['icon'] ?? $this->iconUrl(self::ICONS . 'default.svg'),
                            'order_index' => (int) $filters[$key]->values()->max('order_index') + 1,
                            'status' => true,
                        ]);
                        $byName[$key][$this->norm($name)] = $value;
                    }
                    $option = $filters[$targetKey]->values()->where('value', $value)->first();
                    if ($option && !collect($rows[$targetKey])->contains('key', $value)) {
                        $rows[$targetKey][] = ['key' => $value, 'icon' => $option->icon, 'label' => collect($option->translations)->map(fn ($t) => $t['label'] ?? '')->filter()->all()];
                    }
                    $changed = true;
                }
            }
            if ($changed) {
                foreach (Filter::ICON_LISTS as $key => $column) {
                    $detail->{$column} = $rows[$key];
                }
                $detail->save();
                $linked++;
            }
        });

        return $linked;
    }

    private function norm(string $label): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $label)));
    }
}
