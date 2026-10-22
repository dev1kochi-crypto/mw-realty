<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who last edited a listing (admin | agency | agent + id), alongside created_by_* — shown in the
 * Listing Performance panel's Overview. Existing listings start as "last edited by their creator".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('updated_by_type', 20)->nullable()->after('created_by_id');
            $table->unsignedBigInteger('updated_by_id')->nullable()->after('updated_by_type');
        });

        DB::table('properties')->update([
            'updated_by_type' => DB::raw('created_by_type'),
            'updated_by_id' => DB::raw('created_by_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['updated_by_type', 'updated_by_id']);
        });
    }
};
