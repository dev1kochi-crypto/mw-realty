<?php

namespace Database\Seeders;

use App\Models\PortalUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds the agent/agency roster shown on the public /agents and /agencies pages — reusing the
 * same names, avatars and preferred areas that used to be hardcoded directly in Agents.vue/
 * Agencies.vue, now as real PortalUser records those pages fetch from the API.
 */
class PortalUserSeeder extends Seeder
{
    private const AGENTS = [
        ['name' => 'Ahmed Hassan Al Mansoori', 'image' => 'agents/ahmed.png', 'years' => 7, 'areas' => ['Downtown Dubai', 'Dubai Marina', 'Business Bay'], 'badges' => ['MW Broker', 'Quality Lister'], 'company' => 'DXB Dubai Properties'],
        ['name' => 'Jayme Craig', 'image' => 'agents/jayme.png', 'years' => 5, 'areas' => ['Jumeirah', 'Business Bay'], 'badges' => ['MW Broker', 'Quality Lister', 'Responsive Broker'], 'company' => 'DXB Dubai Properties'],
        ['name' => 'Abhishek Mohan', 'image' => 'agents/abhishek.png', 'years' => 7, 'areas' => ['Downtown Dubai', 'Dubai Marina', 'Business Bay'], 'badges' => ['Responsive Broker'], 'company' => 'Kaal Real Estate Agency'],
        ['name' => 'James Thomas', 'image' => 'agents/james.png', 'years' => 8, 'areas' => ['Downtown Dubai', 'Dubai Marina', 'Business Bay'], 'badges' => ['MW Broker'], 'company' => 'Kaal Real Estate Agency'],
        ['name' => 'Aslam Ali Imran', 'image' => 'agents/aslam.png', 'years' => 6, 'areas' => ['Downtown Dubai', 'Dubai Marina', 'Business Bay'], 'badges' => [], 'company' => 'ABC Real Estate'],
    ];

    private const AGENCIES = [
        ['name' => 'Kaal Real Estate Agency', 'logo' => 'agencies/logo-kaal.png', 'founding_year' => 2015, 'area' => 'Downtown Dubai'],
        ['name' => 'Elite Homes Realty', 'logo' => 'agencies/logo-elite-homes.png', 'founding_year' => 2018, 'area' => 'Dubai Marina'],
        ['name' => 'ABC Real Estate', 'logo' => 'agencies/logo-abc-real-estate.png', 'founding_year' => 2012, 'area' => 'Palm Jumeirah'],
        ['name' => 'Violet Edwards', 'logo' => 'agencies/logo-violet-edwards.png', 'founding_year' => 2019, 'area' => 'Jumeirah Village Circle'],
        ['name' => 'Skyline Properties', 'logo' => 'agencies/logo-skyline.png', 'founding_year' => 2016, 'area' => 'Dubai Hills Estate'],
        ['name' => 'Blue Horizon Real Estate', 'logo' => 'agencies/logo-blue-horizon.png', 'founding_year' => 2020, 'area' => 'Arabian Ranches'],
        ['name' => 'Dalton Wade', 'logo' => 'agencies/logo-dalton-wade.png', 'founding_year' => 2010, 'area' => 'Al Furjan'],
        ['name' => 'DXB Dubai Properties', 'logo' => 'agencies/logo-dxb-dubai.png', 'founding_year' => 2025, 'area' => 'Business Bay'],
    ];

    public function run(): void
    {
        $companyIds = [];
        foreach (self::AGENCIES as $agency) {
            $slug = Str::slug($agency['name']);
            $portalUser = PortalUser::updateOrCreate(
                ['email' => Str::slug($agency['name']).'@example.com'],
                [
                    'type' => 'company',
                    'slug' => $slug,
                    'name' => $agency['name'],
                    'company_name' => $agency['name'],
                    'phone' => '+9715' . random_int(10000000, 99999999),
                    'password' => Hash::make(Str::random(32)),
                    'status' => 'approved',
                    'status_changed_at' => now(),
                    'is_active' => true,
                    'avatar' => $this->copyImage($agency['logo'], $slug),
                    'office_address' => $agency['area'] . ', Dubai, United Arab Emirates',
                    'website' => 'https://' . $slug . '.example.com',
                    'founding_year' => $agency['founding_year'],
                    'orn_number' => 'ORN' . random_int(100000, 999999),
                    'translations' => [
                        'bio' => [
                            'en' => "{$agency['name']} is a trusted Dubai real estate agency helping buyers, sellers and tenants find the right property with confidence.",
                            'ar' => "{$agency['name']} وكالة عقارية موثوقة في دبي تساعد المشترين والبائعين والمستأجرين على إيجاد العقار المناسب بثقة.",
                        ],
                    ],
                ]
            );
            $companyIds[$agency['name']] = $portalUser->id;
        }

        foreach (self::AGENTS as $agent) {
            $slug = Str::slug($agent['name']);
            PortalUser::updateOrCreate(
                ['email' => $slug.'@example.com'],
                [
                    'type' => 'agent',
                    'slug' => $slug,
                    'name' => $agent['name'],
                    'phone' => '+9715' . random_int(10000000, 99999999),
                    'whatsapp_number' => '+9715' . random_int(10000000, 99999999),
                    'password' => Hash::make(Str::random(32)),
                    'status' => 'approved',
                    'status_changed_at' => now(),
                    'is_active' => true,
                    'avatar' => $this->copyImage($agent['image'], $slug),
                    'years_of_experience' => $agent['years'],
                    'preferred_areas' => $agent['areas'],
                    'badges' => $agent['badges'],
                    'company_id' => $companyIds[$agent['company']] ?? null,
                    'translations' => [
                        'bio' => [
                            'en' => "{$agent['name']} is a Dubai-based real estate broker with {$agent['years']} years of experience helping clients buy, sell and rent across " . implode(', ', $agent['areas']) . '.',
                            'ar' => "{$agent['name']} وسيط عقاري مقيم في دبي بخبرة {$agent['years']} سنوات في مساعدة العملاء على الشراء والبيع والإيجار.",
                        ],
                    ],
                ]
            );
        }
    }

    /** Copies a static frontend image into the public storage disk so it can be served via asset('storage/...'), same as an admin-uploaded avatar would be. */
    private function copyImage(string $relativePath, string $slug): ?string
    {
        $source = public_path('frontend/assets/images/' . $relativePath);
        if (!is_file($source)) {
            return null;
        }

        $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
        $destination = 'portal-users/' . $slug . '.' . $extension;
        Storage::disk('public')->put($destination, file_get_contents($source));

        return $destination;
    }
}
