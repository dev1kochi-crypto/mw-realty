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
            CrmAdminMasterDataSeeder::class,
            FilterSeeder::class,
            PropertyOptionsSeeder::class,
        ]);
    }
}
