<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One email or phone a lead has used. A lead keeps every contact it has enquired with, so a
 * later enquiry under any of them is recognised as the same person — see LeadDeduplicationService.
 */
class LeadContact extends Model
{
    public const TYPE_EMAIL = 'email';
    public const TYPE_PHONE = 'phone';

    protected $fillable = [
        'lead_id',
        'type',
        'value',
        'phone_country_code',
        'match_key',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public static function emailKey(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' ? $email : null;
    }

    /**
     * Digits only, compared on the last 9 (a UAE mobile without its country code / trunk 0), so
     * "+971 50 123 4567", "00971501234567" and "050 123 4567" all match. Anything under 6 digits
     * is too short to identify a person and never matches.
     */
    public static function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (strlen($digits) < 6) {
            return null;
        }

        return strlen($digits) > 9 ? substr($digits, -9) : ltrim($digits, '0');
    }

    /** Insertable rows (without lead_id / timestamps) for the given email + phone. */
    public static function rowsFor(?string $email, ?string $phone, ?string $countryCode = null): array
    {
        $rows = [];

        if ($key = self::emailKey($email)) {
            $rows[] = ['type' => self::TYPE_EMAIL, 'value' => trim($email), 'phone_country_code' => null, 'match_key' => $key];
        }
        if ($key = self::phoneKey($phone)) {
            $rows[] = ['type' => self::TYPE_PHONE, 'value' => trim($phone), 'phone_country_code' => $countryCode ?: null, 'match_key' => $key];
        }

        return $rows;
    }
}
