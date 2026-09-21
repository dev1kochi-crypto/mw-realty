<?php

namespace Database\Seeders;

use CMS\SiteManager\Database\Seeders\CmsRolesPermissionsSeeder;
use CMS\SiteManager\Database\Seeders\MetadataSeeder;
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
            MetadataSeeder::class,
            FilterSeeder::class,
            PlanSeeder::class,
            CrmAdminMasterDataSeeder::class,
            // PortalUserSeeder must run before PropertySeeder — PropertySeeder assigns each
            // demo property to a seeded agent/agency.
            PortalUserSeeder::class,
            PropertySeeder::class,
            CommercialPropertySeeder::class,
        ]);
    }
}
