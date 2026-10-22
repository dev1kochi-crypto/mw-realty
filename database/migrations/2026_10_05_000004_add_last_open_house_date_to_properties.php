<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Latest of available_dates (kept in sync by Property::saving) — the "Open house" filter
            // matches listings where it's today or later, without querying inside the JSON.
            $table->date('last_open_house_date')->nullable()->index()->after('available_dates');
        });

        DB::table('properties')->whereNotNull('available_dates')->orderBy('id')->each(function ($row) {
            $dates = array_filter((array) json_decode($row->available_dates, true), 'is_string');
            if ($dates) {
                DB::table('properties')->where('id', $row->id)->update(['last_open_house_date' => max($dates)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['last_open_house_date']);
            $table->dropColumn('last_open_house_date');
        });
    }
};
