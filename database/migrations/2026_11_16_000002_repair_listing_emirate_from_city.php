<?php

use App\Http\Requests\PropertyRequest;
use App\Support\PermitRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 2026_10_01_000004 filled every empty emirate with "dubai", including listings whose address is
 * in Abu Dhabi or the Northern Emirates. Those listings then got Dubai's permit type, buying fees
 * and ownership notes (/compare showed an Al Reem Island, Abu Dhabi listing as Dubai). Set the
 * emirate the address names (city first — see PropertyRequest::emirateFromAddress) and the matching
 * permit type. Listings whose address names no emirate are left alone. Each change is logged.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('properties')->select('id', 'emirate', 'location', 'translations')->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $translations = json_decode($row->translations ?? '{}', true) ?: [];
                    $en = (array) ($translations['en'] ?? (reset($translations) ?: []));
                    $emirate = PropertyRequest::emirateFromAddress($en['city'] ?? null, $en['community'] ?? null, $en['address'] ?? null, $row->location);
                    if (!$emirate || $emirate === $row->emirate) {
                        continue;
                    }
                    // Al Ain never gets here (emirateFromAddress skips it), so a Northern listing is "any other city".
                    $permitCity = $emirate === PermitRules::NORTHERN ? 'other' : null;
                    DB::table('properties')->where('id', $row->id)->update([
                        'emirate' => $emirate,
                        'permit_city' => $permitCity,
                        'permit_type' => PermitRules::resolve($emirate, null, $permitCity),
                    ]);
                    Log::info("Listing {$row->id}: emirate {$row->emirate} → {$emirate} (from its address).");
                }
            });
    }

    public function down(): void
    {
        // Data correction only.
    }
};
