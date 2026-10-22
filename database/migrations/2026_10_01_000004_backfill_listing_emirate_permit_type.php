<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Listings saved before the per-emirate permit flow were all Dubai listings with a DLD (RERA)
 * permit — record that, so the compliance checks (which now depend on emirate / permit type) keep
 * treating them the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('properties')->whereNull('emirate')->update(['emirate' => 'dubai']);
        DB::table('properties')->whereNull('permit_type')->where('emirate', 'dubai')->update(['permit_type' => 'rera']);
    }

    public function down(): void
    {
        // Data fill only.
    }
};
