<?php

namespace App\Support;

/**
 * Which advertising permit a listing needs, decided by its emirate (and permit type / city):
 *
 *   Dubai              → RERA (DLD Trakheesi, validated) · DTCM (holiday homes, rent only) · None (DIFC / JAFZA)
 *   Abu Dhabi          → ADREC (validated)
 *   Northern Emirates  → City "Al Ain" → ADREC (validated) · any other city → no permit
 *
 * Emirate values are the codes of the "emirate" option list (Master › Property Options).
 */
class PermitRules
{
    public const DUBAI = 'dubai';
    public const ABU_DHABI = 'abu_dhabi';
    public const NORTHERN = 'northern_emirates';

    public const TYPES = [
        'rera' => ['label' => 'RERA', 'authority' => 'dld', 'license' => 'orn', 'number_label' => 'RERA permit number', 'license_label' => 'Real estate company license', 'validates' => true],
        'dtcm' => ['label' => 'DTCM', 'authority' => null, 'license' => null, 'number_label' => 'DTCM permit number', 'license_label' => null, 'validates' => false, 'rent_only' => true, 'needs_approval' => true],
        'none' => ['label' => 'None (DIFC/JAFZA only)', 'authority' => null, 'license' => null, 'number_label' => null, 'license_label' => null, 'validates' => false, 'needs_approval' => true],
        'adrec' => ['label' => 'ADREC', 'authority' => 'adrec', 'license' => 'adrec', 'number_label' => 'ADREC permit number', 'license_label' => 'Broker license', 'validates' => true],
        'not_required' => ['label' => 'Not required', 'authority' => null, 'license' => null, 'number_label' => null, 'license_label' => null, 'validates' => false],
    ];

    /** Permit types offered for a Dubai listing (the segmented choice on the form). */
    public const DUBAI_TYPES = ['rera', 'dtcm', 'none'];

    /** Northern Emirates city choice. */
    public const NORTHERN_CITIES = ['al_ain' => 'Al Ain', 'other' => 'Any city except Al Ain'];

    /** The permit type for an emirate + the form's permit type / city choice. */
    public static function resolve(?string $emirate, ?string $permitType, ?string $city): ?string
    {
        return match ($emirate) {
            self::DUBAI => in_array($permitType, self::DUBAI_TYPES, true) ? $permitType : 'rera',
            self::ABU_DHABI => 'adrec',
            self::NORTHERN => match ($city) {
                'al_ain' => 'adrec',
                'other' => 'not_required',
                default => null,
            },
            default => null,
        };
    }

    public static function requiresPermit(?string $type): bool
    {
        return in_array($type, ['rera', 'dtcm', 'adrec'], true);
    }

    /** Permits that come with a QR code and an expiry the buyer can check (Madmoun / ADREC). */
    public static function requiresQr(?string $type): bool
    {
        return in_array($type, ['rera', 'adrec'], true);
    }

    public static function validates(?string $type): bool
    {
        return (bool) (self::TYPES[$type]['validates'] ?? false);
    }

    /**
     * Does a listing with this permit type wait for Super Admin's approval before going live? Always
     * when LISTING_SUPERADMIN_APPROVAL is on; otherwise only DTCM and None, which can't be validated online.
     */
    public static function needsApproval(?string $type): bool
    {
        return (bool) config('permits.superadmin_approval') || (bool) (self::TYPES[$type]['needs_approval'] ?? false);
    }

    public static function authority(?string $type): ?string
    {
        return self::TYPES[$type]['authority'] ?? null;
    }

    public static function rentOnly(?string $type): bool
    {
        return (bool) (self::TYPES[$type]['rent_only'] ?? false);
    }

    /** Public name of the body that issued the permit, for the website ("DLD", "ADREC", "DTCM"). */
    public static function issuer(?string $type): ?string
    {
        return match ($type) {
            'rera' => 'DLD',
            'adrec' => 'ADREC',
            'dtcm' => 'DTCM',
            default => null,
        };
    }

    /**
     * MW Realty's own licenses (house listings): CMS › Site Information (extra fields), falling back
     * to config/permits.php "house" (.env) so a fresh install still works.
     */
    public static function houseLicense(): array
    {
        $site = once(fn () => \App\Models\CmsKit\SiteInformation::first());
        $extra = $site?->extra_fields ?? [];

        return [
            'name' => ($extra['license_name'] ?? null) ?: config('permits.house.name'),
            'orn' => ($extra['rera_orn'] ?? null) ?: config('permits.house.orn'),
            'adrec' => ($extra['adrec_license'] ?? null) ?: config('permits.house.adrec'),
        ];
    }

    /**
     * The license a permit of this type is issued under, for a listing owner (agency or agent;
     * null = MW Realty house listing): ['name' => …, 'number' => …] or null when none is on file.
     */
    public static function license(?string $type, ?\App\Models\PortalUser $owner): ?array
    {
        $kind = self::TYPES[$type]['license'] ?? null;
        if (!$kind) {
            return null;
        }
        // An agency agent lists under their agency's license.
        $holder = $owner && $owner->isAgent() && $owner->company ? $owner->company : $owner;
        if (!$holder) {
            $house = self::houseLicense();
            $number = $kind === 'orn' ? $house['orn'] : $house['adrec'];

            return $number ? ['name' => $house['name'], 'number' => $number] : null;
        }
        $number = $kind === 'orn' ? $holder->orn_number : $holder->adrec_license_no;

        return $number ? ['name' => $holder->company_name ?: $holder->name, 'number' => $number] : null;
    }
}
