<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Listings are no longer approved by Super Admin: a verified permit (Validate with DLD / ADREC, or
 * "Mark as verified") is what lets a listing go live. Listings Super Admin approved under the old
 * flow had their permit checked by hand — record that, so they stay live.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('properties')
            ->where('compliance_status', 'approved')
            ->whereNull('permit_verified_at')
            ->update([
                'permit_verified_at' => DB::raw('COALESCE(compliance_reviewed_at, updated_at, CURRENT_TIMESTAMP)'),
                'permit_verified_via' => 'manual',
            ]);
    }

    public function down(): void
    {
        // Data fill only.
    }
};
