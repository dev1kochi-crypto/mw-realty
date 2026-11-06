<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

/**
 * The /compare page keeps its money & investment figures (buying / move-in costs, total cash
 * needed, price vs area, estimated rent, yields, service charge, area growth) back until the
 * visitor leaves their contact details (POST /api/contact, source=compare). That returns this
 * token; the page keeps it in the browser and sends it as the X-Compare-Unlock header.
 *
 * Encrypted with the app key, so it can't be forged, and it expires after
 * property_compare.unlock_days.
 */
class CompareUnlock
{
    public const HEADER = 'X-Compare-Unlock';

    private const PURPOSE = 'compare-unlock';

    public static function issue(): string
    {
        return Crypt::encryptString(json_encode([
            'p' => self::PURPOSE,
            'exp' => now()->addDays((int) config('property_compare.unlock_days', 365))->timestamp,
        ]));
    }

    public static function valid(?string $token): bool
    {
        if (!$token || strlen($token) > 1000) {
            return false;
        }
        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            return false;
        }

        return is_array($data) && ($data['p'] ?? null) === self::PURPOSE && (int) ($data['exp'] ?? 0) > now()->timestamp;
    }
}
