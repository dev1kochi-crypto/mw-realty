<?php

namespace Database\Seeders;

use CMS\SiteManager\Database\Seeders\CmsRolesPermissionsSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

       $this->call([
            CmsRolesPermissionsSeeder::class,
            SeoPageMetadataSeeder::class,
            PlanSeeder::class,
            CrmAdminMasterDataSeeder::class,
            PortalUserSeeder::class,
            PropertySeeder::class,
            NearbyPlaceSeeder::class, // after PropertySeeder — tags properties by community
            CommercialPropertySeeder::class,
            FilterSeeder::class,
        ]);
    }
}
