<?php

namespace Database\Seeders;

use CMS\SiteManager\Database\Seeders\CmsRolesPermissionsSeeder;
use CMS\SiteManager\Database\Seeders\MetadataSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use CMS\SiteManager\Database\Seeders\CmsRolesPermissionsSeeder;
use CMS\SiteManager\Database\Seeders\MetadataSeeder;

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
            PlanSeeder::class
        ]);
    }
}
