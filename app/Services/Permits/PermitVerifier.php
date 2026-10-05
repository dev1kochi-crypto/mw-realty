<?php

namespace App\Services\Permits;

use App\Models\PortalUser;
use App\Models\Property;
use App\Support\PermitRules;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The property form's "Validate" button and what saving does with its result.
 *
 * validate() checks the permit isn't already on another listing, picks the license it must be
 * issued under (PermitRules::license) and asks DLD / ADREC. A verified answer is kept server-side
 * under a one-off token for a while; the form posts the token back on save and apply() only trusts
 * what was cached — a listing can't mark itself verified, or change what the permit said.
 */
class PermitVerifier
{
    /** Listing fields a verified permit fills in (and that then stay as the permit says). */
    public const PERMIT_FIELDS = ['category', 'listing_type', 'property_type', 'location', 'bedrooms', 'sqft'];

    public function client(string $authority): PermitAuthorityClient
    {
        return match ($authority) {
            'dld' => app(DldPermitClient::class),
            'adrec' => app(AdrecPermitClient::class),
        };
    }

    /**
     * @return array{check: PermitCheck, token: ?string, license: ?array}
     */
    public function validate(string $type, ?PortalUser $owner, string $permitNo, ?int $propertyId): array
    {
        $permitNo = trim($permitNo);
        $license = PermitRules::license($type, $owner);

        if (!PermitRules::validates($type)) {
            return ['check' => PermitCheck::invalid('This permit type is not validated online.'), 'token' => null, 'license' => $license];
        }
        if (!$license) {
            $what = $type === 'adrec' ? 'ADREC brokerage registration number' : 'RERA ORN (office registration number)';

            return ['check' => PermitCheck::invalid("Add your {$what} to your company profile first — the permit is checked against it."), 'token' => null, 'license' => null];
        }
        if ($taken = $this->listingUsing($permitNo, $propertyId)) {
            return ['check' => PermitCheck::invalid("This permit is already used by listing {$taken->reference_no}. Each permit covers one listing only."), 'token' => null, 'license' => $license];
        }

        $check = $this->client(PermitRules::authority($type))->check($license['number'], $permitNo);
        $token = null;
        if ($check->isVerified()) {
            $token = Str::random(40);
            Cache::put($this->cacheKey($token), [
                'type' => $type,
                'permit_number' => $permitNo,
                'license_no' => $license['number'],
                'owner_id' => $owner?->id,
                'data' => $check->data,
            ], now()->addMinutes((int) config('permits.verification_ttl', 120)));
        }

        return ['check' => $check, 'token' => $token, 'license' => $license];
    }

    /** Another listing already carrying this permit number (one permit = one listing), if any. */
    public function listingUsing(string $permitNo, ?int $exceptId): ?Property
    {
        return Property::where('permit_number', trim($permitNo))
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->first(['id', 'reference_no']);
    }

    /** The cached verification for a token, if it matches what is being saved. */
    public function consume(?string $token, string $type, string $permitNo, ?PortalUser $owner): ?array
    {
        if (!$token) {
            return null;
        }
        $cached = Cache::pull($this->cacheKey($token));
        if (!$cached
            || $cached['type'] !== $type
            || $cached['permit_number'] !== trim($permitNo)
            || ($cached['owner_id'] ?? null) !== $owner?->id) {
            return null;
        }

        return $cached;
    }

    /**
     * Permit columns for a listing being saved, from the form's emirate / permit type / city / permit
     * number and the Validate token. $data already holds the listing's other posted fields; permit
     * fields a verified permit dictates overwrite them. $property is null on create.
     */
    public function apply(array $data, array $input, ?Property $property, ?PortalUser $owner): array
    {
        $type = PermitRules::resolve($data['emirate'] ?? null, $input['permit_type'] ?? null, $input['permit_city'] ?? null);
        $data['permit_type'] = $type;
        $data['permit_city'] = ($data['emirate'] ?? null) === PermitRules::NORTHERN ? ($input['permit_city'] ?? null) : null;

        if (!PermitRules::requiresPermit($type)) {
            // DIFC / JAFZA and Northern Emirates outside Al Ain: no permit to advertise.
            return array_merge($data, [
                'permit_number' => null, 'permit_expires_at' => null, 'permit_verification_url' => null,
                'permit_license_no' => null, 'permit_verified_at' => null, 'permit_verified_via' => null, 'permit_data' => null,
            ]);
        }

        $permitNo = trim((string) ($data['permit_number'] ?? ''));
        $data['permit_number'] = $permitNo !== '' ? $permitNo : null;
        $data['permit_license_no'] = PermitRules::license($type, $owner)['number'] ?? null;

        $verified = $permitNo !== '' ? $this->consume($input['permit_token'] ?? null, $type, $permitNo, $owner) : null;
        if ($verified) {
            $permit = $verified['data'];
            $data['permit_license_no'] = $verified['license_no'];
            $data['permit_verified_at'] = now();
            $data['permit_verified_via'] = PermitRules::authority($type);
            $data['permit_data'] = $permit;
            foreach (self::PERMIT_FIELDS as $field) {
                if (isset($permit[$field]) && $permit[$field] !== '') {
                    $data[$field] = $permit[$field];
                }
            }
            foreach (['expires_at' => 'permit_expires_at', 'verify_url' => 'permit_verification_url'] as $from => $to) {
                if (!empty($permit[$from])) {
                    $data[$to] = $permit[$from];
                }
            }

            return $data;
        }

        // No fresh verification: keep the previous one only while the permit stayed the same.
        $unchanged = $property
            && $property->permit_verified_at
            && $property->permit_type === $type
            && $property->permit_number === $data['permit_number']
            && $property->permit_license_no === $data['permit_license_no'];
        if (!$unchanged) {
            $data = array_merge($data, ['permit_verified_at' => null, 'permit_verified_via' => null, 'permit_data' => null]);
        }

        return $data;
    }

    private function cacheKey(string $token): string
    {
        return "permit-check:{$token}";
    }
}
