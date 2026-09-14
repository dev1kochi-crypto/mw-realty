<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the New/Contacted/Closed pipeline status with a simpler
 * Active/Inactive flag. Existing "closed" leads become "inactive"; "new" and
 * "contacted" both become "active" — the closest equivalent mapping.
 */
return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE leads MODIFY status VARCHAR(20) NOT NULL DEFAULT 'active'");

        DB::table('leads')->where('status', 'closed')->update(['status' => 'inactive']);
        DB::table('leads')->whereIn('status', ['new', 'contacted'])->update(['status' => 'active']);

        DB::statement("ALTER TABLE leads MODIFY status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE leads MODIFY status VARCHAR(20) NOT NULL DEFAULT 'new'");

        DB::table('leads')->where('status', 'inactive')->update(['status' => 'closed']);
        DB::table('leads')->where('status', 'active')->update(['status' => 'new']);

        DB::statement("ALTER TABLE leads MODIFY status ENUM('new', 'contacted', 'closed') NOT NULL DEFAULT 'new'");
    }
};
